<?php
/**
 * Base Controller — view rendering, model loading, JSON helpers, auth guard.
 */
abstract class Controller
{
    /** Load a model class from app/models and return an instance. */
    protected function model(string $name)
    {
        require_once APP_PATH . '/models/' . $name . '.php';
        return new $name();
    }

    /** Render a view inside the main layout. */
    protected function view(string $path, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        $viewFile = APP_PATH . '/views/' . $path . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            die("View not found: {$path}");
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }
        require APP_PATH . '/views/layouts/' . $layout . '.php';
    }

    /** Emit JSON and stop. */
    protected function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /** Redirect helper (relative to BASE_URL). */
    protected function redirect(string $route = ''): void
    {
        header('Location: ' . url($route));
        exit;
    }

    /** Require an authenticated session; JSON 401 for API, redirect otherwise. */
    protected function requireAuth(bool $api = false): int
    {
        if (empty($_SESSION['user_id'])) {
            if ($api) $this->json(['ok' => false, 'error' => 'auth'], 401);
            $this->redirect('auth/login');
        }
        return (int) $_SESSION['user_id'];
    }

    protected function input(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
        return $_POST;
    }
}
