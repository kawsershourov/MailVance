# MailFlow bug-fix plan

## Context

A full audit (backend + frontend, both independently verified against actual file contents) found the root cause behind "every feature doesn't work": two Blade views have hard PHP parse errors and 500 on every load — Create Campaign, and Create/Edit Template (they share one view). Beyond those, the audit surfaced real security gaps (duplicate-send risk, an IDOR on campaign creation, plaintext SMTP passwords leaking into page HTML, a trust-the-client CSV import path), three "collected but never used" fields that quietly do nothing (batch throttling, SMTP hourly cap, scheduled send), a missing DB foreign key, and a duplicated/diverging asset pipeline (Tailwind/Alpine/Chart.js/Lucide loaded twice — once via the real Vite build, once via CDN scripts with a different, unsynced Tailwind config) that's the concrete cause of the "not professional" feel and is one blocked CDN request away from the whole UI losing its styling. The user asked to fix everything found, with the SMTP password issue handled by both encrypting the DB column and removing the HTML leak.

All findings below were re-verified by directly reading the current file contents (not just trusting the audit).

## 1. Fix `campaigns/create.blade.php` parse error (P0)

Lines 297-299 have `"{{ $smtps->first()->id ??  }}"` — `??` with no right-hand side, a hard PHP parse error, so `/campaigns/create` 500s on every load (confirmed by reading the file directly).

Fix: change all three lines to use `?? ''` as the fallback. Downstream code does loose (`==`) comparisons against numeric ids and the empty-state branches already hide the radio list when a collection is empty, so `''` is a safe "unselected" sentinel.

## 2. Fix `templates/editor.blade.php` parse error — wider than one line (P0)

Line 140 has the reported bug (unescaped `"` inside a double-quoted PHP string breaks the statement), but this view has a second, broader problem: Blade's `{{ }}` compiler is a text-level regex pass applied to the *entire file*, with no awareness of PHP-string/JS-string context, except inside `@php...@endphp` blocks. Confirmed by reading the file: literal merge tags like `{{first_name}}` appear as **plain, un-escaped text** in six places outside any `@php` block — inside `insertMergeTag('{{first_name}}')` calls (lines 57-62), the fallback subject (line 139), the fallback body HTML (lines 140-150), and inside `applyStarterTemplate()`'s JS (lines 168, 173, 177, 183). Each of these will compile to `<?php echo e(first_name); ?>` and fatal with `Undefined constant "first_name"` the moment the corrupted line 140 issue is fixed and the page actually executes. This view backs both `templates.create` and `templates.edit`.

Fix (all in `templates/editor.blade.php`):
- Move the large default body-HTML string out of the inline `json_encode(...)` call into a PHP nowdoc assigned in an `@php @endphp` block placed after `@endsection` and before `@push('scripts')` (Blade leaves `{{ }}` untouched inside `@php` blocks — verified). Reference that variable in the `bodyHtml:` line instead of the inline string.
- Prefix each literal merge tag used as a JS *argument* with `@` so Blade emits it literally instead of compiling it: `insertMergeTag('@{{first_name}}')`, etc. (the visible button label already correctly does this — only the six `@click` arguments are broken).
- Escape the fallback subject on line 139 the same way (`@{{first_name}}`).
- Escape all merge tags inside `applyStarterTemplate()` (lines 168-187) the same way.

Verify by compiling the file with `BladeCompiler::compileString()` (or just hitting `/templates/create` and `/templates/{id}/edit`) and confirming no `echo e(first_name)`/`echo e(company)`/etc. remain in the compiled output.

## 3. Campaign launch/resume idempotency

`CampaignController::launchCampaign()` and `resume()` query all `pending` `CampaignLog` rows and dispatch a job per row with no guard against being invoked twice (double-click, resubmit); `SendCampaignEmailJob::handle()` never re-checks the log is still `pending` before sending. Net effect: duplicate sends to every recipient.

Fix — two layers:
- **New `app/Services/CampaignLaunchService.php`** with `launch(Campaign $campaign)` and `resume(Campaign $campaign)`, each doing an atomic conditional update (`Campaign::where('id', ...)->where('status', 'draft'|'paused')->update([...])`) and only dispatching jobs for pending logs if the update actually claimed a row (return value > 0). This becomes the one place that dispatches `SendCampaignEmailJob`s, reused later by the scheduled-launch command (item 8).
- **`CampaignController`**: `launchCampaign()` and `resume()` keep their existing `abort(403)` ownership check, then delegate to the service; if the service reports no claim, redirect back with an error flash instead of silently no-op-ing. `store()` calls `launchCampaign()` directly as a PHP method when `send_now` is checked — add `CampaignLaunchService $launcher` to `store()`'s own signature (method injection, same pattern already used in `TemplateController::store`) and pass it through.
- **`SendCampaignEmailJob::handle()`**: immediately after the existing paused/cancelled/draft bail-out, atomically claim the log row (`CampaignLog::where('id', $log->id)->where('status','pending')->update(['status'=>'sending'])`) and return early if 0 rows were affected. `campaign_logs.status` is a plain string column (no enum), and the show-page status badge already falls through to a generic "Pending" display for any non-sent/failed value, so no view or schema change is needed for the transient `sending` state.

Order: the service must exist before the controller/job edits that call it.

## 4. IDOR on campaign creation

`CampaignController::store()` validates `smtp_config_id`/`contact_list_id`/`email_template_id` only with `exists:*` — never ownership — so any authenticated user can create (and launch) a campaign against another user's SMTP relay and contact list.

Fix: after validation, before `Campaign::create()`, add the same inline ownership pattern already used elsewhere in this controller and documented in CLAUDE.md (`if ($model->user_id !== Auth::id()) abort(403);`) for each of the three referenced models (`SmtpConfig::findOrFail`, `ContactList::findOrFail`, `EmailTemplate::findOrFail`).

## 5. SMTP password: encrypt + stop HTML leak

`SmtpConfig` has no `$hidden`, so `smtp/index.blade.php`'s `json_encode($s)` (used to seed the edit modal) serializes the plaintext password into every SMTP card's page HTML on load, regardless of whether the modal is opened. The column is also stored unencrypted (`text`, no cast).

Fix (`app/Models/SmtpConfig.php` only — no other file needs to change):
- Add `protected $hidden = ['password'];` — since `json_encode($model)` routes through `toArray()`, this alone stops the HTML leak (confirmed the edit-modal JS already hard-codes `password: ""` regardless of what's passed in, and `SmtpController::update()` already treats an empty password field as "don't change" — so nothing downstream expects the password to be prefilled).
- Add `'password' => 'encrypted'` to `$casts` — Laravel decrypts/encrypts transparently on read/write, no migration needed (the `text` column has plenty of room for the ciphertext envelope). The one other read site, `SmtpMailService`, accesses `$config->password` directly on the model instance (not through serialization), so it keeps working unchanged.

Rollout note: local dev DB already has one `SmtpConfig` row with a plaintext password. Once the `encrypted` cast is live, reading that row will throw a decrypt error. Since this is single-user dev/test data (confirmed trivial row counts), the plan is to run `php artisan migrate:fresh` (paired with item 9 below) and recreate the SMTP config through the UI afterward — this will be called out explicitly before running, since a fresh migrate wipes local data.

## 6. CSV import: stop trusting a client-supplied file path

`ContactController::uploadCsvPreview()` returns an absolute server path to the browser; `processCsvImport()` accepts it back with only `'required|string'` validation and feeds it straight to `CsvStreamService::streamImport()` — no ownership or containment check, so a user can supply an arbitrary path (their own old temp file, a guessed/leaked one, or a traversal payload).

Fix (`app/Http/Controllers/ContactController.php` only — the frontend JS already treats `temp_file_path` as an opaque string round-tripped verbatim, so no view/JS changes needed):
- `uploadCsvPreview()`: return only the generated filename (a token), and register `Cache::put("csv_import_token:{userId}:{filename}", true, ttl)` scoped to `Auth::id()`.
- `processCsvImport()`: resolve the real path server-side — reject if `basename($input) !== $input` (blocks traversal), require `Cache::pull($cacheKey)` to succeed (atomic one-time use, also enforces the upload belongs to the current user), then `realpath()`-check the resolved path stays inside the `csv_temp` storage directory before calling `streamImport()`.

## 7. Implement `batch_size`/`batch_delay_seconds` and `hourly_limit` (currently dead fields)

Confirmed via grep these are captured/validated/persisted on `Campaign`/`SmtpConfig` but never read by `SendCampaignEmailJob` or `CampaignController`. CLAUDE.md documents throttling as intentionally a blocking, single-worker `usleep()` model (not queue `delay()`); keep new throttling consistent with that.

- **Batch pacing** (`SendCampaignEmailJob`): after a successful send, if `campaign.batch_size > 0`, increment a per-campaign `Cache` counter; once it reaches `batch_size`, reset the counter and `sleep(batch_delay_seconds)` before returning (blocking, same model as the existing per-email `usleep()`).
- **Hourly cap** (`SendCampaignEmailJob`, after the atomic claim from item 3, before send): if `smtp.hourly_limit > 0`, count `sent` logs for campaigns on that SMTP config in the trailing hour; if at/over the cap, release the claim back to `pending`, flip the campaign to `paused`, and cache a `campaign:{id}:hourly_cap_resume_at = now()+1h` marker. A true blocking sleep-until-resume isn't viable here since it could exceed the queue worker's timeout — auto-pause + scheduled auto-resume (item 8) is the mechanism instead. This only ever auto-resumes campaigns *this* mechanism paused (tracked via the cache key), never a campaign a user paused manually.

Depends on item 3's `CampaignLaunchService`/atomic claim landing first.

## 8. Implement `scheduled_at` (currently dead field)

`scheduled_at` is saved but nothing ever reads it to trigger a launch.

Fix: new `app/Console/Commands/ProcessScheduledCampaigns.php` (`campaigns:process-scheduled`) that (a) finds `draft` campaigns with `scheduled_at <= now()` and calls `CampaignLaunchService::launch()`, and (b) finds `paused` campaigns with an expired `hourly_cap_resume_at` cache marker (from item 7) and calls `CampaignLaunchService::resume()`, clearing the marker. Register it in `routes/console.php` via `Schedule::command('campaigns:process-scheduled')->everyMinute()`. Note for the user: this requires `php artisan schedule:work` (or a real cron running `schedule:run`) to actually be running — worth a one-line mention in CLAUDE.md's Commands section as a follow-up, not part of the code fix itself.

Depends on items 3 and 7.

## 9. Fix missing FK constraint in the schema migration

`database/migrations/2026_08_31_000001_create_email_sender_tables.php`: `campaign_logs.contact_id` is declared as `$table->foreignId('contact_id')->nullable()->nullOnDelete();` — missing `->constrained()`, so `nullOnDelete()` silently no-ops and no FK constraint is actually created (contrast with `campaign_id` two lines above, which correctly chains `->constrained()->cascadeOnDelete()`).

Fix: add `->constrained()` — `$table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();`. Since CLAUDE.md documents this as a single-migration schema and the local DB holds only trivial dev data, fix in place and pick this up in the same `php artisan migrate:fresh` used for item 5, rather than adding a follow-up migration.

## 10. Stop double-loading the asset pipeline; sync Tailwind config

`layouts/app.blade.php` (and `auth/login.blade.php`, `auth/register.blade.php`, `tracking/unsubscribed.blade.php`) load Tailwind, Alpine.js, Chart.js, and Lucide via **both** the real Vite build (`app.js`/`app.css`, which already imports and initializes all of these) **and** separate CDN `<script>` tags — with the CDN's inline Tailwind config defining a `brand` color palette + custom fonts that the real `tailwind.config.js` (what the Vite build actually uses) doesn't have at all. The site currently only looks right because the CDN's browser-side JIT compiler papers over the gap live; if that CDN script is ever blocked, the whole UI loses its color scheme and fonts. This is the concrete, fixable cause behind "design... not professional."

Fix:
- `tailwind.config.js`: add the `brand` color palette (full 11-shade version from the layout's CDN config) and the `Plus Jakarta Sans`/`JetBrains Mono` font families to `theme.extend`.
- `layouts/app.blade.php`: delete the CDN Tailwind `<script>` + inline config block, and the CDN Alpine/Lucide/Chart.js `<script>` tags. Keep the existing `@vite([...])` call as the sole asset source. Remove the now-fully-redundant bottom `DOMContentLoaded` → `lucide.createIcons()` script block (already duplicated by `app.js`).
- `auth/login.blade.php` / `auth/register.blade.php`: delete their CDN Tailwind + Lucide blocks, keep `@vite([...])`; keep their existing explicit `lucide.createIcons()` call since these standalone pages don't extend `layouts.app`.
- `tracking/unsubscribed.blade.php`: replace its CDN Tailwind `<script>` with `@vite(['resources/css/app.css'])` — confirmed this page uses no Alpine/Lucide/Chart, so it only needs the compiled CSS, not `app.js`.
- After edits: run `npm run build`, then load `/login` and a `layouts.app` page (e.g. dashboard) and visually confirm brand colors and icons still render correctly (Lucide icons in particular — confirm any dynamically-inserted icon markup after initial load, if any exists, still gets picked up).

`tailwind.config.js` must be updated before/with the view edits — removing the CDN before the config has the brand palette would silently drop all brand styling.

## 11. Remove dead welcome page

`resources/views/welcome.blade.php` is the untouched Laravel starter template; confirmed via grep it's referenced by nothing in `routes/` or `app/`. Delete it.

## Rollout note (destructive step, confirm before running)

Items 5 and 9 both motivate running `php artisan migrate:fresh` against the local dev DB (currently holding 1 user, 1 SmtpConfig with a plaintext password, and 5 CampaignLog rows — trivial dev data, confirmed via inspection). This will be flagged explicitly and only run with confirmation, since it wipes local data; the SMTP config would need to be re-entered afterward through the UI.

## Verification

- `php artisan test` — full suite should still pass (QUEUE_CONNECTION=sync, MAIL_MAILER=array in test config, so job changes run inline).
- Manually load `/campaigns/create` and `/templates/create` / `/templates/{id}/edit` and confirm they render instead of 500ing.
- Click "Launch" twice quickly on a test campaign and confirm only one round of emails is sent (check `sent_count` and per-recipient `CampaignLog` rows, using the `array` mail driver / `log` mailer to inspect what actually went out).
- Attempt to create a campaign via direct POST referencing another user's `smtp_config_id` and confirm a 403.
- View source on `/smtp` and confirm the password is absent from the page HTML; confirm sending a campaign still works after the `encrypted` cast (i.e. `SmtpMailService` still builds a working transport).
- Attempt a CSV import with a tampered `temp_file_path` (e.g. path traversal or another user's filename) and confirm it's rejected.
- `npm run build`, then visually check `/login` and the dashboard render with correct brand colors/fonts/icons with the CDN scripts removed.
