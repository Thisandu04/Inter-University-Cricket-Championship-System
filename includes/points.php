<?php
/**
 * Converts cricket-style overs notation (e.g. 19.4 = 19 overs, 4 balls)
 * into a true decimal value (19.667) for NRR maths.
 */
function oversToDecimal(float $overs): float {
    $wholeOvers = floor($overs);
    $balls = round(($overs - $wholeOvers) * 10);
    return $wholeOvers + ($balls / 6);
}

/**
 * Recalculates and saves the points_table row for a single team,
 * based on every completed match_results row that team was involved in.
 * Call this for BOTH teams after a result is entered or corrected.
 */
function recalcPointsTable(PDO $pdo, int $teamId): void {
    $settings = $pdo->query("SELECT * FROM system_settings WHERE setting_id = 1")->fetch();

    $stmt = $pdo->prepare("
        SELECT m.team1_id, m.team2_id,
               r.team1_score, r.team1_overs,
               r.team2_score, r.team2_overs,
               r.result_type, r.winner_team_id
        FROM matches m
        JOIN match_results r ON r.match_id = m.match_id
        WHERE m.status = 'completed'
          AND (m.team1_id = :team_id1 OR m.team2_id = :team_id2)
    ");
    $stmt->execute(['team_id1' => $teamId, 'team_id2' => $teamId]);
    $matchResults = $stmt->fetchAll();

    $played = $won = $lost = $tied = $noResult = 0;
    $runsScored = $oversFaced = $runsConceded = $oversBowled = 0.0;

    foreach ($matchResults as $r) {
        $played++;
        $isTeam1 = ((int) $r['team1_id'] === $teamId);

        $ownScore  = $isTeam1 ? $r['team1_score'] : $r['team2_score'];
        $ownOvers  = $isTeam1 ? $r['team1_overs'] : $r['team2_overs'];
        $oppScore  = $isTeam1 ? $r['team2_score'] : $r['team1_score'];
        $oppOvers  = $isTeam1 ? $r['team2_overs'] : $r['team1_overs'];

        $runsScored   += $ownScore;
        $oversFaced   += oversToDecimal((float) $ownOvers);
        $runsConceded += $oppScore;
        $oversBowled  += oversToDecimal((float) $oppOvers);

        if ($r['result_type'] === 'tie') {
            $tied++;
        } elseif ($r['result_type'] === 'no_result') {
            $noResult++;
        } elseif ((int) $r['winner_team_id'] === $teamId) {
            $won++;
        } else {
            $lost++;
        }
    }

    $points = ($won * $settings['points_win'])
            + ($tied * $settings['points_tie'])
            + ($noResult * $settings['points_no_result'])
            + ($lost * $settings['points_loss']);

    $nrr = 0.0;
    if ($oversFaced > 0 && $oversBowled > 0) {
        $nrr = ($runsScored / $oversFaced) - ($runsConceded / $oversBowled);
    }
    $nrr = round($nrr, 3);

    $upsert = $pdo->prepare("
        INSERT INTO points_table (team_id, played, won, lost, tied, no_result, points, net_run_rate)
        VALUES (:team_id, :played, :won, :lost, :tied, :no_result, :points, :nrr)
        ON DUPLICATE KEY UPDATE
            played = :played2, won = :won2, lost = :lost2, tied = :tied2,
            no_result = :no_result2, points = :points2, net_run_rate = :nrr2
    ");
    $upsert->execute([
        'team_id'    => $teamId,
        'played'     => $played,     'played2'    => $played,
        'won'        => $won,        'won2'       => $won,
        'lost'       => $lost,       'lost2'      => $lost,
        'tied'       => $tied,       'tied2'      => $tied,
        'no_result'  => $noResult,   'no_result2' => $noResult,
        'points'     => $points,     'points2'    => $points,
        'nrr'        => $nrr,        'nrr2'       => $nrr,
    ]);
}

/**
 * Returns the last N results for a team as an array of 'W', 'L', 'T', or 'NR',
 * most recent first. Used for the points table "Form" column.
 */
function getRecentForm(PDO $pdo, int $teamId, int $limit = 5): array {
    $stmt = $pdo->prepare("
        SELECT m.team1_id, m.team2_id, r.result_type, r.winner_team_id
        FROM matches m
        JOIN match_results r ON r.match_id = m.match_id
        WHERE m.status = 'completed'
          AND (m.team1_id = :t1 OR m.team2_id = :t2)
        ORDER BY m.scheduled_date DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':t1', $teamId, PDO::PARAM_INT);
    $stmt->bindValue(':t2', $teamId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $form = [];
    foreach ($rows as $r) {
        if ($r['result_type'] === 'tie') {
            $form[] = 'T';
        } elseif ($r['result_type'] === 'no_result') {
            $form[] = 'NR';
        } elseif ((int) $r['winner_team_id'] === $teamId) {
            $form[] = 'W';
        } else {
            $form[] = 'L';
        }
    }
    return $form;
}

/**
 * Renders the form dots as HTML, most recent result last (reads left-to-right
 * like a timeline). Call this from any page that already includes points.php.
 */
function renderFormDots(array $form): string {
    if (empty($form)) {
        return '<span class="text-muted" style="font-size:0.8rem;">&mdash;</span>';
    }
    $reversed = array_reverse($form); // oldest first, most recent last
    $html = '<span class="form-dots" title="Last ' . count($reversed) . ' results, oldest to most recent">';
    foreach ($reversed as $result) {
        $class = [
            'W'  => 'form-dot--w',
            'L'  => 'form-dot--l',
            'T'  => 'form-dot--t',
            'NR' => 'form-dot--nr',
        ][$result] ?? 'form-dot--nr';
        $html .= '<span class="form-dot ' . $class . '" aria-hidden="true"></span>';
    }
    $html .= '<span class="sr-only">Recent form: ' . implode(', ', $reversed) . '</span>';
    $html .= '</span>';
    return $html;
}

/**
 * Returns all standings keyed by team_id, with rank attached, sorted the
 * same way the points table is (points desc, NRR desc). Used to compute
 * relative "strength" bars on match cards without re-querying per match.
 */
function getStandingsMap(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT team_id, points, net_run_rate, played
        FROM points_table
        ORDER BY points DESC, net_run_rate DESC
    ");
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $i => $row) {
        $map[(int) $row['team_id']] = [
            'points'  => (int) $row['points'],
            'nrr'     => (float) $row['net_run_rate'],
            'played'  => (int) $row['played'],
            'rank'    => $i + 1,
        ];
    }
    return $map;
}

/**
 * Renders a match card's team section as strength-bar rows if both teams
 * have played at least one match, otherwise falls back to a plain "vs" line.
 * $team1/$team2 are arrays with 'id', 'name', 'code', 'logo' keys.
 */
function renderMatchTeams(array $standings, array $team1, array $team2): string {
    $s1 = $standings[$team1['id']] ?? null;
    $s2 = $standings[$team2['id']] ?? null;

    if (!$s1 || !$s2 || $s1['played'] === 0 || $s2['played'] === 0) {
        return '<div class="match-vs-plain">'
            . '<span>' . htmlspecialchars($team1['code'] ?: $team1['name']) . '</span>'
            . '<span class="text-muted" style="font-weight:400; font-size:0.8rem;">vs</span>'
            . '<span>' . htmlspecialchars($team2['code'] ?: $team2['name']) . '</span>'
            . '</div>';
    }

    $maxPoints = max($s1['points'], $s2['points'], 1); // avoid divide-by-zero
    $pct1 = max(8, round(($s1['points'] / $maxPoints) * 100));
    $pct2 = max(8, round(($s2['points'] / $maxPoints) * 100));

    $rankLabel = function (int $rank): string {
        $suffix = ['th', 'st', 'nd', 'rd'][($rank % 10 <= 3 && !in_array($rank % 100, [11, 12, 13])) ? $rank % 10 : 0] ?? 'th';
        return $rank . $suffix;
    };

    $avatar = function (array $team): string {
        if (!empty($team['logo'])) {
            return '<img src="' . BASE_URL . '/public/' . htmlspecialchars($team['logo']) . '" alt="">';
        }
        $initials = strtoupper(substr($team['code'] ?: $team['name'], 0, 3));
        return htmlspecialchars($initials);
    };

    $html = '<div class="match-teams">';

    $html .= '<div>'
        . '<div class="match-team-row">'
        . '<span class="match-team-avatar">' . $avatar($team1) . '</span>'
        . '<span class="match-team-name">' . htmlspecialchars($team1['code'] ?: $team1['name']) . '</span>'
        . '<span class="match-team-rank">' . $rankLabel($s1['rank']) . '</span>'
        . '</div>'
        . '<div class="strength-track"><div class="strength-fill strength-fill--1" style="width:' . $pct1 . '%;"></div></div>'
        . '</div>';

    $html .= '<div>'
        . '<div class="match-team-row">'
        . '<span class="match-team-avatar">' . $avatar($team2) . '</span>'
        . '<span class="match-team-name">' . htmlspecialchars($team2['code'] ?: $team2['name']) . '</span>'
        . '<span class="match-team-rank">' . $rankLabel($s2['rank']) . '</span>'
        . '</div>'
        . '<div class="strength-track"><div class="strength-fill strength-fill--2" style="width:' . $pct2 . '%;"></div></div>'
        . '</div>';

    $html .= '</div>';
    return $html;
}

/**
 * Renders a completed match's two teams as avatar + name + score rows,
 * with a trophy on the winner and a bar showing their relative scores.
 * $team1/$team2: ['id','name','code','logo','score','wickets','overs']
 * $winnerTeamId: int|null, $resultType: 'team1_won'|'team2_won'|'tie'|'no_result'
 */
function renderResultTeams(array $team1, array $team2, ?int $winnerTeamId, string $resultType): string {
    $avatar = function (array $team): string {
        if (!empty($team['logo'])) {
            return '<img src="' . BASE_URL . '/public/' . htmlspecialchars($team['logo']) . '" alt="">';
        }
        $initials = strtoupper(substr($team['code'] ?: $team['name'], 0, 3));
        return htmlspecialchars($initials);
    };

    $trophySvg = '<span class="result-trophy" title="Winner"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 3h-1V2a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v1H6a1 1 0 0 0-1 1v2a4 4 0 0 0 4 4 4.99 4.99 0 0 0 2.5 4.3V17H10a1 1 0 0 0 0 2h4a1 1 0 0 0 0-2h-1.5v-2.7A4.99 4.99 0 0 0 15 10a4 4 0 0 0 4-4V4a1 1 0 0 0-1-1zM7 8V5h1v3a2 2 0 0 1-2 2 2 2 0 0 1-2-2V6h1v2a1 1 0 0 0 2 0zm12 0a2 2 0 0 1-2 2 2 2 0 0 1-2-2V5h1v3a1 1 0 0 0 2 0V6h1z"/></svg></span>';

    $showBars = ($resultType === 'team1_won' || $resultType === 'team2_won');
    $maxScore = max($team1['score'], $team2['score'], 1);

    $row = function (array $team, bool $isWinner) use ($avatar, $trophySvg, $showBars, $maxScore): string {
        $pct = $showBars ? max(6, round(($team['score'] / $maxScore) * 100)) : 0;
        $barClass = $isWinner ? 'result-bar-fill--winner' : 'result-bar-fill--loser';

        $html = '<div>';
        $html .= '<div class="result-team-row">';
        $html .= '<span class="match-team-avatar">' . $avatar($team) . '</span>';
        $html .= '<span class="result-team-name">' . htmlspecialchars($team['code'] ?: $team['name']) . ($isWinner ? $trophySvg : '') . '</span>';
        $html .= '<span class="result-score">' . $team['score'] . '/' . $team['wickets'] . '<span class="result-score-overs">(' . $team['overs'] . ' ov)</span></span>';
        $html .= '</div>';
        if ($showBars) {
            $html .= '<div class="result-bar-track"><div class="result-bar-fill ' . $barClass . '" style="width:' . $pct . '%;"></div></div>';
        }
        $html .= '</div>';
        return $html;
    };

    $html = '<div class="result-teams">';
    $html .= $row($team1, $team1['id'] === $winnerTeamId);
    $html .= $row($team2, $team2['id'] === $winnerTeamId);
    $html .= '</div>';
    return $html;
}