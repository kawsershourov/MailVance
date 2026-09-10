# Spec: MailFlow Bug Fix & Hardening Pass

Source plan: `plan/bug_fix_plan.md`. This spec turns that plan into implementation-ready units, following the same structure as the `phase/` docs. **Do not implement until explicitly told to proceed.**

## 1. Goal Description

Two Blade views currently have hard PHP parse errors and 500 on every load — Create Campaign, and Create/Edit Template — which is the direct cause of "every feature doesn't work." Beyond that, fix five real security/correctness gaps (duplicate-send risk, an IDOR on campaign creation, plaintext SMTP passwords leaking into page HTML and stored unencrypted, a client-trusted CSV import file path, a missing DB foreign key), implement two features that are collected in forms but silently do nothing (batch/hourly throttling, scheduled send), and de-duplicate the asset pipeline (Tailwind/Alpine/Chart.js/Lucide currently loaded twice — once via the real Vite build, once via CDN scripts with a divergent Tailwind config) which is the concrete, fixable cause of the "not professional" look and a live risk if the CDN is ever blocked.

---

## 2. Bug/Fix Specifications

### A. Fatal parse errors (P0 — blocks core flows entirely)

**A1. `resources/views/campaigns/create.blade.php:297-299`**
`"{{ $smtps->first()->id ??  }}"` — `??` with no right-hand operand is an invalid PHP expression; Blade compiles `{{ }}` straight to `<?php echo e(...); ?>`, so this fatals on every load of `/campaigns/create`.
- Fix: change to `?? ''` on all three lines (`smtp_config_id`, `contact_list_id`, `email_template_id`). Confirmed safe: downstream JS does loose `==` comparisons against numeric ids, and the empty-state Blade branches already hide the relevant radio list when the collection is empty.

**A2. `resources/views/templates/editor.blade.php`**
Two compounding problems on this single view, which backs both `templates.create` and `templates.edit`:
- Line 140: unescaped `"` inside a double-quoted PHP string (`"<div style="font-family...`) terminates the string early → parse error.
- Wider issue (confirmed by reading the file and Blade-compiling it): literal `{{first_name}}`-style merge tags appear as **plain text outside any `@php` block** in six additional places — `insertMergeTag('{{first_name}}')` calls (lines 57-62), the fallback subject (line 139), the fallback body HTML (lines 140-150), and inside `applyStarterTemplate()` (lines 168, 173, 177, 183). Blade's `{{ }}` compiler is a text-level pass over the whole file (no PHP/JS-string awareness) except inside `@php...@endphp`, so each of these compiles to `echo e(first_name)` and fatals with `Undefined constant "first_name"` once the line-140 issue alone is patched.
- Fix:
  1. Move the default body-HTML fallback into a PHP nowdoc inside a new `@php ... @endphp` block placed after `@endsection`, before `@push('scripts')`. Reference that variable in the `bodyHtml:` line.
  2. Prefix every literal merge tag used as a JS string argument with `@` (Blade's literal-escape) — the six `insertMergeTag('@{{first_name}}')` calls, the line-139 fallback subject, and all merge tags inside `applyStarterTemplate()`.
- Verify: compile the file (`BladeCompiler::compileString()`) or hit both routes directly; grep the compiled output for `echo e(first_name)` etc. — none should remain.

### B. Campaign launch/resume idempotency (duplicate-send risk)

`CampaignController::launchCampaign()`/`resume()` dispatch a `SendCampaignEmailJob` per `pending` `CampaignLog` row with no guard against being invoked twice; `SendCampaignEmailJob::handle()` never re-checks the log is still `pending` before sending.

- New `app/Services/CampaignLaunchService.php`: `launch(Campaign $campaign)` and `resume(Campaign $campaign)`, each an atomic conditional update (`->where('status','draft'|'paused')->update([...])`), only dispatching pending-log jobs if the update actually claimed the row. This becomes the single dispatch entry point, reused by the scheduled-launch command (spec E).
- `CampaignController`: `launchCampaign()`/`resume()` keep their existing ownership `abort(403)`, then delegate to the service; redirect with an error flash if the service reports no claim. `store()` gets `CampaignLaunchService $launcher` added to its own signature (method injection, matching `TemplateController::store`'s existing pattern) so its internal `send_now` call to `launchCampaign()` can pass it through.
- `SendCampaignEmailJob::handle()`: right after the existing paused/cancelled/draft bail, atomically claim the log (`->where('status','pending')->update(['status'=>'sending'])`), return early if 0 rows affected. No schema/view change needed — `status` is a plain string column and the show-page badge already renders any non-sent/failed value as generic "Pending".
- Order: service lands before the controller/job edits that depend on it.

### C. IDOR on campaign creation

`CampaignController::store()` validates `smtp_config_id`/`contact_list_id`/`email_template_id` with `exists:*` only — never ownership.

- Fix: after validation, before `Campaign::create()`, add the same inline ownership check already used elsewhere in this controller (`if ($model->user_id !== Auth::id()) abort(403);`) for each of `SmtpConfig::findOrFail`, `ContactList::findOrFail`, `EmailTemplate::findOrFail`.

### D. SMTP password: encrypt column + stop HTML leak

`SmtpConfig` has no `$hidden`; `smtp/index.blade.php`'s `json_encode($s)` (feeding the edit modal) serializes the plaintext password into every SMTP card's HTML on load. Column is stored unencrypted (`text`, no cast).

- `app/Models/SmtpConfig.php` only:
  - `protected $hidden = ['password'];` — stops the HTML leak (confirmed `json_encode($model)` routes through `toArray()`; edit-modal JS already hard-codes `password: ""` regardless of input; `SmtpController::update()` already treats empty password as "don't change").
  - `'password' => 'encrypted'` in `$casts` — transparent decrypt/encrypt on read/write, no migration needed. `SmtpMailService` reads `$config->password` directly off the model (not via serialization), so it needs no change.
- Rollout note: local dev DB has one `SmtpConfig` row with a plaintext password; once the cast is live, reading it will throw a decrypt error. Plan is to pair this with a `php artisan migrate:fresh` (see spec F) and re-enter the SMTP config via the UI afterward. **Confirm with the user before running anything that wipes local data.**

### E. CSV import: stop trusting a client-supplied file path

`ContactController::uploadCsvPreview()` returns an absolute server path to the browser; `processCsvImport()` accepts it back with only `'required|string'` validation and feeds it straight into `CsvStreamService::streamImport()`.

- `uploadCsvPreview()`: return only the generated filename (a token); register `Cache::put("csv_import_token:{userId}:{filename}", true, ttl)` scoped to `Auth::id()`.
- `processCsvImport()`: reject if `basename($input) !== $input` (blocks traversal); require `Cache::pull($cacheKey)` to succeed (atomic one-time use, enforces ownership); `realpath()`-verify the resolved path stays inside the `csv_temp` storage dir before calling `streamImport()`.
- No frontend/JS changes needed — `temp_file_path` is already treated as an opaque round-tripped string by the existing UI.

### F. Dead fields: `batch_size`/`batch_delay_seconds`, `hourly_limit`, `scheduled_at`

Confirmed via grep: captured, validated, and persisted, but never read by `SendCampaignEmailJob` or `CampaignController`. CLAUDE.md documents throttling as an intentional blocking, single-worker `usleep()` model — keep new throttling consistent with that, not queue `delay()`.

- **Batch pacing** (`SendCampaignEmailJob`, after a successful send): if `campaign.batch_size > 0`, increment a per-campaign `Cache` counter; at `batch_size`, reset the counter and `sleep(batch_delay_seconds)` (blocking, same model as the existing per-email `usleep()`).
- **Hourly cap** (`SendCampaignEmailJob`, after the atomic claim from spec B, before send): if `smtp.hourly_limit > 0` and the trailing-hour `sent` count for that SMTP config is at/over the cap, release the claim to `pending`, flip the campaign to `paused`, cache `campaign:{id}:hourly_cap_resume_at = now()+1h`. No long blocking sleep here (could exceed the worker's timeout) — auto-pause + scheduled auto-resume instead. Only ever auto-resumes campaigns this mechanism paused (tracked by the cache key), never a user-initiated pause.
- **Scheduled launch**: new `app/Console/Commands/ProcessScheduledCampaigns.php` (`campaigns:process-scheduled`) — (a) `draft` campaigns with `scheduled_at <= now()` → `CampaignLaunchService::launch()`; (b) `paused` campaigns with an expired `hourly_cap_resume_at` marker → `CampaignLaunchService::resume()`, clearing the marker. Register via `Schedule::command('campaigns:process-scheduled')->everyMinute()` in `routes/console.php`. Requires `php artisan schedule:work` (or real cron `schedule:run`) actually running — note this as a follow-up line in CLAUDE.md's Commands section.
- Depends on spec B's `CampaignLaunchService` landing first; hourly-cap and scheduled-launch are tightly coupled (implement together).

### G. Missing FK constraint

`database/migrations/2026_08_31_000001_create_email_sender_tables.php`: `campaign_logs.contact_id` is `->nullable()->nullOnDelete()` with no `->constrained()` first, so `nullOnDelete()` silently no-ops — no FK constraint actually exists (contrast with `campaign_id` two lines above, which correctly chains `->constrained()->cascadeOnDelete()`).

- Fix in place: `$table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();`. Single-migration schema per CLAUDE.md, local DB holds only trivial dev data — pick this up in the same `migrate:fresh` as spec D rather than adding a follow-up migration.

### H. Duplicate asset pipeline / diverging Tailwind config

`layouts/app.blade.php`, `auth/login.blade.php`, `auth/register.blade.php`, `tracking/unsubscribed.blade.php` load Tailwind/Alpine/Chart.js/Lucide via **both** the real Vite build (`app.js`/`app.css`, which already imports/initializes all of them) **and** separate CDN `<script>` tags — with the CDN's inline Tailwind config defining a `brand` palette + custom fonts that the real `tailwind.config.js` doesn't have at all. Site currently looks right only because the CDN's browser-side JIT compiler papers over the gap live.

- `tailwind.config.js`: add the full 11-shade `brand` color palette and `Plus Jakarta Sans`/`JetBrains Mono` font families to `theme.extend`.
- `layouts/app.blade.php`: delete the CDN Tailwind `<script>` + inline config block and the CDN Alpine/Lucide/Chart.js tags; keep `@vite([...])` as sole source; remove the now fully-redundant bottom `DOMContentLoaded → lucide.createIcons()` block.
- `auth/login.blade.php` / `auth/register.blade.php`: delete their CDN Tailwind + Lucide blocks, keep `@vite([...])` and their existing explicit `lucide.createIcons()` call (standalone pages, don't extend `layouts.app`).
- `tracking/unsubscribed.blade.php`: replace CDN Tailwind `<script>` with `@vite(['resources/css/app.css'])` (confirmed no Alpine/Lucide/Chart usage on this page).
- `tailwind.config.js` must land before/with the view edits. After: `npm run build`, then visually verify `/login` and a `layouts.app` page (brand colors, fonts, icons) with CDN scripts removed.

### I. Dead welcome page

`resources/views/welcome.blade.php` — unmodified Laravel starter template, confirmed unreferenced by any route or controller (`grep -rn "welcome" routes/ app/` → no hits). Delete it.

---

## 3. Step-by-Step Implementation Tasks

1. Fix A1 (`campaigns/create.blade.php`) and A2 (`templates/editor.blade.php`) — unblocks the app; independent of everything else.
2. Build `app/Services/CampaignLaunchService.php` (spec B).
3. Wire `CampaignLaunchService` into `CampaignController::launchCampaign()`/`resume()`/`store()`, and add the atomic per-row claim to `SendCampaignEmailJob::handle()` (spec B).
4. Add ownership checks to `CampaignController::store()` (spec C).
5. Update `app/Models/SmtpConfig.php` (`$hidden`, `encrypted` cast) (spec D).
6. Rework `ContactController::uploadCsvPreview()`/`processCsvImport()` to use a server-side token instead of a client-supplied path (spec E).
7. Add batch pacing + hourly-cap logic to `SendCampaignEmailJob` (spec F, depends on step 3).
8. Build `app/Console/Commands/ProcessScheduledCampaigns.php` and register it in `routes/console.php` (spec F, depends on steps 3 and 7).
9. Fix the `contact_id` FK in the schema migration (spec G).
10. Confirm with user, then run `php artisan migrate:fresh` (covers specs D and G together) and re-create the SMTP config via the UI.
11. Update `tailwind.config.js`, then strip duplicate CDN script/config blocks from the four views listed in spec H; run `npm run build`.
12. Delete `resources/views/welcome.blade.php` (spec I).
13. Run `php artisan test`; manually smoke-test each item per the Acceptance Criteria below.

---

## 4. Verification & Acceptance Criteria

- [ ] `/campaigns/create` and `/templates/create` / `/templates/{id}/edit` render successfully (no 500).
- [ ] Clicking "Launch" twice in quick succession sends each recipient exactly once (verify `sent_count` and per-recipient `CampaignLog` rows against the `array`/`log` mail driver output).
- [ ] POSTing to create a campaign with another user's `smtp_config_id`/`contact_list_id`/`email_template_id` returns 403.
- [ ] View source on `/smtp` — password is absent from page HTML; sending a campaign still succeeds after the `encrypted` cast is live (transport still builds correctly).
- [ ] A tampered/traversal `temp_file_path` on CSV import is rejected; a legitimate upload-then-import flow still works end-to-end.
- [ ] A campaign with `batch_size`/`batch_delay_seconds` set actually pauses at the configured batch boundary; a `SmtpConfig` with `hourly_limit` set auto-pauses the campaign once the cap is hit within an hour.
- [ ] A draft campaign with a past `scheduled_at` gets launched by `campaigns:process-scheduled` (run manually via `php artisan campaigns:process-scheduled` to verify without waiting on the scheduler).
- [ ] `php artisan migrate:fresh` succeeds; `campaign_logs.contact_id` now has a real FK constraint (verify via `PRAGMA foreign_key_list(campaign_logs)` on SQLite or equivalent).
- [ ] `npm run build` succeeds; `/login` and an authenticated `layouts.app` page render with correct brand colors, fonts, and icons with no CDN scripts loaded (check Network tab / view source).
- [ ] `resources/views/welcome.blade.php` no longer exists; nothing 404s or breaks as a result.
- [ ] `php artisan test` passes in full.
