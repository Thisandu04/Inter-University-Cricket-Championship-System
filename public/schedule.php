<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/points.php';
$pageTitle = 'Schedule';
require_once __DIR__ . '/../includes/header.php';

$standingsMap = getStandingsMap($pdo);

$stageFilter = $_GET['stage'] ?? 'all';
$validStages = ['group', 'semi_final', 'final'];

$sql = "
    SELECT m.match_id, m.scheduled_date, m.scheduled_time, m.stage, m.status,
           t1.team_id AS team1_id, t1.team_name AS team1_name, t1.short_code AS team1_code, t1.logo_path AS team1_logo,
           t2.team_id AS team2_id, t2.team_name AS team2_name, t2.short_code AS team2_code, t2.logo_path AS team2_logo,
           v.name AS venue_name, v.city AS venue_city,
           r.team1_score, r.team1_wickets, r.team1_overs,
           r.team2_score, r.team2_wickets, r.team2_overs,
           r.result_type, r.winner_team_id, r.win_margin_type, r.win_margin_value,
           wt.team_name AS winner_name
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    JOIN venues v ON v.venue_id = m.venue_id
    LEFT JOIN match_results r ON r.match_id = m.match_id
    LEFT JOIN teams wt ON wt.team_id = r.winner_team_id
    WHERE m.status != 'cancelled'
";
if (in_array($stageFilter, $validStages, true)) {
    $sql .= " AND m.stage = :stage";
}
$sql .= " ORDER BY m.scheduled_date ASC, m.scheduled_time ASC";

$stmt = $pdo->prepare($sql);
if (in_array($stageFilter, $validStages, true)) {
    $stmt->bindValue(':stage', $stageFilter);
}
$stmt->execute();
$matches = $stmt->fetchAll();

function resultSummary(array $r): string {
    if ($r['result_type'] === 'tie') return 'Match tied';
    if ($r['result_type'] === 'no_result') return 'No result';
    $margin = $r['win_margin_value'] . ' ' . ($r['win_margin_type'] === 'wickets' ? 'wicket(s)' : 'run(s)');
    return htmlspecialchars($r['winner_name']) . ' won by ' . $margin;
}
?>

<section class="section">
    <div class="section-head">
        <h1>Match Schedule</h1>
    </div>

    <div style="margin-bottom:1.5rem; display:flex; gap:0.6rem; flex-wrap:wrap;">
        <a href="?stage=all" class="btn <?= $stageFilter === 'all' ? '' : 'btn--outline' ?>">All</a>
        <a href="?stage=group" class="btn <?= $stageFilter === 'group' ? '' : 'btn--outline' ?>">Group Stage</a>
        <a href="?stage=semi_final" class="btn <?= $stageFilter === 'semi_final' ? '' : 'btn--outline' ?>">Semi-Finals</a>
        <a href="?stage=final" class="btn <?= $stageFilter === 'final' ? '' : 'btn--outline' ?>">Final</a>
    </div>

    <?php if (empty($matches)): ?>
        <div class="empty-state">No matches in this category yet.</div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($matches as $m): ?>
                <div class="card <?= $m['status'] === 'ongoing' ? 'card--live' : '' ?>">
                    <?php if ($m['status'] === 'ongoing'): ?>
                        <div class="live-badge"><span class="live-dot" aria-hidden="true"></span> Live</div>
                    <?php else: ?>
                        <span class="status-tag status-tag--<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span>
                    <?php endif; ?>
                    <span class="status-tag" style="background:rgba(139,147,167,0.12); color:var(--text-dim); margin-left:0.3rem;">
                        <?= ucfirst(str_replace('_', ' ', $m['stage'])) ?>
                    </span>

                    <?php if ($m['status'] === 'completed'): ?>
                        <?= renderResultTeams(
                            ['id' => $m['team1_id'], 'name' => $m['team1_name'], 'code' => $m['team1_code'], 'logo' => $m['team1_logo'], 'score' => $m['team1_score'], 'wickets' => $m['team1_wickets'], 'overs' => $m['team1_overs'] ?? 0],
                            ['id' => $m['team2_id'], 'name' => $m['team2_name'], 'code' => $m['team2_code'], 'logo' => $m['team2_logo'], 'score' => $m['team2_score'], 'wickets' => $m['team2_wickets'], 'overs' => $m['team2_overs'] ?? 0],
                            isset($m['winner_team_id']) && $m['winner_team_id'] !== null ? (int) $m['winner_team_id'] : null,
                            $m['result_type'] ?? ''
                        ) ?>
                        <p class="result-summary-line"><?= resultSummary($m) ?></p>
                    <?php else: ?>
                        <?= renderMatchTeams(
                            $standingsMap,
                            ['id' => $m['team1_id'], 'name' => $m['team1_name'], 'code' => $m['team1_code'], 'logo' => $m['team1_logo']],
                            ['id' => $m['team2_id'], 'name' => $m['team2_name'], 'code' => $m['team2_code'], 'logo' => $m['team2_logo']]
                        ) ?>
                        <p class="card-meta"><?= date('g:i A', strtotime($m['scheduled_time'])) ?></p>
                    <?php endif; ?>

                    <p class="card-meta"><?= date('D, d M Y', strtotime($m['scheduled_date'])) ?></p>
                    <p class="card-meta"><?= htmlspecialchars($m['venue_name']) ?>, <?= htmlspecialchars($m['venue_city']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>