<?php /** Minimal layout — auth & landing pages. */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? APP_NAME) ?> — <?= APP_NAME ?></title>
  <link rel="icon" href="<?= asset('pic/coffeemascot.jpg') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/game.css') ?>">
</head>
<body>
  <?= $content ?>
</body>
</html>
