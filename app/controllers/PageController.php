<?php
/**
 * PageController — landing / home routing.
 */
class PageController extends Controller
{
    public function index(): void
    {
        // Opening screen doubles as the Main Menu hub. Logged-in players get a
        // "Continue Playing" option (used by "Return to Main Menu"); new visitors
        // get Play / Login / Sign Up.
        $this->view('pages/landing', [
            'title'    => 'Brew Master',
            'loggedIn' => isLoggedIn(),
            'username' => $_SESSION['username'] ?? '',
        ], 'blank');
    }
}
