<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
csrf_check();

if (user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare(
        "SELECT u.iduser, u.username, r.nama_role
           FROM `user` u JOIN role r ON r.idrole = u.idrole
          WHERE u.username = ? AND u.password = SHA2(?, 256) AND u.status = 1"
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
<script src="assets/app.js" defer></script>
</head>
<body>
<?php render_decor(true); ?>
<div class="loginbox">
  <div class="card">
    <div style="text-align:center">
      <span class="dotted ink logo"><?= e(NAMA_TOKO) ?></span>
      <span class="tag">supermarket &#9829;</span>
    </div>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <p><button class="btn" style="width:100%;margin-top:16px;padding:13px">Masuk</button></p>
    </form>
    <p class="hint">Akun contoh: <b>admin / admin123</b> (semua menu) dan <b>kasir / kasir123</b> (kasir dan kartu stok).</p>
    <?php render_stars(); ?>
  </div>
</div>
</body>
</html>
