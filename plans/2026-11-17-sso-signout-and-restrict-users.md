# SSO sign-out on denial + `RESTRICT_USERS` allow-list

- **Date:** 2026-11-17
- **Status:** Implemented
- **Scope:** `public/index.php`, `public/sso.php`, `public/manage_users.php`,
  `dbconfig.php`, `docker/.env.example`, `docker/docker-compose.yml`,
  `.gitignore`, `README.md`, `docs/configuration.md`, `tests/sso_helpers_test.php`

## Context

When Keycloak authenticated an account that was not present in the local
`users` table, `index.php` rendered
`Access Denied: User not found in local database.` with only the
"Login with SSO" button. The Keycloak SSO session stayed alive, so the button
instantly re-authenticated the same denied account: the user was locked out of
the login page with no way to switch accounts. The `?logout` handler existed but
was only linked from the dashboard.

There was also no way to limit which Keycloak accounts may use the application
beyond the local `users` table, and `docker/.env` was a tracked template named
like a real secrets file (forbidden by the repository standards, which only
allow `.env.example` and `docker-compose*.yml` inside `/docker`).

## Goal

1. Offer a "Sign out of SSO and try another account" action whenever an SSO
   login is denied, and make the Keycloak logout call reliable (ID token hint,
   modern and legacy redirect parameters).
2. Add an optional `RESTRICT_USERS` environment variable accepting e-mail
   addresses and/or domains that may use SSO. When set, accounts outside the
   list are denied even if they exist in the local database (fail closed).
3. Rename `docker/.env` to `docker/.env.example`, comment every variable, and
   keep a real `.env` out of git.

## Steps

1. **`dbconfig.php`** — new `RESTRICT_USERS` constant read from the
   environment, empty by default.
2. **`public/sso.php` (new)** — DB-free helpers:
   - `keycloak_login_url()`
   - `keycloak_logout_url(?string $idToken)` — sends `client_id`,
     `post_logout_redirect_uri`, legacy `redirect_uri` and `id_token_hint`
   - `sso_allowed_entries()` / `sso_email_allowed()` — case-insensitive exact
     e-mail or exact domain matching (`example.com` does not match
     `mail.example.com` or `evil-example.com`); `*` allows everything.
3. **`public/index.php`**
   - Logout reads `id_token` before clearing/destroying the session and the
     session cookie, then redirects to the Keycloak logout endpoint.
   - The callback stores the ID token, checks `RESTRICT_USERS` **before** the
     database lookup, logs denials with `error_log()`, regenerates the session
     id on success, and stores failures in `$_SESSION['sso_error']`.
   - Failures redirect back to `index.php` (POST/Redirect/GET) so a refresh
     cannot replay the consumed authorization code.
   - The error state renders the alert, an explanation and the red
     "Sign out of SSO and try another account" button.
   - A callback carrying `?error=` (cancelled / IdP error) produces a generic
     message.
4. **`public/manage_users.php`** — shows the active `RESTRICT_USERS` value and
   rejects new users whose e-mail would never be allowed to sign in.
5. **Environment** — `git mv docker/.env docker/.env.example`; the template is
   grouped and commented (database, Keycloak, restriction). Compose passes
   `RESTRICT_USERS` through. `.gitignore` ignores `docker/.env` only.
6. **Docs** — `docs/configuration.md` documents every variable, the Keycloak
   client redirect settings, the allow-list syntax/matching rules and the
   sign-out flow; `README.md` hosting/setup instructions updated.
7. **Tests** — `tests/sso_helpers_test.php`, a dependency-free script covering
   allow-list matching and URL generation.

## Risks

| Risk | Mitigation |
| --- | --- |
| Keycloak client lacks a *Valid post logout redirect URI*: signing out can end on a Keycloak error page. | Documented in `docs/configuration.md` and `.env.example`; both modern and legacy redirect parameters are sent. |
| `env_file: .env` is not optional in Compose, so a missing `.env` breaks startup after the rename. | README and template header instruct `cp .env.example .env`; `docker/.env` stays ignored. |
| A typo in `RESTRICT_USERS` denies every SSO login (fail closed by design). | The login page displays the e-mail Keycloak returned, the denial is written to the PHP error log, and an empty value disables the check. |
| Keycloak logout ends the realm SSO session and signs the user out of other clients. | The sign-out button is only rendered after a denial, never on a fresh login page. |
| No live Keycloak instance in the development environment. | URL builders and the allow-list are unit tested; the end-to-end callback still requires manual verification against a real realm. |

## Verification

```
php -l dbconfig.php public/*.php tests/sso_helpers_test.php
php tests/sso_helpers_test.php
cd docker && docker compose --env-file .env.example config
```

`docker compose config` validates the compose file and the new variable without
starting containers. The end-to-end Keycloak callback and logout redirect were
not exercised against a live Keycloak server (same limitation as the PHP 8.5
upgrade plan); the parameters were verified through the unit-tested URL
builders.

Additionally, `index.php` was smoke tested with PHP's built-in server and a
stub `dbconfig.php` (fake `mysqli` link, no database). 13/13 checks passed:

* the fresh login page offers only "Login with SSO";
* a denied SSO session renders the error, the sign-out button and an
  `id_token_hint` that matches the session token;
* `?logout=1` returns a 302 to the Keycloak logout endpoint with
  `client_id`, `post_logout_redirect_uri`, `redirect_uri`, the ID token hint,
  and clears the session cookie;
* `?error=access_denied` redirects back to `index.php`, shows a generic
  message plus the sign-out button, and omits `id_token_hint` when no token
  exists.
