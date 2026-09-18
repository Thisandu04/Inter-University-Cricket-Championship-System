<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stats.php';
requireRole('admin');

$pendingTeams = $pdo->query("SELECT COUNT(*) AS c FROM teams WHERE approval_status = 'pending'")->fetch()['c'];
$approvedTeams = $pdo->query("SELECT COUNT(*) AS c FROM teams WHERE approval_status = 'approved'")->fetch()['c'];
$totalUsers = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE is_active = 1")->fetch()['c'];
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_status = 'completed'")->fetch()['total'];

$paymentsTrend = getPaymentsTrend($pdo, 7);
$signupsTrend = getSignupsTrend($pdo, 7);

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1>Admin Dashboard</h1>

    <div class="card-grid">
        <?= renderStatCard('Pending Team Approvals', (string) $pendingTeams) ?>
        <?= renderStatCard('Approved Teams', (string) $approvedTeams) ?>
        <?= renderStatCard('Active Users', (string) $totalUsers, $signupsTrend, 'var(--orange)') ?>
        <?= renderStatCard('Payments Collected', 'LKR ' . number_format($totalRevenue, 0), $paymentsTrend, 'var(--cyan)') ?>
    </div>

    <div style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap;">
        <?php if ($pendingTeams > 0): ?>
            <a href="approve_teams.php" class="btn">Review Pending Teams</a>
        <?php endif; ?>
        <a href="manage_users.php" class="btn btn--outline">Manage Users</a>
        <a href="settings.php" class="btn btn--outline">System Settings</a>
        <a href="reports.php" class="btn btn--outline">Reports</a>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>