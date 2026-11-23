<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_login();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$st = $pdo->prepare(
    "SELECT p.*, u.username, m.persen
       FROM penjualan p
       JOIN `user` u ON u.iduser = p.iduser
       JOIN margin_penjualan m ON m.idmargin_penjualan = p.idmargin_penjualan
      WHERE p.idpenjualan = ?"
);
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    flash('error', 'Nota tidak ditemukan.');
    redirect('kasir.php');
}

$st = $pdo->prepare(
    "SELECT d.harga_satuan, d.jumlah, d.subtotal, b.nama, s.nama_satuan
       FROM detail_penjualan d
       JOIN barang b ON b.idbarang = d.idbarang
       JOIN satuan s ON s.idsatuan = b.idsatuan
      WHERE d.penjualan_idpenjualan = ?
      ORDER BY d.iddetail_penjualan"
);
$st->execute([$id]);
$items = $st->fetchAll();

render_header('Nota #' . $id, 'kasir');
?>
<div class="card nota">
  <div class="c"><b><?= e(NAMA_TOKO) ?></b><br>Struk Penjualan</div>
  <hr>
  <div class="l"><span>No</span><span>#<?= $id ?></span></div>
  <div class="l"><span>Waktu</span><span><?= e($p['created_at']) ?></span></div>
  <div class="l"><span>Kasir</span><span><?= e($p['username']) ?></span></div>
  <hr>
  <?php foreach ($items as $it): ?>
    <div><?= e($it['nama']) ?></div>
    <div class="l">
      <span><?= angka($it['jumlah']) ?> <?= e($it['nama_satuan']) ?> x <?= angka($it['harga_satuan']) ?></span>
      <span><?= angka($it['subtotal']) ?></span>
    </div>
  <?php endforeach; ?>
  <hr>
  <div class="l"><span>Subtotal</span><span><?= rupiah($p['subtotal_nilai']) ?></span></div>
  <div class="l"><span>PPN <?= PPN_PERSEN ?>%</span><span><?= rupiah($p['ppn']) ?></span></div>
  <div class="l"><b>Total</b><b><?= rupiah($p['total_nilai']) ?></b></div>
  <hr>
  <div class="c">Margin <?= e($p['persen']) ?>% &middot; Terima kasih</div>
</div>
<p class="noprint">
  <button class="btn" onclick="window.print()">Cetak</button>
  <a class="btn light" href="kasir.php">Transaksi baru</a>
</p>
<?php render_footer();
