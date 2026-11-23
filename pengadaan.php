<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_login();
csrf_check();

$pdo = db();
$uid = (int) user()['id'];

$vendors = $pdo->query("SELECT idvendor, nama_vendor, badan_hukum FROM v_vendor_aktif ORDER BY nama_vendor")->fetchAll();
$barangs = $pdo->query(
    "SELECT b.idbarang, b.nama, b.harga, s.nama_satuan, " . stok_sql() . " AS stok
       FROM barang b JOIN satuan s ON s.idsatuan = b.idsatuan
      WHERE b.status = 1 ORDER BY b.nama"
)->fetchAll();

// =====================================================================
// PROSES BUAT PENGADAAN BARU (status = 'P' Proses)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $idvendor = (int) ($_POST['idvendor'] ?? 0);
        if ($idvendor <= 0) {
            throw new RuntimeException('Silakan pilih vendor terlebih dahulu.');
        }

        $ids = $_POST['idbarang'] ?? [];
        $qtys = $_POST['jumlah'] ?? [];
        $hargas = $_POST['harga_satuan'] ?? [];

        $items = [];
        for ($i = 0; $i < count($ids); $i++) {
            $idb = (int) ($ids[$i] ?? 0);
            $jml = (int) ($qtys[$i] ?? 0);
            $hrg = (int) ($hargas[$i] ?? 0);
            if ($idb > 0 && $jml > 0 && $hrg >= 0) {
                if (isset($items[$idb])) {
                    $items[$idb]['jumlah'] += $jml;
                    $items[$idb]['subtotal'] = $items[$idb]['jumlah'] * $items[$idb]['harga'];
                } else {
                    $items[$idb] = [
                        'idbarang' => $idb,
                        'jumlah'   => $jml,
                        'harga'    => $hrg,
                        'subtotal' => $jml * $hrg,
                    ];
                }
            }
        }

        if (!$items) {
            throw new RuntimeException('Pilih minimal 1 barang dengan jumlah pengadaan lebih dari 0.');
        }

        $pdo->beginTransaction();

        $sub = array_sum(array_column($items, 'subtotal'));
        $stPpn = $pdo->prepare("SELECT fn_hitung_ppn(?)");
        $stPpn->execute([$sub]);
        $ppn = (int) $stPpn->fetchColumn();
        $tot = $sub + $ppn;

        $insPg = $pdo->prepare(
            "INSERT INTO pengadaan (user_iduser, status, vendor_idvendor, subtotal_nilai, ppn, total_nilai)
             VALUES (?, 'P', ?, ?, ?, ?)"
        );
        $insPg->execute([$uid, $idvendor, $sub, $ppn, $tot]);
        $idpg = (int) $pdo->lastInsertId();

        $insDp = $pdo->prepare(
            "INSERT INTO detail_pengadaan (harga_satuan, jumlah, sub_total, idbarang, idpengadaan)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($items as $it) {
            $insDp->execute([$it['harga'], $it['jumlah'], $it['subtotal'], $it['idbarang'], $idpg]);
        }

        $pdo->commit();
        flash('ok', "Pengadaan #{$idpg} berhasil dibuat (status: Proses). Silakan lanjutkan ke menu Penerimaan Stok saat barang tiba.");
        redirect('pengadaan.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Gagal menyimpan pengadaan: ' . $e->getMessage());
        redirect('pengadaan.php');
    }
}

// =====================================================================
// DAFTAR PENGADAAN & DETAIL
// =====================================================================
$listPg = $pdo->query(
    "SELECT p.idpengadaan, p.timestamp, p.status, p.subtotal_nilai, p.ppn, p.total_nilai,
            v.nama_vendor, u.username,
            (SELECT COUNT(*) FROM detail_pengadaan dp WHERE dp.idpengadaan = p.idpengadaan) AS jml_item
       FROM pengadaan p
       JOIN vendor v ON v.idvendor = p.vendor_idvendor
       JOIN `user` u ON u.iduser = p.user_iduser
      ORDER BY p.idpengadaan DESC LIMIT 25"
)->fetchAll();

$detailView = null;
$detailItems = [];
$viewId = (int) ($_GET['id'] ?? 0);
if ($viewId > 0) {
    $st = $pdo->prepare(
        "SELECT p.*, v.nama_vendor, u.username
           FROM pengadaan p
           JOIN vendor v ON v.idvendor = p.vendor_idvendor
           JOIN `user` u ON u.iduser = p.user_iduser
          WHERE p.idpengadaan = ?"
    );
    $st->execute([$viewId]);
    $detailView = $st->fetch();
    if ($detailView) {
        $stIt = $pdo->prepare(
            "SELECT dp.*, b.nama AS nama_barang, s.nama_satuan,
                    IFNULL((SELECT SUM(dpr.jumlah_terima)
                              FROM detail_penerimaan dpr
                              JOIN penerimaan pr ON pr.idpenerimaan = dpr.idpenerimaan
                             WHERE pr.idpengadaan = dp.idpengadaan AND dpr.barang_idbarang = dp.idbarang), 0) AS sudah_diterima
               FROM detail_pengadaan dp
               JOIN barang b ON b.idbarang = dp.idbarang
               JOIN satuan s ON s.idsatuan = b.idsatuan
              WHERE dp.idpengadaan = ?"
        );
        $stIt->execute([$viewId]);
        $detailItems = $stIt->fetchAll();
    }
}

render_header('Pengadaan Barang', 'pengadaan');
?>

<?php if ($detailView): ?>
<div class="card">
  <h2>Detail Pengadaan #<?= (int) $detailView['idpengadaan'] ?></h2>
  <p class="hint">
    Vendor: <b><?= e($detailView['nama_vendor']) ?></b> &middot;
    Dibuat oleh: <b><?= e($detailView['username']) ?></b> &middot;
    Waktu: <?= e($detailView['timestamp']) ?> &middot;
    Status: <span class="badge <?= $detailView['status'] === 'S' ? 'b-ok' : 'b-ret' ?>"><?= $detailView['status'] === 'S' ? 'Selesai' : 'Proses' ?></span>
  </p>
  <div class="tablewrap" style="margin-top:12px">
    <table>
      <tr><th>Barang</th><th class="num">Pesan</th><th class="num">Sudah Diterima</th><th class="num">Harga Satuan</th><th class="num">Subtotal</th></tr>
      <?php foreach ($detailItems as $di): ?>
        <tr>
          <td><?= e($di['nama_barang']) ?></td>
          <td class="num"><?= angka($di['jumlah']) ?> <?= e($di['nama_satuan']) ?></td>
          <td class="num"><?= angka($di['sudah_diterima']) ?> <?= e($di['nama_satuan']) ?></td>
          <td class="num"><?= rupiah($di['harga_satuan']) ?></td>
          <td class="num"><?= rupiah($di['sub_total']) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr><td colspan="4" class="num">Subtotal</td><td class="num"><?= rupiah($detailView['subtotal_nilai']) ?></td></tr>
      <tr><td colspan="4" class="num">PPN <?= PPN_PERSEN ?>%</td><td class="num"><?= rupiah($detailView['ppn']) ?></td></tr>
      <tr><td colspan="4" class="num"><b>Total</b></td><td class="num"><b><?= rupiah($detailView['total_nilai']) ?></b></td></tr>
    </table>
  </div>
  <p style="margin-top:14px">
    <?php if ($detailView['status'] !== 'S'): ?>
      <a class="btn amber" href="penerimaan.php?idpengadaan=<?= (int) $detailView['idpengadaan'] ?>">Terima Barang Sekarang</a>
    <?php endif; ?>
    <a class="btn light" href="pengadaan.php">Tutup Detail</a>
  </p>
</div>
<?php endif; ?>

<div class="card">
  <h2>Buat Pengadaan Baru (Purchase Order ke Vendor)</h2>
  <form method="post">
    <?= csrf_field() ?>
    <div class="row">
      <div>
        <label for="idvendor">Vendor Aktif</label>
        <select name="idvendor" id="idvendor" required>
          <option value="">-- Pilih vendor --</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= (int) $v['idvendor'] ?>"><?= e($v['nama_vendor']) ?> (<?= e($v['badan_hukum']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div id="pg_list" style="margin-top:8px">
      <div class="row pg-item">
        <div>
          <label>Barang</label>
          <select name="idbarang[]" onchange="isiHargaPg(this)" required>
            <option value="">-- Pilih barang --</option>
            <?php foreach ($barangs as $b): ?>
              <option value="<?= (int) $b['idbarang'] ?>" data-harga="<?= (int) $b['harga'] ?>">
                <?= e($b['nama']) ?> (stok saat ini: <?= angka($b['stok']) ?> <?= e($b['nama_satuan']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="max-width:150px">
          <label>Jumlah Pesan</label>
          <input type="number" name="jumlah[]" min="1" placeholder="0" required>
        </div>
        <div style="max-width:200px">
          <label>Harga Beli Satuan (Rp)</label>
          <input type="number" name="harga_satuan[]" min="0" placeholder="0" required>
        </div>
        <div class="fit">
          <button type="button" class="btn small danger" onclick="hapusBarisPg(this)">Hapus</button>
        </div>
      </div>
    </div>

    <p style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
      <button type="button" class="btn light" onclick="tambahBarisPg()">+ Tambah Baris Barang</button>
      <button type="submit" class="btn amber">Simpan Pengadaan</button>
    </p>
    <p class="hint">Pengadaan tersimpan dengan status <b>Proses (P)</b>. Stok akan bertambah setelah barang diterima di menu <b>Penerimaan Stok</b>.</p>
  </form>
</div>

<div class="card" style="padding:0">
  <div style="padding:14px 18px 0"><h2>Daftar Pengadaan</h2></div>
  <div class="tablewrap">
    <table>
      <tr>
        <th>ID</th><th>Waktu</th><th>Vendor</th><th>Admin/User</th>
        <th class="num">Item</th><th class="num">Total Nilai</th><th>Status</th><th></th>
      </tr>
      <?php foreach ($listPg as $r): ?>
        <tr>
          <td>#<?= (int) $r['idpengadaan'] ?></td>
          <td><?= e($r['timestamp']) ?></td>
          <td><?= e($r['nama_vendor']) ?></td>
          <td><?= e($r['username']) ?></td>
          <td class="num"><?= angka($r['jml_item']) ?></td>
          <td class="num"><?= rupiah($r['total_nilai']) ?></td>
          <td>
            <span class="badge <?= $r['status'] === 'S' ? 'b-ok' : 'b-ret' ?>">
              <?= $r['status'] === 'S' ? 'Selesai' : 'Proses' ?>
            </span>
          </td>
          <td class="act">
            <a class="btn small light" href="pengadaan.php?id=<?= (int) $r['idpengadaan'] ?>">Detail</a>
            <?php if ($r['status'] !== 'S'): ?>
              <a class="btn small" href="penerimaan.php?idpengadaan=<?= (int) $r['idpengadaan'] ?>">Terima</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$listPg): ?><tr><td colspan="8" class="empty">Belum ada data pengadaan.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<script>
function isiHargaPg(sel) {
  var opt = sel.options[sel.selectedIndex];
  var h = opt ? opt.getAttribute('data-harga') : '';
  var row = sel.closest('.pg-item');
  var inp = row.querySelector('input[name="harga_satuan[]"]');
  if (h !== null && h !== '' && (!inp.value || inp.value === '0')) {
    inp.value = h;
  }
}
function tambahBarisPg() {
  var wrap = document.getElementById('pg_list');
  var first = wrap.querySelector('.pg-item');
  var clone = first.cloneNode(true);
  clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
  clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
  wrap.appendChild(clone);
}
function hapusBarisPg(btn) {
  var wrap = document.getElementById('pg_list');
  if (wrap.querySelectorAll('.pg-item').length > 1) {
    btn.closest('.pg-item').remove();
  }
}
</script>
<?php render_footer();
