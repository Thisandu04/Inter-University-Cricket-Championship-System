<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/points.php';
$pageTitle = 'Results';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("
    SELECT m.match_id, m.scheduled_date, m.stage,
           t1.team_id AS team1_id, t1.team_name AS team1_name, t1.short_code AS team1_code, t1.logo_path AS team1_logo,
           t2.team_id AS team2_id, t2.team_name AS team2_name, t2.short_code AS team2_code, t2.logo_path AS team2_logo,
           r.team1_score, r.team1_wickets, r.team1_overs,
           r.team2_score, r.team2_wickets, r.team2_overs,
           r.result_type, r.winner_team_id, r.win_margin_type, r.win_margin_value,
           wt.team_name AS winner_name
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    JOIN match_results r ON r.match_id = m.match_id
    LEFT JOIN teams wt ON wt.team_id = r.winner_team_id
    WHERE m.status = 'completed'
    ORDER BY m.scheduled_date DESC
");
$results = $stmt->fetchAll();

function resultSummary(array $r): string {
    if ($r['result_type'] === 'tie') return 'Match tied';
    if ($r['result_type'] === 'no_result') return 'No result';
    $margin = $r['win_margin_value'] . ' ' . ($r['win_margin_type'] === 'wickets' ? 'wicket(s)' : 'run(s)');
    return htmlspecialchars($r['winner_name']) . ' won by ' . $margin;
}
?>

<section class="section">
    <h1>Results</h1>

    <?php if (empty($results)): ?>
        <div class="empty-state">No completed matches yet.</div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($results as $r): ?>
                    <div class="card">
                        <span class="status-tag status-tag--completed"><?= ucfirst(str_replace('_', ' ', $r['stage'])) ?></span>
                    <?= renderResultTeams(
                        ['id' => $r['team1_id'], 'name' => $r['team1_name'], 'code' => $r['team1_code'], 'logo' => $r['team1_logo'], 'score' => $r['team1_score'], 'wickets' => $r['team1_wickets'], 'overs' => $r['team1_overs']],
                        ['id' => $r['team2_id'], 'name' => $r['team2_name'], 'code' => $r['team2_code'], 'logo' => $r['team2_logo'], 'score' => $r['team2_score'], 'wickets' => $r['team2_wickets'], 'overs' => $r['team2_overs']],
                        $r['winner_team_id'] !== null ? (int) $r['winner_team_id'] : null,
                        $r['result_type']
                    ) ?>

                    <p class="result-summary-line"><?= resultSummary($r) ?></p>
                    <p class="card-meta"><?= date('D, d M Y', strtotime($r['scheduled_date'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>