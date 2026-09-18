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

$playersStmt = $pdo->prepare("SELECT player_id, first_name, last_name FROM players WHERE team_id = ? AND is_active = 1");
$playersStmt->execute([$team['team_id']]);
$players = $playersStmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamName    = trim($_POST['team_name']);
    $captainId   = $_POST['captain_player_id'] ?: null;
    $viceCapId   = $_POST['vice_captain_player_id'] ?: null;

    if ($captainId && $viceCapId && $captainId === $viceCapId) {
        $error = "Captain and Vice-Captain must be different players.";
    } else {
        $logoPath = $team['logo_path'];

        // Handle logo upload if provided
        if (!empty($_FILES['logo']['name'])) {
            if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                // PHP's own upload_max_filesize / post_max_size (php.ini)
                // can reject the file before our code runs at all — make
                // sure that case is reported instead of silently ignored.
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE   => "That file is larger than this server's upload limit.",
                    UPLOAD_ERR_FORM_SIZE  => "That file is larger than allowed.",
                    UPLOAD_ERR_PARTIAL    => "The file was only partially uploaded. Please try again.",
                    UPLOAD_ERR_NO_TMP_DIR => "Server upload error (no temp directory). Contact support.",
                    UPLOAD_ERR_CANT_WRITE => "Server upload error (write failed). Contact support.",
                    UPLOAD_ERR_EXTENSION  => "The upload was blocked by a server extension.",
                ];
                $error = $uploadErrors[$_FILES['logo']['error']] ?? "Logo upload failed. Please try again.";
            } else {
                $maxBytes = 2 * 1024 * 1024; // 2MB
                $allowedMimes = [
                    'image/png'  => 'png',
                    'image/jpeg' => 'jpg',
                ];

                if ($_FILES['logo']['size'] > $maxBytes) {
                    $error = "Logo must be smaller than 2MB.";
                } else {
                    // Verify the upload is actually a valid image (not just an
                    // extension rename) and read its real type from the file
                    // contents, not the client-supplied name/extension.
                    // SVG is intentionally not accepted: it can embed <script>
                    // tags and would run as stored XSS when served from our origin.
                    $imageInfo = @getimagesize($_FILES['logo']['tmp_name']);

                    if ($imageInfo === false || !isset($allowedMimes[$imageInfo['mime']])) {
                        $error = "Logo must be a valid PNG or JPG image.";
                    } else {
                        $ext = $allowedMimes[$imageInfo['mime']];
                        $filename = 'team_' . $team['team_id'] . '_' . time() . '.' . $ext;
                        $destDir = __DIR__ . '/../public/assets/logos/';
                        move_uploaded_file($_FILES['logo']['tmp_name'], $destDir . $filename);
                        $logoPath = 'assets/logos/' . $filename;
                    }
                }
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("
                UPDATE teams
                SET team_name = ?, captain_player_id = ?, vice_captain_player_id = ?, logo_path = ?
                WHERE team_id = ?
            ");
            $stmt->execute([$teamName, $captainId, $viceCapId, $logoPath, $team['team_id']]);
            $success = "Team details updated.";
            $team['team_name'] = $teamName;
            $team['captain_player_id'] = $captainId;
            $team['vice_captain_player_id'] = $viceCapId;
            $team['logo_path'] = $logoPath;
        }
    }
}

$pageTitle = 'Team Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section center-page">
    <h1>Team Settings</h1>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data">
            <div class="field">
                <label>Team Name</label>
                <input type="text" name="team_name" value="<?= htmlspecialchars($team['team_name']) ?>" required>
            </div>

            <div class="field">
                <label>Captain</label>
                <select name="captain_player_id">
                    <option value="">-- None --</option>
                    <?php foreach ($players as $p): ?>
                        <option value="<?= $p['player_id'] ?>" <?= $team['captain_player_id'] == $p['player_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Vice-Captain</label>
                <select name="vice_captain_player_id">
                    <option value="">-- None --</option>
                    <?php foreach ($players as $p): ?>
                        <option value="<?= $p['player_id'] ?>" <?= $team['vice_captain_player_id'] == $p['player_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Team Logo</label>
                <?php if ($team['logo_path']): ?>
                    <img src="<?= BASE_URL . '/public/' . htmlspecialchars($team['logo_path']) ?>" style="width:60px; height:60px; border-radius:50%; margin-bottom:0.6rem; display:block;">
                <?php endif; ?>
                <input type="file" name="logo" accept=".png,.jpg,.jpeg">
                <p class="text-muted" style="font-size:0.8rem; margin-top:0.3rem;">PNG or JPG only, max 2MB.</p>
            </div>

            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>