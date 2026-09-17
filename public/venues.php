<?php
require_once __DIR__ . '/../config/db.php';
$pageTitle = 'Venues';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("
    SELECT venue_id, name, city
    FROM venues
    WHERE is_active = 1
    ORDER BY name ASC
");
$venues = $stmt->fetchAll();

// Count upcoming + completed matches per venue for a small stat
$matchCountStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS upcoming_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count
    FROM matches
    WHERE venue_id = ?
");
?>

<section class="section">
    <h1>Venues</h1>

    <?php if (empty($venues)): ?>
        <div class="empty-state">No venues added yet.</div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($venues as $v): ?>
                <?php
                $matchCountStmt->execute([$v['venue_id']]);
                $counts = $matchCountStmt->fetch();
                ?>
                <div class="card">
                    <div class="venue-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/>
                            <circle cx="12" cy="10" r="2.5"/>
                        </svg>
                    </div>
                    <h3 class="card-title"><?= htmlspecialchars($v['name']) ?></h3>
                    <p class="card-meta"><?= htmlspecialchars($v['city']) ?></p>
                    <div class="venue-stats">
                        <span class="status-tag status-tag--scheduled"><?= (int) $counts['upcoming_count'] ?> upcoming</span>
                        <span class="status-tag status-tag--completed"><?= (int) $counts['completed_count'] ?> completed</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>