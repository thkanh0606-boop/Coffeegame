<?php
/** Convenience redirect: /CoffeeGame_PLT/  ->  /CoffeeGame_PLT/public/ */
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
header('Location: ' . $dir . '/public/index.php');
exit;
