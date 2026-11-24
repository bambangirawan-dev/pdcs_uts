<?php
/**
 * Konfigurasi koneksi database MySQL.
 * Kalau koneksi gagal, API mengirim JSON dengan HTTP 500,
 * bukan pesan error HTML bawaan PHP.
 *
 * Password lokal ditaruh di config.local.php (tidak ikut di-commit).
 * Salin config.local.example.php menjadi config.local.php lalu isi sesuai MySQL kamu.
 */
require_once __DIR__ . "/helpers/response.php";

mysqli_report(MYSQLI_REPORT_OFF);

$config = [
    "host" => "localhost",
    "user" => "root",
    "pass" => "",
    "db"   => "pdcs",
    "port" => 3306,
];

if (file_exists(__DIR__ . "/config.local.php")) {
    $config = array_merge($config, require __DIR__ . "/config.local.php");
}

$host = getenv("DB_HOST") ?: $config["host"];
$user = getenv("DB_USER") ?: $config["user"];
$pass = getenv("DB_PASS") ?: $config["pass"];
$db   = getenv("DB_NAME") ?: $config["db"];
$port = (int) (getenv("DB_PORT") ?: $config["port"]);

$koneksi = @mysqli_connect($host, $user, $pass, $db, $port);

if (!$koneksi) {
    sendResponse(
        false,
        "Koneksi database gagal",
        null,
        500
    );
}

mysqli_set_charset($koneksi, "utf8mb4");
