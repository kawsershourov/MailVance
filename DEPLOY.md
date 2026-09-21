# Deploying updates to production

This is the **repeatable runbook for shipping an already-deployed install**
(mailvance.webvanceit.co.uk, on Hostinger). For first-run setup and the
security-hardening requirements (vhost config, `.env` values, `APP_KEY`
rotation), see `DEPLOYMENT.md` instead — this file assumes that's already
done.

Deploy model: SSH + `git pull` on the server, pulling from the `origin`
GitHub remote that `main` is pushed to locally.

Server connection (fill in once, keep out of git if you'd rather not have
the host in a public repo — see note at the bottom):

```
ssh -p <SSH_PORT> <SSH_USER>@<SSH_HOST>
cd <APP_PATH>
```

## 1. Pre-flight (local)

```bash
php artisan test
git status
git diff
```

Confirm the suite is green and review the diff for anything that shouldn't
ship (stray `.env` values, credentials, debug code).

## 2. Commit & push (local)

```bash
git add -A
git commit -m "..."
git push origin main
```

## 3. Build front-end assets (local)

```bash
npm run build
```

`/public/build` is **gitignored**, so a `git pull` on the server never
updates compiled CSS/JS by itself. After building locally, either:

- **rsync/scp** the built `public/build/` directory up to the server, or
- if Node/npm are available on the server (check with `node -v && npm -v`
  over SSH), run `npm install && npm run build` there instead and skip the
  local build/rsync.

## 4. Deploy on the server (SSH)

```bash
cd <APP_PATH>
git status                 # sanity check: no unexpected local diffs
git pull origin main

composer install --no-dev --optimize-autoloader

php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder   # idempotent; picks up any new permission slugs
php artisan storage:link                                # no-op if already linked

php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart
```

If you built locally instead of on the server, `scp`/`rsync` `public/build/`
up now.

`php`/`composer` may need a versioned binary on shared hosting (e.g.
`php8.2`, `~/bin/composer`) if the plain command isn't on `PATH`.

`queue:restart` only signals the running worker to exit after its current
job — whatever actually keeps `queue:work` alive (Supervisor, a Hostinger
cron watchdog, etc.) has to relaunch it. Confirm that mechanism is in place
before assuming the restart took effect (`crontab -l` is a good first
check).

## 5. Verify

- Load `https://mailvance.webvanceit.co.uk/login` and confirm styling loads.
- Spot-check any screens touched by this deploy (e.g. admin Settings, SMTP
  config, login/register).
- Re-run the `DEPLOYMENT.md` §5 curl checks if routes/middleware changed:

```bash
curl -sI https://mailvance.webvanceit.co.uk/login | grep -iE 'content-security-policy|x-frame|nosniff|referrer|strict-transport'
curl -sI https://mailvance.webvanceit.co.uk/.env        # must be 403/404, never 200
curl -s  https://mailvance.webvanceit.co.uk/track/click/anything?url=https://evil.example  # must not redirect off-site
```

---

*Note on the placeholders above:* this file intentionally doesn't hardcode
the real SSH host/port/user/path so that a public (or later-made-public)
copy of this repo doesn't advertise the production login target. Keep the
real values in a password manager or a local, untracked note instead.
