<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamId = (int) $_POST['team_id'];
    $decision = $_POST['decision']; // 'Approved' or 'Rejected'

    $stmt = $pdo->prepare("UPDATE teams SET approval_status = ? WHERE team_id = ?");
    $stmt->execute([$decision, $teamId]);

    if ($decision === 'approved') {
        $check = $pdo->prepare("SELECT points_id FROM points_table WHERE team_id = ?");
        $check->execute([$teamId]);
        if (!$check->fetch()) {
            $init = $pdo->prepare("INSERT INTO points_table (team_id) VALUES (?)");
            $init->execute([$teamId]);
        }
    }

    $success = "Team " . ($decision === 'approved' ? 'approved' : 'rejected') . ".";
}

$pendingTeams = $pdo->query("
    SELECT t.*, u.first_name, u.last_name, u.email,
           (SELECT COUNT(*) FROM players WHERE team_id = t.team_id AND is_active = 1) AS player_count,
           p.payment_status
    FROM teams t
    JOIN users u ON u.user_id = t.manager_user_id
    LEFT JOIN payments p ON p.team_id = t.team_id
    WHERE t.approval_status = 'pending'
    ORDER BY t.created_at ASC
")->fetchAll();

$pageTitle = 'Approve Teams';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1>Approve Teams</h1>

    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (empty($pendingTeams)): ?>
        <div class="empty-state">No teams awaiting approval.</div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($pendingTeams as $t): ?>
                <div class="card">
                    <h3 class="card-title"><?= htmlspecialchars($t['team_name']) ?></h3>
                    <p class="card-meta"><?= htmlspecialchars($t['university_name']) ?></p>
                    <p class="card-meta">Manager: <?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?> (<?= htmlspecialchars($t['email']) ?>)</p>
                    <p class="card-meta"><?= $t['player_count'] ?> players registered</p>
                    <p class="card-meta">
                        Payment:
                        <span class="status-tag status-tag--<?= $t['payment_status'] === 'completed' ? 'approved' : 'pending' ?>">
                            <?= ucfirst($t['payment_status'] ?? 'not started') ?>
                        </span>
                    </p>

                    <form method="POST" style="margin-top:1rem; display:flex; gap:0.6rem;">
                        <input type="hidden" name="team_id" value="<?= $t['team_id'] ?>">
                        <button type="submit" name="decision" value="approved" class="btn">Approve</button>
                        <button type="submit" name="decision" value="rejected" class="btn btn--danger">Reject</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>