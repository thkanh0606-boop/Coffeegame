<?php
/**
 * Global helper functions available everywhere.
 */

/** Build a full URL to an app route. url('game') => BASE_URL/index.php?url=game */
function url(string $route = ''): string
{
    $route = ltrim($route, '/');
    return BASE_URL . '/index.php' . ($route !== '' ? '?url=' . $route : '');
}

/** Build an asset URL (css/js/images under /public). */
function asset(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** HTML-escape shortcut. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Current logged-in user id or null. */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}
