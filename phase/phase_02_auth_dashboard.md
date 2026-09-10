# Phase 2: Authentication & Real-Time Analytics Dashboard

## 1. Goal Description
Implement secure user authentication (Registration, Login, Session Management, Logout) and the primary Analytics Dashboard featuring real-time KPI metric cards (Sent, Not Sent/Failed, Opened, Not Opened, Clicked) and interactive Chart.js visualizations.

---

## 2. Feature Specifications

### A. Authentication System
- **Login Screen**: Clean, branded login page with email, password, "Remember Me", and validation feedback.
- **Registration Screen**: Secure account creation with bcrypt password hashing.
- **Session Protection**: Auth middleware protecting all dashboard and campaign routes.

### B. Analytics Dashboard
- **Top Metric KPI Cards**:
  1. **Total Emails Sent**: Lifetime and current billing/campaign cycle.
  2. **Total Not Sent / Failed**: Bounced, invalid syntax, or SMTP connection errors.
  3. **Total Opened**: Unique and total open counts tracked via 1x1 transparent pixel.
  4. **Total Not Opened**: Pending recipient engagement.
  5. **Open Rate (%)**: `(Opened / Total Sent) * 100` with visual progress ring.
  6. **Click Rate (%)**: `(Clicked / Total Sent) * 100` CTR metric.
- **Interactive Graphs (Chart.js)**:
  - **Sending Volume Over Time**: 30-day timeline chart of sent vs opened emails.
  - **Campaign Performance Comparison**: Bar chart comparing recent campaigns.
- **Live Activity Feed**:
  - Recent campaign dispatch status (Draft, Running, Completed, Paused).
  - Recent delivery logs & SMTP status indicators.

---

## 3. Database Schema (Auth & Dashboard Metrics)
- `users` table: `id`, `name`, `email`, `password`, `remember_token`, `created_at`
- Aggregation queries optimized with indexing on `campaign_logs.status`, `campaign_logs.is_opened`, and `campaign_logs.sent_at`.

---

## 4. Step-by-Step Implementation Tasks
1. Build `AuthController` with `showLogin`, `login`, `showRegister`, `register`, and `logout` actions.
2. Design authentication views (`resources/views/auth/login.blade.php`, `register.blade.php`).
3. Build `DashboardController` with optimized SQL aggregation metrics.
4. Design dashboard view (`resources/views/dashboard/index.blade.php`) with Chart.js integration.
5. Add quick action shortcuts ("New Campaign", "Import Contacts", "Add SMTP").

---

## 5. Verification & Acceptance Criteria
- [ ] User can register, log in, and log out with secure session handling.
- [ ] Unauthenticated users are redirected to the login page.
- [ ] Dashboard displays accurate dynamic numbers for Sent, Failed, Opened, and Not Opened.
- [ ] Charts render responsively on desktop and mobile.
