# Phase 7: Open/Click Tracking & RFC 8058 Unsubscribe Engine

## 1. Goal Description
Implement open rate tracking via an invisible 1x1 transparent pixel, click tracking via secure redirection, and an **RFC 8058 compliant One-Click `List-Unsubscribe` Header and Portal** to maintain sender domain reputation and comply with Google and Yahoo 2024+ sender mandates.

---

## 2. Feature Specifications

### A. Open Tracking Engine
- Injects a unique 1x1 transparent PNG tracking pixel into every outbound email:
  `<img src="https://yourdomain.com/track/open/{tracking_token}.png" width="1" height="1" style="display:none;" />`
- When recipient mail client renders images:
  1. Tracking endpoint `/track/open/{token}.png` receives request.
  2. Increments `is_opened` and sets `opened_at` timestamp on `campaign_logs`.
  3. Increments `opened_count` on `campaigns` table.
  4. Returns a cached binary 1x1 transparent PNG (HTTP 200).

### B. Click Tracking Engine
- Automatically parses all hyperlinks `<a href="...">` in the email template.
- Rewrites links to route through `/track/click/{token}?url={encoded_destination}`.
- Records click timestamp before issuing an instant HTTP 302 redirect to the target destination.

### C. RFC 8058 Compliant One-Click Unsubscribe (Anti-Spam Requirement)
- Injects mandatory email headers required by Google, Yahoo, and Outlook:
  ```http
  List-Unsubscribe: <https://yourdomain.com/unsubscribe/{token}>, <mailto:unsubscribe@yourdomain.com?subject=unsubscribe:{token}>
  List-Unsubscribe-Post: List-Unsubscribe=One-Click
  ```
- Generates web unsubscribe portal allowing recipients to opt-out with a single click.
- Instantly adds unsubscribed email to the global suppression list to prevent future sends.

### D. Detailed Campaign Performance Report
- Per-campaign analytics dashboard:
  - Total Dispatched, Delivered, Opened, Clicked, Bounced, and Unsubscribed.
  - Device / Browser User-Agent breakdown (Desktop vs Mobile).
  - Open timeline graph (opens over first 24/48 hours).
  - Exportable CSV report of all delivery logs.

---

## 3. Step-by-Step Implementation Tasks
1. Build `TrackingController` with `open`, `click`, and `unsubscribe` methods.
2. Implement tracking token generator and HTML link rewriter in `TemplateRendererService`.
3. Add RFC 8058 headers into `SmtpMailService` mail builder.
4. Create recipient-facing unsubscribe confirmation page.
5. Build Campaign Analytics view (`resources/views/campaigns/report.blade.php`).

---

## 4. Verification & Acceptance Criteria
- [ ] Opening test email updates campaign opened count immediately.
- [ ] Clicking a link in the email logs click event and redirects smoothly to target URL.
- [ ] Unsubscribe link successfully adds recipient to suppression list and displays confirmation.
- [ ] Email headers contain valid `List-Unsubscribe` and `List-Unsubscribe-Post` directives.
