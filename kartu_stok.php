<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_login();

$pdo = db();
$filter = (int) ($_GET['b'] ?? 0);

$ringkas = $pdo->query(
    "SELECT b.idbarang, b.nama, s.nama_satuan, " . stok_sql() . " AS stok
       FROM barang b JOIN satuan s ON s.idsatuan = b.idsatuan
      ORDER BY b.nama"
)->fetchAll();

$sql = "SELECT k.idkartu_stok, k.created_at, b.nama, k.jenis_transaksi, k.masuk, k.keluar, k.stock, k.idtransaksi
          FROM kartu_stok k JOIN barang b ON b.idbarang = k.idbarang";
$args = [];
if ($filter) {
    $sql .= " WHERE k.idbarang = ?";
    $args[] = $filter;
}
$sql .= " ORDER BY k.idkartu_stok DESC LIMIT 300";
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$jenis = [
    'M' => ['Penerimaan', 'b-in'],
    'K' => ['Penjualan', 'b-out'],
    'R' => ['Retur', 'b-ret'],
];

render_header('Kartu Stok', 'kartu_stok');
?>
<div class="grid">
  <?php foreach ($ringkas as $r): ?>
    <a class="stat<?= $r['stok'] <= 10 ? ' warn' : '' ?>" href="kartu_stok.php?b=<?= (int) $r['idbarang'] ?>" style="text-decoration:none;color:inherit">
      <div class="n"><?= angka($r['stok']) ?> <small style="font-size:13px"><?= e($r['nama_satuan']) ?></small></div>
      <div class="l"><?= e($r['nama']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<div class="toolbar">
  <form method="get" class="row" style="max-width:420px">
    <div>
      <select name="b" onchange="this.form.submit()">
        <option value="0">Semua barang</option>
        <?php foreach ($ringkas as $r): ?>
          <option value="<?= (int) $r['idbarang'] ?>"<?= $filter === (int) $r['idbarang'] ? ' selected' : '' ?>><?= e($r['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
  <span class="hint">Kartu stok diisi otomatis oleh trigger saat ada penerimaan, penjualan, dan retur.</span>
</div>

<div class="card" style="padding:0">
  <div class="tablewrap"><table>
    <tr><th>Waktu</th><th>Barang</th><th>Jenis</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Stok</th><th>Ref. transaksi</th></tr>
    <?php foreach ($rows as $r): [$lbl, $cls] = $jenis[$r['jenis_transaksi']] ?? [$r['jenis_transaksi'], 'b-off']; ?>
      <tr>
        <td><?= e($r['created_at']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td><span class="badge <?= $cls ?>"><?= e($lbl) ?></span></td>
        <td class="num"><?= $r['masuk'] ? angka($r['masuk']) : '-' ?></td>
        <td class="num"><?= $r['keluar'] ? angka($r['keluar']) : '-' ?></td>
        <td class="num"><b><?= angka($r['stock']) ?></b></td>
        <td>#<?= (int) $r['idtransaksi'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada pergerakan stok.</td></tr><?php endif; ?>
  </table></div>
</div>
<?php render_footer();
