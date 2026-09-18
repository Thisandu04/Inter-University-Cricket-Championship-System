<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/points.php';
requireRole('coordinator');

$user = currentUser();
$error = '';
$success = '';

// Matches eligible for result entry: scheduled or ongoing, not yet completed
$eligibleMatches = $pdo->query("
    SELECT m.match_id, m.scheduled_date, t1.team_id AS team1_id, t1.team_name AS team1_name,
           t2.team_id AS team2_id, t2.team_name AS team2_name
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    WHERE m.status IN ('scheduled', 'ongoing')
    ORDER BY m.scheduled_date ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matchId       = (int) $_POST['match_id'];
    $team1Score    = (int) $_POST['team1_score'];
    $team1Wickets  = (int) $_POST['team1_wickets'];
    $team1OversRaw = trim($_POST['team1_overs']);
    $team2Score    = (int) $_POST['team2_score'];
    $team2Wickets  = (int) $_POST['team2_wickets'];
    $team2OversRaw = trim($_POST['team2_overs']);
    $resultType    = $_POST['result_type'];
    $marginType    = $_POST['win_margin_type'] ?: null;
    $marginValue   = $_POST['win_margin_value'] !== '' ? (int) $_POST['win_margin_value'] : null;
    $tossWinner    = $_POST['toss_winner_team_id'] ?: null;
    $tossDecision  = $_POST['toss_decision'] ?: null;

    // Overs must be 0–20, and the digit after the point (balls) must be 0–5,
    // since an over only has 6 balls (e.g. 19.4 is valid, 19.9 is not).
    $oversPattern = '/^((1?[0-9])(\.[0-5])?|20(\.0)?)$/';

    if (!preg_match($oversPattern, $team1OversRaw) || !preg_match($oversPattern, $team2OversRaw)) {
        $error = "Overs must be between 0 and 20, with the ball digit between 0 and 5 (e.g. 19.4, not 19.9).";
    } else {
        $team1Overs = (float) $team1OversRaw;
        $team2Overs = (float) $team2OversRaw;

        // Look up the match to know team1_id / team2_id
        $mStmt = $pdo->prepare("SELECT team1_id, team2_id FROM matches WHERE match_id = ?");
        $mStmt->execute([$matchId]);
        $match = $mStmt->fetch();

        if (!$match) {
            $error = "Match not found.";
        } else {
            $winnerId = null;
            if ($resultType === 'team1_won') {
                $winnerId = $match['team1_id'];
            } elseif ($resultType === 'team2_won') {
                $winnerId = $match['team2_id'];
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO match_results
                        (match_id, toss_winner_team_id, toss_decision,
                         team1_score, team1_wickets, team1_overs,
                         team2_score, team2_wickets, team2_overs,
                         result_type, winner_team_id, win_margin_type, win_margin_value, entered_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $matchId, $tossWinner, $tossDecision,
                    $team1Score, $team1Wickets, $team1Overs,
                    $team2Score, $team2Wickets, $team2Overs,
                    $resultType, $winnerId, $marginType, $marginValue, $user['user_id'],
                ]);

                $updateMatch = $pdo->prepare("UPDATE matches SET status = 'completed' WHERE match_id = ?");
                $updateMatch->execute([$matchId]);

                // Recalculate points table for both teams involved
                recalcPointsTable($pdo, (int) $match['team1_id']);
                recalcPointsTable($pdo, (int) $match['team2_id']);

                $pdo->commit();
                $success = "Result saved and points table updated.";

                // Refresh eligible matches list
                $eligibleMatches = $pdo->query("
                    SELECT m.match_id, m.scheduled_date, t1.team_id AS team1_id, t1.team_name AS team1_name,
                           t2.team_id AS team2_id, t2.team_name AS team2_name
                    FROM matches m
                    JOIN teams t1 ON t1.team_id = m.team1_id
                    JOIN teams t2 ON t2.team_id = m.team2_id
                    WHERE m.status IN ('scheduled', 'ongoing')
                    ORDER BY m.scheduled_date ASC
                ")->fetchAll();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Could not save result. Please try again.";
            }
        }
    }
}

$pageTitle = 'Enter Result';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section center-page">
    <h1>Enter Match Result</h1>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (empty($eligibleMatches)): ?>
        <div class="empty-state">No matches awaiting a result.</div>
    <?php else: ?>
        <div class="form-card" style="max-width:640px;">
            <form method="POST" id="resultForm">
                <div class="field">
                    <label>Match</label>
                    <select name="match_id" id="matchSelect" required>
                        <?php foreach ($eligibleMatches as $m): ?>
                            <option value="<?= $m['match_id'] ?>"
                                    data-team1="<?= htmlspecialchars($m['team1_name']) ?>"
                                    data-team2="<?= htmlspecialchars($m['team2_name']) ?>"
                                    data-team1id="<?= $m['team1_id'] ?>"
                                    data-team2id="<?= $m['team2_id'] ?>">
                                <?= htmlspecialchars($m['team1_name']) ?> vs <?= htmlspecialchars($m['team2_name']) ?>
                                (<?= date('d M Y', strtotime($m['scheduled_date'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Toss Winner</label>
                    <select name="toss_winner_team_id" id="tossWinnerSelect">
                        <option value="">-- Not recorded --</option>
                    </select>
                </div>
                <div class="field">
                    <label>Toss Decision</label>
                    <select name="toss_decision" id="tossDecisionSelect">
                        <option value="">-- N/A --</option>
                        <option value="bat">Bat</option>
                        <option value="bowl">Bowl</option>
                    </select>
                </div>

                <h3 class="card-title" id="team1Label" style="margin-top:1rem;">Team 1</h3>
                <div class="field"><label>Score (runs)</label><input type="number" id="team1_score" name="team1_score" min="0" required></div>
                <div class="field"><label>Wickets</label><input type="number" id="team1_wickets" name="team1_wickets" min="0" max="10" required></div>
                <div class="field">
                    <label>Overs (e.g. 19.4 &mdash; max 20.0, balls 0-5 only)</label>
                    <input type="text" inputmode="decimal" name="team1_overs" required
                           pattern="^((1?[0-9])(\.[0-5])?|20(\.0)?)$"
                           placeholder="20.0"
                           title="Enter overs as whole.balls, e.g. 19.4 — max 20.0, ball digit 0-5">
                </div>

                <h3 class="card-title" id="team2Label" style="margin-top:1rem;">Team 2</h3>
                <div class="field"><label>Score (runs)</label><input type="number" id="team2_score" name="team2_score" min="0" required></div>
                <div class="field"><label>Wickets</label><input type="number" id="team2_wickets" name="team2_wickets" min="0" max="10" required></div>
                <div class="field">
                    <label>Overs (e.g. 20.0 &mdash; max 20.0, balls 0-5 only)</label>
                    <input type="text" inputmode="decimal" name="team2_overs" required
                           pattern="^((1?[0-9])(\.[0-5])?|20(\.0)?)$"
                           placeholder="20.0"
                           title="Enter overs as whole.balls, e.g. 19.4 — max 20.0, ball digit 0-5">
                </div>

                <div class="field" style="margin-top:1rem;">
                    <label>Result</label>
                    <select name="result_type" id="resultTypeSelect" required>
                        <option value="team1_won">Loading...</option>
                        <option value="team2_won">Loading...</option>
                        <option value="tie">Tie</option>
                        <option value="no_result">No Result</option>
                    </select>
                </div>

                <div id="marginFields">
                    <div class="field">
                        <label>Win Margin Type</label>
                        <select name="win_margin_type" id="marginTypeSelect">
                            <option value="runs">Runs</option>
                            <option value="wickets">Wickets</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Win Margin Value</label>
                        <input type="number" name="win_margin_value" id="marginValueInput" min="0">
                    </div>
                    <p class="text-muted" id="marginAutoNote" style="font-size:0.82rem; margin-top:-0.6rem;"></p>
                </div>

                <button type="submit" class="btn">Save Result</button>
            </form>
        </div>
    <?php endif; ?>
</section>

<script>
const matchSelect     = document.getElementById('matchSelect');
const tossSelect      = document.getElementById('tossWinnerSelect');
const tossDecision    = document.getElementById('tossDecisionSelect');
const resultSelect    = document.getElementById('resultTypeSelect');
const marginTypeSel   = document.getElementById('marginTypeSelect');
const marginValueIn   = document.getElementById('marginValueInput');
const marginAutoNote  = document.getElementById('marginAutoNote');
const marginFields    = document.getElementById('marginFields');
const team1Label      = document.getElementById('team1Label');
const team2Label      = document.getElementById('team2Label');

const team1ScoreIn   = document.getElementById('team1_score');
const team1WicketsIn = document.getElementById('team1_wickets');
const team2ScoreIn   = document.getElementById('team2_score');
const team2WicketsIn = document.getElementById('team2_wickets');

function syncMatchFields() {
    const opt = matchSelect.options[matchSelect.selectedIndex];
    if (!opt) return;
    const t1 = opt.dataset.team1, t2 = opt.dataset.team2;
    const t1id = opt.dataset.team1id, t2id = opt.dataset.team2id;

    team1Label.textContent = t1;
    team2Label.textContent = t2;

    tossSelect.innerHTML = '<option value="">-- Not recorded --</option>'
        + `<option value="${t1id}">${t1}</option>`
        + `<option value="${t2id}">${t2}</option>`;

    const previousValue = resultSelect.value;
    resultSelect.innerHTML =
          `<option value="team1_won">${t1} won</option>`
        + `<option value="team2_won">${t2} won</option>`
        + `<option value="tie">Tie</option>`
        + `<option value="no_result">No Result</option>`;
    if (['team1_won', 'team2_won', 'tie', 'no_result'].includes(previousValue)) {
        resultSelect.value = previousValue;
    }

    autoCalcResult();
}

/**
 * Works out who batted first from toss winner + decision, compares final
 * scores, and auto-fills the Result, Win Margin Type, and Win Margin Value
 * fields. Only runs once toss winner, toss decision, and both scores/wickets
 * are filled in — otherwise leaves everything for manual entry.
 */
function autoCalcResult() {
    const opt = matchSelect.options[matchSelect.selectedIndex];
    if (!opt) return;

    const t1id = opt.dataset.team1id;
    const t2id = opt.dataset.team2id;

    const tossWinnerId = tossSelect.value;
    const decision = tossDecision.value;

    const s1 = team1ScoreIn.value, w1 = team1WicketsIn.value;
    const s2 = team2ScoreIn.value, w2 = team2WicketsIn.value;

    // Need toss info + both scores before we can auto-calculate anything
    if (!tossWinnerId || !decision || s1 === '' || s2 === '' || w1 === '' || w2 === '') {
        marginAutoNote.textContent = '';
        syncMarginVisibility();
        return;
    }

    const score1 = parseInt(s1, 10), wickets1 = parseInt(w1, 10);
    const score2 = parseInt(s2, 10), wickets2 = parseInt(w2, 10);

    // Work out who batted first
    let firstBattingId;
    if (decision === 'bat') {
        firstBattingId = tossWinnerId;
    } else {
        firstBattingId = (tossWinnerId === t1id) ? t2id : t1id;
    }
    const chasingId = (firstBattingId === t1id) ? t2id : t1id;

    const firstScore   = (firstBattingId === t1id) ? score1 : score2;
    const chaseScore   = (chasingId === t1id) ? score1 : score2;
    const chaseWickets = (chasingId === t1id) ? wickets1 : wickets2;

    if (chaseScore === firstScore) {
        resultSelect.value = 'tie';
        marginAutoNote.textContent = '';
    } else if (chaseScore > firstScore) {
        // Chasing team won, by wickets in hand
        resultSelect.value = (chasingId === t1id) ? 'team1_won' : 'team2_won';
        marginTypeSel.value = 'wickets';
        marginValueIn.value = 10 - chaseWickets;
        marginAutoNote.textContent = 'Auto-calculated from scores and toss info — adjust if needed.';
    } else {
        // Team batting first won, by runs
        resultSelect.value = (firstBattingId === t1id) ? 'team1_won' : 'team2_won';
        marginTypeSel.value = 'runs';
        marginValueIn.value = firstScore - chaseScore;
        marginAutoNote.textContent = 'Auto-calculated from scores and toss info — adjust if needed.';
    }

    syncMarginVisibility();
}

function syncMarginVisibility() {
    marginFields.style.display = (resultSelect.value === 'tie' || resultSelect.value === 'no_result') ? 'none' : 'block';
}

matchSelect.addEventListener('change', syncMatchFields);
tossSelect.addEventListener('change', autoCalcResult);
tossDecision.addEventListener('change', autoCalcResult);
[team1ScoreIn, team1WicketsIn, team2ScoreIn, team2WicketsIn].forEach(input => {
    input.addEventListener('input', autoCalcResult);
});
resultSelect.addEventListener('change', syncMarginVisibility);

syncMatchFields(); // run once on page load
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>