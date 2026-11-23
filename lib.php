<?php
require_once __DIR__ . '/config.php';

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function angka($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// pesan singkat (flash): flash('ok','teks') untuk set, flash() untuk ambil
function flash(?string $type = null, ?string $msg = null): array
{
    if ($type !== null) {
        $_SESSION['flash'][] = [$type, $msg];
        return [];
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (user()['role'] ?? '') === 'Admin';
}

function require_login(): void
{
    if (!user()) {
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Halaman ini hanya bisa dibuka oleh Admin.');
        redirect('index.php');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_t" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_t'] ?? '')) {
            http_response_code(400);
            die('Sesi tidak valid. Kembali lalu muat ulang halaman.');
        }
    }
}

function stok_sql(): string
{
<<<<<<< HEAD
    // menggunakan function database fn_stok_barang(idbarang)
    return "fn_stok_barang(b.idbarang)";
=======
    // stok terakhir sebuah barang = baris terakhir pada kartu_stok
    return "IFNULL((SELECT k.stock FROM kartu_stok k WHERE k.idbarang = b.idbarang
                    ORDER BY k.idkartu_stok DESC LIMIT 1), 0)";
>>>>>>> a6735c9e4901add495a1f14cc63b8bc56d26ab18
}
