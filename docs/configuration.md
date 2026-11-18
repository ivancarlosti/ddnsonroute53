# Configuration

DDNS on Route53 is configured through environment variables. The Docker
deployment uses `docker/.env.example` as the template:

```bash
curl -o .env.example https://raw.githubusercontent.com/ivancarlosti/ddnsonroute53/main/docker/.env.example
cp .env.example .env
# edit .env with your real values
```

`.env` is read by `docker-compose.yml` (`env_file`) and must never be committed:
it contains secrets and is listed in `.gitignore`.

## Environment variables

| Variable | Default (in code) | Required | Description |
| --- | --- | --- | --- |
| `DB_SERVER` | `localhost` | Yes | MySQL/MariaDB host. Use `host.docker.internal` when the database runs on the Docker host. |
| `DB_NAME` | `dns` | Yes | Database that holds the application tables. |
| `DB_USERNAME` | `dns` | Yes | Database user. Needs `CREATE`, `SELECT`, `INSERT`, `UPDATE`, `DELETE` and `ALTER` (the setup wizard creates the tables and a stored procedure). |
| `DB_PASSWORD` | `password` | Yes | Password of the database user. |
| `KEYCLOAK_BASE_URL` | `https://keycloak.example.com/auth` | Yes | Keycloak base URL without `/realms/...`. Legacy servers usually end with `/auth`. |
| `KEYCLOAK_REALM` | `myrealm` | Yes | Realm that contains the OIDC client. |
| `KEYCLOAK_CLIENT_ID` | `myclient` | Yes | Client ID of the confidential OIDC client. |
| `KEYCLOAK_CLIENT_SECRET` | `mysecret` | Yes | Client secret of that OIDC client. |
| `KEYCLOAK_REDIRECT_URI` | `http://localhost/index.php` | Yes | Public URL of `index.php`. Registered in Keycloak as *Valid redirect URI* and *Valid post logout redirect URI*. |
| `RESTRICT_USERS` | *(empty)* | No | Optional allow-list of e-mails/domains allowed to use SSO. Empty disables the restriction. |

The defaults are only fallbacks for development. Always set the database and
Keycloak values explicitly in production.

## Keycloak client configuration

In Keycloak, open the client configured in `KEYCLOAK_CLIENT_ID` and set:

* **Valid redirect URIs** — the exact value of `KEYCLOAK_REDIRECT_URI`
  (for example `https://ddnsonroute53.example.com/index.php`).
* **Valid post logout redirect URIs** — the same value. Without it, signing out
  of Keycloak shows an error page instead of returning to the login page.
* **Client authentication** — enabled (confidential client), so that
  `KEYCLOAK_CLIENT_SECRET` is required by the token endpoint.

The application requests the `openid email profile` scopes and matches the
Keycloak account against `users.username` by e-mail (falling back to
`preferred_username` when the `email` claim is absent).

## SSO access restriction (`RESTRICT_USERS`)

`RESTRICT_USERS` is optional. When it is empty (default), any account that
exists in the local `users` table can sign in with SSO.

When it has a value, **every** account must match the list, even if it is
present in the local `users` table. Accounts that do not match are denied with
`Access Denied: <email> is not allowed to use SSO.`, and the event is written
to the PHP error log.

### Syntax

Entries are separated by commas, semicolons or spaces, and matching is
case-insensitive.

| Entry | Matches |
| --- | --- |
| `admin@example.com` | That exact e-mail address only. |
| `example.com` | Any address on exactly `example.com`. |
| `@example.com` | Same as `example.com`. |
| `*@example.com` | Same as `example.com`. |
| `*` | Every account (same behaviour as leaving the variable empty). |

Domain matching is exact, not a suffix match:

* `example.com` matches `user@example.com`
* `example.com` does **not** match `user@mail.example.com`
* `example.com` does **not** match `user@evil-example.com`

### Examples

Allow two specific addresses plus every address of one domain:

```dotenv
RESTRICT_USERS=admin@example.com,ops@example.com,corp.example
```

Allow a single domain:

```dotenv
RESTRICT_USERS=@example.org
```

Mixed entries are also accepted:

```dotenv
RESTRICT_USERS=admin@example.com, example.org, *@contractors.example
```

### Behaviour summary

1. Keycloak authenticates the user and returns an e-mail address.
2. If `RESTRICT_USERS` is set and the e-mail does not match, access is denied
   (the local `users` table is not consulted in that case).
3. The e-mail must also exist in the local `users` table. Accounts that are
   allow-listed but missing locally are denied with
   `Access Denied: User not found in local database.` — add them under
   *Manage Users* first.
4. On success the local session is regenerated and the user is sent to the
   dashboard.

Because the check fails closed, a typo in `RESTRICT_USERS` denies every SSO
login. The login page shows the e-mail Keycloak returned, which usually makes
the mismatch obvious.

## Signing out of Keycloak

The denied login page shows **Sign out of SSO and try another account**. It
clears the local session and calls the Keycloak logout endpoint, so the next
login attempt asks for credentials instead of silently re-using the same
Keycloak session. The dashboard's **Logout** button uses the same endpoint.

The application sends the OIDC ID token (`id_token_hint`) collected during
login, which lets Keycloak sign the user out without an extra confirmation
screen. Signing out ends the Keycloak SSO session, so it also signs the user
out of other clients that share the same realm session.
