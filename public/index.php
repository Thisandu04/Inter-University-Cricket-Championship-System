<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/points.php';
$pageTitle = 'Home';
require_once __DIR__ . '/../includes/header.php';

$standingsMap = getStandingsMap($pdo);

// ---- 0. Spotlight: live match takes priority, else soonest upcoming ----
$liveMatch = $pdo->query("
    SELECT m.match_id, m.scheduled_date, m.scheduled_time, m.stage,
           t1.team_id AS team1_id, t1.team_name AS team1_name, t1.short_code AS team1_code, t1.logo_path AS team1_logo,
           t2.team_id AS team2_id, t2.team_name AS team2_name, t2.short_code AS team2_code, t2.logo_path AS team2_logo,
           v.name AS venue_name, v.city AS venue_city
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    JOIN venues v ON v.venue_id = m.venue_id
    WHERE m.status = 'ongoing'
    ORDER BY m.scheduled_date ASC, m.scheduled_time ASC
    LIMIT 1
")->fetch();

$spotlightMatch = $liveMatch;
$spotlightIsLive = (bool) $liveMatch;

if (!$spotlightMatch) {
    $spotlightMatch = $pdo->query("
        SELECT m.match_id, m.scheduled_date, m.scheduled_time, m.stage,
               t1.team_id AS team1_id, t1.team_name AS team1_name, t1.short_code AS team1_code, t1.logo_path AS team1_logo,
               t2.team_id AS team2_id, t2.team_name AS team2_name, t2.short_code AS team2_code, t2.logo_path AS team2_logo,
               v.name AS venue_name, v.city AS venue_city
        FROM matches m
        JOIN teams t1 ON t1.team_id = m.team1_id
        JOIN teams t2 ON t2.team_id = m.team2_id
        JOIN venues v ON v.venue_id = m.venue_id
        WHERE m.status = 'scheduled'
        ORDER BY m.scheduled_date ASC, m.scheduled_time ASC
        LIMIT 1
    ")->fetch();
}

// ---- 1. Points Table ----
$pointsStmt = $pdo->query("
    SELECT t.team_id, t.team_name, t.short_code, t.logo_path,
           pt.played, pt.won, pt.lost, pt.tied, pt.no_result,
           pt.points, pt.net_run_rate
    FROM points_table pt
    JOIN teams t ON t.team_id = pt.team_id
    WHERE t.approval_status = 'approved'
    ORDER BY pt.points DESC, pt.net_run_rate DESC
");
$standings = $pointsStmt->fetchAll();

// ---- 2. Upcoming matches (excluding the one already shown in spotlight) ----
$excludeId = $spotlightMatch ? (int) $spotlightMatch['match_id'] : 0;
$upcomingStmt = $pdo->prepare("
    SELECT m.match_id, m.scheduled_date, m.scheduled_time, m.stage, m.status,
           t1.team_id AS team1_id, t1.team_name AS team1_name, t1.short_code AS team1_code, t1.logo_path AS team1_logo,
           t2.team_id AS team2_id, t2.team_name AS team2_name, t2.short_code AS team2_code, t2.logo_path AS team2_logo,
           v.name AS venue_name, v.city AS venue_city
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    JOIN venues v ON v.venue_id = m.venue_id
    WHERE m.status IN ('scheduled', 'ongoing') AND m.match_id != ?
    ORDER BY m.scheduled_date ASC, m.scheduled_time ASC
    LIMIT 4
");
$upcomingStmt->execute([$excludeId]);
$upcoming = $upcomingStmt->fetchAll();

// ---- 3. Recent results ----
$resultsStmt = $pdo->query("
    SELECT m.match_id, m.scheduled_date, m.stage,
           t1.team_id AS team1_id, t1.team_name AS team1_name, t1.short_code AS team1_code, t1.logo_path AS team1_logo,
           t2.team_id AS team2_id, t2.team_name AS team2_name, t2.short_code AS team2_code, t2.logo_path AS team2_logo,
           r.team1_score, r.team1_wickets, r.team1_overs, r.team2_score, r.team2_wickets, r.team2_overs,
           r.result_type, r.winner_team_id, r.win_margin_type, r.win_margin_value,
           wt.team_name AS winner_name
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    JOIN match_results r ON r.match_id = m.match_id
    LEFT JOIN teams wt ON wt.team_id = r.winner_team_id
    WHERE m.status = 'completed'
    ORDER BY m.scheduled_date DESC
    LIMIT 6
");
$recentResults = $resultsStmt->fetchAll();

function resultSummary(array $r): string {
    if ($r['result_type'] === 'tie') return 'Match tied';
    if ($r['result_type'] === 'no_result') return 'No result';
    $margin = $r['win_margin_value'] . ' ' . ($r['win_margin_type'] === 'wickets' ? 'wicket(s)' : 'run(s)');
    return htmlspecialchars($r['winner_name']) . ' won by ' . $margin;
}
?>

<section class="section hero">
    <h1>Inter-University Cricket Tournament</h1>
    <p class="text-muted">Live standings, fixtures, and results from this season's tournament.</p>
</section>

<?php if ($spotlightMatch): ?>
<section class="section">
    <div class="spotlight <?= $spotlightIsLive ? 'spotlight--live' : '' ?>">
        <div class="spotlight-eyebrow <?= $spotlightIsLive ? 'spotlight-eyebrow--live' : 'spotlight-eyebrow--next' ?>">
            <?php if ($spotlightIsLive): ?>
                <span class="live-dot" aria-hidden="true"></span> Live now
            <?php else: ?>
                Next Match
            <?php endif; ?>
        </div>

        <div class="spotlight-teams">
            <div class="spotlight-team-row">
                <span class="match-team-avatar">
                    <?php if (!empty($spotlightMatch['team1_logo'])): ?>
                        <img src="<?= BASE_URL . '/public/' . htmlspecialchars($spotlightMatch['team1_logo']) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars(strtoupper(substr($spotlightMatch['team1_code'] ?: $spotlightMatch['team1_name'], 0, 3))) ?>
                    <?php endif; ?>
                </span>
                <span class="spotlight-team-name"><?= htmlspecialchars($spotlightMatch['team1_code'] ?: $spotlightMatch['team1_name']) ?></span>
            </div>
            <div class="spotlight-vs">vs</div>
            <div class="spotlight-team-row">
                <span class="match-team-avatar">
                    <?php if (!empty($spotlightMatch['team2_logo'])): ?>
                        <img src="<?= BASE_URL . '/public/' . htmlspecialchars($spotlightMatch['team2_logo']) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars(strtoupper(substr($spotlightMatch['team2_code'] ?: $spotlightMatch['team2_name'], 0, 3))) ?>
                    <?php endif; ?>
                </span>
                <span class="spotlight-team-name"><?= htmlspecialchars($spotlightMatch['team2_code'] ?: $spotlightMatch['team2_name']) ?></span>
            </div>
        </div>

        <div class="spotlight-meta">
            <span><?= ucfirst(str_replace('_', ' ', $spotlightMatch['stage'])) ?></span>
            <span class="spotlight-meta-divider">&middot;</span>
            <span><?= date('D, d M Y', strtotime($spotlightMatch['scheduled_date'])) ?></span>
            <span class="spotlight-meta-divider">&middot;</span>
            <span><?= date('g:i A', strtotime($spotlightMatch['scheduled_time'])) ?></span>
            <span class="spotlight-meta-divider">&middot;</span>
            <span><?= htmlspecialchars($spotlightMatch['venue_name']) ?>, <?= htmlspecialchars($spotlightMatch['venue_city']) ?></span>

            <?php if (!$spotlightIsLive): ?>
                <span class="spotlight-meta-divider">&middot;</span>
                <span class="countdown" id="spotlightCountdown"
                     data-target="<?= date('c', strtotime($spotlightMatch['scheduled_date'] . ' ' . $spotlightMatch['scheduled_time'])) ?>">
                    &mdash;
                </span>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="section-head">
        <h2>Points Table</h2>
        <a href="teams.php" class="text-muted">View all teams &rarr;</a>
    </div>

    <?php if (empty($standings)): ?>
        <div class="empty-state">Standings will appear here once matches begin.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Team</th>
                        <th>P</th>
                        <th>W</th>
                        <th>L</th>
                        <th>T</th>
                        <th>NR</th>
                        <th>NRR</th>
                        <th>Pts</th>
                        <th>Form</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($standings as $i => $row): $rank = $i + 1; ?>
                        <tr class="<?= $rank === 1 ? 'rank-1' : ($rank === 4 ? 'rank-4' : '') ?>">
                            <td><?= $rank ?></td>
                            <td>
                                <?php if (!empty($row['logo_path'])): ?>
                                    <img src="<?= BASE_URL . '/public/' . htmlspecialchars($row['logo_path']) ?>" alt="" class="team-logo" style="width:24px;height:24px;vertical-align:middle;margin-right:8px;">
                                <?php endif; ?>
                                <?= htmlspecialchars($row['short_code'] ?: $row['team_name']) ?>
                            </td>
                            <td><?= $row['played'] ?></td>
                            <td><?= $row['won'] ?></td>
                            <td><?= $row['lost'] ?></td>
                            <td><?= $row['tied'] ?></td>
                            <td><?= $row['no_result'] ?></td>
                            <td><?= number_format($row['net_run_rate'], 3) ?></td>
                            <td><strong><?= $row['points'] ?></strong></td>
                            <td><?= renderFormDots(getRecentForm($pdo, $row['team_id'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted" style="margin-top:0.6rem; font-size:0.85rem;">
            🏆 The top 4 teams will advance to the Semi-Finals
        </p>
    <?php endif; ?>
</section>

<section class="section">
    <div class="homepage-columns">
        <div>
            <div class="section-head">
                <h2>Upcoming</h2>
                <a href="schedule.php" class="text-muted">Full schedule &rarr;</a>
            </div>
            <?php if (empty($upcoming)): ?>
                <div class="empty-state">No further matches scheduled yet.</div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($upcoming as $m): ?>
                        <div class="card <?= $m['status'] === 'ongoing' ? 'card--live' : '' ?>">
                            <?php if ($m['status'] === 'ongoing'): ?>
                                <div class="live-badge"><span class="live-dot" aria-hidden="true"></span> Live</div>
                            <?php else: ?>
                                <span class="status-tag status-tag--scheduled"><?= ucfirst(str_replace('_', ' ', $m['stage'])) ?></span>
                            <?php endif; ?>

                            <?= renderMatchTeams(
                                $standingsMap,
                                ['id' => $m['team1_id'], 'name' => $m['team1_name'], 'code' => $m['team1_code'], 'logo' => $m['team1_logo']],
                                ['id' => $m['team2_id'], 'name' => $m['team2_name'], 'code' => $m['team2_code'], 'logo' => $m['team2_logo']]
                            ) ?>

                            <p class="card-meta">
                                <?= date('D, d M Y', strtotime($m['scheduled_date'])) ?>
                                &middot; <?= date('g:i A', strtotime($m['scheduled_time'])) ?>
                            </p>
                            <p class="card-meta"><?= htmlspecialchars($m['venue_name']) ?>, <?= htmlspecialchars($m['venue_city']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div class="section-head">
                <h2>Recent Results</h2>
                <a href="results.php" class="text-muted">All results &rarr;</a>
            </div>
            <?php if (empty($recentResults)): ?>
                <div class="empty-state">No results yet.</div>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($recentResults as $r): ?>
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
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>