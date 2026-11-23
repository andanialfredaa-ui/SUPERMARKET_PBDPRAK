<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_login();
csrf_check();

$pdo = db();
$margin = $pdo->query("SELECT idmargin_penjualan, persen FROM v_margin_aktif LIMIT 1")->fetch();

// =====================================================================
// PROSES BAYAR
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$margin) {
            throw new RuntimeException('Belum ada margin penjualan yang aktif. Minta Admin mengaktifkan satu margin.');
        }

        $cart = [];
        foreach (($_POST['idbarang'] ?? []) as $i => $id) {
            $id = (int) $id;
            $q  = (int) ($_POST['jumlah'][$i] ?? 0);
            if ($id > 0 && $q > 0) {
                $cart[$id] = ($cart[$id] ?? 0) + $q;
            }
        }
        if (!$cart) {
            throw new RuntimeException('Keranjang masih kosong.');
        }

        $pdo->beginTransaction();

        // kunci baris barang agar stok tidak bentrok dengan kasir lain
        $in = implode(',', array_fill(0, count($cart), '?'));
        $st = $pdo->prepare("SELECT idbarang, nama, harga, status FROM barang WHERE idbarang IN ($in) FOR UPDATE");
        $st->execute(array_keys($cart));
        $brg = $st->fetchAll(PDO::FETCH_UNIQUE);
        if (count($brg) !== count($cart)) {
            throw new RuntimeException('Ada barang yang tidak ditemukan.');
        }

        $cekStok = $pdo->prepare("SELECT fn_stok_barang(?)");
        $lines = [];
        $sub = 0;
        foreach ($cart as $id => $q) {
            $b = $brg[$id];
            if ((int) $b['status'] !== 1) {
                throw new RuntimeException($b['nama'] . ' sedang nonaktif.');
            }
            $cekStok->execute([$id]);
            $stok = (int) $cekStok->fetchColumn();
            if ($q > $stok) {
                throw new RuntimeException("Stok {$b['nama']} tidak cukup (sisa $stok).");
            }
            $stHj = $pdo->prepare("SELECT fn_harga_jual(?)"); $stHj->execute([$id]); $hj = (int) $stHj->fetchColumn();
            $lines[] = [$id, $hj, $q, $hj * $q];
            $sub += $hj * $q;
        }
        $stPpn = $pdo->prepare("SELECT fn_hitung_ppn(?)"); $stPpn->execute([$sub]); $ppn = (int) $stPpn->fetchColumn();
        $total = $sub + $ppn;

        $pdo->prepare("INSERT INTO penjualan (subtotal_nilai, ppn, total_nilai, iduser, idmargin_penjualan) VALUES (?,?,?,?,?)")
            ->execute([$sub, $ppn, $total, user()['id'], $margin['idmargin_penjualan']]);
        $idj = (int) $pdo->lastInsertId();

        // setiap INSERT ke detail_penjualan memicu trigger kartu stok (stok keluar)
        $ins = $pdo->prepare("INSERT INTO detail_penjualan (harga_satuan, jumlah, subtotal, penjualan_idpenjualan, idbarang) VALUES (?,?,?,?,?)");
        foreach ($lines as [$id, $hj, $q, $sb]) {
            $ins->execute([$hj, $q, $sb, $idj, $id]);
        }
        $pdo->commit();

        flash('ok', 'Transaksi berhasil disimpan.');
        redirect('nota.php?id=' . $idj);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Transaksi gagal: ' . $e->getMessage());
        redirect('kasir.php');
    }
}

// =====================================================================
// TAMPILAN
// =====================================================================
$barang = $pdo->query(
    "SELECT b.idbarang, b.nama, b.harga, s.nama_satuan, " . stok_sql() . " AS stok
       FROM barang b JOIN satuan s ON s.idsatuan = b.idsatuan
      WHERE b.status = 1 ORDER BY b.nama"
)->fetchAll();

$riwayat = $pdo->query(
    "SELECT p.idpenjualan, p.created_at, p.total_nilai, u.username
       FROM penjualan p JOIN `user` u ON u.iduser = p.iduser
      ORDER BY p.idpenjualan DESC LIMIT 8"
)->fetchAll();

render_header('Kasir', 'kasir');
?>
<?php if (!$margin): ?>
  <div class="alert warn">Belum ada margin penjualan yang aktif, jadi transaksi belum bisa dilakukan. Minta Admin mengaktifkan satu margin di menu Data Master &rarr; Margin Penjualan.</div>
<?php else: ?>
<form method="post" id="frm" onsubmit="return cekKeranjang()">
  <?= csrf_field() ?>
  <div class="kasir">
    <div>
      <div class="card">
        <h2>Tambah barang</h2>
        <div class="row">
          <div>
            <label for="pilih">Barang</label>
            <select id="pilih">
              <?php foreach ($barang as $b): ?>
                <option value="<?= (int) $b['idbarang'] ?>"<?= $b['stok'] <= 0 ? ' disabled' : '' ?>>
                  <?= e($b['nama']) ?> (stok <?= angka($b['stok']) ?> <?= e($b['nama_satuan']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="max-width:110px">
            <label for="qty">Jumlah</label>
            <input type="number" id="qty" min="1" value="1">
          </div>
          <div class="fit"><button type="button" class="btn" onclick="tambah()">Tambah</button></div>
        </div>
        <p class="hint">Margin aktif: <b><?= e($margin['persen']) ?>%</b> &middot; PPN <?= PPN_PERSEN ?>%</p>
      </div>

      <div class="card" style="padding:0">
        <div class="tablewrap"><table>
          <thead><tr><th>Barang</th><th class="num">Harga</th><th style="width:110px">Jumlah</th><th class="num">Subtotal</th><th></th></tr></thead>
          <tbody id="keranjang"><tr><td colspan="5" class="empty">Keranjang kosong.</td></tr></tbody>
        </table></div>
      </div>
    </div>

    <div class="total">
      <div class="line"><span>Subtotal</span><span id="t_sub">Rp 0</span></div>
      <div class="line"><span>PPN <?= PPN_PERSEN ?>%</span><span id="t_ppn">Rp 0</span></div>
      <div class="line grand"><span>Total</span><span id="t_tot">Rp 0</span></div>
      <button class="btn amber">Bayar &amp; simpan</button>
    </div>
  </div>
</form>
<?php endif; ?>

<div class="card" style="margin-top:18px;padding:0">
  <div style="padding:14px 18px 0"><h2>Transaksi terakhir</h2></div>
  <div class="tablewrap"><table>
    <tr><th>No</th><th>Waktu</th><th>Kasir</th><th class="num">Total</th></tr>
    <?php foreach ($riwayat as $r): ?>
      <tr>
        <td><a href="nota.php?id=<?= (int) $r['idpenjualan'] ?>">#<?= (int) $r['idpenjualan'] ?></a></td>
        <td><?= e($r['created_at']) ?></td><td><?= e($r['username']) ?></td>
        <td class="num"><?= rupiah($r['total_nilai']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$riwayat): ?><tr><td colspan="4" class="empty">Belum ada transaksi.</td></tr><?php endif; ?>
  </table></div>
</div>

<?php if ($margin): ?>
<script>
var BARANG = <?= json_encode(array_column($barang, null, 'idbarang'), JSON_NUMERIC_CHECK) ?>;
var MARGIN = <?= json_encode((float) $margin['persen']) ?>;
var PPN = <?= (int) PPN_PERSEN ?>;
var cart = {};   // idbarang -> jumlah

function rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
function hargaJual(b) { return Math.round(b.harga * (100 + MARGIN) / 100); }

function tambah() {
  var id = document.getElementById('pilih').value;
  var q = parseInt(document.getElementById('qty').value, 10) || 0;
  if (!id || q < 1) return;
  var b = BARANG[id];
  var baru = (cart[id] || 0) + q;
  if (baru > b.stok) { alert('Stok ' + b.nama + ' hanya ' + b.stok + '.'); baru = b.stok; }
  cart[id] = baru;
  document.getElementById('qty').value = 1;
  gambar();
}
function ubah(id, v) {
  var b = BARANG[id];
  v = parseInt(v, 10) || 1;
  if (v > b.stok) { alert('Stok ' + b.nama + ' hanya ' + b.stok + '.'); v = b.stok; }
  if (v < 1) v = 1;
  cart[id] = v;
  gambar();
}
function hapus(id) { delete cart[id]; gambar(); }

function gambar() {
  var tb = document.getElementById('keranjang');
  var ids = Object.keys(cart);
  var sub = 0, html = '';
  ids.forEach(function (id) {
    var b = BARANG[id], hj = hargaJual(b), q = cart[id];
    sub += hj * q;
    html += '<tr><td>' + b.nama.replace(/</g, '&lt;') +
      '<input type="hidden" name="idbarang[]" value="' + id + '"></td>' +
      '<td class="num">' + rp(hj) + '</td>' +
      '<td><input type="number" name="jumlah[]" min="1" max="' + b.stok + '" value="' + q +
      '" onchange="ubah(' + id + ', this.value)"></td>' +
      '<td class="num">' + rp(hj * q) + '</td>' +
      '<td class="act"><button type="button" class="btn small danger" onclick="hapus(' + id + ')">Hapus</button></td></tr>';
  });
  tb.innerHTML = html || '<tr><td colspan="5" class="empty">Keranjang kosong.</td></tr>';
  var ppn = Math.round(sub * PPN / 100);
  document.getElementById('t_sub').textContent = rp(sub);
  document.getElementById('t_ppn').textContent = rp(ppn);
  document.getElementById('t_tot').textContent = rp(sub + ppn);
}
function cekKeranjang() {
  if (Object.keys(cart).length === 0) { alert('Keranjang masih kosong.'); return false; }
  return true;
}
</script>
<?php endif; ?>
<?php render_footer();
