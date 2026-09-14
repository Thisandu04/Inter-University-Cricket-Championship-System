<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('team_manager');

$user = currentUser();

$teamStmt = $pdo->prepare("SELECT * FROM teams WHERE manager_user_id = ?");
$teamStmt->execute([$user['user_id']]);
$team = $teamStmt->fetch();

if (!$team) {
    header("Location: register_team.php");
    exit;
}

$settings = $pdo->query("SELECT max_players_per_team FROM system_settings WHERE setting_id = 1")->fetch();
$maxPlayers = $settings['max_players_per_team'];

$error = '';
$success = '';

// Add player
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM players WHERE team_id = ? AND is_active = 1");
    $countStmt->execute([$team['team_id']]);
    $currentCount = $countStmt->fetch()['c'];

    $contactNo = trim($_POST['contact_no']);

    if ($currentCount >= $maxPlayers) {
        $error = "You've reached the maximum of $maxPlayers players.";
    } elseif (!preg_match('/^(0|\+94)?7[0-9]{8}$/', $contactNo)) {
        $error = "Please enter a valid phone number (e.g. 0771234567 or +94771234567).";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO players (team_id, first_name, last_name, contact_no, date_of_birth, playing_role)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $team['team_id'],
            trim($_POST['first_name']),
            trim($_POST['last_name']),
            $contactNo,
            $_POST['date_of_birth'],
            $_POST['playing_role'],
        ]);
        $success = "Player added.";
    }
}

// Delete player
if (isset($_GET['delete'])) {
    $delStmt = $pdo->prepare("UPDATE players SET is_active = 0 WHERE player_id = ? AND team_id = ?");
    $delStmt->execute([(int) $_GET['delete'], $team['team_id']]);
    header("Location: manage_players.php");
    exit;
}

$playersStmt = $pdo->prepare("
    SELECT * FROM players WHERE team_id = ? AND is_active = 1 ORDER BY created_at ASC
");
$playersStmt->execute([$team['team_id']]);
$players = $playersStmt->fetchAll();

$pageTitle = 'Manage Players';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section center-page">
    <h1>Manage Players</h1>
    <p class="text-muted"><?= count($players) ?> / <?= $maxPlayers ?> players</p>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (count($players) < $maxPlayers): ?>
    <div class="form-card" style="max-width:600px;">
        <h3 class="card-title">Add Player</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="field">
                <label>First Name</label>
                <input type="text" name="first_name" required>
            </div>
            <div class="field">
                <label>Last Name</label>
                <input type="text" name="last_name" required>
            </div>
            <div class="field">
                <label>Contact Number</label>
                <input type="tel" name="contact_no" required
                    pattern="^(0|\+94)?7[0-9]{8}$"
                    maxlength="13"
                    placeholder="0771234567"
                    title="Enter a valid phone number, e.g. 0771234567 or +94771234567">
            </div>
            <div class="field">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" required>
            </div>
            <div class="field">
                <label>Playing Role</label>
                <select name="playing_role" required>
                    <option value="Batsman">Batsman</option>
                    <option value="Bowler">Bowler</option>
                    <option value="All-rounder">All-rounder</option>
                    <option value="Wicketkeeper">Wicketkeeper</option>
                </select>
            </div>
            <button type="submit" class="btn">Add Player</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="table-wrap" style="margin-top:2rem;">
        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Contact</th><th>DOB</th><th>Role</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($players as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= htmlspecialchars($p['contact_no']) ?></td>
                        <td><?= date('d M Y', strtotime($p['date_of_birth'])) ?></td>
                        <td><?= htmlspecialchars($p['playing_role']) ?></td>
                        <td>
                            <a href="?delete=<?= $p['player_id'] ?>"
                               onclick="return confirm('Remove this player?');"
                               class="text-muted">Remove</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>