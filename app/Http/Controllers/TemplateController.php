<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\DeliverabilityScoreService;
use App\Services\EmailTemplateBuilderService;
use App\Services\TemplateRendererService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemplateController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $templates = EmailTemplate::where('user_id', $user->id)->latest()->get();

        return view('templates.index', compact('templates'));
    }

    public function create(EmailTemplateBuilderService $builder)
    {
        $template = new EmailTemplate;

        return view('templates.editor', $this->editorData($template, $builder));
    }

    public function store(Request $request, DeliverabilityScoreService $scoreService, EmailTemplateBuilderService $builder)
    {
        $validated = $this->validatePayload($request);
        $validated = $this->applyDesign($validated, $request, $builder);

        $scoreAnalysis = $scoreService->analyzeSpamScore($validated['subject'], $validated['body_html'], $validated['body_text'] ?? '');

        $validated['user_id'] = Auth::id();
        $validated['spam_score'] = $scoreAnalysis['score'];
        if (empty($validated['body_text'])) {
            $validated['body_text'] = strip_tags($validated['body_html']);
        }

        EmailTemplate::create($validated);

        return redirect()->route('templates.index')->with('success', 'Email template saved successfully.');
    }

    public function edit(EmailTemplate $template, EmailTemplateBuilderService $builder)
    {
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        return view('templates.editor', $this->editorData($template, $builder));
    }

    private function editorData(EmailTemplate $template, EmailTemplateBuilderService $builder): array
    {
        $logoPath = $template->logo_path;

        return [
            'template' => $template,
            'fonts' => EmailTemplateBuilderService::FONTS,
            'designDefaults' => $builder->normalize($template->design ?? []),
            'logoUrl' => $logoPath && Storage::disk('local')->exists($logoPath)
                ? route('templates.logo', ['filename' => basename($logoPath)])
                : '',
        ];
    }

    public function update(Request $request, EmailTemplate $template, DeliverabilityScoreService $scoreService, EmailTemplateBuilderService $builder)
    {
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $this->validatePayload($request);
        $validated = $this->applyDesign($validated, $request, $builder, $template);

        $scoreAnalysis = $scoreService->analyzeSpamScore($validated['subject'], $validated['body_html'], $validated['body_text'] ?? '');
        $validated['spam_score'] = $scoreAnalysis['score'];
        if (empty($validated['body_text'])) {
            $validated['body_text'] = strip_tags($validated['body_html']);
        }

        $template->update($validated);

        return redirect()->route('templates.index')->with('success', 'Email template updated.');
    }

    public function destroy(EmailTemplate $template)
    {
        if ($template->user_id !== Auth::id()) {
            abort(403);
        }

        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'nullable|string',
            'body_text' => 'nullable|string',
            'editor_mode' => 'nullable|in:design,html',
            'design' => 'nullable|array',
            'design_json' => 'nullable|string',
            'logo_path' => 'nullable|string',
        ]);
    }

    /**
     * In design mode the stored HTML is generated from the settings, so the
     * template stays editable in the panel instead of becoming opaque markup.
     */
    private function applyDesign(array $validated, Request $request, EmailTemplateBuilderService $builder, ?EmailTemplate $template = null): array
    {
        $designJson = $validated['design_json'] ?? null;
        unset($validated['design_json'], $validated['editor_mode']);

        if (($request->input('editor_mode', 'design')) !== 'design') {
            $validated['design'] = null;
            $validated['logo_path'] = $template?->logo_path;

            return $validated;
        }

        $design = $validated['design'] ?? [];

        // design_json carries the nested per-section settings a flat form cannot express.
        if (! empty($designJson) && is_array($decoded = json_decode($designJson, true))) {
            $design = $decoded;
        }

        // Only trust a logo reference this user actually uploaded.
        $logoPath = $this->resolveOwnedLogo($validated['logo_path'] ?? null) ?? $template?->logo_path;
        $design['has_logo'] = (bool) $logoPath;

        // Persist the normalized settings, so what is stored matches what is
        // rendered and the editor never reloads an out-of-range value.
        $validated['design'] = $builder->normalize($design);
        $validated['logo_path'] = $logoPath;
        $validated['body_html'] = $builder->render($design);
        $validated['body_text'] = $builder->renderText($design);

        return $validated;
    }

    private function resolveOwnedLogo(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $expectedPrefix = 'template_logos/'.Auth::id().'/';
        if (! str_starts_with($path, $expectedPrefix) || str_contains($path, '..')) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
        ]);

        $path = $request->file('logo')->store('template_logos/'.Auth::id(), 'local');

        return response()->json([
            'success' => true,
            'logo_path' => $path,
            'preview_url' => route('templates.logo', ['filename' => basename($path)]),
        ]);
    }

    /** Serves a logo back to its owner for the editor preview only. */
    public function showLogo(string $filename)
    {
        $path = 'template_logos/'.Auth::id().'/'.basename($filename);

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $absolute = Storage::disk('local')->path($path);
        $mime = @mime_content_type($absolute) ?: 'application/octet-stream';

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'application/octet-stream';
        }

        $response = new BinaryFileResponse($absolute);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', 'inline');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function renderLivePreview(Request $request, TemplateRendererService $renderer, DeliverabilityScoreService $scoreService, EmailTemplateBuilderService $builder)
    {
        // Typed and bounded — an array payload used to reach the string-typed
        // renderer as a TypeError, and body_html was unbounded.
        $validated = $request->validate([
            'subject' => 'nullable|string|max:2000',
            'body_html' => 'nullable|string|max:500000',
            'design' => 'nullable|array',
            'design_json' => 'nullable|string|max:500000',
            'logo_path' => 'nullable|string|max:512',
        ]);

        $subject = $validated['subject'] ?? '';
        $html = $validated['body_html'] ?? '';

        $design = $validated['design'] ?? null;
        if (is_string($json = $request->input('design_json')) && is_array($decoded = json_decode($json, true))) {
            $design = $decoded;
        }

        if (is_array($design)) {
            $logoPath = $this->resolveOwnedLogo($request->input('logo_path'));
            $design['has_logo'] = (bool) $logoPath;
            $html = $builder->render(
                $design,
                $logoPath ? route('templates.logo', ['filename' => basename($logoPath)]) : null,
                forPreview: true
            );
        }

        $renderedSubject = $renderer->render($subject);
        $renderedHtml = $renderer->render($html);
        $spamAnalysis = $scoreService->analyzeSpamScore($subject, $html);

        return response()->json([
            'rendered_subject' => $renderedSubject,
            'rendered_html' => $renderedHtml,
            'spam_analysis' => $spamAnalysis,
        ]);
    }
}
