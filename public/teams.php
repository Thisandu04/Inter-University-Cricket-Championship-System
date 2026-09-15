<?php
require_once __DIR__ . '/../config/db.php';
$pageTitle = 'Teams';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("
    SELECT team_id, team_name, university_name, short_code, logo_path
    FROM teams
    WHERE approval_status = 'approved'
    ORDER BY team_name ASC
");
$teams = $stmt->fetchAll();
?>

<section class="section">
    <h1>Teams</h1>

    <?php if (empty($teams)): ?>
        <div class="empty-state">No approved teams yet.</div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($teams as $t): ?>
                <a href="team_profile.php?id=<?= $t['team_id'] ?>" class="card" style="display:flex; gap:1rem; align-items:center; text-decoration:none; color:inherit;">
                    <img
                        src="<?= !empty($t['logo_path']) ? BASE_URL . '/public/' . htmlspecialchars($t['logo_path']) : BASE_URL . '/public/assets/logos/placeholder.svg' ?>"
                        alt="<?= htmlspecialchars($t['team_name']) ?> logo"
                        class="team-logo"
                    >
                    <div>
                        <h3 class="card-title"><?= htmlspecialchars($t['team_name']) ?></h3>
                        <p class="card-meta"><?= htmlspecialchars($t['university_name']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>