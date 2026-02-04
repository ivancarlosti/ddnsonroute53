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

// 1. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    $logoutUrl = KEYCLOAK_BASE_URL . "/realms/" . KEYCLOAK_REALM . "/protocol/openid-connect/logout?redirect_uri=" . urlencode(KEYCLOAK_REDIRECT_URI);
    header("Location: $logoutUrl");
    exit;
}

// 2. Handle OAuth Callback
if (isset($_GET['code'])) {
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
    curl_close($ch);

    $tokenData = json_decode($response, true);

    if (isset($tokenData['access_token'])) {
        // Get User Info
        $userInfoUrl = KEYCLOAK_BASE_URL . "/realms/" . KEYCLOAK_REALM . "/protocol/openid-connect/userinfo";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $tokenData['access_token']]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $userInfoResponse = curl_exec($ch);
        curl_close($ch);

        $userInfo = json_decode($userInfoResponse, true);
        $email = $userInfo['email'] ?? $userInfo['preferred_username'] ?? null;

        if ($email) {
            // Check if user exists in local DB
            $stmt = $link->prepare("SELECT id, username FROM users WHERE username = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Access Denied: User not found in local database.";
            }
            $stmt->close();
        } else {
            $error = "Could not retrieve email from identity provider.";
        }
    } else {
        $error = "Failed to authenticate with SSO.";
    }
}

// 3. Generate Login URL
$loginUrl = KEYCLOAK_BASE_URL . "/realms/" . KEYCLOAK_REALM . "/protocol/openid-connect/auth" .
    "?client_id=" . KEYCLOAK_CLIENT_ID .
    "&response_type=code" .
    "&redirect_uri=" . urlencode(KEYCLOAK_REDIRECT_URI) .
    "&scope=openid email profile";

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

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="btn"
                style="width: 100%; box-sizing: border-box;">
                Login with SSO
            </a>
        </div>
    </div>
</body>

</html>