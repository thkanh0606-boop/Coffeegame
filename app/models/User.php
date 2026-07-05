<?php
/**
 * User model — registration, login and new-player world bootstrap.
 */
class User extends Model
{
    protected string $table = 'users';

    public function findByLogin(string $login): ?array
    {
        return $this->one(
            "SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$login, $login]
        );
    }

    public function usernameTaken(string $username, string $email): bool
    {
        return (bool) $this->one(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
    }

    /** Create the account + bootstrap the whole game world for the player. */
    public function register(string $username, string $email, string $password): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->insert(
            "INSERT INTO users (username, email, password_hash) VALUES (?,?,?)",
            [$username, $email, $hash]
        );
        $this->bootstrapWorld($userId);
        return $userId;
    }

    /** Create all the starter rows a fresh player needs. */
    public function bootstrapWorld(int $userId): void
    {
        $this->run("INSERT INTO coffee_shop (user_id) VALUES (?)", [$userId]);
        $this->run(
            "INSERT INTO game_progress (user_id, coins, unlocked_recipes)
             VALUES (?, 120, ?)",
            [$userId, json_encode(['espresso', 'americano', 'latte'])]
        );
        $this->run("INSERT INTO settings (user_id) VALUES (?)", [$userId]);
        $this->run("INSERT INTO leaderboard (user_id) VALUES (?)", [$userId]);

        // Starter inventory: one row per ingredient.
        $ingredients = $this->all("SELECT id FROM ingredients");
        foreach ($ingredients as $ing) {
            $this->run(
                "INSERT INTO inventory (user_id, ingredient_id, quantity, capacity, low_threshold)
                 VALUES (?, ?, 24, 40, 8)",
                [$userId, $ing['id']]
            );
        }

        // Base equipment upgrades at level 1.
        $this->run(
            "INSERT INTO user_upgrades (user_id, upgrade_id, level)
             SELECT ?, id, 1 FROM upgrades WHERE category = 'equipment'",
            [$userId]
        );

        // Achievement progress trackers.
        $this->run(
            "INSERT INTO user_achievements (user_id, achievement_id, progress)
             SELECT ?, id, 0 FROM achievements",
            [$userId]
        );
    }

    public function touchLogin(int $userId): void
    {
        $this->run("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);
    }
}
