-- ============================================================
--  BREW MASTER — Coffee Shop Management Game
--  MySQL / MariaDB schema + seed data
--  Charset: utf8mb4  Engine: InnoDB (foreign keys)
-- ============================================================

CREATE DATABASE IF NOT EXISTS pltprov1_jindo_plt_coffeegame
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pltprov1_jindo_plt_coffeegame;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ai_scan_history;
DROP TABLE IF EXISTS leaderboard;
DROP TABLE IF EXISTS achievements;
DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS upgrades;
DROP TABLE IF EXISTS user_upgrades;
DROP TABLE IF EXISTS decorations;
DROP TABLE IF EXISTS user_decorations;
DROP TABLE IF EXISTS revenue;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS order_details;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS recipes;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS drinks;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS game_progress;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS coffee_shop;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
--  USERS  (player accounts)
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(40)  NOT NULL UNIQUE,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  avatar        VARCHAR(20)  NOT NULL DEFAULT 'a1',
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  last_login    TIMESTAMP    NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  COFFEE_SHOP  (one shop per user)
-- ------------------------------------------------------------
CREATE TABLE coffee_shop (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  shop_name    VARCHAR(80) NOT NULL DEFAULT 'My Cafe',
  level        INT NOT NULL DEFAULT 1,
  satisfaction INT NOT NULL DEFAULT 100,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_shop_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  GAME_PROGRESS  (the live save state)
-- ------------------------------------------------------------
CREATE TABLE game_progress (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  user_id            INT NOT NULL UNIQUE,
  coins              INT NOT NULL DEFAULT 100,
  gems               INT NOT NULL DEFAULT 0,
  level              INT NOT NULL DEFAULT 1,
  xp                 INT NOT NULL DEFAULT 0,
  current_day        INT NOT NULL DEFAULT 1,
  game_time          INT NOT NULL DEFAULT 0,     -- in-game seconds elapsed today
  best_combo         INT NOT NULL DEFAULT 0,
  total_served       INT NOT NULL DEFAULT 0,
  perfect_served     INT NOT NULL DEFAULT 0,
  wrong_served       INT NOT NULL DEFAULT 0,
  unlocked_recipes   TEXT NULL,                  -- JSON array of drink ids
  updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  SETTINGS  (per-user preferences)
-- ------------------------------------------------------------
CREATE TABLE settings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL UNIQUE,
  music_on    TINYINT(1) NOT NULL DEFAULT 1,
  sfx_on      TINYINT(1) NOT NULL DEFAULT 1,
  music_vol   DECIMAL(3,2) NOT NULL DEFAULT 0.40,
  sfx_vol     DECIMAL(3,2) NOT NULL DEFAULT 0.70,
  difficulty  ENUM('easy','normal','hard') NOT NULL DEFAULT 'normal',
  CONSTRAINT fk_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  INGREDIENTS  (master list)
-- ------------------------------------------------------------
CREATE TABLE ingredients (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(30) NOT NULL UNIQUE,   -- machine/station key
  name         VARCHAR(50) NOT NULL,
  icon         VARCHAR(20) NOT NULL DEFAULT 'bean',
  unit_cost    INT NOT NULL DEFAULT 2,        -- coins per restock unit
  restock_size INT NOT NULL DEFAULT 10,
  station      VARCHAR(30) NOT NULL DEFAULT 'espresso'
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  DRINKS  (menu)
-- ------------------------------------------------------------
CREATE TABLE drinks (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(30) NOT NULL UNIQUE,
  name          VARCHAR(50) NOT NULL,
  icon          VARCHAR(20) NOT NULL DEFAULT 'cup',
  price         INT NOT NULL DEFAULT 5,
  prep_seconds  DECIMAL(4,1) NOT NULL DEFAULT 3.0,
  unlock_level  INT NOT NULL DEFAULT 1,
  is_cold       TINYINT(1) NOT NULL DEFAULT 0,
  description   VARCHAR(160) NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  RECIPES  (ordered steps per drink)
-- ------------------------------------------------------------
CREATE TABLE recipes (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  drink_id      INT NOT NULL,
  ingredient_id INT NOT NULL,
  step_order    INT NOT NULL DEFAULT 1,
  qty           INT NOT NULL DEFAULT 1,
  CONSTRAINT fk_recipe_drink FOREIGN KEY (drink_id) REFERENCES drinks(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipe_ing   FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  INVENTORY  (per-user ingredient stock)
-- ------------------------------------------------------------
CREATE TABLE inventory (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  ingredient_id INT NOT NULL,
  quantity      INT NOT NULL DEFAULT 20,
  capacity      INT NOT NULL DEFAULT 40,
  low_threshold INT NOT NULL DEFAULT 8,
  UNIQUE KEY uq_user_ing (user_id, ingredient_id),
  CONSTRAINT fk_inv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_ing  FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  CUSTOMERS  (archetype pool: patience / tip behaviour)
-- ------------------------------------------------------------
CREATE TABLE customers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(40) NOT NULL,
  avatar        VARCHAR(20) NOT NULL DEFAULT 'c1',
  patience_base INT NOT NULL DEFAULT 20,    -- seconds
  tip_factor    DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  spawn_weight  INT NOT NULL DEFAULT 10
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  ORDERS  (one row per customer order)
-- ------------------------------------------------------------
CREATE TABLE orders (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  customer_id  INT NULL,
  day          INT NOT NULL DEFAULT 1,
  status       ENUM('pending','served','cancelled') NOT NULL DEFAULT 'pending',
  total_price  INT NOT NULL DEFAULT 0,
  tip          INT NOT NULL DEFAULT 0,
  satisfaction INT NOT NULL DEFAULT 100,   -- 0-100 for this order
  combo        INT NOT NULL DEFAULT 0,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_cust FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  ORDER_DETAILS  (drinks within an order)
-- ------------------------------------------------------------
CREATE TABLE order_details (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  drink_id   INT NOT NULL,
  price      INT NOT NULL DEFAULT 0,
  correct    TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_od_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_od_drink FOREIGN KEY (drink_id) REFERENCES drinks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  TRANSACTIONS  (coin ledger: income & expenses)
-- ------------------------------------------------------------
CREATE TABLE transactions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  type       ENUM('income','expense') NOT NULL,
  category   VARCHAR(30) NOT NULL,     -- sale, tip, restock, upgrade, decoration
  amount     INT NOT NULL,
  ref_id     INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_txn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  REVENUE  (daily rollup)
-- ------------------------------------------------------------
CREATE TABLE revenue (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT NOT NULL,
  day              INT NOT NULL,
  gross            INT NOT NULL DEFAULT 0,
  tips             INT NOT NULL DEFAULT 0,
  expenses         INT NOT NULL DEFAULT 0,
  orders_completed INT NOT NULL DEFAULT 0,
  orders_cancelled INT NOT NULL DEFAULT 0,
  customers_served INT NOT NULL DEFAULT 0,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_day (user_id, day),
  CONSTRAINT fk_rev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  UPGRADES  (catalogue) + user_upgrades (owned levels)
-- ------------------------------------------------------------
CREATE TABLE upgrades (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(30) NOT NULL UNIQUE,
  name          VARCHAR(50) NOT NULL,
  category      ENUM('equipment','interior','staff','recipe') NOT NULL DEFAULT 'equipment',
  icon          VARCHAR(20) NOT NULL DEFAULT 'machine',
  max_level     INT NOT NULL DEFAULT 5,
  base_cost     INT NOT NULL DEFAULT 200,
  cost_growth   DECIMAL(3,2) NOT NULL DEFAULT 1.60,
  effect_type   VARCHAR(30) NOT NULL DEFAULT 'speed', -- speed, capacity, patience, income, unlock
  effect_value  DECIMAL(5,2) NOT NULL DEFAULT 0.10,
  description   VARCHAR(160) NULL
) ENGINE=InnoDB;

CREATE TABLE user_upgrades (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  upgrade_id INT NOT NULL,
  level      INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_user_upg (user_id, upgrade_id),
  CONSTRAINT fk_uu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uu_upg  FOREIGN KEY (upgrade_id) REFERENCES upgrades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  DECORATIONS  (shop catalogue) + user_decorations (owned)
-- ------------------------------------------------------------
CREATE TABLE decorations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  code          VARCHAR(30) NOT NULL UNIQUE,
  name          VARCHAR(50) NOT NULL,
  category      ENUM('decor','furniture','exterior') NOT NULL DEFAULT 'decor',
  icon          VARCHAR(20) NOT NULL DEFAULT 'plant',
  cost          INT NOT NULL DEFAULT 150,
  satisfaction_bonus INT NOT NULL DEFAULT 2
) ENGINE=InnoDB;

CREATE TABLE user_decorations (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  decoration_id INT NOT NULL,
  placed        TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_user_dec (user_id, decoration_id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ud_dec  FOREIGN KEY (decoration_id) REFERENCES decorations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  ACHIEVEMENTS  (catalogue) + user_achievements (unlocked)
-- ------------------------------------------------------------
CREATE TABLE achievements (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(30) NOT NULL UNIQUE,
  name         VARCHAR(60) NOT NULL,
  description  VARCHAR(160) NOT NULL,
  icon         VARCHAR(20) NOT NULL DEFAULT 'star',
  metric       VARCHAR(30) NOT NULL,   -- total_served, best_combo, coins, level, perfect_streak, no_wrong
  goal         INT NOT NULL DEFAULT 1,
  reward_coins INT NOT NULL DEFAULT 50
) ENGINE=InnoDB;

CREATE TABLE user_achievements (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  achievement_id INT NOT NULL,
  progress       INT NOT NULL DEFAULT 0,
  unlocked_at    TIMESTAMP NULL,
  UNIQUE KEY uq_user_ach (user_id, achievement_id),
  CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ua_ach  FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  LEADERBOARD  (best snapshot per user)
-- ------------------------------------------------------------
CREATE TABLE leaderboard (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL UNIQUE,
  highest_revenue   INT NOT NULL DEFAULT 0,
  customers_served  INT NOT NULL DEFAULT 0,
  highest_combo     INT NOT NULL DEFAULT 0,
  highest_level     INT NOT NULL DEFAULT 1,
  best_satisfaction INT NOT NULL DEFAULT 0,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_lb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  AI_SCAN_HISTORY  (uploaded coffee image predictions)
-- ------------------------------------------------------------
CREATE TABLE ai_scan_history (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  image_path   VARCHAR(255) NULL,
  drink_name   VARCHAR(50) NOT NULL,
  confidence   DECIMAL(4,1) NOT NULL DEFAULT 0,
  ingredients  TEXT NULL,   -- JSON
  steps        TEXT NULL,   -- JSON
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_scan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Ingredients (stations map to gameplay machines)
INSERT INTO ingredients (code, name, icon, unit_cost, restock_size, station) VALUES
  ('espresso',   'Espresso Shot',  'bean',    3, 10, 'espresso'),
  ('milk',       'Steamed Milk',   'milk',    2, 12, 'frother'),
  ('foam',       'Milk Foam',      'foam',    2, 12, 'frother'),
  ('water',      'Hot Water',      'water',   1, 20, 'espresso'),
  ('ice',        'Ice',            'ice',     1, 20, 'ice'),
  ('chocolate',  'Chocolate',      'choco',   3, 10, 'syrup'),
  ('caramel',    'Caramel Syrup',  'syrup',   3, 10, 'syrup'),
  ('vanilla',    'Vanilla Syrup',  'syrup',   3, 10, 'syrup'),
  ('mint',       'Mint Syrup',     'syrup',   3, 10, 'syrup'),
  ('matcha',     'Matcha Powder',  'matcha',  4, 10, 'syrup'),
  ('whip',       'Whipped Cream',  'whip',    2, 10, 'topping'),
  ('sugar',      'Sugar',          'sugar',   1, 20, 'topping');

-- Drinks
INSERT INTO drinks (code, name, icon, price, prep_seconds, unlock_level, is_cold, description) VALUES
  ('espresso',      'Espresso',       'espresso',   5, 2.0, 1, 0, 'A single bold shot.'),
  ('americano',     'Americano',      'americano',  6, 2.5, 1, 0, 'Espresso stretched with hot water.'),
  ('latte',         'Latte',          'latte',      8, 3.0, 1, 0, 'Espresso with steamed milk.'),
  ('cappuccino',    'Cappuccino',     'cappuccino', 8, 3.5, 2, 0, 'Espresso, milk and a foam cap.'),
  ('flatwhite',     'Flat White',     'flatwhite',  9, 3.5, 3, 0, 'Silky micro-foam over espresso.'),
  ('macchiato',     'Macchiato',      'macchiato',  9, 3.5, 3, 0, 'Espresso marked with foam.'),
  ('mocha',         'Mocha',          'mocha',     10, 4.0, 4, 0, 'Chocolate, espresso and milk.'),
  ('coldbrew',      'Cold Brew',      'coldbrew',   9, 3.0, 5, 1, 'Slow-steeped, poured over ice.'),
  ('vanillalatte',  'Vanilla Latte',  'latte',     11, 4.0, 5, 0, 'Latte kissed with vanilla.'),
  ('caramellatte',  'Caramel Latte',  'latte',     11, 4.0, 6, 0, 'Latte with caramel swirl.'),
  ('matchalatte',   'Matcha Latte',   'matcha',    12, 4.0, 7, 0, 'Matcha whisked with milk.'),
  ('chocomint',     'Chocolate Mint', 'mocha',     13, 5.0, 8, 0, 'Mocha with mint and whipped cream.');

-- Recipes  (ingredient codes resolved via sub-selects)
INSERT INTO recipes (drink_id, ingredient_id, step_order, qty)
SELECT d.id, i.id, x.step, 1 FROM (
  SELECT 'espresso' dc, 'espresso' ic, 1 step UNION ALL
  SELECT 'americano','espresso',1 UNION ALL SELECT 'americano','water',2 UNION ALL
  SELECT 'latte','espresso',1 UNION ALL SELECT 'latte','milk',2 UNION ALL
  SELECT 'cappuccino','espresso',1 UNION ALL SELECT 'cappuccino','milk',2 UNION ALL SELECT 'cappuccino','foam',3 UNION ALL
  SELECT 'flatwhite','espresso',1 UNION ALL SELECT 'flatwhite','milk',2 UNION ALL SELECT 'flatwhite','foam',3 UNION ALL
  SELECT 'macchiato','espresso',1 UNION ALL SELECT 'macchiato','foam',2 UNION ALL
  SELECT 'mocha','espresso',1 UNION ALL SELECT 'mocha','chocolate',2 UNION ALL SELECT 'mocha','milk',3 UNION ALL
  SELECT 'coldbrew','espresso',1 UNION ALL SELECT 'coldbrew','ice',2 UNION ALL SELECT 'coldbrew','water',3 UNION ALL
  SELECT 'vanillalatte','espresso',1 UNION ALL SELECT 'vanillalatte','vanilla',2 UNION ALL SELECT 'vanillalatte','milk',3 UNION ALL
  SELECT 'caramellatte','espresso',1 UNION ALL SELECT 'caramellatte','caramel',2 UNION ALL SELECT 'caramellatte','milk',3 UNION ALL
  SELECT 'matchalatte','matcha',1 UNION ALL SELECT 'matchalatte','milk',2 UNION ALL
  SELECT 'chocomint','espresso',1 UNION ALL SELECT 'chocomint','chocolate',2 UNION ALL SELECT 'chocomint','milk',3 UNION ALL SELECT 'chocomint','mint',4 UNION ALL SELECT 'chocomint','whip',5
) x
JOIN drinks d ON d.code = x.dc
JOIN ingredients i ON i.code = x.ic;

-- Customer archetypes
INSERT INTO customers (name, avatar, patience_base, tip_factor, spawn_weight) VALUES
  ('Regular',   'c1', 22, 1.00, 20),
  ('Student',   'c2', 26, 0.80, 16),
  ('Office',    'c3', 18, 1.30, 14),
  ('Elder',     'c4', 30, 1.10, 10),
  ('Hipster',   'c5', 16, 1.50, 10),
  ('Tourist',   'c6', 20, 1.20, 12);

-- Upgrades
INSERT INTO upgrades (code, name, category, icon, max_level, base_cost, cost_growth, effect_type, effect_value, description) VALUES
  ('espresso_machine','Espresso Machine','equipment','machine',5,300,1.60,'speed',0.12,'Increase espresso making speed.'),
  ('grinder',         'Coffee Grinder',  'equipment','grinder',5,300,1.55,'speed',0.10,'Grind beans faster.'),
  ('frother',         'Milk Frother',    'equipment','frother',5,400,1.60,'capacity',0.15,'Higher milk capacity.'),
  ('ice_machine',     'Ice Machine',     'equipment','ice',    5,350,1.55,'capacity',0.15,'More ice on hand.'),
  ('blender',         'Blender',         'equipment','blender',5,450,1.65,'speed',0.12,'Blend cold drinks faster.'),
  ('cup_storage',     'Cup Storage',     'equipment','cup',    5,250,1.50,'capacity',0.20,'Store more cups.'),
  ('comfy_seats',     'Comfy Seats',     'interior','chair',   5,300,1.55,'patience',0.10,'Customers wait longer.'),
  ('barista',         'Extra Barista',   'staff',   'staff',   3,800,1.80,'income',0.10,'Boost all income.');

-- Decorations (shop)
INSERT INTO decorations (code, name, category, icon, cost, satisfaction_bonus) VALUES
  ('plant',     'Potted Plant',  'decor',     'plant',   150, 2),
  ('wallart',   'Wall Art',      'decor',     'frame',   200, 2),
  ('pendant',   'Pendant Lamp',  'decor',     'lamp',    180, 2),
  ('menuboard', 'Menu Board',    'decor',     'board',   250, 3),
  ('clock',     'Wall Clock',    'decor',     'clock',   300, 3),
  ('rug',       'Cozy Rug',      'decor',     'rug',     400, 4),
  ('table',     'Wood Table',    'furniture', 'table',   220, 3),
  ('chair',     'Bistro Chair',  'furniture', 'chair',   160, 2),
  ('sofa',      'Corner Sofa',   'furniture', 'sofa',    500, 5),
  ('awning',    'Shop Awning',   'exterior',  'awning',  450, 4),
  ('sign',      'Neon Sign',     'exterior',  'sign',    600, 5),
  ('facade',    'Brick Facade',  'exterior',  'facade',  700, 6);

-- Achievements
INSERT INTO achievements (code, name, description, icon, metric, goal, reward_coins) VALUES
  ('serve_10',   'First Shift',      'Serve 10 drinks.',            'cup',   'total_served', 10,   50),
  ('serve_100',  'Rush Hour',        'Serve 100 drinks.',           'cup',   'total_served', 100,  200),
  ('serve_500',  'Coffee Master',    'Serve 500 drinks.',           'crown', 'total_served', 500,  800),
  ('combo_5',    'Perfect Combo',    'Reach a 5x combo.',           'flame', 'best_combo',   5,    100),
  ('combo_10',   'On Fire',          'Reach a 10x combo.',          'flame', 'best_combo',   10,   250),
  ('coins_1000', '1000 Coins',       'Bank 1000 coins.',            'coin',  'coins',        1000, 150),
  ('level_10',   'Level 10',         'Reach player level 10.',      'star',  'level',        10,   300),
  ('no_wrong',   'Flawless Day',     'Serve 20 perfect in a row.',  'shield','perfect_streak',20,  400);

SELECT 'Schema installed.' AS status;
