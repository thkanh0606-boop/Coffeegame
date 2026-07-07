<?php
/**
 * Front controller — the single entry point for the whole game.
 */
session_start();

require_once __DIR__ . '/../app/config/config.php';
require_once APP_PATH . '/core/helpers.php';
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Model.php';
require_once APP_PATH . '/core/Controller.php';
require_once APP_PATH . '/core/Router.php';
require_once APP_PATH . '/views/partials/svg.php';

try {
    (new Router())->dispatch();
} catch (Throwable $ex) {
    // API requests expect JSON errors; pages get a friendly message.
    $isApi = str_starts_with(trim($_GET['url'] ?? ''), 'api/');
    if ($isApi) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
    } else {
        http_response_code(500);
        echo '<pre style="font-family:monospace;padding:24px">'
           . 'Something broke:' . "\n\n"
           . htmlspecialchars($ex->getMessage()) . "\n\n"
           . htmlspecialchars($ex->getFile()) . ':' . $ex->getLine()
           . '</pre>'
    }
}
