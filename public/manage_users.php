<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: index.php');
    exit;
}

include '../dbconfig.php';

// Fetch the logged-in user's ID and username
$logged_in_user_id = $_SESSION['id'];
$logged_in_username = $_SESSION['username'];

// Handle form submission to add a new user (admin-only feature)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    // Password is now optional for SSO users
    $password = !empty($_POST['password']) ? $_POST['password'] : null;

    // Validate input
    if (empty($username)) {
        $error = "Username (Email) is required.";
    } else {
        // Check if the username is a valid email address
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } else {
            // Check if the user already exists
            $check_sql = "SELECT id FROM users WHERE username = ?";
            if ($check_stmt = $link->prepare($check_sql)) {
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {
                    $error = "User with this email already exists.";
                } else {
                    // Insert the new user
                    $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
                    $insert_sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
                    if ($insert_stmt = $link->prepare($insert_sql)) {
                        $insert_stmt->bind_param("ss", $username, $password_hash);
                        if ($insert_stmt->execute()) {
                            $success = "User '$username' added successfully!";
                        } else {
                            $error = "Error adding user: " . $insert_stmt->error;
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
        $error = "You cannot delete your own account.";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        if ($stmt = $link->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success = "User deleted successfully!";
            } else {
                $error = "Error deleting user: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all users from the database
$sql = "SELECT id, username, password_hash FROM users";
$users = [];
if ($result = $link->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Manage Users</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Add New User</h2>
            <p>Add a user to allow them to login via Keycloak SSO. Password is optional and only needed if you plan to support local login (legacy).</p>
            <form method="post">
                <label>Email address:</label>
                <input type="email" name="username" required placeholder="user@example.com">
                
                <label>Password (Optional):</label>
                <input type="password" name="password" placeholder="Leave empty for SSO-only users">
                
                <input type="submit" name="add_user" value="Add User">
            </form>
        </div>

        <div class="card">
            <h2>User List</h2>
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email address</th>
                        <th>Auth Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td>
                            <?php echo $user['password_hash'] ? 'Password + SSO' : 'SSO Only'; ?>
                        </td>
                        <td>
                            <?php if ($user['id'] != $logged_in_user_id): ?>
                                <a href="manage_users.php?delete=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Delete</a>
                            <?php else: ?>
                                <em>Current User</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>