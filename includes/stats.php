<?php
/**
 * Returns the last $days days as ['Y-m-d' => 0, ...] so trend queries can
 * fill in zero-count days that have no rows, rather than skipping them.
 */
function emptyDayBuckets(int $days): array {
    $buckets = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $buckets[$date] = 0;
    }
    return $buckets;
}

/**
 * Daily sum of completed payment amounts over the last $days days.
 */
function getPaymentsTrend(PDO $pdo, int $days = 7): array {
    $buckets = emptyDayBuckets($days);
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) AS d, SUM(amount) AS total
        FROM payments
        WHERE payment_status = 'completed'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
    ");
    $stmt->execute(['days' => $days - 1]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['d']] = (float) $row['total'];
    }
    return array_values($buckets);
}

/**
 * Daily count of new user signups over the last $days days.
 */
function getSignupsTrend(PDO $pdo, int $days = 7): array {
    $buckets = emptyDayBuckets($days);
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
    ");
    $stmt->execute(['days' => $days - 1]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['d']] = (int) $row['c'];
    }
    return array_values($buckets);
}

/**
 * Daily count of matches marked completed (based on when the result was
 * entered) over the last $days days.
 */
function getMatchesCompletedTrend(PDO $pdo, int $days = 7): array {
    $buckets = emptyDayBuckets($days);
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM match_results
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
    ");
    $stmt->execute(['days' => $days - 1]);
    foreach ($stmt->fetchAll() as $row) {
        $buckets[$row['d']] = (int) $row['c'];
    }
    return array_values($buckets);
}

/**
 * Renders a small inline SVG sparkline from an array of numeric values.
 */
function renderSparklineSvg(array $values, string $color): string {
    if (count($values) < 2 || max($values) === 0) {
        return '<svg class="stat-sparkline" viewBox="0 0 100 28" preserveAspectRatio="none"></svg>';
    }
    $max = max($values);
    $min = min($values);
    $range = ($max - $min) ?: 1;
    $count = count($values);

    $points = [];
    foreach ($values as $i => $v) {
        $x = ($i / ($count - 1)) * 100;
        $y = 26 - (($v - $min) / $range) * 22;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $pointsAttr = implode(' ', $points);

    return '<svg class="stat-sparkline" viewBox="0 0 100 28" preserveAspectRatio="none">'
        . '<polyline points="' . $pointsAttr . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}

/**
 * Renders a full stat card: label, big value, optional sparkline + trend note.
 * Pass $trendValues = null for a plain stat card with no sparkline.
 */
function renderStatCard(string $label, string $displayValue, ?array $trendValues = null, string $accentColor = 'var(--cyan)', string $trendUnit = ''): string {
    $html = '<div class="card stat-card">';
    $html .= '<p class="stat-card-label">' . htmlspecialchars($label) . '</p>';
    $html .= '<p class="stat-card-value">' . $displayValue . '</p>';

    if ($trendValues !== null && count(array_filter($trendValues)) > 0) {
        $html .= renderSparklineSvg($trendValues, $accentColor);

        $first = reset($trendValues);
        $last = end($trendValues);

        if ($first == 0 && $last > 0) {
            $html .= '<span class="stat-trend stat-trend--up">&uarr; new activity</span>';
        } elseif ($first > 0) {
            $pctChange = round((($last - $first) / $first) * 100);
            if ($pctChange > 0) {
                $html .= '<span class="stat-trend stat-trend--up">&uarr; ' . $pctChange . '% vs ' . count($trendValues) . 'd ago</span>';
            } elseif ($pctChange < 0) {
                $html .= '<span class="stat-trend stat-trend--down">&darr; ' . abs($pctChange) . '% vs ' . count($trendValues) . 'd ago</span>';
            } else {
                $html .= '<span class="stat-trend stat-trend--flat">&mdash; no change</span>';
            }
        } else {
            $html .= '<span class="stat-trend stat-trend--flat">&mdash; no change</span>';
        }
    }

    $html .= '</div>';
    return $html;
}