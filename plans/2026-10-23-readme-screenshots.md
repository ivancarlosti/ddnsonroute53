# README Screenshots

- **Date:** 2026-10-23
- **Status:** Implemented
- **Scope:** `docs/screenshots/`, `README.md`, `.dockerignore`, `.github/workflows/build.yml`

## Goal

Document the UI in `README.md` and host the image files in `docs/screenshots/` so
GitHub can render them from the repository (relative links keep working on forks and
in the release archives that exclude `docs/`).

## Inventory

| File | Page | Route | Capture viewport |
| --- | --- | --- | --- |
| `docs/screenshots/01-login.png` | Login (Keycloak SSO) | `/index.php` | 1440×900 |
| `docs/screenshots/02-setup-wizard.png` | Setup wizard | `/setup.php` (empty database) | 1000×480 |
| `docs/screenshots/03-dashboard.png` | Dashboard + cURL command | `/dashboard.php` | 1440×560 |
| `docs/screenshots/04-manage-ddns.png` | Add / list / update DDNS entries | `/manage_ddns.php` | 1440×1400 |
| `docs/screenshots/05-manage-aws.png` | AWS credentials form | `/manage_aws.php` | 1440×1180 |
| `docs/screenshots/06-manage-users.png` | User list + add user | `/manage_users.php` | 1440×940 |
| `docs/screenshots/07-view-logs.png` | All DDNS logs + pagination | `/view_logs.php` | 1440×930 |

The images were captured from the PHP 8.5 image built in
[`2026-10-23-php-8.5-upgrade.md`](2026-10-23-php-8.5-upgrade.md) with dummy data only
(`example.com`, `203.0.113.0/24`, `198.51.100.0/24`, `192.0.2.0/24`, AWS example keys).
No real credentials, FQDNs or customer data are present.

## README layout

The section is placed right after the buttons block and **before** `<!-- footer -->`:

```markdown
## Screenshots

Captured from a demo instance running PHP 8.5 with sample data.

| Login (Keycloak SSO) | Setup wizard |
| --- | --- |
| ![Login screen with the Login with SSO button](docs/screenshots/01-login.png) | ![Setup wizard to create the first admin user](docs/screenshots/02-setup-wizard.png) |

…one two-column table per row of screenshots…
```

`<!-- footer -->` is a hard boundary: `.github/workflows/update-readme.yml` replaces
everything from that marker onward with the shared template footer and rewrites the
buttons block, so any new README content must be inserted above it.

## How the captures were produced

1. Build the runtime image:

   ```bash
   docker build -t ddnsonroute53:php85 .
   ```

2. Start a throwaway database and application on an isolated Docker network:

   ```bash
   mkdir -p /tmp/ddns-shots
   docker network create ddns-docs-net
   docker run -d --name ddns-docs-db --network ddns-docs-net \
     -e MARIADB_ROOT_PASSWORD=rootpw -e MARIADB_DATABASE=dns \
     -e MARIADB_USER=dns -e MARIADB_PASSWORD=dnspw mariadb:11.4

   docker run -d --name ddns-docs-app --network ddns-docs-net -p 127.0.0.1:5667:80 \
     -e DB_SERVER=ddns-docs-db -e DB_NAME=dns -e DB_USERNAME=dns -e DB_PASSWORD=dnspw \
     -e KEYCLOAK_BASE_URL=https://keycloak.example.com/auth -e KEYCLOAK_REALM=ddns-demo \
     -e KEYCLOAK_CLIENT_ID=ddns-manager -e KEYCLOAK_CLIENT_SECRET=demo-secret \
     -e KEYCLOAK_REDIRECT_URI=http://localhost:5667/index.php ddnsonroute53:php85
   ```

3. Capture `02-setup-wizard.png` while the database is still empty, then create the
   schema:

   ```bash
   google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars \
     --disable-dev-shm-usage --force-device-scale-factor=1 --virtual-time-budget=5000 \
     --window-size=1440,900 --user-data-dir=/tmp/chrome-ddns-1 \
     --screenshot=/tmp/ddns-shots/02-setup-wizard.png http://127.0.0.1:5667/setup.php

   curl -s -X POST -d 'username=admin@example.com&password=Sup3rS3cret!&create_admin=1' \
     http://127.0.0.1:5667/setup.php
   ```

   `setup.php` refuses to run when tables already exist, which is why the wizard is
   captured first.

4. Seed illustrative data (documentation-only values):

   ```sql
   INSERT INTO users (id, username, password_hash) VALUES
     (2, 'jane.doe@example.com', '$2y$10$…dummy-bcrypt-hash…'),
     (3, 'ops@example.com', NULL);

   INSERT INTO aws_credentials (region, access_key_id, secret_access_key, hosted_zone_id, approved_fqdn)
   VALUES ('us-east-1', 'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
           'Z0123456789ABCDEFGHIJ', 'example.com');

   INSERT INTO ddns_entries (ddns_fqdn, ddns_password, last_ipv4, ttl, last_update) VALUES
     ('home.example.com', 'hom3-r0uter-p4ss', '203.0.113.24', 300, NOW() - INTERVAL 12 MINUTE),
     ('lab.example.com',  'l4b-r0uter-p4ss',  '198.51.100.7', 60,  NOW() - INTERVAL 3 HOUR),
     ('vpn.example.com',  'vpn-r0uter-p4ss',  '192.0.2.55',   300, NOW() - INTERVAL 2 DAY);

   INSERT INTO ddns_logs (ddns_entry_id, action, ip_address, details, timestamp) VALUES
     (1, 'update', '203.0.113.24', 'Updated IP: 203.0.113.24', NOW() - INTERVAL 12 MINUTE),
     (2, 'update', '198.51.100.7', 'Updated IP: 198.51.100.7', NOW() - INTERVAL 3 HOUR),
     (3, 'create', '192.0.2.55',   'Created entry vpn.example.com', NOW() - INTERVAL 4 DAY);
   ```

5. The authenticated pages need `$_SESSION['loggedin']`, so a temporary helper was
   copied into the throwaway container (never into the repository) and deleted with the
   container:

   ```php
   <?php
   error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
   ini_set('display_errors', '0');
   session_start();
   $_SESSION['loggedin'] = true;
   $_SESSION['id'] = 1;
   $_SESSION['username'] = 'admin@example.com';
   chdir(__DIR__);
   require __DIR__ . '/' . basename($_GET['page'] ?? 'dashboard.php');
   ```

   ```bash
   docker cp /tmp/ddns-shots/__shot.php ddns-docs-app:/var/www/html/public/__shot.php
   ```

6. Capture each page, then tune `--window-size` so the screenshot ends right below the
   content (Chrome's CLI screenshot is viewport-sized, not full-page). The dashboard
   also displays `HTTP_HOST` in its cURL example, so it is captured through a mapped
   hostname to avoid leaking `127.0.0.1:5667`:

   ```bash
   google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars \
     --disable-dev-shm-usage --force-device-scale-factor=1 --virtual-time-budget=5000 \
     --host-resolver-rules='MAP ddns.example.com 127.0.0.1' --window-size=1440,560 \
     --user-data-dir=/tmp/chrome-ddns-r3 --screenshot=/tmp/ddns-shots/03-dashboard.png \
     'http://ddns.example.com:5667/__shot.php?page=dashboard.php'

   google-chrome --headless=new --no-sandbox --disable-gpu --hide-scrollbars \
     --disable-dev-shm-usage --force-device-scale-factor=1 --virtual-time-budget=5000 \
     --window-size=1440,1400 --user-data-dir=/tmp/chrome-ddns-r4 \
     --screenshot=/tmp/ddns-shots/04-manage-ddns.png \
     'http://127.0.0.1:5667/__shot.php?page=manage_ddns.php'
   ```

   Repeat for `manage_aws.php`, `manage_users.php` and `view_logs.php`, copy the PNGs
   into `docs/screenshots/`, and verify the files with `md5sum` against `/tmp`.

7. Tear down:

   ```bash
   docker rm -f ddns-docs-app ddns-docs-db
   docker network rm ddns-docs-net
   ```

## Refreshing the screenshots later

Re-run steps 1–7 above. Keep the file names stable — the README links to them by name,
and the numbering defines the order in which they appear. If a page is added, append a
new `08-…png` and a matching table row in `README.md` before `<!-- footer -->`.

## Guardrails

- `docs/` and `plans/` are listed in `.dockerignore` so they never end up in the
  runtime image.
- `.github/workflows/build.yml` excludes `docs/*` and `plans/*` from the release ZIP,
  keeping the distributed package limited to the application.
- Only dummy data is used: RFC 5737 documentation IP ranges, `example.com` FQDNs and
  AWS' published example keys.
- The session helper is a capture-time artifact only; do not commit it to `public/`.
