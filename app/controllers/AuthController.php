<?php
/**
 * AuthController — register / login / logout.
 */
class AuthController extends Controller
{
    public function index(): void { $this->redirect('auth/login'); }

    public function login(): void
    {
        if (isLoggedIn()) $this->redirect('game');
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $login = trim($_POST['login'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $user  = $this->model('User');
            $row   = $user->findByLogin($login);
            if ($row && password_verify($pass, $row['password_hash'])) {
                $_SESSION['user_id']  = (int) $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['avatar']   = $row['avatar'];
                $user->touchLogin((int) $row['id']);
                $this->redirect('game');
            }
            $error = 'Invalid username or password.';
        }
        $this->view('auth/login', ['error' => $error, 'title' => 'Sign In'], 'blank');
    }

    public function register(): void
    {
        if (isLoggedIn()) $this->redirect('game');
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $pass     = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm'] ?? '';

            if (strlen($username) < 3)            $error = 'Username must be at least 3 characters.';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email.';
            elseif (strlen($pass) < 6)            $error = 'Password must be at least 6 characters.';
            elseif ($pass !== $confirm)           $error = 'Passwords do not match.';
            else {
                $user = $this->model('User');
                if ($user->usernameTaken($username, $email)) {
                    $error = 'That username or email is already taken.';
                } else {
                    $id = $user->register($username, $email, $pass);
                    $_SESSION['user_id']  = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['avatar']   = 'a1';
                    $this->redirect('game');
                }
            }
        }
        $this->view('auth/register', ['error' => $error, 'title' => 'Create Account'], 'blank');
    }

    public function logout(): void
    {
        // Clear login state fully, then return to the Opening Screen (not the login page).
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        $this->redirect('');
    }
}
