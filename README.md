# ☕ Brew Master — Coffee Shop Management Game

A fast-paced, browser-based coffee-shop time-management game inspired by the
*Cooking Fever* style of play, rebuilt around an original coffee theme with a
**monochrome, flat, rounded, cute-casual** visual identity. 100% original art
(inline SVG + hand-drawn mascot) and procedurally-synthesized audio — **no
copyrighted assets**.

Built with **PHP 8 + MySQL (PDO)** in an **MVC** structure, **Tailwind-style
custom CSS**, and **vanilla ES6** with an **AJAX** API.

---

## Gameplay

Customers walk in (max 5 at once), each ordering 1–3 drinks with a ticking
patience bar. You:

1. **New Cup** → 2. tap ingredient **stations** (espresso, milk, foam, ice,
syrups, matcha, whip…) to build the drink → 3. tap the **matching customer** to
serve.

The server validates the recipe, consumes inventory, pays out coins + tips
(scaled by speed & combo), updates satisfaction, levels you up, unlocks recipes,
and checks achievements. Wrong drinks pay little and break your combo. Run a
station dry and you must **restock**. Earn coins to buy **upgrades** (faster/
bigger machines, more patience, more income) and **decorations** (raise
satisfaction). Track everything in **Statistics**, climb the **Leaderboard**,
and use the **AI Coffee Scan** to identify a drink from a photo.

The day runs 09:00 → 18:00, then auto-advances. Progress **auto-saves** every
20s and on exit.

---

## Requirements

- XAMPP (or any) with **PHP 8+** and **MySQL / MariaDB**
- No Composer / npm needed. (Optional: PHP **GD** extension improves AI-scan
  image analysis; the game works without it.)

## Setup

1. Place this folder at `c:/xampp/htdocs/CoffeeGame_PLT` (already here).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Import the database:
   ```
   c:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   ```
   (or run `database/schema.sql` in phpMyAdmin).
4. If your MySQL root has a password, set it in
   [`app/config/config.php`](app/config/config.php) (`DB_PASS`).
5. Open **http://localhost/CoffeeGame_PLT/public/** and create an account.

To reset the whole game world, re-run `database/schema.sql`.

---

## Architecture (MVC)

```
app/
  config/      config.php (DB + auto BASE_URL)
  core/        Database (PDO), Model, Controller, Router, helpers, svg loader
  controllers/ Auth, Page, Game, Api (AJAX), Aiscan
  models/      User, GameState (core logic), AiScan
  views/       layouts/ auth/ pages/ game/ partials/(svg art)
public/
  index.php    front controller
  assets/css/  game.css (monochrome theme)
  assets/js/   api, audio (Web Audio SFX+music), ui, game (engine), panels, aiscan
  pic/         mascot + reference art
  uploads/     AI-scan uploads (auto-created)
database/schema.sql
```

### Routing
Front controller pattern: `index.php?url=controller/action/params`.
Works with or without `mod_rewrite`. `BASE_URL` is auto-detected so it runs from
any sub-folder. AJAX endpoints live under `?url=api/...`.

### Database (18 tables + join tables)
`users, coffee_shop, game_progress, settings, ingredients, drinks, recipes,
inventory, customers, orders, order_details, transactions, revenue, upgrades
(+user_upgrades), decorations (+user_decorations), achievements
(+user_achievements), leaderboard, ai_scan_history` — all InnoDB with foreign
keys. Recipes are stored as ordered ingredient steps and validated server-side.

---

## Notes on originality
- All artwork is original inline SVG; the mascot is a hand-drawn line drawing.
- All sound is generated at runtime with the Web Audio API (no audio files).
- The reference image was used only for layout/style direction.
