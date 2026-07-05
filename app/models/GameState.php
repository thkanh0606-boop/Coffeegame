<?php
/**
 * GameState — the heart of the game's server-side logic.
 * Aggregates progress, inventory, recipes, upgrades and resolves
 * serve/restock/upgrade/save actions with recipe validation.
 */
class GameState extends Model
{
    /* ---------------- Catalogue (shared, cached-ish) ---------------- */

    public function drinks(): array
    {
        $drinks = $this->all("SELECT * FROM drinks ORDER BY unlock_level, id");
        $recipes = $this->all(
            "SELECT r.drink_id, i.code AS ing, i.name AS ing_name, r.step_order
             FROM recipes r JOIN ingredients i ON i.id = r.ingredient_id
             ORDER BY r.drink_id, r.step_order"
        );
        $byDrink = [];
        foreach ($recipes as $r) {
            $byDrink[$r['drink_id']][] = ['code' => $r['ing'], 'name' => $r['ing_name']];
        }
        foreach ($drinks as &$d) {
            $d['recipe'] = $byDrink[$d['id']] ?? [];
            $d['recipe_codes'] = array_column($d['recipe'], 'code');
        }
        return $drinks;
    }

    public function ingredients(): array
    {
        return $this->all("SELECT * FROM ingredients ORDER BY id");
    }

    public function customers(): array
    {
        return $this->all("SELECT * FROM customers ORDER BY id");
    }

    /* ---------------- Per-user state ---------------- */

    public function progress(int $userId): array
    {
        return $this->one("SELECT * FROM game_progress WHERE user_id = ?", [$userId]) ?? [];
    }

    public function shop(int $userId): array
    {
        return $this->one("SELECT * FROM coffee_shop WHERE user_id = ?", [$userId]) ?? [];
    }

    public function settings(int $userId): array
    {
        return $this->one("SELECT * FROM settings WHERE user_id = ?", [$userId]) ?? [];
    }

    public function inventory(int $userId): array
    {
        return $this->all(
            "SELECT inv.*, i.code, i.name, i.icon, i.unit_cost, i.restock_size, i.station
             FROM inventory inv JOIN ingredients i ON i.id = inv.ingredient_id
             WHERE inv.user_id = ? ORDER BY i.id",
            [$userId]
        );
    }

    public function upgrades(int $userId): array
    {
        return $this->all(
            "SELECT u.*, COALESCE(uu.level, 0) AS level
             FROM upgrades u
             LEFT JOIN user_upgrades uu ON uu.upgrade_id = u.id AND uu.user_id = ?
             ORDER BY u.category, u.id",
            [$userId]
        );
    }

    /** Everything the client needs to boot the game. */
    public function fullState(int $userId): array
    {
        $progress = $this->progress($userId);
        $progress['unlocked_recipes'] = json_decode($progress['unlocked_recipes'] ?? '[]', true) ?: [];

        return [
            'progress'  => $progress,
            'shop'      => $this->shop($userId),
            'settings'  => $this->settings($userId),
            'inventory' => $this->inventory($userId),
            'upgrades'  => $this->upgrades($userId),
            'drinks'    => $this->drinks(),
            'customers' => $this->customers(),
            'levels'    => $this->levelTable(),
        ];
    }

    /* ---------------- Progression ---------------- */

    /** XP required to reach a given level (cumulative-ish curve). */
    public function xpForLevel(int $level): int
    {
        return (int) (80 * $level * $level);
    }

    public function levelTable(): array
    {
        $t = [];
        for ($l = 1; $l <= 30; $l++) $t[$l] = $this->xpForLevel($l);
        return $t;
    }

    /* ---------------- Core action: SERVE an order ---------------- */

    /**
     * Serve one drink. Validates recipe, consumes inventory, awards coins/xp,
     * updates combo/satisfaction, revenue and achievements.
     *
     * @param array $payload { drink_code, made:[codes], patience:0-1, combo:int, tip_factor:float }
     */
    public function serveDrink(int $userId, array $payload): array
    {
        $drinkCode = $payload['drink_code'] ?? '';
        $made      = array_values($payload['made'] ?? []);
        $patience  = max(0.0, min(1.0, (float) ($payload['patience'] ?? 1)));
        $tipFactor = (float) ($payload['tip_factor'] ?? 1.0);

        $drink = $this->one("SELECT * FROM drinks WHERE code = ?", [$drinkCode]);
        if (!$drink) return ['ok' => false, 'error' => 'unknown_drink'];

        $recipe = $this->all(
            "SELECT i.code FROM recipes r JOIN ingredients i ON i.id = r.ingredient_id
             WHERE r.drink_id = ? ORDER BY r.step_order",
            [$drink['id']]
        );
        $recipeCodes = array_column($recipe, 'code');

        // Recipe validation: same multiset of ingredients (order-independent, forgiving).
        $correct = $this->recipeMatches($recipeCodes, $made);

        $pdo = $this->db();
        $pdo->beginTransaction();
        try {
            $progress = $this->one("SELECT * FROM game_progress WHERE user_id = ? FOR UPDATE", [$userId]);
            $day = (int) $progress['current_day'];

            // Consume inventory for whatever the player actually used.
            $shortages = [];
            foreach ($made as $code) {
                $upd = $this->run(
                    "UPDATE inventory inv JOIN ingredients i ON i.id = inv.ingredient_id
                     SET inv.quantity = GREATEST(inv.quantity - 1, 0)
                     WHERE inv.user_id = ? AND i.code = ? AND inv.quantity > 0",
                    [$userId, $code]
                );
                if ($upd->rowCount() === 0) $shortages[] = $code;
            }

            // Economics
            $base = (int) $drink['price'];
            $combo = (int) ($payload['combo'] ?? 0);
            $earn = 0; $tip = 0; $orderSat = 0; $xp = 0;

            if ($correct) {
                // Patience bonus: faster service = better tip.
                $comboMult = 1 + min($combo, 10) * 0.05;
                $earn = (int) round($base * $comboMult);
                $tip  = (int) round($base * 0.4 * $patience * $tipFactor * $comboMult);
                $orderSat = (int) round(60 + 40 * $patience);
                $xp = 10 + (int) round($base / 2);
                $newCombo = $combo + 1;
                $this->run("UPDATE game_progress
                            SET total_served = total_served + 1,
                                perfect_served = perfect_served + 1
                            WHERE user_id = ?", [$userId]);
            } else {
                // Wrong drink: small pity payment, satisfaction hit, combo reset.
                $earn = (int) round($base * 0.25);
                $orderSat = 20;
                $xp = 2;
                $newCombo = 0;
                $this->run("UPDATE game_progress
                            SET total_served = total_served + 1,
                                wrong_served = wrong_served + 1
                            WHERE user_id = ?", [$userId]);
            }

            $total = $earn + $tip;

            // Persist order + detail
            $orderId = $this->insert(
                "INSERT INTO orders (user_id, day, status, total_price, tip, satisfaction, combo)
                 VALUES (?,?, 'served', ?, ?, ?, ?)",
                [$userId, $day, $earn, $tip, $orderSat, $newCombo]
            );
            $this->run(
                "INSERT INTO order_details (order_id, drink_id, price, correct) VALUES (?,?,?,?)",
                [$orderId, $drink['id'], $earn, $correct ? 1 : 0]
            );
            $this->run(
                "INSERT INTO transactions (user_id, type, category, amount, ref_id)
                 VALUES (?, 'income', 'sale', ?, ?)",
                [$userId, $total, $orderId]
            );

            // Update coins / xp / combo
            $bestCombo = max((int) $progress['best_combo'], $newCombo);
            $this->run(
                "UPDATE game_progress
                 SET coins = coins + ?, xp = xp + ?, best_combo = ?
                 WHERE user_id = ?",
                [$total, $xp, $bestCombo, $userId]
            );

            // Level up check
            $fresh = $this->one("SELECT coins, xp, level FROM game_progress WHERE user_id = ?", [$userId]);
            $level = (int) $fresh['level'];
            $leveled = false;
            while ((int) $fresh['xp'] >= $this->xpForLevel($level + 1) && $level < 30) {
                $level++; $leveled = true;
            }
            if ($leveled) {
                $this->run("UPDATE game_progress SET level = ? WHERE user_id = ?", [$level, $userId]);
                $this->run("UPDATE coffee_shop SET level = ? WHERE user_id = ?", [$level, $userId]);
            }

            // Daily revenue rollup
            $this->rollupRevenue($userId, $day, $earn, $tip, true, $correct);

            // Satisfaction (shop rolling average nudge)
            $this->nudgeSatisfaction($userId, $orderSat);

            // Achievements + leaderboard
            $unlocked = $this->refreshAchievements($userId);
            $this->refreshLeaderboard($userId);

            // Auto-unlock recipes for the new level
            $newlyUnlocked = $this->syncUnlockedRecipes($userId, $level);

            $pdo->commit();

            return [
                'ok' => true,
                'correct' => $correct,
                'earn' => $earn,
                'tip' => $tip,
                'total' => $total,
                'xp' => $xp,
                'combo' => $newCombo,
                'level' => $level,
                'leveled' => $leveled,
                'coins' => (int) $fresh['coins'] + $total,
                'shortages' => $shortages,
                'unlocked_achievements' => $unlocked,
                'unlocked_recipes' => $newlyUnlocked,
                'satisfaction' => $this->shop($userId)['satisfaction'] ?? 100,
            ];
        } catch (Throwable $ex) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => $ex->getMessage()];
        }
    }

    /** A customer left angry (order cancelled / patience expired). */
    public function cancelOrder(int $userId): array
    {
        $progress = $this->one("SELECT current_day FROM game_progress WHERE user_id = ?", [$userId]);
        $day = (int) ($progress['current_day'] ?? 1);
        $this->insert(
            "INSERT INTO orders (user_id, day, status, satisfaction) VALUES (?,?, 'cancelled', 0)",
            [$userId, $day]
        );
        $this->run("UPDATE game_progress SET best_combo = best_combo WHERE user_id = ?", [$userId]);
        $this->rollupRevenue($userId, $day, 0, 0, false, false);
        $this->nudgeSatisfaction($userId, -8, true);
        $this->refreshLeaderboard($userId);
        return ['ok' => true, 'satisfaction' => $this->shop($userId)['satisfaction'] ?? 100];
    }

    private function recipeMatches(array $recipe, array $made): bool
    {
        $r = $recipe; sort($r);
        $m = $made;   sort($m);
        return $r === $m;
    }

    private function rollupRevenue(int $userId, int $day, int $gross, int $tips, bool $completed, bool $correct): void
    {
        $this->run(
            "INSERT INTO revenue (user_id, day, gross, tips, orders_completed, orders_cancelled, customers_served)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                gross = gross + VALUES(gross),
                tips = tips + VALUES(tips),
                orders_completed = orders_completed + VALUES(orders_completed),
                orders_cancelled = orders_cancelled + VALUES(orders_cancelled),
                customers_served = customers_served + VALUES(customers_served)",
            [$userId, $day, $gross, $tips, $completed ? 1 : 0, $completed ? 0 : 1, $completed ? 1 : 0]
        );
    }

    private function nudgeSatisfaction(int $userId, int $delta, bool $absolute = false): void
    {
        // Rolling average toward the order's satisfaction; decorations add a floor bonus.
        $decoBonus = (int) ($this->one(
            "SELECT COALESCE(SUM(d.satisfaction_bonus),0) b
             FROM user_decorations ud JOIN decorations d ON d.id = ud.decoration_id
             WHERE ud.user_id = ? AND ud.placed = 1", [$userId]
        )['b'] ?? 0);

        $shop = $this->one("SELECT satisfaction FROM coffee_shop WHERE user_id = ?", [$userId]);
        $cur = (int) ($shop['satisfaction'] ?? 100);
        if ($absolute) {
            $new = $cur + $delta;
        } else {
            $new = (int) round($cur * 0.85 + $delta * 0.15);
        }
        $new = max(0, min(100, $new + (int) round($decoBonus * 0.2)));
        $this->run("UPDATE coffee_shop SET satisfaction = ? WHERE user_id = ?", [$new, $userId]);
    }

    /* ---------------- Restock ---------------- */

    public function restock(int $userId, string $ingredientCode, int $times = 1): array
    {
        $ing = $this->one("SELECT * FROM ingredients WHERE code = ?", [$ingredientCode]);
        if (!$ing) return ['ok' => false, 'error' => 'unknown_ingredient'];

        $inv = $this->one(
            "SELECT * FROM inventory WHERE user_id = ? AND ingredient_id = ?",
            [$userId, $ing['id']]
        );
        if (!$inv) return ['ok' => false, 'error' => 'no_inventory'];

        $addPerBuy = (int) $ing['restock_size'];
        $costPerBuy = $addPerBuy * (int) $ing['unit_cost'];
        $totalCost = $costPerBuy * $times;

        $progress = $this->one("SELECT coins FROM game_progress WHERE user_id = ?", [$userId]);
        if ((int) $progress['coins'] < $totalCost) {
            return ['ok' => false, 'error' => 'insufficient_coins', 'need' => $totalCost];
        }

        $newQty = min((int) $inv['capacity'], (int) $inv['quantity'] + $addPerBuy * $times);
        $this->run("UPDATE inventory SET quantity = ? WHERE id = ?", [$newQty, $inv['id']]);
        $this->run("UPDATE game_progress SET coins = coins - ? WHERE user_id = ?", [$totalCost, $userId]);
        $this->run(
            "INSERT INTO transactions (user_id, type, category, amount) VALUES (?, 'expense', 'restock', ?)",
            [$userId, $totalCost]
        );
        $day = (int) $this->one("SELECT current_day FROM game_progress WHERE user_id = ?", [$userId])['current_day'];
        $this->run(
            "INSERT INTO revenue (user_id, day, expenses) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE expenses = expenses + VALUES(expenses)",
            [$userId, $day, $totalCost]
        );

        return [
            'ok' => true,
            'ingredient' => $ingredientCode,
            'quantity' => $newQty,
            'coins' => (int) $progress['coins'] - $totalCost,
        ];
    }

    /**
     * Set an ingredient to an exact quantity (in-game "edit / remove").
     * Increasing costs coins (buy the difference); decreasing refunds half.
     * Used by the integrated in-game ingredient management panel.
     */
    public function setStock(int $userId, string $ingredientCode, int $qty): array
    {
        $ing = $this->one("SELECT * FROM ingredients WHERE code = ?", [$ingredientCode]);
        if (!$ing) return ['ok' => false, 'error' => 'unknown_ingredient'];

        $inv = $this->one(
            "SELECT * FROM inventory WHERE user_id = ? AND ingredient_id = ?",
            [$userId, $ing['id']]
        );
        if (!$inv) return ['ok' => false, 'error' => 'no_inventory'];

        // Clamp the requested quantity to the valid range.
        $target  = max(0, min((int) $inv['capacity'], $qty));
        $current = (int) $inv['quantity'];
        $delta   = $target - $current;
        if ($delta === 0) {
            return ['ok' => true, 'ingredient' => $ingredientCode, 'quantity' => $current,
                    'coins' => (int) $this->one("SELECT coins FROM game_progress WHERE user_id=?", [$userId])['coins']];
        }

        $progress = $this->one("SELECT coins FROM game_progress WHERE user_id = ?", [$userId]);
        $coins = (int) $progress['coins'];
        $day = (int) $this->one("SELECT current_day FROM game_progress WHERE user_id = ?", [$userId])['current_day'];

        if ($delta > 0) {
            // Buying the extra units.
            $cost = $delta * (int) $ing['unit_cost'];
            if ($coins < $cost) return ['ok' => false, 'error' => 'insufficient_coins', 'need' => $cost];
            $this->run("UPDATE inventory SET quantity = ? WHERE id = ?", [$target, $inv['id']]);
            $this->run("UPDATE game_progress SET coins = coins - ? WHERE user_id = ?", [$cost, $userId]);
            $this->run("INSERT INTO transactions (user_id, type, category, amount) VALUES (?, 'expense', 'restock', ?)", [$userId, $cost]);
            $this->run("INSERT INTO revenue (user_id, day, expenses) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE expenses = expenses + VALUES(expenses)", [$userId, $day, $cost]);
            $coins -= $cost;
        } else {
            // Removing units: refund half the value.
            $refund = (int) floor(abs($delta) * (int) $ing['unit_cost'] * 0.5);
            $this->run("UPDATE inventory SET quantity = ? WHERE id = ?", [$target, $inv['id']]);
            if ($refund > 0) {
                $this->run("UPDATE game_progress SET coins = coins + ? WHERE user_id = ?", [$refund, $userId]);
                $this->run("INSERT INTO transactions (user_id, type, category, amount) VALUES (?, 'income', 'refund', ?)", [$userId, $refund]);
                $coins += $refund;
            }
        }

        return ['ok' => true, 'ingredient' => $ingredientCode, 'quantity' => $target, 'coins' => $coins];
    }

    /* ---------------- Upgrade ---------------- */

    public function buyUpgrade(int $userId, string $code): array
    {
        $u = $this->one("SELECT * FROM upgrades WHERE code = ?", [$code]);
        if (!$u) return ['ok' => false, 'error' => 'unknown_upgrade'];

        $uu = $this->one("SELECT * FROM user_upgrades WHERE user_id = ? AND upgrade_id = ?", [$userId, $u['id']]);
        $curLevel = $uu ? (int) $uu['level'] : 0;
        if ($curLevel >= (int) $u['max_level']) {
            return ['ok' => false, 'error' => 'maxed'];
        }
        $cost = (int) round($u['base_cost'] * pow((float) $u['cost_growth'], $curLevel));

        $progress = $this->one("SELECT coins FROM game_progress WHERE user_id = ?", [$userId]);
        if ((int) $progress['coins'] < $cost) {
            return ['ok' => false, 'error' => 'insufficient_coins', 'need' => $cost];
        }

        if ($uu) {
            $this->run("UPDATE user_upgrades SET level = level + 1 WHERE id = ?", [$uu['id']]);
        } else {
            $this->run("INSERT INTO user_upgrades (user_id, upgrade_id, level) VALUES (?,?,1)", [$userId, $u['id']]);
        }
        $this->run("UPDATE game_progress SET coins = coins - ? WHERE user_id = ?", [$cost, $userId]);
        $this->run("INSERT INTO transactions (user_id, type, category, amount) VALUES (?, 'expense', 'upgrade', ?)",
            [$userId, $cost]);

        // Frother/ice capacity upgrades expand inventory capacity.
        if ($u['effect_type'] === 'capacity') {
            $this->run(
                "UPDATE inventory inv JOIN ingredients i ON i.id = inv.ingredient_id
                 SET inv.capacity = inv.capacity + 8
                 WHERE inv.user_id = ? AND i.station = ?",
                [$userId, $this->stationForUpgrade($code)]
            );
        }

        return [
            'ok' => true,
            'code' => $code,
            'level' => $curLevel + 1,
            'coins' => (int) $progress['coins'] - $cost,
        ];
    }

    private function stationForUpgrade(string $code): string
    {
        return match ($code) {
            'frother'     => 'frother',
            'ice_machine' => 'ice',
            'cup_storage' => 'espresso',
            default       => 'espresso',
        };
    }

    /* ---------------- Decorations / Shop ---------------- */

    public function decorations(int $userId): array
    {
        return $this->all(
            "SELECT d.*, CASE WHEN ud.id IS NULL THEN 0 ELSE 1 END AS owned
             FROM decorations d
             LEFT JOIN user_decorations ud ON ud.decoration_id = d.id AND ud.user_id = ?
             ORDER BY d.category, d.cost",
            [$userId]
        );
    }

    public function buyDecoration(int $userId, string $code): array
    {
        $d = $this->one("SELECT * FROM decorations WHERE code = ?", [$code]);
        if (!$d) return ['ok' => false, 'error' => 'unknown'];
        $owned = $this->one("SELECT id FROM user_decorations WHERE user_id = ? AND decoration_id = ?", [$userId, $d['id']]);
        if ($owned) return ['ok' => false, 'error' => 'owned'];

        $progress = $this->one("SELECT coins FROM game_progress WHERE user_id = ?", [$userId]);
        if ((int) $progress['coins'] < (int) $d['cost']) {
            return ['ok' => false, 'error' => 'insufficient_coins', 'need' => (int) $d['cost']];
        }
        $this->run("INSERT INTO user_decorations (user_id, decoration_id, placed) VALUES (?,?,1)", [$userId, $d['id']]);
        $this->run("UPDATE game_progress SET coins = coins - ? WHERE user_id = ?", [(int) $d['cost'], $userId]);
        $this->run("INSERT INTO transactions (user_id, type, category, amount) VALUES (?, 'expense', 'decoration', ?)",
            [$userId, (int) $d['cost']]);
        $this->nudgeSatisfaction($userId, (int) $d['satisfaction_bonus'], true);

        return ['ok' => true, 'code' => $code, 'coins' => (int) $progress['coins'] - (int) $d['cost']];
    }

    /* ---------------- Recipes unlocking ---------------- */

    private function syncUnlockedRecipes(int $userId, int $level): array
    {
        $row = $this->one("SELECT unlocked_recipes FROM game_progress WHERE user_id = ?", [$userId]);
        $cur = json_decode($row['unlocked_recipes'] ?? '[]', true) ?: [];
        $eligible = $this->all("SELECT code FROM drinks WHERE unlock_level <= ?", [$level]);
        $eligibleCodes = array_column($eligible, 'code');
        $new = array_values(array_diff($eligibleCodes, $cur));
        if ($new) {
            $merged = array_values(array_unique(array_merge($cur, $eligibleCodes)));
            $this->run("UPDATE game_progress SET unlocked_recipes = ? WHERE user_id = ?",
                [json_encode($merged), $userId]);
        }
        return $new;
    }

    /* ---------------- Achievements ---------------- */

    public function achievements(int $userId): array
    {
        return $this->all(
            "SELECT a.*, COALESCE(ua.progress,0) AS progress,
                    ua.unlocked_at,
                    CASE WHEN ua.unlocked_at IS NULL THEN 0 ELSE 1 END AS unlocked
             FROM achievements a
             LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
             ORDER BY a.goal",
            [$userId]
        );
    }

    public function refreshAchievements(int $userId): array
    {
        $p = $this->progress($userId);
        $metrics = [
            'total_served'   => (int) ($p['total_served'] ?? 0),
            'best_combo'     => (int) ($p['best_combo'] ?? 0),
            'coins'          => (int) ($p['coins'] ?? 0),
            'level'          => (int) ($p['level'] ?? 1),
            'perfect_streak' => (int) ($p['best_combo'] ?? 0),
        ];
        $newly = [];
        $achs = $this->all("SELECT * FROM achievements");
        foreach ($achs as $a) {
            $val = $metrics[$a['metric']] ?? 0;
            $ua = $this->one("SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ?",
                [$userId, $a['id']]);
            $already = $ua && $ua['unlocked_at'] !== null;
            $this->run(
                "INSERT INTO user_achievements (user_id, achievement_id, progress)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE progress = VALUES(progress)",
                [$userId, $a['id'], min($val, (int) $a['goal'])]
            );
            if (!$already && $val >= (int) $a['goal']) {
                $this->run("UPDATE user_achievements SET unlocked_at = NOW()
                            WHERE user_id = ? AND achievement_id = ?", [$userId, $a['id']]);
                $this->run("UPDATE game_progress SET coins = coins + ? WHERE user_id = ?",
                    [(int) $a['reward_coins'], $userId]);
                $newly[] = ['name' => $a['name'], 'reward' => (int) $a['reward_coins']];
            }
        }
        return $newly;
    }

    /* ---------------- Leaderboard ---------------- */

    public function refreshLeaderboard(int $userId): void
    {
        $p = $this->progress($userId);
        $shop = $this->shop($userId);
        $rev = $this->one("SELECT COALESCE(SUM(gross+tips),0) total FROM revenue WHERE user_id = ?", [$userId]);
        $served = $this->one("SELECT COALESCE(SUM(customers_served),0) c FROM revenue WHERE user_id = ?", [$userId]);
        $this->run(
            "INSERT INTO leaderboard (user_id, highest_revenue, customers_served, highest_combo, highest_level, best_satisfaction)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                highest_revenue = GREATEST(highest_revenue, VALUES(highest_revenue)),
                customers_served = GREATEST(customers_served, VALUES(customers_served)),
                highest_combo = GREATEST(highest_combo, VALUES(highest_combo)),
                highest_level = GREATEST(highest_level, VALUES(highest_level)),
                best_satisfaction = GREATEST(best_satisfaction, VALUES(best_satisfaction))",
            [
                $userId,
                (int) $rev['total'],
                (int) $served['c'],
                (int) ($p['best_combo'] ?? 0),
                (int) ($p['level'] ?? 1),
                (int) ($shop['satisfaction'] ?? 0),
            ]
        );
    }

    public function leaderboard(string $metric = 'highest_revenue', int $limit = 20): array
    {
        $allowed = ['highest_revenue', 'customers_served', 'highest_combo', 'highest_level', 'best_satisfaction'];
        if (!in_array($metric, $allowed, true)) $metric = 'highest_revenue';
        return $this->all(
            "SELECT u.username, u.avatar, lb.*
             FROM leaderboard lb JOIN users u ON u.id = lb.user_id
             ORDER BY lb.$metric DESC, lb.updated_at ASC
             LIMIT $limit"
        );
    }

    /* ---------------- Revenue reporting ---------------- */

    public function revenueReport(int $userId): array
    {
        $rows = $this->all("SELECT * FROM revenue WHERE user_id = ? ORDER BY day", [$userId]);
        $daily = $this->one(
            "SELECT COALESCE(SUM(gross),0) gross, COALESCE(SUM(tips),0) tips,
                    COALESCE(SUM(expenses),0) expenses, COALESCE(SUM(orders_completed),0) completed,
                    COALESCE(SUM(orders_cancelled),0) cancelled
             FROM revenue WHERE user_id = ?", [$userId]);
        $popular = $this->one(
            "SELECT dr.name, COUNT(*) c FROM order_details od
             JOIN orders o ON o.id = od.order_id
             JOIN drinks dr ON dr.id = od.drink_id
             WHERE o.user_id = ? GROUP BY dr.id ORDER BY c DESC LIMIT 1", [$userId]);
        $avg = $this->one(
            "SELECT COALESCE(AVG(total_price+tip),0) a FROM orders
             WHERE user_id = ? AND status='served'", [$userId]);

        $gross = (int) $daily['gross']; $tips = (int) $daily['tips']; $exp = (int) $daily['expenses'];
        return [
            'daily_rows'    => $rows,
            'gross'         => $gross,
            'tips'          => $tips,
            'expenses'      => $exp,
            'profit'        => $gross + $tips - $exp,
            'completed'     => (int) $daily['completed'],
            'cancelled'     => (int) $daily['cancelled'],
            'avg_order'     => round((float) $avg['a'], 1),
            'popular_drink' => $popular['name'] ?? '—',
        ];
    }

    /* ---------------- Save / advance day / settings ---------------- */

    public function save(int $userId, array $data): array
    {
        $fields = [];
        $params = [];
        $map = ['coins', 'gems', 'xp', 'level', 'current_day', 'game_time', 'best_combo'];
        foreach ($map as $f) {
            if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = (int) $data[$f]; }
        }
        if ($fields) {
            $params[] = $userId;
            $this->run("UPDATE game_progress SET " . implode(', ', $fields) . " WHERE user_id = ?", $params);
        }
        $this->refreshLeaderboard($userId);
        return ['ok' => true, 'saved_at' => date('H:i:s')];
    }

    public function advanceDay(int $userId): array
    {
        $this->run("UPDATE game_progress SET current_day = current_day + 1, game_time = 0 WHERE user_id = ?", [$userId]);
        $day = (int) $this->one("SELECT current_day FROM game_progress WHERE user_id = ?", [$userId])['current_day'];
        $this->run("INSERT IGNORE INTO revenue (user_id, day) VALUES (?, ?)", [$userId, $day]);
        return ['ok' => true, 'day' => $day];
    }

    public function saveSettings(int $userId, array $data): array
    {
        $this->run(
            "UPDATE settings SET music_on=?, sfx_on=?, music_vol=?, sfx_vol=?, difficulty=?
             WHERE user_id = ?",
            [
                !empty($data['music_on']) ? 1 : 0,
                !empty($data['sfx_on']) ? 1 : 0,
                max(0, min(1, (float) ($data['music_vol'] ?? 0.4))),
                max(0, min(1, (float) ($data['sfx_vol'] ?? 0.7))),
                in_array($data['difficulty'] ?? 'normal', ['easy', 'normal', 'hard'], true) ? $data['difficulty'] : 'normal',
                $userId,
            ]
        );
        return ['ok' => true];
    }
}
