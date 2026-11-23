<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_login();
csrf_check();

$pdo = db();
$uid = (int) user()['id'];

// Daftar penerimaan yang masih memiliki sisa item untuk diretur
$penerimaans = $pdo->query(
    "SELECT pr.idpenerimaan, pr.created_at, v.nama_vendor
       FROM penerimaan pr
       JOIN pengadaan pg ON pg.idpengadaan = pr.idpengadaan
       JOIN vendor v ON v.idvendor = pg.vendor_idvendor
      ORDER BY pr.idpenerimaan DESC LIMIT 30"
)->fetchAll();

$selectedPr = (int) ($_GET['idpenerimaan'] ?? ($penerimaans[0]['idpenerimaan'] ?? 0));
$detailTerima = [];
if ($selectedPr > 0) {
    $st = $pdo->prepare(
        "SELECT dp.iddetail_penerimaan, dp.barang_idbarang, dp.jumlah_terima, dp.harga_satuan_terima,
                b.nama AS nama_barang, s.nama_satuan,
                fn_stok_barang(dp.barang_idbarang) AS stok_saat_ini,
                IFNULL((SELECT SUM(dr.jumlah) FROM detail_retur dr WHERE dr.iddetail_penerimaan = dp.iddetail_penerimaan), 0) AS sudah_diretur
           FROM detail_penerimaan dp
           JOIN barang b ON b.idbarang = dp.barang_idbarang
           JOIN satuan s ON s.idsatuan = b.idsatuan
          WHERE dp.idpenerimaan = ?"
    );
    $st->execute([$selectedPr]);
    $detailTerima = $st->fetchAll();
}

// =====================================================================
// PROSES SIMPAN RETUR KE VENDOR
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $idpr = (int) ($_POST['idpenerimaan'] ?? 0);
        if ($idpr <= 0) {
            throw new RuntimeException('Pilih transaksi penerimaan terlebih dahulu.');
        }

        $iddps = $_POST['iddetail_penerimaan'] ?? [];
        $qtys = $_POST['jumlah_retur'] ?? [];
        $alasans = $_POST['alasan'] ?? [];

        $items = [];
        for ($i = 0; $i < count($iddps); $i++) {
            $iddp = (int) ($iddps[$i] ?? 0);
            $jml = (int) ($qtys[$i] ?? 0);
            $als = trim($alasans[$i] ?? '');
            if ($iddp > 0 && $jml > 0) {
                if ($als === '') {
                    throw new RuntimeException('Alasan retur wajib diisi untuk setiap barang yang diretur.');
                }
                $items[] = [
                    'iddetail_penerimaan' => $iddp,
                    'jumlah'              => $jml,
                    'alasan'              => mb_substr($als, 0, 200),
                ];
            }
        }

        if (!$items) {
            throw new RuntimeException('Masukkan jumlah retur minimal pada 1 barang.');
        }

        $pdo->beginTransaction();

        // Validasi sisa penerimaan & stok saat ini
        $cekSt = $pdo->prepare(
            "SELECT dp.iddetail_penerimaan, dp.barang_idbarang, dp.jumlah_terima, b.nama,
                    fn_stok_barang(dp.barang_idbarang) AS stok_saat_ini,
                    IFNULL((SELECT SUM(dr.jumlah) FROM detail_retur dr WHERE dr.iddetail_penerimaan = dp.iddetail_penerimaan), 0) AS sudah_diretur
               FROM detail_penerimaan dp
               JOIN barang b ON b.idbarang = dp.barang_idbarang
              WHERE dp.iddetail_penerimaan = ? AND dp.idpenerimaan = ? FOR UPDATE"
        );

        foreach ($items as $it) {
            $cekSt->execute([$it['iddetail_penerimaan'], $idpr]);
            $row = $cekSt->fetch();
            if (!$row) {
                throw new RuntimeException('Detail penerimaan tidak valid.');
            }
            $maksRetur = (int) $row['jumlah_terima'] - (int) $row['sudah_diretur'];
            if ($it['jumlah'] > $maksRetur) {
                throw new RuntimeException("Jumlah retur {$row['nama']} melebihi sisa penerimaan (maksimal {$maksRetur}).");
            }
            if ($it['jumlah'] > (int) $row['stok_saat_ini']) {
                throw new RuntimeException("Stok {$row['nama']} di gudang tidak cukup untuk diretur (stok saat ini: {$row['stok_saat_ini']}).");
            }
        }

        $insRet = $pdo->prepare("INSERT INTO retur (idpenerimaan, iduser) VALUES (?, ?)");
        $insRet->execute([$idpr, $uid]);
        $idretur = (int) $pdo->lastInsertId();

        // Setiap INSERT ke detail_retur memicu trigger trg_retur_kartu_stok (stok keluar)
        $insDetRet = $pdo->prepare(
            "INSERT INTO detail_retur (jumlah, alasan, idretur, iddetail_penerimaan)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($items as $it) {
            $insDetRet->execute([$it['jumlah'], $it['alasan'], $idretur, $it['iddetail_penerimaan']]);
        }

        $pdo->commit();
        flash('ok', "Retur #{$idretur} berhasil disimpan. Stok otomatis berkurang di Kartu Stok.");
        redirect('retur.php?idpenerimaan=' . $idpr);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Gagal menyimpan retur: ' . $e->getMessage());
        redirect('retur.php?idpenerimaan=' . (int) ($_POST['idpenerimaan'] ?? 0));
    }
}

$riwayatRetur = $pdo->query(
    "SELECT dr.iddetail_retur, r.idretur, r.created_at, r.idpenerimaan,
            v.nama_vendor, b.nama AS nama_barang, s.nama_satuan,
            dr.jumlah, dr.alasan, u.username
       FROM detail_retur dr
       JOIN retur r ON r.idretur = dr.idretur
       JOIN penerimaan pr ON pr.idpenerimaan = r.idpenerimaan
       JOIN pengadaan pg ON pg.idpengadaan = pr.idpengadaan
       JOIN vendor v ON v.idvendor = pg.vendor_idvendor
       JOIN detail_penerimaan dp ON dp.iddetail_penerimaan = dr.iddetail_penerimaan
       JOIN barang b ON b.idbarang = dp.barang_idbarang
       JOIN satuan s ON s.idsatuan = b.idsatuan
       JOIN `user` u ON u.iduser = r.iduser
      ORDER BY dr.iddetail_retur DESC LIMIT 20"
)->fetchAll();

render_header('Retur ke Vendor', 'retur');
?>

<div class="card">
  <h2>Input Retur Barang ke Vendor</h2>
  <form method="get" class="row" style="max-width:520px;margin-bottom:14px">
    <div>
      <label for="idpenerimaan_pilih">Pilih Transaksi Penerimaan</label>
      <select name="idpenerimaan" id="idpenerimaan_pilih" onchange="this.form.submit()">
        <?php foreach ($penerimaans as $pr): ?>
          <option value="<?= (int) $pr['idpenerimaan'] ?>"<?= $selectedPr === (int) $pr['idpenerimaan'] ? ' selected' : '' ?>>
            Penerimaan #<?= (int) $pr['idpenerimaan'] ?> &mdash; <?= e($pr['nama_vendor']) ?> (<?= e($pr['created_at']) ?>)
          </option>
        <?php endforeach; ?>
        <?php if (!$penerimaans): ?><option value="0">Belum ada data penerimaan</option><?php endif; ?>
      </select>
    </div>
  </form>

  <?php if ($detailTerima): ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="idpenerimaan" value="<?= (int) $selectedPr ?>">
      <div class="tablewrap">
        <table>
          <tr>
            <th>Barang</th>
            <th class="num">Diterima</th>
            <th class="num">Sudah Diretur</th>
            <th class="num">Stok Gudang</th>
            <th style="width:130px">Jumlah Retur</th>
            <th>Alasan Retur</th>
          </tr>
          <?php foreach ($detailTerima as $dt):
              $sisaBisaRetur = max(0, min((int) $dt['jumlah_terima'] - (int) $dt['sudah_diretur'], (int) $dt['stok_saat_ini']));
          ?>
            <tr>
              <td>
                <?= e($dt['nama_barang']) ?> (<?= e($dt['nama_satuan']) ?>)
                <input type="hidden" name="iddetail_penerimaan[]" value="<?= (int) $dt['iddetail_penerimaan'] ?>">
              </td>
              <td class="num"><?= angka($dt['jumlah_terima']) ?></td>
              <td class="num"><?= angka($dt['sudah_diretur']) ?></td>
              <td class="num"><b><?= angka($dt['stok_saat_ini']) ?></b></td>
              <td>
                <input type="number" name="jumlah_retur[]" min="0" max="<?= $sisaBisaRetur ?>" value="0"<?= $sisaBisaRetur <= 0 ? ' disabled' : '' ?>>
              </td>
              <td>
                <input type="text" name="alasan[]" maxlength="200" placeholder="Contoh: Kemasan rusak / kadaluarsa"<?= $sisaBisaRetur <= 0 ? ' disabled' : '' ?>>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <p style="margin-top:16px">
        <button type="submit" class="btn danger">Proses Retur &amp; Kurangi Stok</button>
        <a class="btn light" href="kartu_stok.php">Lihat Kartu Stok</a>
      </p>
      <p class="hint">Menyimpan retur akan mengisi <code>detail_retur</code> sehingga trigger <code>trg_retur_kartu_stok</code> mencatat stok keluar (jenis <b>R</b>) di Kartu Stok.</p>
    </form>
  <?php else: ?>
    <p class="empty">Pilih transaksi penerimaan yang memiliki barang untuk diretur.</p>
  <?php endif; ?>
</div>

<div class="card" style="padding:0">
  <div style="padding:14px 18px 0"><h2>Riwayat Retur Terakhir</h2></div>
  <div class="tablewrap">
    <table>
      <tr>
        <th>No. Retur</th><th>Ref. Terima</th><th>Waktu</th><th>Vendor</th><th>Barang</th>
        <th class="num">Keluar</th><th>Alasan</th><th>User</th>
      </tr>
      <?php foreach ($riwayatRetur as $rr): ?>
        <tr>
          <td>#<?= (int) $rr['idretur'] ?></td>
          <td>#<?= (int) $rr['idpenerimaan'] ?></td>
          <td><?= e($rr['created_at']) ?></td>
          <td><?= e($rr['nama_vendor']) ?></td>
          <td><?= e($rr['nama_barang']) ?></td>
          <td class="num">-<?= angka($rr['jumlah']) ?> <?= e($rr['nama_satuan']) ?></td>
          <td><?= e($rr['alasan']) ?></td>
          <td><?= e($rr['username']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$riwayatRetur): ?><tr><td colspan="8" class="empty">Belum ada data retur.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php render_footer();
