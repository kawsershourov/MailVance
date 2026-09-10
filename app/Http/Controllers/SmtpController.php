<?php

namespace App\Http\Controllers;

use App\Models\SmtpConfig;
use App\Rules\SafeSmtpHost;
use App\Services\SmtpMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SmtpController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $smtps = SmtpConfig::where('user_id', $user->id)->latest()->get();

        return view('smtp.index', compact('smtps'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'host' => ['required', 'string', 'max:255', new SafeSmtpHost],
            'port' => ['required', 'integer', Rule::in(config('mailflow.smtp.allowed_ports'))],
            'encryption' => 'required|in:tls,ssl,starttls,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'hourly_limit' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        if (! empty($validated['is_default'])) {
            SmtpConfig::where('user_id', $user->id)->update(['is_default' => false]);
        }

        // If this is the first SMTP, make it default automatically
        $isFirst = SmtpConfig::where('user_id', $user->id)->count() === 0;
        $validated['is_default'] = $isFirst || ! empty($validated['is_default']);
        $validated['user_id'] = $user->id;

        SmtpConfig::create($validated);

        return redirect()->route('smtp.index')->with('success', 'SMTP relay added successfully!');
    }

    public function update(Request $request, SmtpConfig $smtp)
    {
        if ($smtp->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'host' => ['required', 'string', 'max:255', new SafeSmtpHost],
            'port' => ['required', 'integer', Rule::in(config('mailflow.smtp.allowed_ports'))],
            'encryption' => 'required|in:tls,ssl,starttls,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'hourly_limit' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_default'])) {
            SmtpConfig::where('user_id', Auth::id())->where('id', '!=', $smtp->id)->update(['is_default' => false]);
        }

        // Keep existing password if not provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $smtp->update($validated);

        return redirect()->route('smtp.index')->with('success', 'SMTP relay updated successfully!');
    }

    public function destroy(SmtpConfig $smtp)
    {
        if ($smtp->user_id !== Auth::id()) {
            abort(403);
        }

        $smtp->delete();

        return redirect()->route('smtp.index')->with('success', 'SMTP relay deleted.');
    }

    public function testSend(Request $request, SmtpConfig $smtp, SmtpMailService $mailerService)
    {
        if ($smtp->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'test_email' => 'required|email',
        ]);

        $result = $mailerService->testConnection($smtp, $request->input('test_email'));

        return response()->json($result);
    }
}
