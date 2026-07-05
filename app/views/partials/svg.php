<?php
/**
 * svg.php — original monochrome SVG art library.
 * Usage:  echo icon('latte', 'svg');
 * All art is line/flat, uses currentColor so it inverts on dark chips.
 */
if (!function_exists('icon')) {
    function icon(string $name, string $class = 'svg'): string
    {
        $s = SvgLib::get($name);
        return '<svg class="' . e($class) . '" viewBox="0 0 64 64" fill="none" '
             . 'stroke="currentColor" stroke-width="2.4" stroke-linecap="round" '
             . 'stroke-linejoin="round" aria-hidden="true">' . $s . '</svg>';
    }
}

class SvgLib
{
    public static function get(string $name): string
    {
        $m = self::map();
        return $m[$name] ?? $m['cup'];
    }

    /** Export every icon as a full <svg> string for client-side use. */
    public static function exportAll(): array
    {
        $out = [];
        foreach (self::map() as $name => $inner) {
            $out[$name] = '<svg class="svg" viewBox="0 0 64 64" fill="none" '
                . 'stroke="currentColor" stroke-width="2.4" stroke-linecap="round" '
                . 'stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
        }
        return $out;
    }

    private static function map(): array
    {
        // Reusable cup body path
        $cup   = '<path d="M16 22 h32 l-3 30 a6 6 0 0 1-6 6 H25 a6 6 0 0 1-6-6 Z"/>';
        $handle= '<path d="M48 28 a8 8 0 0 1 0 16"/>';
        $saucer= '<path d="M12 58 h40"/>';

        return [
        /* ---------- generic ---------- */
        'cup' => $cup.$handle.$saucer,

        /* ---------- DRINKS ---------- */
        'espresso' =>
            '<path d="M20 26 h20 l-2 18 a5 5 0 0 1-5 5 H27 a5 5 0 0 1-5-5 Z"/>'
            .'<path d="M40 30 a6 6 0 0 1 0 12"/><path d="M16 54 h30"/>'
            .'<path d="M28 16 c-2 3 2 4 0 7 M34 15 c-2 3 2 4 0 7" stroke-width="2"/>',
        'americano' =>
            $cup.$handle.$saucer
            .'<path d="M22 30 h20" stroke-width="1.6"/>',
        'latte' =>
            $cup.$handle.$saucer
            .'<path d="M20 34 c6 4 18 4 24 0" stroke-width="1.8"/>'
            .'<circle cx="32" cy="30" r="3"/>',
        'cappuccino' =>
            $cup.$handle.$saucer
            .'<path d="M18 26 c4-5 24-5 28 0" stroke-width="2"/>'
            .'<circle cx="26" cy="24" r="2.4"/><circle cx="34" cy="23" r="2.6"/><circle cx="40" cy="25" r="2"/>',
        'flatwhite' =>
            $cup.$handle.$saucer
            .'<path d="M22 32 q10 6 20 0" stroke-width="1.8"/>',
        'macchiato' =>
            '<path d="M20 26 h20 l-2 18 a5 5 0 0 1-5 5 H27 a5 5 0 0 1-5-5 Z"/>'
            .'<path d="M40 30 a6 6 0 0 1 0 12"/><path d="M16 54 h30"/>'
            .'<circle cx="30" cy="28" r="3"/>',
        'mocha' =>
            $cup.$handle.$saucer
            .'<path d="M20 34 c6 4 18 4 24 0" stroke-width="1.8"/>'
            .'<path d="M27 27 l3 3 3-3 3 3" stroke-width="1.8"/>',
        'coldbrew' =>
            '<path d="M22 16 h20 l-2 40 a4 4 0 0 1-4 4 H28 a4 4 0 0 1-4-4 Z"/>'
            .'<path d="M26 26 l2 2 M34 24 l2 2 M30 34 l2 2" stroke-width="1.8"/>'
            .'<path d="M30 12 v6 M34 12 v6"/>',
        'frappe' =>
            '<path d="M22 22 h20 l-2 34 a4 4 0 0 1-4 4 H28 a4 4 0 0 1-4-4 Z"/>'
            .'<path d="M20 22 c4-6 20-6 24 0" stroke-width="2"/>'
            .'<circle cx="32" cy="16" r="3"/><path d="M36 22 v-8"/>',
        'matcha' =>
            $cup.$handle.$saucer
            .'<path d="M20 34 c6 4 18 4 24 0" stroke-width="1.8"/>'
            .'<path d="M24 28 q8-5 16 0" stroke-width="1.6"/>',

        /* ---------- MACHINES / STATIONS ---------- */
        'machine' => /* espresso machine */
            '<rect x="10" y="14" width="44" height="30" rx="5"/>'
            .'<rect x="16" y="20" width="14" height="8" rx="2"/>'
            .'<circle cx="42" cy="22" r="3"/><circle cx="50" cy="22" r="3"/>'
            .'<path d="M30 34 h4 v6 h-4 Z"/><path d="M28 40 h8"/>'
            .'<path d="M20 44 v6 M44 44 v6"/>',
        'grinder' =>
            '<path d="M24 12 h16 l-2 10 H26 Z"/>'
            .'<rect x="22" y="22" width="20" height="16" rx="3"/>'
            .'<path d="M26 38 v8 h12 v-8"/><circle cx="32" cy="30" r="3"/>'
            .'<path d="M28 50 h8"/>',
        'frother' => /* milk jug */
            '<path d="M18 24 h22 v20 a6 6 0 0 1-6 6 H24 a6 6 0 0 1-6-6 Z"/>'
            .'<path d="M40 28 l8-4 v6 l-8 2"/>'
            .'<path d="M22 20 q9-6 16 0" stroke-width="1.8"/>',
        'blender' =>
            '<path d="M22 12 h20 l-2 26 H24 Z"/>'
            .'<rect x="24" y="38" width="16" height="8" rx="2"/>'
            .'<rect x="22" y="46" width="20" height="6" rx="2"/>'
            .'<path d="M28 20 l8 8 M36 20 l-8 8" stroke-width="1.6"/>',
        'ice' =>
            '<rect x="14" y="20" width="36" height="30" rx="6"/>'
            .'<path d="M22 30 l4 4-4 4 M32 28 l4 4-4 4 M42 30 l-4 4 4 4" stroke-width="1.8"/>'
            .'<path d="M14 26 h36"/>',
        'syrup' => /* syrup bottles */
            '<path d="M22 22 h8 v-4 h-8 Z"/><path d="M22 26 h8 l2 24 a3 3 0 0 1-3 3 h-6 a3 3 0 0 1-3-3 Z"/>'
            .'<path d="M38 24 h8 v-4 h-8 Z"/><path d="M38 28 h8 l1 22 a3 3 0 0 1-3 3 h-4 a3 3 0 0 1-3-3 Z"/>',
        'cupstack' =>
            '<path d="M20 20 h24 l-2 6 H22 Z"/><path d="M22 28 h20 l-1 6 H23 Z"/>'
            .'<path d="M23 36 h18 l-1 6 H24 Z"/><path d="M24 44 h16 l-1 8 a3 3 0 0 1-3 3 h-8 a3 3 0 0 1-3-3 Z"/>',
        'trash' =>
            '<path d="M18 22 h28 l-3 30 a4 4 0 0 1-4 4 H25 a4 4 0 0 1-4-4 Z"/>'
            .'<path d="M14 22 h36"/><path d="M26 18 h12"/>'
            .'<path d="M28 30 v18 M36 30 v18" stroke-width="1.8"/>',
        'sink' =>
            '<rect x="12" y="30" width="40" height="18" rx="4"/>'
            .'<path d="M40 30 v-8 a6 6 0 0 0-12 0" stroke-width="2"/>'
            .'<path d="M32 34 v6" stroke-width="1.8"/>',
        'water' =>
            '<path d="M32 12 c8 12 12 18 12 26 a12 12 0 0 1-24 0 c0-8 4-14 12-26 Z"/>',
        'serve' =>
            '<path d="M10 46 h44"/><path d="M18 46 c0-10 28-10 28 0"/><circle cx="32" cy="30" r="2.5"/>',

        /* ---------- INGREDIENT ICONS ---------- */
        'bean' =>
            '<ellipse cx="32" cy="32" rx="13" ry="18"/><path d="M32 15 c-6 8-6 26 0 34" stroke-width="1.8"/>',
        'milk' =>
            '<path d="M24 16 h16 v8 l4 6 v22 a2 2 0 0 1-2 2 H22 a2 2 0 0 1-2-2 V30 l4-6 Z"/>'
            .'<path d="M20 34 h24" stroke-width="1.6"/>',
        'foam' =>
            '<circle cx="24" cy="30" r="6"/><circle cx="34" cy="26" r="7"/><circle cx="42" cy="31" r="6"/>'
            .'<path d="M18 36 h30 v6 a6 6 0 0 1-6 6 H24 a6 6 0 0 1-6-6 Z"/>',
        'choco' =>
            '<rect x="18" y="18" width="28" height="28" rx="3"/>'
            .'<path d="M32 18 v28 M18 32 h28" stroke-width="1.6"/>',
        'sugar' =>
            '<rect x="20" y="24" width="24" height="18" rx="3"/><path d="M26 24 v-4 h12 v4"/>',
        'matchapowder' =>
            '<rect x="18" y="26" width="28" height="20" rx="4"/><path d="M24 26 v-6 h16 v6"/><circle cx="32" cy="36" r="3"/>',
        'whip' =>
            '<path d="M22 30 q10-12 20 0" /><path d="M20 30 h24 l-4 8 h-16 Z"/><circle cx="32" cy="22" r="2.4"/>',

        /* Customer characters are rendered from public/pic/anh*.png (see game.js),
           so the old c1–c6 bust SVGs were removed. 'a1' is the USER avatar. */
        'a1' => '<circle cx="32" cy="22" r="11"/><path d="M14 56 c0-12 8-18 18-18 s18 6 18 18"/>',
        'unknown' => '<circle cx="32" cy="22" r="11" stroke-dasharray="4 4"/><path d="M14 56 c0-12 8-18 18-18 s18 6 18 18" stroke-dasharray="4 4"/><path d="M28 20 q4-6 8 0 q0 4-4 5"/><circle cx="32" cy="30" r="1"/>',

        /* ---------- MISC ICONS ---------- */
        'coin' => '<circle cx="32" cy="32" r="18"/><circle cx="32" cy="32" r="12" stroke-width="1.8"/><path d="M32 24 v16" stroke-width="1.8"/>',
        'gem'  => '<path d="M20 20 h24 l8 10-20 22-20-22 Z"/><path d="M20 20 l12 10 12-10 M12 30 h40 M32 30 v22"/>',
        'star' => '<path d="M32 14 l6 12 13 2-9 9 2 13-12-6-12 6 2-13-9-9 13-2 Z"/>',
        'crown'=> '<path d="M14 44 l-2-22 12 10 8-16 8 16 12-10-2 22 Z"/><path d="M14 50 h36"/>',
        'flame'=> '<path d="M32 12 c8 10 12 14 12 24 a12 12 0 0 1-24 0 c0-6 4-8 6-14 c2 4 6 4 6 8 c4-2 2-12-6-18 Z"/>',
        'shield'=> '<path d="M32 12 l16 6 v14 c0 12-8 18-16 22-8-4-16-10-16-22 V18 Z"/><path d="M25 32 l5 5 9-11" stroke-width="2.2"/>',
        'plant'=> '<path d="M24 50 h16 l-2-14 h-12 Z"/><path d="M32 36 c-8-2-12-8-10-16 6 0 10 4 10 10 M32 34 c8-2 12-8 10-16-6 0-10 4-10 10"/>',
        'frame'=> '<rect x="16" y="16" width="32" height="32" rx="3"/><path d="M22 40 l8-10 6 6 4-4 6 8"/><circle cx="26" cy="26" r="3"/>',
        'lamp' => '<path d="M32 12 v8"/><path d="M20 34 c0-8 5-14 12-14 s12 6 12 14 Z"/><path d="M28 40 h8"/>',
        'board'=> '<rect x="14" y="16" width="36" height="26" rx="3"/><path d="M20 24 h16 M20 30 h20 M20 36 h12"/><path d="M26 42 v6 M38 42 v6"/>',
        'clock'=> '<circle cx="32" cy="32" r="18"/><path d="M32 22 v10 l7 5"/>',
        'rug'  => '<rect x="12" y="24" width="40" height="18" rx="3"/><path d="M18 24 v18 M46 24 v18" stroke-width="1.6"/>',
        'table'=> '<path d="M14 26 h36"/><path d="M18 26 v22 M46 26 v22"/><path d="M14 30 h36" stroke-width="1.6"/>',
        'chair'=> '<path d="M22 14 v34 M22 30 h16"/><path d="M38 14 v34"/><path d="M22 48 h16"/>',
        'sofa' => '<path d="M14 30 v16 h36 V30"/><path d="M14 30 a4 4 0 0 1 8 0 v6 h20 v-6 a4 4 0 0 1 8 0"/><path d="M18 46 v6 M46 46 v6"/>',
        'awning'=> '<path d="M12 20 h40 l-4 12 H16 Z"/><path d="M20 20 l-2 12 M28 20 l-1 12 M36 20 l1 12 M44 20 l2 12"/>',
        'sign' => '<rect x="16" y="20" width="32" height="18" rx="4"/><path d="M32 38 v8"/><path d="M22 29 h8 M36 26 v6" stroke-width="1.8"/>',
        'facade'=> '<rect x="14" y="16" width="36" height="34" rx="2"/><path d="M14 26 h36 M22 26 v24 M38 26 v24 M14 38 h36"/>',
        'staff'=> '<circle cx="32" cy="20" r="8"/><path d="M18 50 c0-10 6-16 14-16 s14 6 14 16"/><path d="M24 40 h16" stroke-width="1.6"/>',
        'menu' => '<path d="M16 22 h32 M16 32 h32 M16 42 h32"/>',
        'gear' => '<circle cx="32" cy="32" r="8"/><path d="M32 14 v6 M32 44 v6 M14 32 h6 M44 32 h6 M20 20 l4 4 M40 40 l4 4 M44 20 l-4 4 M24 40 l-4 4"/>',
        'pause'=> '<path d="M24 18 v28 M40 18 v28"/>',
        'play' => '<path d="M24 16 l24 16-24 16 Z"/>',
        /* ---- minimalist nav icons (sidebar) ---- */
        'box'  => '<path d="M32 12 L52 22 L32 32 L12 22 Z"/><path d="M12 22 v20 l20 10 V32"/><path d="M52 22 v20 l-20 10"/>',
        'chart'=> '<path d="M14 14 v36 h36"/><path d="M22 44 v-8 M32 44 v-16 M42 44 v-24"/>',
        'trophy'=> '<path d="M22 15 h20 v9 a10 10 0 0 1-20 0 Z"/><path d="M22 19 h-6 a6 6 0 0 0 6 8 M42 19 h6 a6 6 0 0 1-6 8"/><path d="M27 42 h10 M24 49 h16 M32 34 v8"/>',
        'medal'=> '<circle cx="32" cy="40" r="12"/><path d="M25 10 l7 15 M39 10 l-7 15"/><path d="M32 35 l2.2 4.5 5 .7-3.6 3.5.8 5-4.4-2.4-4.4 2.4.8-5-3.6-3.5 5-.7 Z" stroke-width="1.5"/>',
        'scan' => '<path d="M14 24 V18 a4 4 0 0 1 4-4 h6 M50 24 V18 a4 4 0 0 0-4-4 h-6 M14 40 v6 a4 4 0 0 0 4 4 h6 M50 40 v6 a4 4 0 0 0-4 4 h-6"/><path d="M18 32 h28"/>',
        'book' => '<path d="M32 18 v32"/><path d="M32 18 C 26 14, 18 14, 14 16 V46 c 4-2 12-2 18 2 6-4 14-4 18-2 V16 c-4-2-12-2-18 2"/>',
        'power'=> '<path d="M32 14 v16"/><path d="M22 21 a14 14 0 1 0 20 0"/>',
        'happy'=> '<circle cx="32" cy="32" r="18"/><circle cx="26" cy="28" r="1.6"/><circle cx="38" cy="28" r="1.6"/><path d="M24 38 q8 6 16 0"/>',
        'up'   => '<path d="M32 16 v32 M20 28 l12-12 12 12"/>',
        ];
    }
}
