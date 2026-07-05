<?php
/**
 * ApiController — all AJAX/JSON endpoints.
 * Routed as: index.php?url=api/<action>
 */
class ApiController extends Controller
{
    private function gs(): GameState { return $this->model('GameState'); }

    /** GET api/state — full game state for booting the client. */
    public function state(): void
    {
        $userId = $this->requireAuth(true);
        $this->json(['ok' => true, 'state' => $this->gs()->fullState($userId)]);
    }

    /** POST api/serve — serve one drink (recipe-validated). */
    public function serve(): void
    {
        $userId = $this->requireAuth(true);
        $this->json($this->gs()->serveDrink($userId, $this->input()));
    }

    /** POST api/cancel — a customer left angry. */
    public function cancel(): void
    {
        $userId = $this->requireAuth(true);
        $this->json($this->gs()->cancelOrder($userId));
    }

    /** POST api/restock — buy ingredients. body: {ingredient, times} */
    public function restock(): void
    {
        $userId = $this->requireAuth(true);
        $in = $this->input();
        $this->json($this->gs()->restock($userId, $in['ingredient'] ?? '', (int) ($in['times'] ?? 1)));
    }

    /** POST api/setStock — set an ingredient to an exact quantity. body: {ingredient, qty} */
    public function setStock(): void
    {
        $userId = $this->requireAuth(true);
        $in = $this->input();
        $this->json($this->gs()->setStock($userId, $in['ingredient'] ?? '', (int) ($in['qty'] ?? 0)));
    }

    /** POST api/upgrade — buy an upgrade level. body: {code} */
    public function upgrade(): void
    {
        $userId = $this->requireAuth(true);
        $this->json($this->gs()->buyUpgrade($userId, $this->input()['code'] ?? ''));
    }

    /** POST api/buyDecoration — body: {code} */
    public function buyDecoration(): void
    {
        $userId = $this->requireAuth(true);
        $this->json($this->gs()->buyDecoration($userId, $this->input()['code'] ?? ''));
    }

    /** POST api/save — persist progress snapshot. */
    public function save(): void
    {
        $userId = $this->requireAuth(true);
        $this->json($this->gs()->save($userId, $this->input()));
    }

    /** POST api/advanceDay */
    public function advanceDay(): void
    {
        $userId = $this->requireAuth(true);
        $this->json($this->gs()->advanceDay($userId));
    }

    /** POST api/settings */
    public function settings(): void
    {
        $userId = $this->requireAuth(true);
        $this->json($this->gs()->saveSettings($userId, $this->input()));
    }

    /** GET api/leaderboard?metric= */
    public function leaderboard(): void
    {
        $this->requireAuth(true);
        $metric = $_GET['metric'] ?? 'highest_revenue';
        $this->json(['ok' => true, 'rows' => $this->gs()->leaderboard($metric)]);
    }

    /** GET api/revenue */
    public function revenue(): void
    {
        $userId = $this->requireAuth(true);
        $this->json(['ok' => true, 'report' => $this->gs()->revenueReport($userId)]);
    }
}
