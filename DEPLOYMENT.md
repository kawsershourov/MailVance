# Deploying MailFlow safely

Everything here is a hard requirement for an internet-facing, multi-tenant
install. The defaults in `.env.example` already reflect it; this file explains
*why*, so nobody relaxes one of them without knowing the cost.

## 1. Point the document root at `public/`

Today the app is reached at `htdocs/email_sender/`, which means the **project
root** is the web root, and a four-line `.htaccess` rewrite is the only thing
standing between the internet and `.env`, `storage/logs/laravel.log`,
`database/`, `vendor/` and `composer.lock`. If `mod_rewrite` is ever disabled,
or Apache is configured `AllowOverride None` for that directory, the rewrite
silently stops applying and every one of those files becomes directly
fetchable — including the `APP_KEY` that is the only protection on every stored
SMTP password.

A security control belongs in the server config, not in a file the server can be
told to ignore:

```apache
<VirtualHost *:443>
    ServerName mail.example.com
    DocumentRoot /var/www/mailflow/public

    <Directory /var/www/mailflow/public>
        AllowOverride All
        Require all granted
        Options -Indexes -MultiViews
    </Directory>

    # Belt and braces: even if the root is ever misconfigured.
    <DirectoryMatch "/var/www/mailflow/(storage|database|bootstrap/cache|vendor|app|config|routes)">
        Require all denied
    </DirectoryMatch>
    <FilesMatch "^\.env">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

Once the vhost points at `public/`, delete the root `.htaccess`.

## 2. Environment

| Key | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | Gates HSTS and `URL::forceScheme('https')`. |
| `APP_DEBUG` | `false` | Debug pages render the full environment, **including `APP_KEY`**. |
| `APP_URL` | `https://...` | Tracking pixels and unsubscribe links are built from it. |
| `DB_USERNAME` / `DB_PASSWORD` | a real, least-privilege account | Never the XAMPP `root` with an empty password. |
| `SESSION_SECURE_COOKIE` | `true` (the default) | Otherwise the session cookie travels over plain HTTP. |
| `SESSION_SAME_SITE` | `strict` (the default) | |
| `SESSION_ENCRYPT` | `true` | Session payloads are stored in the database. |
| `LOG_LEVEL` / `LOG_STACK` | `warning` / `daily` | `debug` on a single unrotated file. |
| `ALLOW_REGISTRATION` | `false` | Open signup on a sending platform is an abuse magnet. |
| `TRUSTED_PROXIES` | your LB's IPs, or `*` | Without it every request appears to come from the proxy, collapsing the IP-keyed rate limiters into one shared bucket. |

## 3. Never rotate `APP_KEY` in place

SMTP relay passwords are stored with the `encrypted` cast, so they are encrypted
with `APP_KEY` and nothing else. Rotating it without first populating
`APP_PREVIOUS_KEYS` **permanently destroys every stored relay credential**.

To rotate: put the current key into `APP_PREVIOUS_KEYS`, set the new `APP_KEY`,
deploy, then re-save each relay so it is re-encrypted under the new key.

## 4. First run

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder   # required: without it nobody has any permission
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The queue worker and scheduler are **not optional** — campaign mail is queued,
and scheduled launches plus hourly-cap auto-resume run off the scheduler:

```bash
php artisan queue:work --tries=1
* * * * * cd /var/www/mailflow && php artisan schedule:run >> /dev/null 2>&1
```

Note that throttling is enforced by a blocking `usleep()` inside the job, so
running several workers in parallel multiplies throughput *and* bypasses the
configured delay between sends.

## 5. Verifying the hardening

```bash
curl -sI https://mail.example.com/login | grep -iE 'content-security-policy|x-frame|nosniff|referrer|strict-transport'
curl -sI https://mail.example.com/.env        # must be 403/404, never 200
curl -s  https://mail.example.com/track/click/anything?url=https://evil.example  # must not redirect off-site
```
