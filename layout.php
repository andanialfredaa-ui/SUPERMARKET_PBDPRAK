<?php
function render_header(string $title, string $active = ''): void
{
    $u = user();
    $masters = [
        'satuan' => 'Satuan',
        'barang' => 'Barang',
        'vendor' => 'Vendor',
        'role'   => 'Role',
        'user'   => 'User',
        'margin' => 'Margin Penjualan',
    ];
    $cls = fn(string $k) => $active === $k ? ' class="on"' : '';
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - <?= e(NAMA_TOKO) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <aside class="side">
    <div class="brand"><span class="logo">&#128722;</span> <?= e(NAMA_TOKO) ?></div>
    <nav>
      <div class="sec">Menu</div>
      <a href="index.php"<?= $cls('dashboard') ?>>Dashboard</a>
      <a href="kasir.php"<?= $cls('kasir') ?>>Kasir</a>
      <a href="kartu_stok.php"<?= $cls('kartu_stok') ?>>Kartu Stok</a>
      <?php if (is_admin()): ?>
        <div class="sec">Data Master</div>
        <?php foreach ($masters as $k => $label): ?>
          <a href="master.php?m=<?= $k ?>"<?= $cls('master:' . $k) ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <div class="me">
      <div><b><?= e($u['username'] ?? '') ?></b><br><small><?= e($u['role'] ?? '') ?></small></div>
      <a class="btn small ghost" href="logout.php">Keluar</a>
    </div>
  </aside>
  <main class="main">
    <header class="top"><h1><?= e($title) ?></h1></header>
    <?php foreach (flash() as [$type, $msg]): ?>
      <div class="alert <?= $type === 'ok' ? 'ok' : 'err' ?>"><?= e($msg) ?></div>
    <?php endforeach; ?>
<?php
}

function render_footer(): void
{
    ?>
  </main>
</div>
</body>
</html>
<?php
}
