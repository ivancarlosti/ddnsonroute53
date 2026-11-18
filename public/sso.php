<?php
/**
 * Helpers for the Keycloak (OpenID Connect) SSO flow.
 *
 * Kept free of database access so the login page, the "sign out" links and the
 * optional SSO allow-list share a single implementation.
 */

/**
 * Build the Keycloak authorization-code login URL.
 */
function keycloak_login_url(): string
{
    return KEYCLOAK_BASE_URL . '/realms/' . rawurlencode(KEYCLOAK_REALM) . '/protocol/openid-connect/auth?' . http_build_query([
        'client_id' => KEYCLOAK_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri' => KEYCLOAK_REDIRECT_URI,
        'scope' => 'openid email profile',
    ]);
}

/**
 * Build the RP-initiated logout URL.
 *
 * `post_logout_redirect_uri` is the parameter used by current Keycloak
 * releases while `redirect_uri` keeps older (legacy /auth) servers working;
 * both point at the same registered URI. Passing the ID token lets Keycloak
 * end the session without asking for confirmation again.
 */
function keycloak_logout_url(?string $idToken = null): string
{
    $params = [
        'client_id' => KEYCLOAK_CLIENT_ID,
        'post_logout_redirect_uri' => KEYCLOAK_REDIRECT_URI,
        'redirect_uri' => KEYCLOAK_REDIRECT_URI,
    ];

    if ($idToken !== null && $idToken !== '') {
        $params['id_token_hint'] = $idToken;
    }

    return KEYCLOAK_BASE_URL . '/realms/' . rawurlencode(KEYCLOAK_REALM) . '/protocol/openid-connect/logout?' . http_build_query($params);
}

/**
 * Parse a RESTRICT_USERS value into e-mail addresses and domain names.
 *
 * Entries are separated by commas, semicolons or whitespace and are matched
 * case-insensitively. Accepted forms:
 *   user@example.com   full e-mail address
 *   example.com        domain (also @example.com or *@example.com)
 *   *                  allow every account, same as leaving the value empty
 */
function sso_allowed_entries(?string $raw = null): array
{
    $raw = $raw ?? RESTRICT_USERS;
    $entries = ['all' => false, 'emails' => [], 'domains' => []];

    foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $entry) {
        $entry = strtolower(trim($entry));

        if ($entry === '*') {
            $entries['all'] = true;
            continue;
        }

        if (str_starts_with($entry, '*@')) {
            $entries['domains'][] = substr($entry, 2);
        } elseif (str_starts_with($entry, '@')) {
            $entries['domains'][] = substr($entry, 1);
        } elseif (str_contains($entry, '@')) {
            $entries['emails'][] = $entry;
        } else {
            $entries['domains'][] = $entry;
        }
    }

    $entries['emails'] = array_values(array_unique($entries['emails']));
    $entries['domains'] = array_values(array_unique(array_filter(
        $entries['domains'],
        static fn (string $domain): bool => $domain !== ''
    )));

    return $entries;
}

/**
 * Whether an SSO e-mail address is allowed by RESTRICT_USERS.
 *
 * An empty value means "every account that exists in the local users table may
 * sign in". When the value is set, any account that does not match the list is
 * denied, even if it exists in the local database.
 */
function sso_email_allowed(string $email, ?string $raw = null): bool
{
    $raw = $raw ?? RESTRICT_USERS;

    if (trim($raw) === '') {
        return true;
    }

    $email = strtolower(trim($email));
    $atPosition = strrpos($email, '@');

    if ($atPosition === false) {
        return false;
    }

    $domain = substr($email, $atPosition + 1);
    if ($domain === '') {
        return false;
    }

    $allowed = sso_allowed_entries($raw);

    if ($allowed['all'] || in_array($email, $allowed['emails'], true)) {
        return true;
    }

    // Exact domain match only: "example.com" does not match "mail.example.com"
    // and "evil-example.com" is not treated as "example.com".
    return in_array($domain, $allowed['domains'], true);
}
