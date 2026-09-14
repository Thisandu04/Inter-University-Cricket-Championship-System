<?php
require_once _DIR_ . '/auth.php';
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function () {
            var saved = localStorage.getItem('iuct-theme');
            document.documentElement.setAttribute('data-theme', saved || 'dark');
        })();
    </script>
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.46.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/style.css">
</head>
<body>
    <a href="#mainContent" class="skip-link">Skip to main content</a>

    <header class="site-header">
        <div class="header-inner">
                <a href="<?= BASE_URL ?>/public/index.php" class="brand">
                    <img src="<?= BASE_URL ?>/public/assets/logos/site-logo.png" alt="IUCT" class="brand-logo">
                    <span class="brand-full">Inter-University Cricket Tournament</span>
                </a>

            <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>

            <nav class="site-nav" id="siteNav">
                <a href="<?= BASE_URL ?>/public/index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a>
                <a href="<?= BASE_URL ?>/public/schedule.php" class="<?= $currentPage === 'schedule.php' ? 'active' : '' ?>">Schedule</a>
                <a href="<?= BASE_URL ?>/public/results.php" class="<?= $currentPage === 'results.php' ? 'active' : '' ?>">Results</a>
                <a href="<?= BASE_URL ?>/public/teams.php" class="<?= $currentPage === 'teams.php' ? 'active' : '' ?>">Teams</a>
                <a href="<?= BASE_URL ?>/public/venues.php" class="<?= $currentPage === 'venues.php' ? 'active' : '' ?>">Venues</a>

                <button id="themeToggle" class="theme-toggle" aria-label="Toggle light/dark theme" title="Toggle theme">
                    <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                    </svg>
                    <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                    </svg>
                </button>

                <span class="nav-divider"></span>

                <?php if (!$user): ?>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="nav-cta">Login</a>
                <?php else: ?>
                    <?php
                    $dashboardLink = [
                        'admin'        => '/admin/dashboard.php',
                        'coordinator'  => '/coordinator/dashboard.php',
                        'team_manager' => '/manager/dashboard.php',
                    ][$user['role']];
                    ?>
                    <a href="<?= BASE_URL . $dashboardLink ?>">Dashboard</a>
                    <span class="nav-user">Hi, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
                    <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-cta nav-cta--muted">Logout</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="site-main" id="mainContent">