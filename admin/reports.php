<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

// Report data
$teamsSummary = $pdo->query("
    SELECT t.team_name, t.university_name, t.approval_status,
           (SELECT COUNT(*) FROM players WHERE team_id = t.team_id AND is_active = 1) AS player_count,
           p.payment_status, p.amount
    FROM teams t
    LEFT JOIN payments p ON p.team_id = t.team_id
    ORDER BY t.team_name
")->fetchAll();

$standings = $pdo->query("
    SELECT t.team_name, pt.played, pt.won, pt.lost, pt.tied, pt.no_result, pt.points, pt.net_run_rate
    FROM points_table pt
    JOIN teams t ON t.team_id = pt.team_id
    ORDER BY pt.points DESC, pt.net_run_rate DESC
")->fetchAll();

$revenue = $pdo->query("
    SELECT COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS total
    FROM payments WHERE payment_status = 'completed'
")->fetch();

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section no-print center-page">
    <h1>Tournament Reports</h1>
    <button onclick="window.print()" class="btn">Download as PDF</button>
    <p class="text-muted" style="margin:0.5rem auto 0; text-align:center; max-width:50ch;">
        In the print dialog, choose "Save as PDF" as the destination.
    </p>
</section>

<section class="section">
    <h2>Payment Summary</h2>
    <p><?= $revenue['payment_count'] ?> completed payments &middot; Total collected: LKR <?= number_format($revenue['total'], 2) ?></p>
</section>

<section class="section">
    <h2>Teams Report</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Team</th><th>University</th><th>Status</th><th>Players</th><th>Payment</th><th>Amount</th></tr></thead>
            <tbody>
                <?php foreach ($teamsSummary as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['team_name']) ?></td>
                        <td><?= htmlspecialchars($t['university_name']) ?></td>
                        <td><?= ucfirst($t['approval_status']) ?></td>
                        <td><?= $t['player_count'] ?></td>
                        <td><?= ucfirst($t['payment_status'] ?? 'N/A') ?></td>
                        <td><?= $t['amount'] ? 'LKR ' . number_format($t['amount'], 2) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="section">
    <h2>Final Standings</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Team</th><th>P</th><th>W</th><th>L</th><th>T</th><th>NR</th><th>NRR</th><th>Pts</th></tr></thead>
            <tbody>
                <?php foreach ($standings as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['team_name']) ?></td>
                        <td><?= $s['played'] ?></td>
                        <td><?= $s['won'] ?></td>
                        <td><?= $s['lost'] ?></td>
                        <td><?= $s['tied'] ?></td>
                        <td><?= $s['no_result'] ?></td>
                        <td><?= number_format($s['net_run_rate'], 3) ?></td>
                        <td><strong><?= $s['points'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
@media print {
    .site-header, .site-footer, .no-print { display: none !important; }
    body { background: white; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>