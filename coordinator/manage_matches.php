<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('coordinator');

$user = currentUser();
$error = '';
$success = '';

// Create match
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $team1 = (int) $_POST['team1_id'];
    $team2 = (int) $_POST['team2_id'];
    $venue = (int) $_POST['venue_id'];
    $date  = $_POST['scheduled_date'];
    $time  = $_POST['scheduled_time'];
    $stage = $_POST['stage'];

    if ($team1 === $team2) {
        $error = "A team cannot play against itself.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO matches (team1_id, team2_id, venue_id, scheduled_date, scheduled_time, stage, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$team1, $team2, $venue, $date, $time, $stage, $user['user_id']]);
            $success = "Match scheduled.";
        } catch (PDOException $e) {
            // Unique index uq_venue_datetime catches double-booking
            if ($e->getCode() === '23000') {
                $error = "That venue is already booked at this date and time.";
            } else {
                $error = "Could not schedule the match.";
            }
        }
    }
}

// Update match status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $matchId = (int) $_POST['match_id'];
    $newStatus = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE matches SET status = ? WHERE match_id = ?");
    $stmt->execute([$newStatus, $matchId]);
    header("Location: manage_matches.php");
    exit;
}

$teams  = $pdo->query("SELECT team_id, team_name FROM teams WHERE approval_status = 'approved' ORDER BY team_name")->fetchAll();
$venues = $pdo->query("SELECT venue_id, name, city FROM venues WHERE is_active = 1 ORDER BY name")->fetchAll();

$matches = $pdo->query("
    SELECT m.*, t1.team_name AS team1_name, t2.team_name AS team2_name, v.name AS venue_name
    FROM matches m
    JOIN teams t1 ON t1.team_id = m.team1_id
    JOIN teams t2 ON t2.team_id = m.team2_id
    JOIN venues v ON v.venue_id = m.venue_id
    ORDER BY m.scheduled_date DESC, m.scheduled_time DESC
")->fetchAll();

$pageTitle = 'Manage Matches';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section center-page">
    <h1>Manage Matches</h1>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (count($teams) < 2): ?>
        <div class="empty-state">You need at least 2 approved teams before scheduling matches.</div>
    <?php elseif (empty($venues)): ?>
        <div class="empty-state">Add a venue before scheduling matches.</div>
    <?php else: ?>
        <div class="form-card" style="max-width:520px;">
            <h3 class="card-title">Schedule Match</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="field">
                    <label>Team 1</label>
                    <select name="team1_id" required>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?= $t['team_id'] ?>"><?= htmlspecialchars($t['team_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Team 2</label>
                    <select name="team2_id" required>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?= $t['team_id'] ?>"><?= htmlspecialchars($t['team_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Venue</label>
                    <select name="venue_id" required>
                        <?php foreach ($venues as $v): ?>
                            <option value="<?= $v['venue_id'] ?>"><?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['city']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Date</label>
                    <input type="date" name="scheduled_date" required>
                </div>
                <div class="field">
                    <label>Time</label>
                    <input type="time" name="scheduled_time" required>
                </div>
                <div class="field">
                    <label>Stage</label>
                    <select name="stage" required>
                        <option value="group">Group Stage</option>
                        <option value="semi_final">Semi-Final</option>
                        <option value="final">Final</option>
                    </select>
                </div>
                <button type="submit" class="btn">Schedule Match</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="table-wrap" style="margin-top:2rem;">
        <table class="data-table">
            <thead>
                <tr><th>Match</th><th>Venue</th><th>Date/Time</th><th>Stage</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['team1_name']) ?> vs <?= htmlspecialchars($m['team2_name']) ?></td>
                        <td><?= htmlspecialchars($m['venue_name']) ?></td>
                        <td><?= date('d M Y', strtotime($m['scheduled_date'])) ?>, <?= date('g:i A', strtotime($m['scheduled_time'])) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $m['stage'])) ?></td>
                        <td><span class="status-tag status-tag--<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
                        <td>
                            <?php if ($m['status'] !== 'completed'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="scheduled" <?= $m['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                        <option value="ongoing" <?= $m['status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                        <option value="cancelled" <?= $m['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>