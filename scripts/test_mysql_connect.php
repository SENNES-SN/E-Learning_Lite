<?php

$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "OK\n";
} catch (PDOException $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
