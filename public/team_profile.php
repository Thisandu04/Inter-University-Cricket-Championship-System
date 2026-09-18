<?php
require_once __DIR__ . '/../config/db.php';

$teamId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT team_id, team_name, university_name, short_code, logo_path,
           captain_player_id, vice_captain_player_id
    FROM teams
    WHERE team_id = ? AND approval_status = 'approved'
");
$stmt->execute([$teamId]);
$team = $stmt->fetch();

if (!$team) {
    $pageTitle = 'Team Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state">Team not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $team['team_name'];
require_once __DIR__ . '/../includes/header.php';

// Players
$playersStmt = $pdo->prepare("
    SELECT player_id, first_name, last_name, playing_role
    FROM players
    WHERE team_id = ? AND is_active = 1
    ORDER BY last_name ASC
");
$playersStmt->execute([$teamId]);
$players = $playersStmt->fetchAll();

// This team's points table row
$ptStmt = $pdo->prepare("SELECT * FROM points_table WHERE team_id = ?");
$ptStmt->execute([$teamId]);
$standing = $ptStmt->fetch();
?>

<section class="section" style="display:flex; gap:1.5rem; align-items:center; flex-wrap:wrap;">
    <img
        src="<?= !empty($team['logo_path']) ? BASE_URL . '/public/' . htmlspecialchars($team['logo_path']) : BASE_URL . '/public/assets/logos/placeholder.svg' ?>"
        alt="<?= htmlspecialchars($team['team_name']) ?> logo"
        style="width:96px; height:96px; border-radius:50%; border:3px solid var(--color-border); object-fit:cover;"
    >
    <div>
        <h1><?= htmlspecialchars($team['team_name']) ?></h1>
        <p class="text-muted"><?= htmlspecialchars($team['university_name']) ?></p>
    </div>
</section>

<?php if ($standing): ?>
<section class="section">
    <h2>Tournament Record</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Played</th><th>Won</th><th>Lost</th><th>Tied</th><th>No Result</th><th>NRR</th><th>Points</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= $standing['played'] ?></td>
                    <td><?= $standing['won'] ?></td>
                    <td><?= $standing['lost'] ?></td>
                    <td><?= $standing['tied'] ?></td>
                    <td><?= $standing['no_result'] ?></td>
                    <td><?= number_format($standing['net_run_rate'], 3) ?></td>
                    <td><strong><?= $standing['points'] ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <h2>Squad</h2>
    <?php if (empty($players)): ?>
        <div class="empty-state">Squad list not published yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Playing Role</th><th>Position</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $p): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                            </td>
                            <td><?= htmlspecialchars($p['playing_role']) ?></td>
                            <td>
                                <?php if ($p['player_id'] === $team['captain_player_id']): ?>
                                    <span class="status-tag status-tag--approved">Captain</span>
                                <?php elseif ($p['player_id'] === $team['vice_captain_player_id']): ?>
                                    <span class="status-tag status-tag--scheduled">Vice-Captain</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>