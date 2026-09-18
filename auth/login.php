<?php
require_once __DIR__ . '/../config/db.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['role']    = $user['role'];

        if ($user['role'] === 'team_manager') {
            $t = $pdo->prepare("SELECT team_id FROM teams WHERE manager_user_id = ?");
            $t->execute([$user['user_id']]);
            $team = $t->fetch();
            $_SESSION['team_id'] = $team['team_id'] ?? null;
        }

        $redirects = [
            'admin'        => BASE_URL . '/admin/dashboard.php',
            'coordinator'  => BASE_URL . '/coordinator/dashboard.php',
            'team_manager' => BASE_URL . '/manager/dashboard.php',
        ];
        header("Location: " . $redirects[$user['role']]);
        exit;
    } else {
        $error = "Invalid email or password.";
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
    <title>Login &mdash; <?= SITE_NAME ?></title>
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
            <h2 class="auth-title">Welcome back</h2>
            <p class="auth-subtitle">Log in to manage your team, matches, or the tournament.</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="success-msg">Account created. You can log in now.</div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn" style="width:100%;">Login</button>
            </form>

            <div class="auth-divider"><span>or</span></div>

            <p class="auth-footer-link">
                Team Manager? <a href="signup.php">Register here</a>
            </p>
        </div>

        <a href="<?= BASE_URL ?>/public/index.php" class="auth-back-link">&larr; Back to homepage</a>
    </div>
</body>
</html>