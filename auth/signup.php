<?php
require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName  = trim($_POST['last_name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];

    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        $error = "An account with this email already exists.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role)
            VALUES (?, ?, ?, ?, 'team_manager')
        ");
        $stmt->execute([$firstName, $lastName, $email, $hash]);

        header("Location: login.php?registered=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            var saved = localStorage.getItem('iuct-theme');
            document.documentElement.setAttribute('data-theme', saved || 'dark');
        })();
    </script>
    <title>Team Manager Signup &mdash; <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-brand">
                <img src="<?= BASE_URL ?>/public/assets/logos/site-logo.png" alt="IUCT" class="brand-logo">
            </div>
            <h2 class="auth-title">Team Manager Registration</h2>
            <p class="auth-subtitle">Create an account to register and manage your university's team.</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required autofocus>
                </div>
                <div class="field">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <button type="submit" class="btn" style="width:100%;">Register</button>
            </form>

            <p class="text-muted" style="font-size:0.82rem; margin-top:1.2rem; text-align:left;">
                Note: after signing up, you'll still need to
                <strong>register your university's team</strong>, which requires
                Admin approval before it becomes official.
            </p>

            <div class="auth-divider"><span>or</span></div>

            <p class="auth-footer-link">
                Already have an account? <a href="login.php">Login</a>
            </p>
        </div>

        <a href="<?= BASE_URL ?>/public/index.php" class="auth-back-link">&larr; Back to homepage</a>
    </div>
</body>
</html>