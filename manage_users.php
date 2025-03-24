<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

include 'dbconfig.php';

// Fetch the logged-in user's ID and username
$logged_in_user_id = $_SESSION['id'];
$logged_in_username = $_SESSION['username'];

// Handle form submission to change the logged-in user's password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        echo "New password and confirmation do not match.";
    } else {
        // Fetch the current password hash from the database
        $sql = "SELECT password_hash FROM users WHERE id = ?";
        if ($stmt = $link->prepare($sql)) {
            $stmt->bind_param("i", $logged_in_user_id);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($password_hash);
            $stmt->fetch();

            // Verify the current password
            if (password_verify($current_password, $password_hash)) {
                // Hash the new password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // Update the password in the database
                $update_sql = "UPDATE users SET password_hash = ? WHERE id = ?";
                if ($update_stmt = $link->prepare($update_sql)) {
                    $update_stmt->bind_param("si", $new_password_hash, $logged_in_user_id);
                    if ($update_stmt->execute()) {
                        echo "Password updated successfully!";
                    } else {
                        echo "Error updating password: " . $update_stmt->error;
                    }
                    $update_stmt->close();
                }
            } else {
                echo "Current password is incorrect.";
            }
            $stmt->close();
        }
    }
}

// Handle form submission to add a new user (admin-only feature)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate input
    if (empty($username) || empty($password)) {
        echo "Username and password are required.";
    } else {
        // Check if the username is a valid email address
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            echo "Invalid email address.";
        } else {
            // Check if the user already exists
            $check_sql = "SELECT id FROM users WHERE username = ?";
            if ($check_stmt = $link->prepare($check_sql)) {
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {
                    echo "User with this email already exists.";
                } else {
                    // Insert the new user
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $insert_sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
                    if ($insert_stmt = $link->prepare($insert_sql)) {
                        $insert_stmt->bind_param("ss", $username, $password_hash);
                        if ($insert_stmt->execute()) {
                            echo "User '$username' added successfully!";
                        } else {
                            echo "Error adding user: " . $insert_stmt->error;
                        }
                        $insert_stmt->close();
                    }
                }
                $check_stmt->close();
            }
        }
    }
}

// Handle user deletion (admin-only feature)
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];

    // Prevent the logged-in user from deleting themselves
    if ($user_id == $logged_in_user_id) {
        echo "You cannot delete your own account.";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        if ($stmt = $link->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                echo "User deleted successfully!";
            } else {
                echo "Error deleting user: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Handle form submission to add/edit reCAPTCHA keys
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_recaptcha'])) {
    $site_key = $_POST['site_key'];
    $secret_key = $_POST['secret_key'];

    // Check if keys already exist
    $check_sql = "SELECT id FROM recaptcha_keys LIMIT 1";
    $result = $link->query($check_sql);

    if ($result->num_rows > 0) {
        // Update existing keys
        $update_sql = "UPDATE recaptcha_keys SET site_key = ?, secret_key = ?";
        if ($stmt = $link->prepare($update_sql)) {
            $stmt->bind_param("ss", $site_key, $secret_key);
            if ($stmt->execute()) {
                echo "reCAPTCHA keys updated successfully!";
            } else {
                echo "Error updating reCAPTCHA keys: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        // Insert new keys
        $insert_sql = "INSERT INTO recaptcha_keys (site_key, secret_key) VALUES (?, ?)";
        if ($stmt = $link->prepare($insert_sql)) {
            $stmt->bind_param("ss", $site_key, $secret_key);
            if ($stmt->execute()) {
                echo "reCAPTCHA keys added successfully!";
            } else {
                echo "Error adding reCAPTCHA keys: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all users from the database
$sql = "SELECT id, username FROM users";
$users = [];
if ($result = $link->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

// Fetch existing reCAPTCHA keys
$recaptcha_keys = [];
$sql = "SELECT site_key, secret_key FROM recaptcha_keys LIMIT 1";
if ($result = $link->query($sql)) {
    $recaptcha_keys = $result->fetch_assoc();
    $result->free();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
</head>
<body>
    <h1>Manage Users</h1>
    <h2>Change Your Password</h2>
    <form method="post">
        <label>Current Password:</label>
        <input type="password" name="current_password" required><br>
        <label>New Password:</label>
        <input type="password" name="new_password" required><br>
        <label>Confirm New Password:</label>
        <input type="password" name="confirm_password" required><br>
        <input type="submit" name="change_password" value="Change Password">
    </form>

    <h2>Add New User</h2>
    <form method="post">
        <label>Email (Username):</label>
        <input type="email" name="username" required><br>
        <label>Password:</label>
        <input type="password" name="password" required><br>
        <input type="submit" name="add_user" value="Add User">
    </form>

    <h2>Manage reCAPTCHA Keys</h2>
    <form method="post">
        <label>Site Key:</label>
        <input type="text" name="site_key" value="<?php echo $recaptcha_keys['site_key'] ?? ''; ?>" required><br>
        <label>Secret Key:</label>
        <input type="text" name="secret_key" value="<?php echo $recaptcha_keys['secret_key'] ?? ''; ?>" required><br>
        <input type="submit" name="update_recaptcha" value="Update reCAPTCHA Keys">
    </form>

    <h2>User List</h2>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Username (Email)</th>
            <th>Action</th>
        </tr>
        <?php foreach ($users as $user): ?>
        <tr>
            <td><?php echo $user['id']; ?></td>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td>
                <?php if ($user['id'] != $logged_in_user_id): ?>
                    <a href="manage_users.php?delete=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                <?php else: ?>
                    <em>Current User</em>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>