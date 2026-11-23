<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_login();
csrf_check();

$pdo = db();
$uid = (int) user()['id'];
$preselectPg = (int) ($_GET['idpengadaan'] ?? 0);

$vendors = $pdo->query("SELECT idvendor, nama_vendor FROM v_vendor_aktif ORDER BY nama_vendor")->fetchAll();
$barangs = $pdo->query(
    "SELECT b.idbarang, b.nama, b.harga, s.nama_satuan, " . stok_sql() . " AS stok
       FROM barang b JOIN satuan s ON s.idsatuan = b.idsatuan
      WHERE b.status = 1 ORDER BY b.nama"
)->fetchAll();
$pengadaans = $pdo->query(
    "SELECT p.idpengadaan, p.timestamp, p.status, p.total_nilai, v.nama_vendor
       FROM pengadaan p JOIN vendor v ON v.idvendor = p.vendor_idvendor
      ORDER BY (p.status = 'P') DESC, p.idpengadaan DESC LIMIT 30"
)->fetchAll();

// Ambil detail pengadaan jika dipilih
$pgItems = [];
if ($preselectPg > 0) {
    $stPgIt = $pdo->prepare(
        "SELECT dp.idbarang, dp.jumlah AS jumlah_pesan, dp.harga_satuan,
                b.nama AS nama_barang, s.nama_satuan,
                IFNULL((SELECT SUM(dpr.jumlah_terima)
                          FROM detail_penerimaan dpr
                          JOIN penerimaan pr ON pr.idpenerimaan = dpr.idpenerimaan
                         WHERE pr.idpengadaan = dp.idpengadaan AND dpr.barang_idbarang = dp.idbarang), 0) AS sudah_terima
           FROM detail_pengadaan dp
           JOIN barang b ON b.idbarang = dp.idbarang
           JOIN satuan s ON s.idsatuan = b.idsatuan
          WHERE dp.idpengadaan = ?"
    );
    $stPgIt->execute([$preselectPg]);
    $pgItems = $stPgIt->fetchAll();
}

// =====================================================================
// PROSES SIMPAN PENERIMAAN / TAMBAH STOK
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mode = $_POST['mode_pengadaan'] ?? 'baru';
        $idpgExisting = (int) ($_POST['idpengadaan'] ?? 0);
        $idvendor = (int) ($_POST['idvendor'] ?? 0);

        $ids = $_POST['idbarang'] ?? [];
        $qtys = $_POST['jumlah_terima'] ?? [];
        $hargas = $_POST['harga_satuan_terima'] ?? [];

        $items = [];
        for ($i = 0; $i < count($ids); $i++) {
            $idb = (int) ($ids[$i] ?? 0);
            $jml = (int) ($qtys[$i] ?? 0);
            $hrg = (int) ($hargas[$i] ?? 0);
            if ($idb > 0 && $jml > 0 && $hrg >= 0) {
                $items[] = [
                    'idbarang' => $idb,
                    'jumlah'   => $jml,
                    'harga'    => $hrg,
                    'subtotal' => $jml * $hrg,
                ];
            }
        }

        if (!$items) {
            throw new RuntimeException('Pilih minimal 1 barang dengan jumlah masuk lebih dari 0.');
        }

        $pdo->beginTransaction();

        $in = implode(',', array_fill(0, count($items), '?'));
        $stBrg = $pdo->prepare("SELECT idbarang, nama, status FROM barang WHERE idbarang IN ($in) FOR UPDATE");
        $stBrg->execute(array_column($items, 'idbarang'));
        $brgRows = $stBrg->fetchAll(PDO::FETCH_UNIQUE);

        foreach ($items as $it) {
            if (!isset($brgRows[$it['idbarang']])) {
                throw new RuntimeException('Ada barang yang tidak ditemukan.');
            }
            if ((int) $brgRows[$it['idbarang']]['status'] !== 1) {
                throw new RuntimeException($brgRows[$it['idbarang']]['nama'] . ' sedang nonaktif.');
            }
        }

        $sub = array_sum(array_column($items, 'subtotal'));
        $stPpn = $pdo->prepare("SELECT fn_hitung_ppn(?)");
        $stPpn->execute([$sub]);
        $ppn = (int) $stPpn->fetchColumn();
        $tot = $sub + $ppn;

        if ($mode === 'existing' && $idpgExisting > 0) {
            $cekPg = $pdo->prepare("SELECT idpengadaan FROM pengadaan WHERE idpengadaan = ? FOR UPDATE");
            $cekPg->execute([$idpgExisting]);
            if (!$cekPg->fetchColumn()) {
                throw new RuntimeException('Data pengadaan tidak ditemukan.');
            }
            $idpg = $idpgExisting;
        } else {
            if ($idvendor <= 0) {
                throw new RuntimeException('Silakan pilih vendor terlebih dahulu.');
            }
            $insPg = $pdo->prepare(
                "INSERT INTO pengadaan (user_iduser, status, vendor_idvendor, subtotal_nilai, ppn, total_nilai)
                 VALUES (?, 'S', ?, ?, ?, ?)"
            );
            $insPg->execute([$uid, $idvendor, $sub, $ppn, $tot]);
            $idpg = (int) $pdo->lastInsertId();

            $insDpPg = $pdo->prepare(
                "INSERT INTO detail_pengadaan (harga_satuan, jumlah, sub_total, idbarang, idpengadaan)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($items as $it) {
                $insDpPg->execute([$it['harga'], $it['jumlah'], $it['subtotal'], $it['idbarang'], $idpg]);
            }
        }

        $insPr = $pdo->prepare("INSERT INTO penerimaan (status, idpengadaan, iduser) VALUES ('S', ?, ?)");
        $insPr->execute([$idpg, $uid]);
        $idpr = (int) $pdo->lastInsertId();

        // Setiap INSERT ke detail_penerimaan memicu trigger trg_penerimaan_kartu_stok
        $insDp = $pdo->prepare(
            "INSERT INTO detail_penerimaan
                (idpenerimaan, barang_idbarang, jumlah_terima, harga_satuan_terima, sub_total_terima)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($items as $it) {
            $insDp->execute([$idpr, $it['idbarang'], $it['jumlah'], $it['harga'], $it['subtotal']]);
        }

        // Cek apakah seluruh item di pengadaan sudah terpenuhi -> update status pengadaan jadi 'S'
        if ($mode === 'existing') {
            $stSisa = $pdo->prepare(
                "SELECT COUNT(*) FROM detail_pengadaan dp
                  WHERE dp.idpengadaan = ?
                    AND dp.jumlah > IFNULL((
                        SELECT SUM(dpr.jumlah_terima)
                          FROM detail_penerimaan dpr
                          JOIN penerimaan pr ON pr.idpenerimaan = dpr.idpenerimaan
                         WHERE pr.idpengadaan = dp.idpengadaan AND dpr.barang_idbarang = dp.idbarang
                    ), 0)"
            );
            $stSisa->execute([$idpg]);
            if ((int) $stSisa->fetchColumn() === 0) {
                $pdo->prepare("UPDATE pengadaan SET status = 'S' WHERE idpengadaan = ?")->execute([$idpg]);
            }
        }

        $pdo->commit();
        flash('ok', "Penerimaan #{$idpr} berhasil disimpan. Stok & Kartu Stok otomatis bertambah.");
        redirect('penerimaan.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Gagal menambah stok: ' . $e->getMessage());
        redirect('penerimaan.php');
    }
}

$riwayat = $pdo->query(
    "SELECT dp.iddetail_penerimaan, pr.idpenerimaan, pr.created_at, pr.idpengadaan,
            v.nama_vendor, b.nama AS nama_barang, s.nama_satuan,
            dp.jumlah_terima, dp.harga_satuan_terima, dp.sub_total_terima
       FROM detail_penerimaan dp
       JOIN penerimaan pr ON pr.idpenerimaan = dp.idpenerimaan
       JOIN pengadaan pg ON pg.idpengadaan = pr.idpengadaan
       JOIN vendor v ON v.idvendor = pg.vendor_idvendor
       JOIN barang b ON b.idbarang = dp.barang_idbarang
       JOIN satuan s ON s.idsatuan = b.idsatuan
      ORDER BY dp.iddetail_penerimaan DESC LIMIT 20"
)->fetchAll();

render_header('Penerimaan Stok', 'penerimaan');
?>

<div class="card">
  <h2>Form penerimaan barang (tambah stok)</h2>
  <form method="post" id="frmTerima">
    <?= csrf_field() ?>
    <div class="row">
      <div>
        <label for="mode_pengadaan">Sumber pengadaan</label>
        <select name="mode_pengadaan" id="mode_pengadaan" onchange="toggleMode()">
          <option value="baru"<?= $preselectPg === 0 ? ' selected' : '' ?>>Buat pengadaan baru otomatis (langsung terima)</option>
          <option value="existing"<?= $preselectPg > 0 ? ' selected' : '' ?>>Dari pengadaan yang sudah dibuat</option>
        </select>
      </div>
      <div id="box_vendor" style="<?= $preselectPg > 0 ? 'display:none' : '' ?>">
        <label for="idvendor">Vendor</label>
        <select name="idvendor" id="idvendor">
          <option value="">-- Pilih vendor --</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= (int) $v['idvendor'] ?>"><?= e($v['nama_vendor']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="box_pengadaan" style="<?= $preselectPg > 0 ? '' : 'display:none' ?>">
        <label for="idpengadaan">Pilih ID Pengadaan</label>
        <select name="idpengadaan" id="idpengadaan" onchange="if(this.value) window.location='penerimaan.php?idpengadaan='+this.value;">
          <option value="">-- Pilih ID pengadaan --</option>
          <?php foreach ($pengadaans as $pg): ?>
            <option value="<?= (int) $pg['idpengadaan'] ?>"<?= $preselectPg === (int) $pg['idpengadaan'] ? ' selected' : '' ?>>
              #<?= (int) $pg['idpengadaan'] ?> [<?= $pg['status'] === 'S' ? 'Selesai' : 'Proses' ?>] &mdash; <?= e($pg['nama_vendor']) ?> (<?= rupiah($pg['total_nilai']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div id="baris_list" style="margin-top:8px">
      <?php if ($pgItems): ?>
        <?php foreach ($pgItems as $pi): $sisa = max(0, (int) $pi['jumlah_pesan'] - (int) $pi['sudah_terima']); ?>
          <div class="row baris-item">
            <div>
              <label>Barang (Pesan: <?= angka($pi['jumlah_pesan']) ?>, Sudah terima: <?= angka($pi['sudah_terima']) ?>)</label>
              <select name="idbarang[]">
                <option value="<?= (int) $pi['idbarang'] ?>" selected><?= e($pi['nama_barang']) ?> (<?= e($pi['nama_satuan']) ?>)</option>
              </select>
            </div>
            <div style="max-width:160px">
              <label>Jumlah masuk</label>
              <input type="number" name="jumlah_terima[]" min="0" value="<?= $sisa ?>" required>
            </div>
            <div style="max-width:200px">
              <label>Harga satuan (Rp)</label>
              <input type="number" name="harga_satuan_terima[]" min="0" value="<?= (int) $pi['harga_satuan'] ?>" required>
            </div>
            <div class="fit">
              <button type="button" class="btn small danger" onclick="hapusBaris(this)">Hapus</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="row baris-item">
          <div>
            <label>Barang</label>
            <select name="idbarang[]" onchange="setHargaBawaan(this)">
              <option value="">-- Pilih barang --</option>
              <?php foreach ($barangs as $b): ?>
                <option value="<?= (int) $b['idbarang'] ?>" data-harga="<?= (int) $b['harga'] ?>">
                  <?= e($b['nama']) ?> (stok <?= angka($b['stok']) ?> <?= e($b['nama_satuan']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="max-width:160px">
            <label>Jumlah masuk</label>
            <input type="number" name="jumlah_terima[]" min="1" placeholder="0" required>
          </div>
          <div style="max-width:200px">
            <label>Harga satuan (Rp)</label>
            <input type="number" name="harga_satuan_terima[]" min="0" placeholder="0" required>
          </div>
          <div class="fit">
            <button type="button" class="btn small danger" onclick="hapusBaris(this)">Hapus</button>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <p style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <button type="button" class="btn light" onclick="tambahBaris()">+ Tambah baris barang</button>
      <button type="submit" class="btn amber">Simpan &amp; tambah stok</button>
      <a class="btn light" href="kartu_stok.php">Lihat Kartu Stok</a>
    </p>
    <p class="hint">Menyimpan penerimaan akan memicu trigger <code>trg_penerimaan_kartu_stok</code> dan memperbarui status pengadaan menjadi <b>Selesai (S)</b> jika semua item telah diterima.</p>
  </form>
</div>

<div class="card" style="padding:0">
  <div style="padding:14px 18px 0"><h2>Riwayat penerimaan terakhir</h2></div>
  <div class="tablewrap">
    <table>
      <tr>
        <th>No. Terima</th><th>Ref. Pengadaan</th><th>Waktu</th><th>Vendor</th><th>Barang</th>
        <th class="num">Masuk</th><th class="num">Harga satuan</th><th class="num">Subtotal</th>
      </tr>
      <?php foreach ($riwayat as $r): ?>
        <tr>
          <td>#<?= (int) $r['idpenerimaan'] ?></td>
          <td><a href="pengadaan.php?id=<?= (int) $r['idpengadaan'] ?>">#<?= (int) $r['idpengadaan'] ?></a></td>
          <td><?= e($r['created_at']) ?></td>
          <td><?= e($r['nama_vendor']) ?></td>
          <td><?= e($r['nama_barang']) ?></td>
          <td class="num">+<?= angka($r['jumlah_terima']) ?> <?= e($r['nama_satuan']) ?></td>
          <td class="num"><?= rupiah($r['harga_satuan_terima']) ?></td>
          <td class="num"><?= rupiah($r['sub_total_terima']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$riwayat): ?><tr><td colspan="8" class="empty">Belum ada penerimaan barang.</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<script>
function toggleMode() {
  var m = document.getElementById('mode_pengadaan').value;
  document.getElementById('box_vendor').style.display = (m === 'baru') ? '' : 'none';
  document.getElementById('box_pengadaan').style.display = (m === 'existing') ? '' : 'none';
}
function setHargaBawaan(sel) {
  var opt = sel.options[sel.selectedIndex];
  var h = opt ? opt.getAttribute('data-harga') : '';
  var row = sel.closest('.baris-item');
  var inp = row.querySelector('input[name="harga_satuan_terima[]"]');
  if (h !== null && h !== '' && (!inp.value || inp.value === '0')) {
    inp.value = h;
  }
}
function tambahBaris() {
  var wrap = document.getElementById('baris_list');
  var first = wrap.querySelector('.baris-item');
  var clone = first.cloneNode(true);
  clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
  clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
  wrap.appendChild(clone);
}
function hapusBaris(btn) {
  var wrap = document.getElementById('baris_list');
  if (wrap.querySelectorAll('.baris-item').length > 1) {
    btn.closest('.baris-item').remove();
  }
}
</script>
<?php render_footer();
