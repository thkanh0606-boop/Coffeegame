<?php
/**
 * GameController — renders the main game screens (server-side shell).
 * Live data is hydrated via the API on the client.
 */
class GameController extends Controller
{
    private function gs(): GameState { return $this->model('GameState'); }

    public function index(): void  { $this->play(); }

    /** The main real-time gameplay screen. */
    public function play(): void
    {
        $userId = $this->requireAuth();
        $state  = $this->gs()->fullState($userId);
        $this->view('game/play', [
            'title' => 'Brew Master — Cafe',
            'state' => $state,
            'active' => 'play',
        ]);
    }

    public function upgrades(): void
    {
        $userId = $this->requireAuth();
        $this->view('game/upgrades', [
            'title'    => 'Upgrades',
            'upgrades' => $this->gs()->upgrades($userId),
            'progress' => $this->gs()->progress($userId),
            'active'   => 'upgrades',
        ]);
    }

    public function shop(): void
    {
        $userId = $this->requireAuth();
        $this->view('game/shop', [
            'title'       => 'Decorate & Shop',
            'decorations' => $this->gs()->decorations($userId),
            'progress'    => $this->gs()->progress($userId),
            'shop'        => $this->gs()->shop($userId),
            'active'      => 'shop',
        ]);
    }

    public function stats(): void
    {
        $userId = $this->requireAuth();
        $this->view('game/stats', [
            'title'    => 'Statistics',
            'report'   => $this->gs()->revenueReport($userId),
            'progress' => $this->gs()->progress($userId),
            'shop'     => $this->gs()->shop($userId),
            'active'   => 'stats',
        ]);
    }

    public function achievements(): void
    {
        $userId = $this->requireAuth();
        $this->view('game/achievements', [
            'title'        => 'Achievements',
            'achievements' => $this->gs()->achievements($userId),
            'active'       => 'achievements',
        ]);
    }

    public function leaderboard(): void
    {
        $userId = $this->requireAuth();
        $metric = $_GET['metric'] ?? 'highest_revenue';
        $this->view('game/leaderboard', [
            'title'   => 'Leaderboard',
            'rows'    => $this->gs()->leaderboard($metric),
            'metric'  => $metric,
            'meId'    => $userId,
            'active'  => 'leaderboard',
        ]);
    }
}
