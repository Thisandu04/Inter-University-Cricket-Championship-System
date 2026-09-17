<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stripe.php';
requireRole('team_manager');

$user = currentUser();
$sessionId = $_GET['session_id'] ?? '';

$error = '';
$verified = false;

if (empty($sessionId)) {
    $error = "Missing payment session reference.";
} else {
    $session = retrieveCheckoutSession($sessionId);

    if (isset($session['error'])) {
        $error = "Could not verify payment: " . htmlspecialchars($session['error']['message']);
    } elseif (($session['payment_status'] ?? '') === 'paid') {
        $teamId = (int) ($session['client_reference_id'] ?? 0);

        $stmt = $pdo->prepare("
            UPDATE payments
            SET payment_status = 'completed', transaction_id = ?
            WHERE team_id = ? AND transaction_id = ?
        ");
        $stmt->execute([$session['payment_intent'] ?? $sessionId, $teamId, $sessionId]);

        $verified = true;
    } else {
        $error = "Payment was not completed successfully.";
    }
}

$pageTitle = 'Payment Result';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <h1>Payment Result</h1>

    <div class="form-card">
        <?php if ($verified): ?>
            <div class="success-msg">
                Payment completed successfully! Your team's registration fee has been recorded.
            </div>
            <a href="receipt.php" class="btn" style="margin-top:1rem;">View Receipt</a>
            <a href="dashboard.php" class="btn btn--outline" style="margin-top:1rem;">Back to Dashboard</a>
        <?php else: ?>
            <div class="error-msg"><?= $error ?></div>
            <a href="payment.php" class="btn btn--outline" style="margin-top:1rem;">Try Again</a>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>