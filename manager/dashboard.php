<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('team_manager');

$user = currentUser();

$stmt = $pdo->prepare("
    SELECT t.*,
           (SELECT COUNT(*) FROM players WHERE team_id = t.team_id AND is_active = 1) AS player_count,
           p.payment_status
    FROM teams t
    LEFT JOIN payments p ON p.team_id = t.team_id
    WHERE t.manager_user_id = ?
");
$stmt->execute([$user['user_id']]);
$team = $stmt->fetch();

$settingsStmt = $pdo->query("SELECT * FROM system_settings WHERE setting_id = 1");
$settings = $settingsStmt->fetch();

$pageTitle = 'Manager Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1>Team Manager Dashboard</h1>

    <?php if (!$team): ?>
        <div class="empty-state">
            <p>You haven't registered a team yet.</p>
            <?php if ($settings['registration_deadline'] && strtotime($settings['registration_deadline']) < time()): ?>
                <p class="error-msg" style="margin-top:1rem; display:inline-block;">
                    Registration deadline (<?= date('d M Y', strtotime($settings['registration_deadline'])) ?>) has passed.
                </p>
            <?php else: ?>
                <a href="register_team.php" class="btn" style="margin-top:1rem;">Register Your Team</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <div class="card">
                <h3 class="card-title">Team Status</h3>
                <span class="status-tag status-tag--<?= $team['approval_status'] ?>">
                    <?= ucfirst($team['approval_status']) ?>
                </span>
                <?php if ($team['approval_status'] === 'pending'): ?>
                    <p class="card-meta" style="margin-top:0.6rem;">Waiting for Admin approval.</p>
                <?php elseif ($team['approval_status'] === 'rejected'): ?>
                    <p class="card-meta" style="margin-top:0.6rem;">Contact the Admin for details.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 class="card-title">Squad</h3>
                <p class="card-meta"><?= $team['player_count'] ?> / <?= $settings['max_players_per_team'] ?> players registered</p>
                <a href="manage_players.php" class="btn btn--outline" style="margin-top:0.8rem;">Manage Players</a>
            </div>

            <div class="card">
                <h3 class="card-title">Registration Fee</h3>
                <span class="status-tag status-tag--<?= $team['payment_status'] === 'completed' ? 'approved' : 'pending' ?>">
                    <?= ucfirst($team['payment_status'] ?? 'not started') ?>
                </span>
                <?php if ($team['payment_status'] !== 'completed'): ?>
                    <a href="payment.php" class="btn btn--outline" style="margin-top:0.8rem; display:block; text-align:center;">Pay Now</a>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 class="card-title">Team Details</h3>
                <p class="card-meta"><?= htmlspecialchars($team['team_name']) ?></p>
                <a href="team_settings.php" class="btn btn--outline" style="margin-top:0.8rem;">Edit Team</a>
            </div>
        </div>

        <div class="section" style="margin-top:2rem;">
            <a href="<?= BASE_URL ?>/public/team_profile.php?id=<?= $team['team_id'] ?>" class="text-muted">
                View public team profile &rarr;
            </a>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>