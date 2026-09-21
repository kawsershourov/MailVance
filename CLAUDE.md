# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

MailFlow — a Laravel 11 email marketing/campaign platform. Users connect SMTP relays, import large contact lists via streamed CSV upload (designed for 200MB+ files), build HTML email templates with merge tags, and launch rate-limited campaigns with open/click tracking and a suppression (unsubscribe/bounce) list.

Server-rendered Blade views styled with Tailwind CSS, sprinkled with Alpine.js for interactivity and Chart.js for dashboard charts — there is no SPA/API layer or JS framework build beyond Vite bundling `resources/js/app.js` and `resources/css/app.css`.

The README brands the product **MailVance**; `MailFlow` is the internal name still used by `config/mailflow.php`, `DEPLOYMENT.md` and the planning docs. Both refer to this codebase.

Planning docs for the original build are kept in `plan/` (overall architecture + ER diagram in `plan/email_sender_platform_plan.md`), `phase/` (per-phase specs) and `spec/bug_fix_spec.md` (the hardening pass that produced most of the security architecture below). These describe intended design and are useful background, but always verify against actual code — implementation may have diverged.

`DEPLOYMENT.md` is not background: it is the hard requirements for an internet-facing install (document root must be `public/`, `APP_KEY` must never be rotated in place because it decrypts every stored SMTP password, `TRUSTED_PROXIES` must be set or the IP-keyed rate limiters collapse into one bucket). Read it before changing anything about hosting, `.env` defaults, or the `.htaccess`.

## Commands

```bash
# Install deps
composer install
npm install

# Run everything (server + queue worker + log tailer + vite), matches composer.json "dev" script
composer run dev

# Or individually:
php artisan serve
php artisan queue:listen --tries=1   # campaign emails are queued jobs — must be running to actually send
php artisan schedule:work            # required for scheduled_at campaign launches + hourly-cap auto-resume
npm run dev                          # vite dev server
npm run build                        # production asset build

# Tests (PHPUnit, not Pest)
php artisan test
php artisan test --filter=test_method_name
php artisan test tests/Feature/SecurityTest.php

# Lint / format (Laravel Pint)
vendor/bin/pint
vendor/bin/pint --dirty

# DB
php artisan migrate
php artisan migrate:fresh
```

Testing uses `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, and an in-memory SQLite DB (see `phpunit.xml`), so queued jobs run inline, no real mail is sent, and the test run never touches the MySQL dev database. The suite is four real files: `EmailSenderPlatformTest` (happy-path CRUD + send flow), `SecurityTest` and `BugFixRegressionTest` (the two largest — they pin the hardening invariants described under **Security model**; a change that trips one of these is almost certainly reintroducing a fixed vulnerability), and `UserProfileAndPermissionsTest` (RBAC + profile).

`composer run dev` starts server, queue worker, log tailer and vite — but **not** `schedule:work`. Scheduled-send launches, hourly-cap auto-resume, and temp-upload pruning need it running separately.

Local setup uses the XAMPP MariaDB instance (`DB_CONNECTION=mysql`, database `email_sender`, socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`) with `QUEUE_CONNECTION=database` and `MAIL_MAILER=log` per `.env`. The queue driver is `database`, so a queue worker (`php artisan queue:listen` / `queue:work`) must be running for campaign emails to actually go out — the web request only enqueues jobs. Campaign mail does not use `MAIL_MAILER`; `SmtpMailService` builds its own transport per `SmtpConfig` row.

## Architecture

### Domain model

`User` owns `SmtpConfig` (multiple SMTP relays), `ContactList` → `Contact` (many-to-one), `EmailTemplate`, and `Campaign`. A `Campaign` references one `SmtpConfig`, one `ContactList`, and one `EmailTemplate`, and produces one `CampaignLog` row per recipient (created at launch time from the target list's contacts). The bulk of the schema is one migration (`2026_08_31_000001_create_email_sender_tables.php`), with three follow-ups layered on top: `2026_09_01_000001` (template `design` JSON), `2026_09_01_000002` (roles/permissions + user profile columns), and `2026_09_02_000001` (suppression scoping). Check all four before assuming a column's shape.

`SuppressionList` is **owned, not global** — it carries a nullable `user_id`, and the unique index is `(user_id, email)`. A null `user_id` is a platform-wide entry an operator added; every other row belongs to one account. It was global once, which made the public unsubscribe endpoint a cross-tenant weapon: suppressing an address for one sender removed it from everybody else's campaigns. Always resolve entries through `SuppressionList::appliesTo($userId)` (own rows + platform-wide) or `lookupFor($userId)` (a flipped array for O(1) membership tests during import) — never query `email` alone.

### Campaign send flow

1. `CampaignController::launchCampaign` delegates to `CampaignLaunchService::launch()`, which atomically claims the campaign (`where status = draft` → `processing`) and only then dispatches one `SendCampaignEmailJob` per `pending` `CampaignLog`. A second concurrent launch claims nothing and is rejected, so double-submits cannot double-send. `resume()` works the same way from `paused`.
2. `SendCampaignEmailJob::handle` (`app/Jobs/SendCampaignEmailJob.php`) does the real work per email: bails out if the campaign has since been paused/cancelled/reverted to draft, resolves the `SmtpConfig` and `EmailTemplate`, renders merge tags via `TemplateRendererService`, rewrites links for click tracking, **blocks synchronously with `usleep()`** for `delay_seconds` (+ optional jitter) as its throttle mechanism, sends via `SmtpMailService::sendCampaignEmail`, and updates the `CampaignLog`/`Campaign` counters. When no `pending` logs remain for the campaign it flips `status` to `completed`.
3. Pause/cancel only toggle `campaigns.status` — already-dispatched/queued jobs still run but no-op (see step 2's early bail), so a paused campaign's in-flight queue jobs drain without sending. Resume re-dispatches the remaining `pending` logs via `CampaignLaunchService::resume()`.
4. Before sending, the job atomically claims its own row (`where status = pending` → `sending`) and bails if it claims nothing — a second layer that keeps a duplicate-dispatched job from emailing the same recipient twice.
5. `batch_size`/`batch_delay_seconds` pause the worker at batch boundaries via a cache counter. A `SmtpConfig.hourly_limit` that is hit releases the recipient back to `pending`, pauses the campaign, and records a `campaign:{id}:hourly_cap_resume_at` cache marker; the `campaigns:process-scheduled` command (scheduled every minute) resumes it once the hour clears, and also launches `draft` campaigns whose `scheduled_at` has arrived.

Because throttling happens via blocking `usleep()` inside the job rather than Laravel's queue `delay()`, a single queue worker processes campaign emails effectively serially at the configured pace — running multiple queue workers in parallel is how you'd increase throughput, and doing so bypasses the configured delay between emails.

### SMTP + mail sending

`SmtpMailService` builds a Symfony Mailer transport at runtime from a `SmtpConfig` row (`Transport::fromDsn(...)`), rather than using Laravel's static `config/mail.php` mailers — every send picks its transport dynamically per campaign/user. It also injects the open-tracking pixel and anti-spam/one-click-unsubscribe headers (`List-Unsubscribe`, RFC 8058) directly when composing the `Symfony\Component\Mime\Email`. `testConnection()` does a raw `fsockopen` handshake check before attempting an actual Symfony send, for the SMTP diagnostics UI.

### Tracking

Each `CampaignLog` gets a unique `tracking_token` (a per-recipient UUIDv4) at creation. That token is the *only* credential these public, unauthenticated routes accept — nothing here trusts a caller-supplied address. `TrackingController` serves:

- `/track/open/{token}.png` — 1x1 pixel, marks `is_opened`.
- `/track/click/{token}?url=...&sig=...` — marks `is_clicked`, then redirects **only if** `sig` is a valid HMAC over `url`. Campaign links legitimately point anywhere on the web, so an allowlist is impossible; instead `TrackingUrlSigner` stamps every URL at rewrite time and the redirect drops anything it did not stamp. Without this the endpoint is an open redirect on the sending domain. `isRedirectable()` additionally confines schemes to http/https (`FILTER_VALIDATE_URL` alone passes `javascript:`). The signing key is `config('app.key')`, base64-decoded.
- `GET /unsubscribe/{token}` — **read-only confirmation page**. Mail clients, link scanners and browser prefetch all fire GETs, so a GET that suppressed the recipient would unsubscribe people who never clicked.
- `POST /unsubscribe/{token}` — the acting route, and what RFC 8058 one-click actually calls. Adds to `suppression_lists` scoped to the *campaign owner*, and marks matching contacts `unsubscribed` only within that owner's lists. CSRF is exempted for `unsubscribe/*` in `bootstrap/app.php` because the provider POSTs with no session.

Outbound links are rewritten to the click-tracking endpoint by `TemplateRendererService::rewriteLinksForTracking`, which explicitly skips rewriting `/unsubscribe/` links.

### CSV import

`CsvStreamService` handles both the upload preview (`inspectHeaders` — sniffs headers + auto-maps `email`/`first_name`/`last_name`/`company` by column-name heuristics, returns 5 sample rows) and the actual import (`streamImport` — raw `fopen`/`fgetcsv` streaming, not loaded into memory at once, batched `insertOrIgnore` every 1000 rows, RFC email validation, suppression-list filtering, and any unmapped columns preserved into `contacts.custom_fields` JSON keyed by original header name). Contacts are deduped via a DB-level unique constraint on `(contact_list_id, email)`, so `insertOrIgnore` is what makes re-imports/duplicates safe. `streamImport` takes an `$ownerId` so suppression filtering resolves against that account's entries.

Import is a **two-request handshake**, and the second request must never trust the first's output. `uploadCsvPreview` stores the file under `storage/app/private/csv_temp` with a generated name and registers a cache token keyed `csv_import_token:{userId}:{filename}`. `processCsvImport` resolves the client-supplied reference back to a real path through `resolveCsvUploadPath()`, which requires all four of: the reference is a bare filename (`basename($ref) === $ref`), a `Cache::pull` of that user's token succeeds (single-use, so an upload reference cannot be replayed), and `realpath` still lands inside `csv_temp`. Keep all four if you touch this — the client once supplied the path directly, which was an arbitrary-file-read.

An upload that previews cleanly but is never imported is orphaned, so `contacts:prune-temp-uploads` (scheduled hourly, `--hours=2`) reaps `csv_temp`. At 200MB per file with no per-user quota, this is what stops a disk filling.

### Template merge tags

`TemplateRendererService::render()` does simple `strtr()` replacement of `{{first_name}}`, `{{last_name}}`, `{{name}}`, `{{email}}`, `{{company}}`, `{{unsubscribe_url}}`, plus any keys present in a contact's `custom_fields` JSON. When called without a `Contact` (e.g. live template preview), it falls back to dummy "John Doe" data.

### Logo alignment

The logo's position comes from `design['sections']['logo']['align']` — that is the **only** value the renderer reads. The Logo panel's dropdown and the "Logo" entry in the spacing panel are two views of it. A top-level `logo_align` exists for designs saved before this was fixed (the panel used to write it and nothing read it); `normalize()` folds it into the logo section unless that section's align was set explicitly, and keeps the two in step afterwards.

The logo `<img>` is `display:block`, which **ignores the cell's `align` attribute** — only auto margins move a block element. `logoMargin()` emits `margin:0` / `margin:0 auto` / `margin:0 0 0 auto`, and the mobile media query overrides that margin (not just `text-align`) for the same reason.

### Responsive email / mobile overrides

`EmailTemplateBuilderService` renders desktop styling **inline** (Outlook ignores most CSS) and emits exactly one `<style>` block containing a single `@media only screen and (max-width:600px)` query — media queries are the only way to vary a layout by viewport in email. Clients that drop the block still get the complete desktop design from the inline styles.

That query always carries `.mf-card{width:100% !important;}`: the card is a fixed `content_width` table, which does **not** shrink on its own and otherwise overflows a phone screen.

`design['mobile']` holds phone-only overrides for `font_size`, `heading_size`, and each section's `align` + `pt/pr/pb/pl`. **A null entry means "inherit the desktop value"** — this is what keeps the two views independent, so editing the mobile layout never rewrites the desktop design. Overridden values are emitted as `!important` rules inside the media query against the `mf-*` classes on each block; because CSS `padding` shorthand is all-or-nothing, overriding one side re-emits the other three from the desktop values.

In the editor, `previewDevice` decides where the controls write: the Alpine getter/setter pairs (`fontSize`, `headingSize`, `secAlign`, `secPt/Pr/Pb/Pl`) target `design.mobile` in mobile view and `design` in desktop view. Because it is the *edit target* and not just a view toggle, **nothing may set `previewDevice` but the Desktop/Mobile buttons** — switching it automatically (on viewport width, say) would silently record phone-only overrides the user never asked for. The desktop preview frame is clamped to a minimum 620px (`desktopPreviewWidth`) so a narrow `content_width` can't drop it under the 600px breakpoint and wrongly show mobile styling.

To fit that 620px+ frame on a narrow screen, the preview is shrunk with a **CSS `transform: scale()`** (`previewScale`, sized from the measured `#mfPreviewViewport` width), with a wrapper reserving the scaled box and a `%` badge in the preview header. `transform` is paint-only: the frame keeps its true layout width, so the iframe's own viewport stays at 620px+ and the email's `max-width:600px` rules still evaluate against it. **Never substitute CSS `zoom`** — `zoom` affects layout, would take the iframe's inner viewport under 600px, and would show mobile styling in the desktop preview: exactly the bug the clamp exists to prevent. The measurement reads `document.getElementById`, not `$refs`, which is not reliably populated when `watchPreviewSize()` runs; an unmeasured width (`0`) is treated as "render unscaled", so a missed measurement shows up as overflow rather than a wrong scale.

### Deliverability checks

`DeliverabilityScoreService` is two independent, stateless checks: `checkDomainDns()` does live DNS lookups (`dns_get_record`) for MX/SPF/DMARC on a given domain, and `analyzeSpamScore()` does static keyword/heuristic analysis (spam trigger words, all-caps subject, exclamation spam, HTML-to-text ratio) on template content — no external API calls involved in either.

### Security model

Most of this was retrofitted in one hardening pass (`spec/bug_fix_spec.md`) and is pinned by `tests/Feature/SecurityTest.php`. Each control exists because its absence was exploitable — don't relax one without reading the comment that explains the cost.

**SMTP relay egress.** Users supply their own relay host and the diagnostics screen opens a socket to it, so without limits the platform is an internal port scanner. `App\Rules\SafeSmtpHost` (used on store/update) requires a bare hostname or IP — which is also what blocks `smtp.example.com?verify_peer=0` from reaching `Transport::fromDsn()` and silently disabling cert verification — then resolves **every** A/AAAA record and rejects the host if any is private/reserved (a name with one public and one private record must not slip through). Ports are confined to `config('mailflow.smtp.allowed_ports')`. `block_private_hosts` may be disabled only for single-tenant installs where relays are legitimately on the LAN.

**SMTP credentials.** `SmtpConfig::$casts` marks `password` as `encrypted` (hence the `APP_KEY` rotation warning in `DEPLOYMENT.md`) and `$hidden` drops it from serialization — views `json_encode()` the model to seed the edit modal, which would otherwise print the credential into page HTML. `smtp_password` is also in the `dontFlash` list in `bootstrap/app.php`, so a validation bounce cannot round-trip it into the session.

**Rate limiting.** Laravel 11 ships no `RouteServiceProvider`, so every named limiter is declared in `AppServiceProvider::configureRateLimiting()` — a route referencing a limiter that isn't declared there will fail. Existing buckets: `login` (keyed on email **and** IP, so an attacker can't lock a known victim out), `register`, `smtp-test`, `csv-upload`, `campaign-launch`, `preview`, `deliverability`, `tracking`, `unsubscribe`. Authenticated limiters key on user id, falling back to IP (`actorKey()`).

**Response headers.** `SecurityHeaders` middleware is appended to the `web` group: frame denial, nosniff, referrer policy (which is what stops `/track/click` leaking campaign URLs to third-party sites), permissions policy, and a CSP. The CSP still needs `'unsafe-inline'`/`'unsafe-eval'` for script because the template editor is Alpine-driven with inline handlers — everything else is locked down. HSTS is only emitted over TLS in production.

**Other.** Registration is off by default (`config('mailflow.allow_registration')`); the routes stay registered so `route('register')` resolves, and the controller 404s rather than leaking that the feature exists. Password defaults are min-12/mixed-case/numbers, with the haveibeenpwned check added only in production. `trustProxies` reads `TRUSTED_PROXIES`. Template logos and profile avatars live on the **private** disk and are streamed through authorized controller routes, never served from `public/`.

### Authorization

Two independent layers, and new endpoints need both:

1. **Ownership** — there's no policy/gate layer; controllers check ownership inline (`if ($model->user_id !== Auth::id()) abort(403);`) per action. Keep this pattern on user-owned resources. Roles never grant access to *another* user's campaigns, lists, templates or relays.
2. **Permissions** — what a user may *do* at all, enforced by the `permission:` route middleware (`->middleware('permission:campaigns.launch')`, `|` between slugs means "any of"). There's also `role:<slug>` and `active` (which logs out deactivated accounts mid-session).

`App\Services\PermissionRegistry` is the single source of truth for the permission catalogue — add a slug there, re-run `php artisan db:seed --class=RolesAndPermissionsSeeder`, and it appears in the role/user matrices automatically.

A user's effective permissions are the union of their roles' permissions, with per-user overrides applied last: a row in `permission_user` with `granted = false` revokes even what a role grants, `true` grants without one. `User::hasPermission()` / `hasAnyPermission()` resolve this (memoised per instance — call `forgetPermissionCache()` after changing roles or overrides). The `super-admin` role short-circuits every check and implicitly holds future permissions, so it carries no `permission_role` rows.

Blade mirrors the middleware with `@permission('slug', ...)` / `@endpermission` and `@anyrole(...)`; use them to hide controls that would otherwise 403, as the module views already do.

Built-in roles: `super-admin`, `admin`, `manager`, `member`, `viewer` (`is_system = true`, so they can't be deleted and their slugs are fixed). Guard rails in `Admin\UserController`: only a super admin can manage or mint another super admin, nobody can deactivate/delete their own account, and the last super admin can't be demoted away.

**Fresh installs must run `php artisan db:seed --class=RolesAndPermissionsSeeder`** — without it the roles/permissions tables are empty and nobody has any permission. The seeder is idempotent, only resets system roles, and assigns the earliest account to `super-admin`.

### User profiles

Profile fields (`avatar_path`, `job_title`, `company`, `phone`, `timezone`, `bio`, `is_active`, `last_login_at`) live directly on `users`. `ProfileController` handles self-service editing at `/profile`; `Admin\UserController` handles the same fields for other people. Avatars are stored on the **private** local disk and streamed back through `profile.avatar` (like template logos) rather than served from `public/`.
