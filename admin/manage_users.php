<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$error = '';
$success = '';

// Create a new Coordinator account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_coordinator') {
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
            VALUES (?, ?, ?, ?, 'coordinator')
        ");
        $stmt->execute([$firstName, $lastName, $email, $hash]);
        $success = "Coordinator account created.";
    }
}

// Deactivate / reactivate a user
if (isset($_GET['toggle'])) {
    $userId = (int) $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ? AND role != 'admin'");
    $stmt->execute([$userId]);
    header("Location: manage_users.php");
    exit;
}

$users = $pdo->query("
    SELECT user_id, first_name, last_name, email, role, is_active, created_at
    FROM users
    ORDER BY FIELD(role, 'admin', 'coordinator', 'team_manager'), created_at ASC
")->fetchAll();

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section center-page">
    <h1>Manage Users</h1>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="form-card">
        <h3 class="card-title">Create Coordinator Account</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_coordinator">
            <div class="field"><label>First Name</label><input type="text" name="first_name" required></div>
            <div class="field"><label>Last Name</label><input type="text" name="last_name" required></div>
            <div class="field"><label>Email</label><input type="email" name="email" required></div>
            <div class="field"><label>Password</label><input type="password" name="password" required minlength="8"></div>
            <button type="submit" class="btn">Create Account</button>
        </form>
    </div>

    <div class="table-wrap" style="margin-top:2rem;">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></td>
                        <td>
                            <span class="status-tag status-tag--<?= $u['is_active'] ? 'approved' : 'rejected' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['role'] !== 'admin'): ?>
                                <a href="?toggle=<?= $u['user_id'] ?>" class="text-muted"
                                   onclick="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?> this account?');">
                                    <?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>