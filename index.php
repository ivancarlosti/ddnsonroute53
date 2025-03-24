<?php
session_start();

error_reporting(0);
ini_set('display_errors', 0);

function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        die("A fatal error occurred. Please check your configuration and try again, maybe database connection is not properly configured. Please check the dbconfig.php file and ensure the database credentials are correct.");
    }
}

register_shutdown_function('handleFatalError');

if (!file_exists('dbconfig.php')) {
    die("The database configuration file (dbconfig.php) is missing. Please create it with the correct database credentials.");
}

include 'dbconfig.php';

if ($link === null || $link->connect_error) {
    die("Database connection failed. Please check the dbconfig.php file and ensure the database credentials are correct.");
}


// Function to check if a table exists in the database
function tableExists($link, $tableName) {
    $sql = "SHOW TABLES LIKE '$tableName'";
    $result = $link->query($sql);
    return $result->num_rows > 0;
}

// Check if the required tables exist
if (!tableExists($link, 'recaptcha_keys') || !tableExists($link, 'users')) {
    header('Location: setup.php'); // Redirect to setup.php
    exit;
}

// Fetch reCAPTCHA keys from the database
$recaptcha_keys = [];
$sql = "SELECT site_key, secret_key FROM recaptcha_keys LIMIT 1";
if ($result = $link->query($sql)) {
    $recaptcha_keys = $result->fetch_assoc();
    $result->free();
}

$recaptcha_enabled = !empty($recaptcha_keys['site_key']) && !empty($recaptcha_keys['secret_key']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($recaptcha_enabled) {
        // Verify reCAPTCHA
        $recaptchaResponse = $_POST['recaptcha_response'];
        $verifyUrl = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_keys['secret_key']}&response={$recaptchaResponse}";
        $response = file_get_contents($verifyUrl);
        $responseData = json_decode($response);

        if (!$responseData->success) {
            die("reCAPTCHA verification failed. Please try again.");
        }
    }

    // Proceed with login
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, username, password_hash FROM users WHERE username = ?";
    if ($stmt = $link->prepare($sql)) {
        $stmt->bind_param("s", $username);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id, $username, $hashed_password);
                if ($stmt->fetch()) {
                    if (password_verify($password, $hashed_password)) {
                        session_regenerate_id();
                        $_SESSION['loggedin'] = TRUE;
                        $_SESSION['id'] = $id;
                        $_SESSION['username'] = $username;
                        header('location: dashboard.php');
                        exit;
                    } else {
                        $error = "Invalid password.";
                    }
                }
            } else {
                $error = "Invalid username.";
            }
        }
        $stmt->close();
    }
    $link->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <?php if ($recaptcha_enabled): ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo $recaptcha_keys['site_key']; ?>"></script>
        <script>
            function onSubmit(token) {
                document.getElementById("loginForm").submit();
            }
        </script>
    <?php endif; ?>
</head>
<body>
    <h1>Login</h1>
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
    <form id="loginForm" action="index.php" method="post">
        <label>Email address:</label>
        <input type="text" name="username" required><br>
        <label>Password:</label>
        <input type="password" name="password" required><br>
        <?php if ($recaptcha_enabled): ?>
            <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
            <button class="g-recaptcha" data-sitekey="<?php echo $recaptcha_keys['site_key']; ?>" data-callback='onSubmit' data-action='submit'>Login</button>
        <?php else: ?>
            <input type="submit" value="Login">
        <?php endif; ?>
    </form>

    <?php if ($recaptcha_enabled): ?>
        <script>
            // Automatically execute reCAPTCHA when the form is loaded
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo $recaptcha_keys['site_key']; ?>', { action: 'submit' }).then(function(token) {
                    document.getElementById('recaptchaResponse').value = token;
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>