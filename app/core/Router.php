<?php
/**
 * Minimal front-controller router.
 * URL form:  index.php?url=controller/action/param1/param2
 * Defaults:  controller = Page, action = index
 */
class Router
{
    public function dispatch(): void
    {
        $url = trim($_GET['url'] ?? '', '/');
        $parts = $url === '' ? [] : explode('/', $url);

        $controllerName = !empty($parts[0]) ? ucfirst(strtolower($parts[0])) . 'Controller' : 'PageController';
        $action         = !empty($parts[1]) ? $parts[1] : 'index';
        $params         = array_slice($parts, 2);

        $file = APP_PATH . '/controllers/' . $controllerName . '.php';
        if (!file_exists($file)) {
            $this->notFound("Controller '{$controllerName}' not found");
            return;
        }
        require_once $file;
        if (!class_exists($controllerName)) {
            $this->notFound("Class '{$controllerName}' missing");
            return;
        }
        $controller = new $controllerName();
        if (!method_exists($controller, $action)) {
            $this->notFound("Action '{$action}' not found");
            return;
        }
        call_user_func_array([$controller, $action], $params);
    }

    private function notFound(string $msg): void
    {
        http_response_code(404);
        echo "<h1>404</h1><p>" . htmlspecialchars($msg) . "</p>";
    }
}
