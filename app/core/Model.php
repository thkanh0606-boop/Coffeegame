<?php
/**
 * Base Model — exposes the shared PDO helpers to every model.
 * Subclasses set $table and inherit basic CRUD helpers.
 */
abstract class Model
{
    protected string $table = '';

    protected function db(): PDO            { return Database::pdo(); }
    protected function one(string $s, array $p = []): ?array { return Database::one($s, $p); }
    protected function all(string $s, array $p = []): array  { return Database::all($s, $p); }
    protected function run(string $s, array $p = []): PDOStatement { return Database::run($s, $p); }
    protected function insert(string $s, array $p = []): int { return Database::insert($s, $p); }

    public function find(int $id): ?array
    {
        return $this->one("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function allRows(): array
    {
        return $this->all("SELECT * FROM {$this->table}");
    }
}
