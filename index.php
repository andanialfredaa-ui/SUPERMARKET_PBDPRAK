<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_login();

$pdo = db();
$jmlBarang  = (int) $pdo->query("SELECT COUNT(*) FROM barang WHERE status = 1")->fetchColumn();
$jmlVendor  = (int) $pdo->query("SELECT COUNT(*) FROM vendor WHERE status = 'A'")->fetchColumn();
$hariIni    = $pdo->query("SELECT COUNT(*) n, IFNULL(SUM(total_nilai),0) t FROM penjualan WHERE DATE(created_at) = CURDATE()")->fetch();
$margin     = $pdo->query("SELECT persen FROM v_margin_aktif LIMIT 1")->fetch();

$menipis = $pdo->query(
    "SELECT b.nama, s.nama_satuan, " . stok_sql() . " AS stok
       FROM barang b JOIN satuan s ON s.idsatuan = b.idsatuan
      WHERE b.status = 1
     HAVING stok <= 10
      ORDER BY stok, b.nama LIMIT 8"
)->fetchAll();

$terakhir = $pdo->query(
    "SELECT p.idpenjualan, p.created_at, p.total_nilai, u.username
       FROM penjualan p JOIN `user` u ON u.iduser = p.iduser
      ORDER BY p.idpenjualan DESC LIMIT 5"
)->fetchAll();

render_header('Dashboard', 'dashboard');
?>
<div class="grid">
  <div class="stat"><div class="n"><?= angka($jmlBarang) ?></div><div class="l">Barang aktif</div></div>
  <div class="stat"><div class="n"><?= angka($jmlVendor) ?></div><div class="l">Vendor aktif</div></div>
  <div class="stat"><div class="n"><?= rupiah($hariIni['t']) ?></div><div class="l">Penjualan hari ini (<?= angka($hariIni['n']) ?> transaksi)</div></div>
  <div class="stat"><div class="n"><?= $margin ? e($margin['persen']) . '%' : '-' ?></div><div class="l">Margin penjualan aktif</div></div>
</div>

<div class="kasir" style="grid-template-columns:1fr 1fr">
  <div class="card">
    <h2>Stok menipis (10 atau kurang)</h2>
    <div class="tablewrap"><table>
      <tr><th>Barang</th><th class="num">Stok</th></tr>
      <?php foreach ($menipis as $r): ?>
        <tr><td><?= e($r['nama']) ?></td><td class="num"><?= angka($r['stok']) ?> <?= e($r['nama_satuan']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$menipis): ?><tr><td colspan="2" class="empty">Semua stok aman.</td></tr><?php endif; ?>
    </table></div>
  </div>
  <div class="card">
    <h2>Penjualan terakhir</h2>
    <div class="tablewrap"><table>
      <tr><th>No</th><th>Waktu</th><th>Kasir</th><th class="num">Total</th></tr>
      <?php foreach ($terakhir as $r): ?>
        <tr>
          <td><a href="nota.php?id=<?= (int) $r['idpenjualan'] ?>">#<?= (int) $r['idpenjualan'] ?></a></td>
          <td><?= e($r['created_at']) ?></td><td><?= e($r['username']) ?></td>
          <td class="num"><?= rupiah($r['total_nilai']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$terakhir): ?><tr><td colspan="4" class="empty">Belum ada penjualan.</td></tr><?php endif; ?>
    </table></div>
  </div>
</div>
<?php render_footer();
