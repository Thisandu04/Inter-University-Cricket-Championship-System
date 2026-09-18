<?php

require_once __DIR__ . '/../config/config.php';

session_start();

/**
 * Call at the top of any page that requires the user to be logged in.
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }
}

/**
 * Call at the top of any role-restricted page.
 * Example: requireRole('admin');
 * Example: requireRole(['admin', 'coordinator']);
 */
function requireRole($roles) {
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die("Access denied. You don't have permission to view this page.");
    }
}

/**
 * Convenience helper to get the current logged-in user's info.
 */
function currentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'name'    => $_SESSION['name'],
        'role'    => $_SESSION['role'],
        'team_id' => $_SESSION['team_id'] ?? null, // set at login for team managers
    ];
}