<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim($_POST['name']);
    $city = trim($_POST['city']);
    $stmt = $pdo->prepare("INSERT INTO venues (name, city) VALUES (?, ?)");
    $stmt->execute([$name, $city]);
    $success = "Venue added.";
}

if (isset($_GET['deactivate'])) {
    $stmt = $pdo->prepare("UPDATE venues SET is_active = 0 WHERE venue_id = ?");
    $stmt->execute([(int) $_GET['deactivate']]);
    header("Location: manage_venues.php");
    exit;
}

$venues = $pdo->query("SELECT * FROM venues WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

$pageTitle = 'Manage Venues';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section center-page">
    <h1>Manage Venues</h1>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="form-card">
        <h3 class="card-title">Add Venue</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="field">
                <label>Venue Name</label>
                <input type="text" name="name" required>
            </div>
            <div class="field">
                <label>City</label>
                <input type="text" name="city" required>
            </div>
            <button type="submit" class="btn">Add Venue</button>
        </form>
    </div>

    <div class="table-wrap" style="margin-top:2rem;">
        <table class="data-table">
            <thead><tr><th>Name</th><th>City</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($venues as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['name']) ?></td>
                        <td><?= htmlspecialchars($v['city']) ?></td>
                        <td>
                            <a href="?deactivate=<?= $v['venue_id'] ?>"
                               onclick="return confirm('Deactivate this venue?');"
                               class="text-muted">Deactivate</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>