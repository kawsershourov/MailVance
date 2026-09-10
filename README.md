# MailVance ✉️🚀

**MailVance** is a powerful, high-performance email marketing and campaign automation platform built on **Laravel 11**. Designed for scale, security, and ease of use, MailVance enables businesses and marketers to manage subscriber lists, build responsive HTML email templates, configure multi-SMTP relays, execute rate-limited campaigns, track real-time analytics, and enforce granular role-based access control.

---

## ✨ Key Features

### 🔌 Multi-SMTP Relay Management
- Connect multiple custom SMTP relays with independent hourly limit caps.
- Instant connection testing and diagnostic tool using raw TCP/SMTP socket handshakes (`fsockopen`).
- Dynamic runtime transport building per campaign.

### 📥 High-Performance CSV Contact Import
- Memory-efficient streaming CSV parser designed to process large files (**200MB+**).
- Automatic header sniffing and field auto-mapping (`email`, `first_name`, `last_name`, `company`).
- Automatic contact deduplication and suppression list filtering.
- Dynamic `custom_fields` storage for custom merge attributes.

### 🎨 Visual Email Template Builder
- Rich HTML email builder with real-time **Desktop and Mobile** preview modes.
- Responsive CSS inlining engine ensuring compatibility with email clients like Outlook, Gmail, and Apple Mail.
- Support for dynamic merge tags (`{{first_name}}`, `{{email}}`, `{{company}}`, `{{unsubscribe_url}}`).

### 📦 Campaign Engine & Background Queuing
- Rate-limited sending engine with configurable delay between emails.
- Automatic batch pauses and hourly limit auto-resume schedules.
- Real-time campaign lifecycle states (`Draft`, `Processing`, `Paused`, `Completed`, `Cancelled`).
- Safe queue architecture preventing duplicate recipient dispatches.

### 📊 Real-Time Analytics & Tracking
- **Open Tracking**: Automatic 1x1 transparent tracking pixel injection.
- **Click Tracking**: Outbound URL rewriting with click event records and instant redirection.
- **Unsubscribe Management**: Automated one-click unsubscribe links and RFC 8058 `List-Unsubscribe` headers.
- Interactive performance dashboard powered by Chart.js.

### 🛡️ Deliverability & Spam Analysis
- **DNS Deliverability Analyzer**: Real-time domain verification checking SPF, DMARC, and MX records.
- **Spam Score Engine**: Static keyword and heuristic scanner detecting spam trigger phrases, excessive capitalization, and low HTML-to-text ratios.

### 🔐 Granular Role-Based Access Control (RBAC)
- 5 built-in system roles: `Super Admin`, `Admin`, `Manager`, `Member`, `Viewer`.
- Modular permission catalogue for granular resource control.
- Profile management with private storage avatar streaming and session security.

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.2+, Laravel 11 Framework
- **Queue & Mail Engine**: Laravel Queue Worker (Database driver), Symfony Mailer
- **Frontend**: Blade Templating, Tailwind CSS, Alpine.js, Chart.js, Vite
- **Database**: MySQL / MariaDB

---

## 🚀 Getting Started

### Prerequisites
- PHP >= 8.2 (with `pdo`, `mbstring`, `openssl`, `curl`, `sockets`, `gd` extensions)
- Composer
- Node.js & npm
- MySQL or MariaDB

### Installation

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/kawsershourov/MailVance.git
   cd MailVance
   ```

2. **Install Dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Configure your database credentials in `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=mailvance
   DB_USERNAME=root
   DB_PASSWORD=
   QUEUE_CONNECTION=database
   ```

4. **Run Migrations & Seeders**:
   ```bash
   php artisan migrate
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```

5. **Build Assets**:
   ```bash
   npm run build
   ```

6. **Start Application**:
   Run all services concurrently using composer dev script:
   ```bash
   composer run dev
   ```
   *Or run individually:*
   ```bash
   php artisan serve                       # Web server
   php artisan queue:listen --tries=1      # Campaign queue worker
   php artisan schedule:work               # Campaign launch & resume scheduler
   npm run dev                             # Vite dev server
   ```

---

## 🧪 Running Tests

MailVance includes a comprehensive PHPUnit test suite using an in-memory SQLite database:

```bash
php artisan test
```

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).
