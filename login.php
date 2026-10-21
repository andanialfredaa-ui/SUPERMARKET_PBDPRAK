<?php
require __DIR__ . '/lib.php';
csrf_check();

if (user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare(
        "SELECT u.iduser, u.username, r.nama_role
           FROM `user` u JOIN role r ON r.idrole = u.idrole
          WHERE u.username = ? AND u.password = SHA2(?, 256)"
    );
    $st->execute([trim($_POST['username'] ?? ''), $_POST['password'] ?? '']);
    $row = $st->fetch();
    if ($row) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'       => (int) $row['iduser'],
            'username' => $row['username'],
            'role'     => $row['nama_role'],
        ];
        redirect('index.php');
    }
    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - <?= e(NAMA_TOKO) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="loginbox">
  <div class="card">
    <h1>&#128722; <?= e(NAMA_TOKO) ?></h1>
    <p class="hint">Sistem Informasi Supermarket</p>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <p><button class="btn" style="width:100%;margin-top:14px">Masuk</button></p>
    </form>
    <p class="hint">Akun contoh: <b>admin / admin123</b> (semua menu) dan <b>kasir / kasir123</b> (kasir dan kartu stok).</p>
  </div>
</div>
</body>
</html>
