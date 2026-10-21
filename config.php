<?php
// =====================================================================
// PENGATURAN APLIKASI - sesuaikan bila perlu
// =====================================================================
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'db_uts_penjualan';
const DB_USER = 'root';
const DB_PASS = '';            // bawaan Laragon: kosong

const NAMA_TOKO  = 'SuperMart';
const PPN_PERSEN = 11;

date_default_timezone_set('Asia/Jakarta');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h3>Koneksi database gagal</h3><p>Pastikan Laragon (MySQL) sudah Start All dan database <b>'
                . htmlspecialchars(DB_NAME) . '</b> sudah diimport.</p><pre>'
                . htmlspecialchars($e->getMessage()) . '</pre>');
        }
    }
    return $pdo;
}
