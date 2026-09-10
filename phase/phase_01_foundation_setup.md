# Phase 1: Foundation, Stack Setup & Responsive Shell

## 1. Goal Description
Establish the robust architectural foundation for the Email Sender Web Platform using Laravel 11 (PHP 8.5), Tailwind CSS, Alpine.js, Lucide Icons, and database configuration (SQLite / MariaDB). Build the master layout, dark/light ready UI shell, responsive collapsible sidebar, top navigation, and toast notification system.

---

## 2. Technical Stack & Environment
- **Framework**: Laravel 11.x (PHP 8.5)
- **Database**: SQLite (default zero-friction) / MariaDB (XAMPP compatibility)
- **Styling**: Tailwind CSS 3.4+
- **Interactivity**: Alpine.js 3.x
- **Icons**: Lucide Icons
- **Target Directory**: `/Applications/XAMPP/xamppfiles/htdocs/email_sender`

---

## 3. Directory & Component Architecture
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── DashboardController.php
│   │   ├── SmtpController.php
│   │   ├── ContactController.php
│   │   ├── TemplateController.php
│   │   ├── CampaignController.php
│   │   └── TrackingController.php
│   └── Middleware/
├── Models/
│   ├── User.php
│   ├── SmtpConfig.php
│   ├── ContactList.php
│   ├── Contact.php
│   ├── EmailTemplate.php
│   ├── Campaign.php
│   └── CampaignLog.php
├── Jobs/
│   ├── ImportCsvJob.php
│   └── SendCampaignEmailJob.php
└── Services/
    ├── SmtpMailService.php
    ├── CsvStreamService.php
    ├── TemplateRendererService.php
    └── DeliverabilityScoreService.php
```

---

## 4. Master Layout & Sidebar Structure
- **Sidebar Navigation Links**:
  1. 📊 **Dashboard**: Overview metrics (Sent, Failed, Opened, Clicked, Graphs)
  2. ⚙️ **SMTP Servers**: Multi-SMTP configuration & live test sender
  3. 👥 **Audience & Contacts**: Contact lists & 200MB CSV upload
  4. 🎨 **Email Templates**: Visual WYSIWYG editor & mobile preview
  5. 🚀 **Campaigns**: Creation wizard, throttle/delay scheduler & live monitor
  6. 📈 **Reports & Analytics**: Per-campaign delivery breakdowns
  7. 🛡️ **Anti-Spam & Deliverability**: DNS health & spam score inspector
  8. 👤 **Settings**: Profile & security keys

---

## 5. Step-by-Step Implementation Tasks
1. Initialize Laravel 11 project structure in workspace.
2. Configure `.env` with app settings, queue connection (`database`), and storage symlinks.
3. Install frontend assets (Tailwind CSS, Alpine.js, Lucide Icons, Chart.js).
4. Create base Blade layout `resources/views/layouts/app.blade.php` with responsive sidebar, header, and notification toasts.
5. Create base migrations for core platform tables.
6. Verify local development server boots cleanly.

---

## 6. Verification & Acceptance Criteria
- [ ] Application loads on localhost without runtime errors.
- [ ] Sidebar collapses seamlessly on mobile and expands on desktop.
- [ ] Tailwind styles and Alpine.js interactive elements work smoothly.
