<?php
/**
 * AiScan — heuristic "AI" coffee-image classifier + scan history.
 *
 * There is no external ML dependency: predictions are derived from
 * lightweight image statistics (average brightness / aspect / warmth
 * proxy from the JPEG) mixed with a deterministic hash of the file so
 * results are stable per-image. It maps onto the real drink catalogue
 * so the returned recipe/ingredients/steps are game-accurate.
 */
class AiScan extends Model
{
    public function history(int $userId, int $limit = 20): array
    {
        return $this->all(
            "SELECT * FROM ai_scan_history WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit",
            [$userId]
        );
    }

    /**
     * Per-drink colour "fingerprint": [avg brightness 0-255, warmth (R-B), green (G-(R+B)/2)].
     * The browser measures these three values from the actual image pixels
     * (this PHP build has no GD, so pixel work happens client-side) and we
     * match against these profiles. Distinctive traits: black coffee = dark,
     * milky = light+warm, matcha = green, mocha/caramel = brown/warm.
     */
    private const DRINK_PROFILES = [
        'espresso'     => [46, 26, -6],
        'americano'    => [58, 20, -6],
        'coldbrew'     => [66, 8, -6],
        'mocha'        => [96, 36, -6],
        'chocomint'    => [92, 8, 12],
        'macchiato'    => [150, 22, -5],
        'matchalatte'  => [158, -14, 42],
        'caramellatte' => [178, 34, -3],
        'flatwhite'    => [186, 14, -3],
        'cappuccino'   => [196, 15, -3],
        'vanillalatte' => [196, 22, -2],
        'latte'        => [204, 18, -3],
    ];

    /**
     * Analyse an uploaded image and return a prediction.
     * @param array|null $features client-measured {brightness, warmth, green} from the image pixels
     */
    public function analyze(int $userId, string $filePath, string $publicPath, ?array $features = null): array
    {
        [$brightness, $warmth, $green] = $this->resolveFeatures($filePath, $features);

        // Nearest colour profile wins (green weighted highest — matcha is the
        // most colour-distinct; warmth next; brightness last).
        $best = null; $bestDist = INF;
        foreach (self::DRINK_PROFILES as $code => $p) {
            $dist = abs($brightness - $p[0]) * 1.0 + abs($warmth - $p[1]) * 1.5 + abs($green - $p[2]) * 2.6;
            if ($dist < $bestDist) { $bestDist = $dist; $best = $code; }
        }

        $drink = $this->one("SELECT * FROM drinks WHERE code = ?", [$best])
              ?? $this->one("SELECT * FROM drinks ORDER BY id LIMIT 1");
        if (!$drink) return ['ok' => false, 'error' => 'no_drinks'];

        $confidence = max(52.0, min(96.0, round(97 - $bestDist * 0.32, 1)));
        $sure = $confidence >= 62.0;

        $recipe = $this->all(
            "SELECT i.code, i.name FROM recipes r JOIN ingredients i ON i.id = r.ingredient_id
             WHERE r.drink_id = ? ORDER BY r.step_order", [$drink['id']]);
        $steps = $this->buildSteps($recipe);
        $ingredients = array_column($recipe, 'name');

        $id = $this->insert(
            "INSERT INTO ai_scan_history (user_id, image_path, drink_name, confidence, ingredients, steps)
             VALUES (?,?,?,?,?,?)",
            [$userId, $publicPath, $drink['name'], $confidence,
             json_encode($ingredients), json_encode($steps)]
        );

        // Ingredients the player still needs (missing or low in stock).
        $needed = $this->all(
            "SELECT i.name, inv.quantity, inv.low_threshold
             FROM recipes r JOIN ingredients i ON i.id = r.ingredient_id
             LEFT JOIN inventory inv ON inv.ingredient_id = i.id AND inv.user_id = ?
             WHERE r.drink_id = ?", [$userId, $drink['id']]);

        return [
            'ok' => true,
            'id' => $id,
            'drink_name' => $drink['name'],
            'drink_code' => $drink['code'],
            'confidence' => $confidence,
            'sure' => $sure,
            'ingredients' => $ingredients,
            'recipe' => array_map(fn($r) => $r['name'], $recipe),
            'steps' => $steps,
            'inventory_needed' => $needed,
            'image' => $publicPath,
            'brightness' => $brightness,
        ];
    }

    /** Prefer the browser's pixel measurements; fall back to a server estimate. */
    private function resolveFeatures(string $filePath, ?array $f): array
    {
        if ($f && isset($f['brightness']) && is_numeric($f['brightness'])) {
            return [
                max(0, min(255, (int) $f['brightness'])),
                (int) ($f['warmth'] ?? 15),
                (int) ($f['green'] ?? -3),
            ];
        }
        // No client features (e.g. JS disabled): estimate brightness, assume milky tone.
        [$brightness] = $this->imageStats($filePath);
        return [$brightness, 15, -3];
    }

    private function buildSteps(array $recipe): array
    {
        $verbs = [
            'espresso'  => 'Pull a fresh espresso shot',
            'water'     => 'Top up with hot water',
            'milk'      => 'Steam and pour milk',
            'foam'      => 'Spoon a foam cap on top',
            'ice'       => 'Fill the cup with ice',
            'chocolate' => 'Stir in chocolate',
            'caramel'   => 'Drizzle caramel syrup',
            'vanilla'   => 'Add vanilla syrup',
            'mint'      => 'Add a splash of mint syrup',
            'matcha'    => 'Whisk in matcha powder',
            'whip'      => 'Finish with whipped cream',
            'sugar'     => 'Sweeten with sugar',
        ];
        $steps = ['Choose the right cup'];
        foreach ($recipe as $r) {
            $steps[] = $verbs[$r['code']] ?? ('Add ' . $r['name']);
        }
        $steps[] = 'Serve immediately while hot';
        return $steps;
    }

    /** Brightness + aspect ratio. Uses GD when present, else a content proxy. */
    private function imageStats(string $filePath, ?string $content = null): array
    {
        $data = $content ?? @file_get_contents($filePath);
        $ratio = 1.0;
        $brightness = null;

        if (function_exists('imagecreatefromstring')) {
            $img = $data ? @imagecreatefromstring($data) : false;
            if ($img) {
                $w = imagesx($img); $h = imagesy($img);
                $ratio = $h > 0 ? round($w / $h, 2) : 1.0;
                $sum = 0; $n = 0;
                $stepX = max(1, (int) ($w / 24));
                $stepY = max(1, (int) ($h / 24));
                for ($x = 0; $x < $w; $x += $stepX) {
                    for ($y = 0; $y < $h; $y += $stepY) {
                        $rgb = imagecolorat($img, $x, $y);
                        $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
                        $sum += (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                        $n++;
                    }
                }
                if ($n) $brightness = (int) ($sum / $n);
                if (function_exists('imagedestroy')) imagedestroy($img);
            }
        }

        // Fallback when GD is unavailable: derive a stable pseudo-brightness
        // from a sample of the file's bytes so different images differ.
        if ($brightness === null) {
            $len = $data ? strlen($data) : 0;
            if ($len > 0) {
                $sum = 0; $n = 0; $stride = max(1, (int) ($len / 500));
                for ($i = 0; $i < $len; $i += $stride) { $sum += ord($data[$i]); $n++; }
                $brightness = $n ? (40 + (int) ($sum / $n) % 200) : 128;
            } else {
                $brightness = 128;
            }
        }
        return [$brightness, $ratio];
    }
}
