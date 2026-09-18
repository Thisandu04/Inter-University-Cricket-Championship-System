<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        UPDATE system_settings SET
            registration_deadline = ?,
            max_teams = ?,
            max_players_per_team = ?,
            registration_fee = ?,
            points_win = ?,
            points_tie = ?,
            points_no_result = ?,
            points_loss = ?
        WHERE setting_id = 1
    ");
    $stmt->execute([
        $_POST['registration_deadline'] ?: null,
        (int) $_POST['max_teams'],
        (int) $_POST['max_players_per_team'],
        (float) $_POST['registration_fee'],
        (int) $_POST['points_win'],
        (int) $_POST['points_tie'],
        (int) $_POST['points_no_result'],
        (int) $_POST['points_loss'],
    ]);
    $success = "Settings updated.";
}

$settings = $pdo->query("SELECT * FROM system_settings WHERE setting_id = 1")->fetch();

$pageTitle = 'System Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section center-page">
    <h1>System Settings</h1>

    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="form-card" style="max-width:520px;">
        <form method="POST">
            <div class="field">
                <label>Registration Deadline</label>
                <input type="datetime-local" name="registration_deadline"
                       value="<?= $settings['registration_deadline'] ? date('Y-m-d\TH:i', strtotime($settings['registration_deadline'])) : '' ?>">
            </div>
            <div class="field">
                <label>Max Teams</label>
                <input type="number" name="max_teams" value="<?= $settings['max_teams'] ?>" min="2" required>
            </div>
            <div class="field">
                <label>Max Players per Team</label>
                <input type="number" name="max_players_per_team" value="<?= $settings['max_players_per_team'] ?>" min="1" required>
            </div>
            <div class="field">
                <label>Registration Fee (LKR)</label>
                <input type="number" step="0.01" name="registration_fee" value="<?= $settings['registration_fee'] ?>" min="0" required>
            </div>

            <h3 class="card-title" style="margin-top:1.5rem;">Points System</h3>
            <div class="field"><label>Points for Win</label><input type="number" name="points_win" value="<?= $settings['points_win'] ?>" required></div>
            <div class="field"><label>Points for Tie</label><input type="number" name="points_tie" value="<?= $settings['points_tie'] ?>" required></div>
            <div class="field"><label>Points for No Result</label><input type="number" name="points_no_result" value="<?= $settings['points_no_result'] ?>" required></div>
            <div class="field"><label>Points for Loss</label><input type="number" name="points_loss" value="<?= $settings['points_loss'] ?>" required></div>

            <button type="submit" class="btn">Save Settings</button>
        </form>
    </div>
    <p class="text-muted" style="margin:1rem auto 0; text-align:center; max-width:50ch;">
        Changes here apply to future results only. Existing standings stay as-is until a team's next match result is entered.
    </p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>