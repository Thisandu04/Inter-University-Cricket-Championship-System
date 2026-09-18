<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('team_manager');

$user = currentUser();

$stmt = $pdo->prepare("
    SELECT t.team_name, t.university_name, p.*
    FROM teams t
    JOIN payments p ON p.team_id = t.team_id
    WHERE t.manager_user_id = ? AND p.payment_status = 'completed'
");
$stmt->execute([$user['user_id']]);
$payment = $stmt->fetch();

if (!$payment) {
    header("Location: payment.php");
    exit;
}

$invoiceNumber = 'IUCT-' . str_pad((string) $payment['payment_id'], 5, '0', STR_PAD_LEFT);
$paidDate = date('d M Y', strtotime($payment['updated_at']));

$pageTitle = 'Receipt';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="section no-print">
    <button onclick="window.print()" class="btn">Download as PDF</button>
    <p class="text-muted" style="margin-top:0.5rem; font-size:0.85rem;">
        Uses your browser's "Print to PDF" — choose that as the destination printer.
    </p>
</section>

<section class="section">
    <div class="invoice">
        <div class="invoice-header">
            <div>
                <div class="invoice-brand">IUCT</div>
                <p class="text-muted" style="font-size:0.85rem;">Inter-University Cricket Tournament</p>
            </div>
            <div class="invoice-meta">
                <p><strong>Invoice</strong> <?= htmlspecialchars($invoiceNumber) ?></p>
                <p>Date: <?= $paidDate ?></p>
            </div>
        </div>

        <div class="invoice-status">Paid</div>

        <div class="invoice-section" style="display:flex; gap:3rem; flex-wrap:wrap;">
            <div>
                <p class="invoice-label">Billed To</p>
                <p class="invoice-value"><?= htmlspecialchars($payment['team_name']) ?></p>
                <p class="invoice-value text-muted"><?= htmlspecialchars($payment['university_name']) ?></p>
            </div>
            <div>
                <p class="invoice-label">Payment Method</p>
                <p class="invoice-value"><?= ucfirst(htmlspecialchars($payment['payment_method'] ?? 'Card')) ?> via <?= ucfirst(htmlspecialchars($payment['gateway_name'] ?? 'Stripe')) ?></p>
            </div>
            <div>
                <p class="invoice-label">Transaction Reference</p>
                <p class="invoice-value"><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></p>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tournament Registration Fee &mdash; <?= htmlspecialchars($payment['team_name']) ?></td>
                    <td class="text-right">LKR <?= number_format($payment['amount'], 2) ?></td>
                </tr>
                <tr class="invoice-total-row">
                    <td>Total Paid</td>
                    <td class="text-right">LKR <?= number_format($payment['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="invoice-footer">
            <p>This receipt confirms payment of the registration fee for the Inter-University Cricket Tournament. Please retain this for your records.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>