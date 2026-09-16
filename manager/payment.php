<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stripe.php';
requireRole('team_manager');

$user = currentUser();

$teamStmt = $pdo->prepare("SELECT * FROM teams WHERE manager_user_id = ?");
$teamStmt->execute([$user['user_id']]);
$team = $teamStmt->fetch();

if (!$team) {
    header("Location: register_team.php");
    exit;
}

$settings = $pdo->query("SELECT registration_fee FROM system_settings WHERE setting_id = 1")->fetch();
$fee = (float) $settings['registration_fee'];

$paymentStmt = $pdo->prepare("SELECT * FROM payments WHERE team_id = ?");
$paymentStmt->execute([$team['team_id']]);
$payment = $paymentStmt->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_checkout') {
    $session = createCheckoutSession($team['team_id'], $team['team_name'], $fee);

    if (isset($session['error'])) {
        $error = "Payment could not be started: " . htmlspecialchars($session['error']['message']);
    } elseif (!empty($session['url'])) {
        $upsert = $pdo->prepare("
            INSERT INTO payments (team_id, amount, payment_method, gateway_name, transaction_id, payment_status)
            VALUES (?, ?, 'card', 'stripe', ?, 'pending')
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount), transaction_id = VALUES(transaction_id), payment_status = 'pending'
        ");
        $upsert->execute([$team['team_id'], $fee, $session['id']]);

        header("Location: " . $session['url']);
        exit;
    } else {
        $error = "Unexpected response from payment provider. Please try again.";
    }
}

$pageTitle = 'Payment';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1>Registration Fee Payment</h1>

    <div class="form-card">
        <p><strong>Amount due:</strong> LKR <?= number_format($fee, 2) ?></p>

        <?php if ($error): ?>
            <div class="error-msg" style="margin-top:1rem;"><?= $error ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="error-msg" style="margin-top:1rem;">Payment was cancelled. You can try again below.</div>
        <?php endif; ?>

        <?php if ($payment && $payment['payment_status'] === 'completed'): ?>
            <div class="success-msg" style="margin-top:1rem;">
                Payment completed on <?= date('d M Y', strtotime($payment['updated_at'])) ?>.
                Transaction ref: <?= htmlspecialchars($payment['transaction_id']) ?>
            </div>
            <a href="receipt.php" class="btn" style="margin-top:1rem;">View / Download Receipt</a>
        <?php else: ?>
            <p class="text-muted" style="margin-top:1rem;">
                You’ll be redirected to Stripe’s secure checkout page.
            </p>
            <form method="POST" style="margin-top:1rem;">
                <input type="hidden" name="action" value="start_checkout">
                <button type="submit" class="btn">Pay with Stripe</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>