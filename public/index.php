<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);

function handleFatalError()
{
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        die("A fatal error occurred. Please check your configuration.");
    }
}
register_shutdown_function('handleFatalError');

include '../dbconfig.php';
require_once __DIR__ . '/sso.php';

if ($link === null || $link->connect_error) {
    die("Database connection failed.");
}

// Helper to check table existence
function tableExists($link, $tableName)
{
    $sql = "SHOW TABLES LIKE '$tableName'";
    $result = $link->query($sql);
    return $result->num_rows > 0;
}

if (!tableExists($link, 'users')) {
    header('Location: setup.php');
    exit;
}

// --- Keycloak SSO Logic ---

// 1. Handle Logout (dashboard button and "try another account" on this page)
if (isset($_GET['logout'])) {
    // Keep the ID token so Keycloak can end the SSO session without showing a
    // confirmation screen again.
    $idToken = isset($_SESSION['id_token']) && is_string($_SESSION['id_token'])
        ? $_SESSION['id_token']
        : null;

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookieParams = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();

    header('Location: ' . keycloak_logout_url($idToken));
    exit;
}

// 2. Keycloak redirected back without a code (user cancelled or IdP error)
if (isset($_GET['error'])) {
    $_SESSION['sso_error'] = "SSO login was cancelled or failed. Please try again.";
    header('Location: index.php');
    exit;
}

// 3. Handle OAuth Callback
if (isset($_GET['code']) && is_string($_GET['code'])) {
    $code = $_GET['code'];
    $tokenUrl = KEYCLOAK_BASE_URL . "/realms/" . KEYCLOAK_REALM . "/protocol/openid-connect/token";

    $data = [
        'grant_type' => 'authorization_code',
        'client_id' => KEYCLOAK_CLIENT_ID,
        'client_secret' => KEYCLOAK_CLIENT_SECRET,
        'code' => $code,
        'redirect_uri' => KEYCLOAK_REDIRECT_URI
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    $tokenData = json_decode($response, true);

    if (isset($tokenData['access_token'])) {
        // Keep the ID token so "sign out" can end the Keycloak session.
        $_SESSION['id_token'] = isset($tokenData['id_token']) && is_string($tokenData['id_token'])
            ? $tokenData['id_token']
            : null;

        // Get User Info
        $userInfoUrl = KEYCLOAK_BASE_URL . "/realms/" . KEYCLOAK_REALM . "/protocol/openid-connect/userinfo";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $tokenData['access_token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $userInfoResponse = curl_exec($ch);

        $userInfo = json_decode($userInfoResponse, true);
        $email = $userInfo['email'] ?? $userInfo['preferred_username'] ?? null;
        $email = is_string($email) ? trim($email) : null;

        if (!$email) {
            $_SESSION['sso_error'] = "Could not retrieve email from identity provider.";
        } elseif (!sso_email_allowed($email)) {
            // Fail closed: when RESTRICT_USERS is set, accounts outside the
            // list are denied even if they exist in the local database.
            error_log("SSO denied by RESTRICT_USERS: {$email}");
            $_SESSION['sso_error'] = "Access Denied: {$email} is not allowed to use SSO.";
        } else {
            // Check if user exists in local DB
            $stmt = $link->prepare("SELECT id, username FROM users WHERE username = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                unset($_SESSION['sso_error']);
                session_regenerate_id(true);
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: dashboard.php");
                exit;
            }

            $_SESSION['sso_error'] = "Access Denied: User not found in local database.";
            $stmt->close();
        }
    } else {
        $_SESSION['sso_error'] = "Failed to authenticate with SSO.";
    }

    // Back to a clean login URL: refreshing must not replay the used code.
    header('Location: index.php');
    exit;
}

// 4. Render the login page (last SSO error is shown once, then cleared)
$error = $_SESSION['sso_error'] ?? null;
unset($_SESSION['sso_error']);

$loginUrl = keycloak_login_url();
$logoutUrl = keycloak_logout_url(
    isset($_SESSION['id_token']) && is_string($_SESSION['id_token']) ? $_SESSION['id_token'] : null
);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DDNS Manager</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="login-container">
        <div class="card login-card text-center">
            <h1>DDNS Manager</h1>
            <p class="mb-4" style="color: #94a3b8;">Secure Access Control</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <p class="mb-4" style="color: #94a3b8;">
                    You are still signed in to the identity provider. Sign out first to use a different account.
                </p>
                <a href="<?php echo htmlspecialchars($logoutUrl); ?>" class="btn btn-danger"
                    style="width: 100%; box-sizing: border-box; margin-bottom: 0.75rem;">
                    Sign out of SSO and try another account
                </a>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="btn"
                style="width: 100%; box-sizing: border-box;">
                Login with SSO
            </a>
        </div>
    </div>
</body>

</html>