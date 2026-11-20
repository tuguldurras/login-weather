<?php
$cfg = require __DIR__ . "/config.php";
$pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=hereglegch_db;charset=utf8mb4",
    $cfg["db_user"],
    $cfg["db_pass"],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
