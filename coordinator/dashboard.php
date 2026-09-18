<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stats.php';
requireRole('coordinator');

$upcomingCount = $pdo->query("SELECT COUNT(*) AS c FROM matches WHERE status = 'scheduled'")->fetch()['c'];
$ongoingCount  = $pdo->query("SELECT COUNT(*) AS c FROM matches WHERE status = 'ongoing'")->fetch()['c'];
$venueCount    = $pdo->query("SELECT COUNT(*) AS c FROM venues WHERE is_active = 1")->fetch()['c'];
$teamCount     = $pdo->query("SELECT COUNT(*) AS c FROM teams WHERE approval_status = 'approved'")->fetch()['c'];

$matchesTrend = getMatchesCompletedTrend($pdo, 7);

$pageTitle = 'Coordinator Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1>Coordinator Dashboard</h1>

    <div class="card-grid">
        <?= renderStatCard('Approved Teams', (string) $teamCount) ?>
        <?= renderStatCard('Upcoming Matches', (string) $upcomingCount) ?>
        <?= renderStatCard('Ongoing Matches', (string) $ongoingCount) ?>
        <?= renderStatCard('Active Venues', (string) $venueCount) ?>
        <?= renderStatCard('Matches Completed (7d)', (string) array_sum($matchesTrend), $matchesTrend, 'var(--gold)') ?>
    </div>

    <div style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap;">
        <a href="manage_matches.php" class="btn">Manage Matches</a>
        <a href="manage_venues.php" class="btn btn--outline">Manage Venues</a>
        <a href="enter_result.php" class="btn btn--outline">Enter Result</a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>