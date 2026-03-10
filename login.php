<?php  
session_start();

// If already logged in, redirect to index.php
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit;
}

$valid_username = "storepjvk";
$valid_password = "KELISAria2025$$"; 
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Check credentials
    if ($username === $valid_username && $password === $valid_password) {
        // ✅ Set session variables
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;

        // Redirect to main page
        header("Location: index.php"); 
        exit;
    } else {
        $error = "Incorrect username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PJVK WMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="login-page"> 

    <div class="login-card">
        
        <?php if ($error): ?>
            <div class="error-message"> 
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= htmlspecialchars($error) ?></span> 
            </div>
        <?php endif; ?>

        <div class="logo-circle">
            <img src="logo.png" alt="EPMB PJVK Logo" class="login-logo">
        </div>

        <h2>Welcome Back</h2>
        <p>Login to continue to PJVK FG & Warehouse System</p> 

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="storepjvk" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" value="KELISAria2025$$" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">Login</button>
            
            <p class="form-hint">
                Username: storepjvk / Password: Please contact admin
            </p>

            <div class="footer-text">
                <p>© 2025 P.JVK. All rights reserved.</p>
            </div>
        </form>
    </div>
</body>
</html>