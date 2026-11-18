<?php
/**
 * Standalone checks for the SSO helpers (no framework, no database).
 * Run with: php tests/sso_helpers_test.php
 */

define('KEYCLOAK_BASE_URL', 'https://sso.example.com');
define('KEYCLOAK_REALM', 'example realm');
define('KEYCLOAK_CLIENT_ID', 'ddnsonroute53');
define('KEYCLOAK_CLIENT_SECRET', 'secret');
define('KEYCLOAK_REDIRECT_URI', 'https://ddns.example.com/index.php');
define('RESTRICT_USERS', '');

require __DIR__ . '/../public/sso.php';

$failures = 0;

function check(string $label, $actual, $expected): void
{
    global $failures;

    if ($actual === $expected) {
        echo "PASS  {$label}\n";
        return;
    }

    $failures++;
    echo "FAIL  {$label}\n";
    echo "      expected: " . var_export($expected, true) . "\n";
    echo "      actual:   " . var_export($actual, true) . "\n";
}

// Empty configuration: no restriction.
check('empty RESTRICT_USERS allows any address', sso_email_allowed('anyone@example.com'), true);

// Allow-list matching.
check('exact e-mail matches', sso_email_allowed('admin@example.com', 'admin@example.com,ops@corp.example'), true);
check('matching is case-insensitive', sso_email_allowed('ADMIN@Example.COM', 'admin@example.com'), true);
check('domain entry matches its domain', sso_email_allowed('user@corp.example', 'corp.example'), true);
check('@-prefixed domain entry matches', sso_email_allowed('user@corp.example', '@corp.example'), true);
check('*@-prefixed domain entry matches', sso_email_allowed('user@corp.example', '*@corp.example'), true);
check('subdomain is not matched by parent domain', sso_email_allowed('user@mail.corp.example', 'corp.example'), false);
check('look-alike domain is not matched', sso_email_allowed('user@evil-corp.example', 'corp.example'), false);
check('non-listed address is denied', sso_email_allowed('other@example.com', 'admin@example.com,corp.example'), false);
check('wildcard * allows every address', sso_email_allowed('anything@anywhere.test', '*'), true);
check('value without @ cannot match', sso_email_allowed('not-an-email', 'example.com'), false);
check('empty domain after @ is denied', sso_email_allowed('user@', 'example.com'), false);
check('semicolons and spaces separate entries', sso_email_allowed('ops@corp.example', 'admin@example.com; ops@corp.example'), true);

// Parsing.
check('parser normalises entries', sso_allowed_entries(' A@B.com ; corp.example , @other.test  *@third.test '), [
    'all' => false,
    'emails' => ['a@b.com'],
    'domains' => ['corp.example', 'other.test', 'third.test'],
]);
check('parser detects wildcard', sso_allowed_entries('*'), [
    'all' => true,
    'emails' => [],
    'domains' => [],
]);

// URLs.
$loginUrl = keycloak_login_url();
check(
    'login URL targets the auth endpoint',
    str_starts_with($loginUrl, 'https://sso.example.com/realms/example%20realm/protocol/openid-connect/auth?'),
    true
);
check('login URL encodes the redirect URI', str_contains($loginUrl, 'redirect_uri=' . urlencode(KEYCLOAK_REDIRECT_URI)), true);
check('login URL requests the expected scopes', str_contains($loginUrl, 'scope=openid+email+profile'), true);

$logoutUrl = keycloak_logout_url('id.token.value');
check(
    'logout URL targets the logout endpoint',
    str_starts_with($logoutUrl, 'https://sso.example.com/realms/example%20realm/protocol/openid-connect/logout?'),
    true
);
check('logout URL passes the ID token', str_contains($logoutUrl, 'id_token_hint=id.token.value'), true);
check('logout URL passes the client id', str_contains($logoutUrl, 'client_id=ddnsonroute53'), true);
check(
    'logout URL passes the modern redirect parameter',
    str_contains($logoutUrl, 'post_logout_redirect_uri=' . urlencode(KEYCLOAK_REDIRECT_URI)),
    true
);
check(
    'logout URL passes the legacy redirect parameter',
    str_contains($logoutUrl, '&redirect_uri=' . urlencode(KEYCLOAK_REDIRECT_URI)),
    true
);

$logoutUrlWithoutToken = keycloak_logout_url();
check('logout URL without ID token omits the hint', str_contains($logoutUrlWithoutToken, 'id_token_hint'), false);

echo "\n";
if ($failures > 0) {
    echo "{$failures} check(s) failed.\n";
    exit(1);
}

echo "All checks passed.\n";
