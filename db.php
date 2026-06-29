<?php
session_start();
$host = '127.0.0.1';
$db   = 'esalary';
$user = 'esalary';
$pass = 'ERbzT3e7rJbEQ8qHUiPe'; // ใส่รหัสผ่านฐานข้อมูลของคุณ

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}
?>