<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('team_manager');

$user = currentUser();

// Redirect if they already have a team
$check = $pdo->prepare("SELECT team_id FROM teams WHERE manager_user_id = ?");
$check->execute([$user['user_id']]);
if ($check->fetch()) {
    header("Location: dashboard.php");
    exit;
}

$settings = $pdo->query("SELECT * FROM system_settings WHERE setting_id = 1")->fetch();
$deadlinePassed = $settings['registration_deadline'] && strtotime($settings['registration_deadline']) < time();

$error = '';

if (!$deadlinePassed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $universityName = trim($_POST['university_name']);
    $teamName       = trim($_POST['team_name']);

    // Check university not already registered
    $dup = $pdo->prepare("SELECT team_id FROM teams WHERE university_name = ?");
    $dup->execute([$universityName]);

    // Check team limit
    $countStmt = $pdo->query("SELECT COUNT(*) AS c FROM teams");
    $teamCount = $countStmt->fetch()['c'];

    if ($dup->fetch()) {
        $error = "This university already has a registered team.";
    } elseif ($teamCount >= $settings['max_teams']) {
        $error = "The maximum number of teams (" . $settings['max_teams'] . ") has been reached.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO teams (manager_user_id, university_name, team_name, approval_status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$user['user_id'], $universityName, $teamName]);

        header("Location: dashboard.php?registered=1");
        exit;
    }
}

$pageTitle = 'Register Team';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1>Register Your Team</h1>

    <?php if ($deadlinePassed): ?>
        <div class="error-msg">
            Registration closed on <?= date('d M Y', strtotime($settings['registration_deadline'])) ?>.
        </div>
    <?php else: ?>
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="form-card">
            <form method="POST">
                <div class="field">
                    <label for="university_name">University Name</label>
                    <input type="text" id="university_name" name="university_name" required>
                </div>
                <div class="field">
                    <label for="team_name">Team Name</label>
                    <input type="text" id="team_name" name="team_name" required>
                </div>
                <button type="submit" class="btn">Submit for Approval</button>
            </form>
        </div>
        <p class="text-muted" style="margin-top:1rem;">
            One team per university. Your team will need Admin approval, and the
            registration fee must be paid, before it appears publicly and can play matches.
        </p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>