# Phase 3: Multi-SMTP Server Manager & Diagnostic Tester

## 1. Goal Description
Build the multi-SMTP connection manager allowing users to connect, configure, and switch between multiple SMTP relays (Amazon SES, SendGrid, Mailgun, Postmark, Google Workspace, or custom private SMTP servers). Includes an **Instant Test Email Sender** with real-time handshake diagnostic logs and a **DNS SPF/DKIM/DMARC Health Checker** to prevent spam classification.

---

## 2. Feature Specifications

### A. Multi-SMTP Configuration
- **Connection Parameters**:
  - Provider Label (e.g. "Primary AWS SES", "SendGrid Marketing Relay")
  - Host (e.g. `smtp.sendgrid.net`, `email-smtp.us-east-1.amazonaws.com`)
  - Port (`587`, `465`, `25`, `2525`)
  - Encryption (`TLS`, `SSL`, `STARTTLS`, `None`)
  - Username & Encrypted Password storage
  - Default From Name & From Email Address
  - Reply-To Email Address
  - Hourly / Daily Sending Limit Capping
  - Default Sender Flag

### B. Instant Test Email Sender Tool
- Modal interface to input a recipient test address.
- Dispatches a diagnostic test email using the selected SMTP credentials.
- Displays live step-by-step SMTP handshake logs:
  - Socket Connection -> TLS Handshake -> EHLO -> AUTH LOGIN -> MAIL FROM -> RCPT TO -> DATA -> 250 OK.
  - Clear error diagnosis if connection fails (e.g. Bad credentials, Port blocked, SSL certificate mismatch).

### C. Anti-Spam DNS Health Inspector (SPF / DKIM / DMARC)
- Automated DNS record lookup for the sender domain:
  - **SPF Check**: Verifies `v=spf1` TXT record includes the SMTP server IP/host.
  - **DKIM Check**: Verifies public key selector existence.
  - **DMARC Check**: Verifies `v=DMARC1; p=quarantine/reject` policy.
  - **MX Check**: Verifies valid inbound MX records.
- Provides actionable DNS copy-paste instructions if records are missing.

---

## 3. Dynamic Mailer Architecture
```php
// Dynamic runtime mailer configuration in SmtpMailService
public function createTransport(SmtpConfig $config): TransportInterface {
    $scheme = match(strtolower($config->encryption)) {
        "ssl" => "smtps",
        "tls", "starttls" => "smtp",
        default => "smtp",
    };
    return Transport::fromDsn(
        sprintf("%s://%s:%s@%s:%d",
            $scheme,
            urlencode($config->username),
            urlencode($config->password),
            $config->host,
            $config->port
        )
    );
}
```

---

## 4. Step-by-Step Implementation Tasks
1. Create `smtp_configs` migration & `SmtpConfig` model.
2. Build `SmtpController` for listing, creating, editing, and deleting SMTP profiles.
3. Build `SmtpMailService` with dynamic transport creation and diagnostic socket logger.
4. Implement DNS health inspector service (`dns_get_record` validation).
5. Build UI views with Test Email Modal and DNS status badges.

---

## 5. Verification & Acceptance Criteria
- [ ] User can add and edit multiple SMTP configurations.
- [ ] Test email sends successfully and renders live handshake logs in UI.
- [ ] Domain DNS inspector accurately identifies SPF, DKIM, and DMARC records.
