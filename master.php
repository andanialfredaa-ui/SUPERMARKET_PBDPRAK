<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/layout.php';
require_admin();
csrf_check();

$pdo = db();
$uid = user()['id'];

$AKTIF = ['1' => 'Aktif', '0' => 'Nonaktif'];

// =====================================================================
// Konfigurasi 6 data master. Daftar dibaca dari VIEW, simpan ke TABEL.
// =====================================================================
$M = [
    'satuan' => [
        'title' => 'Satuan', 'table' => 'satuan', 'view' => 'v_satuan', 'pk' => 'idsatuan',
        'fields' => [
            ['name' => 'nama_satuan', 'label' => 'Nama satuan', 'type' => 'text', 'max' => 45, 'unique' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $AKTIF],
        ],
    ],
    'barang' => [
        'title' => 'Barang', 'table' => 'barang', 'view' => 'v_barang', 'pk' => 'idbarang',
        'fields' => [
            ['name' => 'nama', 'label' => 'Nama barang', 'type' => 'text', 'max' => 45, 'unique' => true],
            ['name' => 'jenis', 'label' => 'Jenis', 'type' => 'select', 'options' => ['M' => 'Makanan', 'N' => 'Minuman', 'L' => 'Lainnya']],
            ['name' => 'idsatuan', 'label' => 'Satuan', 'type' => 'select', 'sql' => 'SELECT idsatuan, nama_satuan FROM satuan ORDER BY nama_satuan'],
            ['name' => 'harga', 'label' => 'Harga beli / dasar (Rp)', 'type' => 'number', 'min' => 0],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $AKTIF],
        ],
        'hint' => 'Harga jual di kasir = harga dasar + margin penjualan yang sedang aktif.',
    ],
    'vendor' => [
        'title' => 'Vendor', 'table' => 'vendor', 'view' => 'v_vendor', 'pk' => 'idvendor',
        'fields' => [
            ['name' => 'nama_vendor', 'label' => 'Nama vendor', 'type' => 'text', 'max' => 100, 'unique' => true],
            ['name' => 'badan_hukum', 'label' => 'Badan hukum', 'type' => 'select', 'options' => ['P' => 'PT', 'C' => 'CV', 'O' => 'Perorangan']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['A' => 'Aktif', 'N' => 'Nonaktif']],
        ],
    ],
    'role' => [
        'title' => 'Role', 'table' => 'role', 'view' => 'v_role', 'pk' => 'idrole',
        'fields' => [
            ['name' => 'nama_role', 'label' => 'Nama role', 'type' => 'text', 'max' => 100, 'unique' => true],
        ],
        'hint' => 'Role "Admin" bisa membuka semua menu. Role lain hanya kasir dan kartu stok.',
    ],
    'user' => [
        'title' => 'User', 'table' => 'user', 'view' => 'v_user', 'pk' => 'iduser',
        'fields' => [
            ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'max' => 45, 'unique' => true],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
            ['name' => 'idrole', 'label' => 'Role', 'type' => 'select', 'sql' => 'SELECT idrole, nama_role FROM role ORDER BY nama_role'],
        ],
    ],
    'margin' => [
        'title' => 'Margin Penjualan', 'table' => 'margin_penjualan', 'view' => 'v_margin_penjualan', 'pk' => 'idmargin_penjualan',
        'fields' => [
            ['name' => 'persen', 'label' => 'Margin (%)', 'type' => 'number', 'min' => 0, 'max' => 500, 'unique' => true],
        ],
        'extra_insert' => ['iduser' => $uid, 'status' => 0],
        'hint' => 'Hanya satu margin yang aktif. Margin aktif dipakai kasir untuk menghitung harga jual.',
    ],
];

$key = $_GET['m'] ?? 'satuan';
if (!isset($M[$key])) {
    $key = 'satuan';
}
$c     = $M[$key];
$pk    = $c['pk'];
$table = '`' . $c['table'] . '`';
$self  = 'master.php?m=' . $key;

function field_options(array $f): array
{
    if (isset($f['options'])) {
        return $f['options'];
    }
    $o = [];
    if (isset($f['sql'])) {
        foreach (db()->query($f['sql']) as $r) {
            $v = array_values($r);
            $o[$v[0]] = $v[1];
        }
    }
    return $o;
}

$act    = $_GET['act'] ?? 'list';
$errors = [];
$old    = [];
$editId = (int) ($_GET['id'] ?? 0);

// =====================================================================
// PROSES FORM (POST)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'save') {
            $editId = (int) ($_POST['id'] ?? 0);
            $old    = $_POST;
            $cols   = [];   // [kolom, nilai, ekspresi]

            foreach ($c['fields'] as $f) {
                $n = $f['name'];
                $v = trim($_POST[$n] ?? '');

                if ($f['type'] === 'password') {
                    if ($v === '' && $editId) {
                        continue;               // kosong saat ubah = password tidak diganti
                    }
                    if (strlen($v) < 4) {
                        $errors[] = 'Password minimal 4 karakter.';
                        continue;
                    }
                    $cols[] = [$n, $v, 'SHA2(?, 256)'];
                    continue;
                }
                if ($v === '') {
                    $errors[] = $f['label'] . ' wajib diisi.';
                    continue;
                }
                if ($f['type'] === 'number') {
                    if (!is_numeric($v)) { $errors[] = $f['label'] . ' harus berupa angka.'; continue; }
                    if (isset($f['min']) && $v < $f['min']) { $errors[] = $f['label'] . ' minimal ' . $f['min'] . '.'; continue; }
                    if (isset($f['max']) && $v > $f['max']) { $errors[] = $f['label'] . ' maksimal ' . $f['max'] . '.'; continue; }
                }
                if ($f['type'] === 'select' && !array_key_exists($v, field_options($f))) {
                    $errors[] = $f['label'] . ' tidak valid.';
                    continue;
                }
                if ($f['type'] === 'text' && isset($f['max']) && mb_strlen($v) > $f['max']) {
                    $errors[] = $f['label'] . ' maksimal ' . $f['max'] . ' karakter.';
                    continue;
                }
                if (!empty($f['unique'])) {
                    $q = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE `$n` = ? AND `$pk` <> ?");
                    $q->execute([$v, $editId]);
                    if ($q->fetchColumn() > 0) {
                        $errors[] = $f['label'] . ' "' . $v . '" sudah ada.';
                        continue;
                    }
                }
                $cols[] = [$n, $v, '?'];
            }

            if (!$errors) {
                if ($editId) {
                    $set = implode(', ', array_map(fn($x) => "`{$x[0]}` = {$x[2]}", $cols));
                    $pdo->prepare("UPDATE $table SET $set WHERE `$pk` = ?")
                        ->execute([...array_map(fn($x) => $x[1], $cols), $editId]);
                    flash('ok', $c['title'] . ' berhasil diubah.');
                } else {
                    foreach ($c['extra_insert'] ?? [] as $k => $v) {
                        $cols[] = [$k, $v, '?'];
                    }
                    $names = implode(', ', array_map(fn($x) => "`{$x[0]}`", $cols));
                    $exprs = implode(', ', array_map(fn($x) => $x[2], $cols));
                    $pdo->prepare("INSERT INTO $table ($names) VALUES ($exprs)")
                        ->execute(array_map(fn($x) => $x[1], $cols));
                    flash('ok', $c['title'] . ' berhasil ditambahkan.');
                }
                redirect($self);
            }
            $act = 'form';

        } elseif ($do === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($key === 'user' && $id === $uid) {
                flash('error', 'Kamu tidak bisa menghapus akun yang sedang dipakai.');
            } elseif ($key === 'margin' && $pdo->query("SELECT status FROM margin_penjualan WHERE idmargin_penjualan = $id")->fetchColumn() == 1) {
                flash('error', 'Margin yang sedang aktif tidak bisa dihapus. Aktifkan margin lain dulu.');
            } else {
                try {
                    $pdo->prepare("DELETE FROM $table WHERE `$pk` = ?")->execute([$id]);
                    flash('ok', 'Data berhasil dihapus.');
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        flash('error', 'Data tidak bisa dihapus karena masih dipakai data lain (misalnya transaksi). '
                            . 'Jika ada kolom status, ubah menjadi Nonaktif.');
                    } else {
                        throw $e;
                    }
                }
            }
            redirect($self);

        } elseif ($do === 'aktifkan' && $key === 'margin') {
            $id = (int) ($_POST['id'] ?? 0);
            $ada = $pdo->prepare("SELECT COUNT(*) FROM margin_penjualan WHERE idmargin_penjualan = ?");
            $ada->execute([$id]);
            if ($ada->fetchColumn() > 0) {
                $pdo->prepare("CALL sp_aktifkan_margin(?)")->execute([$id]);
                flash('ok', 'Margin berhasil diaktifkan. Margin lain otomatis nonaktif.');
            }
            redirect($self);
        }
    } catch (PDOException $e) {
        flash('error', 'Terjadi kesalahan database: ' . $e->getMessage());
        redirect($self);
    }
}

// =====================================================================
// TAMPILAN
// =====================================================================
render_header('Master ' . $c['title'], 'master:' . $key);

if ($act === 'form') {
    // nilai awal form
    $vals = [];
    if ($errors) {
        $vals = $old;
    } elseif ($editId) {
        $q = $pdo->prepare("SELECT * FROM $table WHERE `$pk` = ?");
        $q->execute([$editId]);
        $vals = $q->fetch();
        if (!$vals) {
            flash('error', 'Data tidak ditemukan.');
            redirect($self);
        }
    }
    ?>
    <div class="card formcard">
      <h2><?= $editId ? 'Ubah' : 'Tambah' ?> <?= e($c['title']) ?></h2>
      <?php foreach ($errors as $er): ?><div class="alert err"><?= e($er) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="save">
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <?php foreach ($c['fields'] as $f):
            $n = $f['name'];
            $val = $f['type'] === 'password' ? '' : ($vals[$n] ?? ''); ?>
          <label for="f_<?= e($n) ?>"><?= e($f['label']) ?></label>
          <?php if ($f['type'] === 'select'): ?>
            <select id="f_<?= e($n) ?>" name="<?= e($n) ?>" required>
              <?php foreach (field_options($f) as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= (string) $k === (string) $val ? ' selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($f['type'] === 'password'): ?>
            <input type="password" id="f_<?= e($n) ?>" name="<?= e($n) ?>" autocomplete="new-password"<?= $editId ? '' : ' required' ?>>
            <?php if ($editId): ?><p class="hint">Kosongkan jika password tidak ingin diganti.</p><?php endif; ?>
          <?php elseif ($f['type'] === 'number'): ?>
            <input type="number" id="f_<?= e($n) ?>" name="<?= e($n) ?>" value="<?= e($val) ?>" step="any"
                   min="<?= e($f['min'] ?? 0) ?>"<?= isset($f['max']) ? ' max="' . e($f['max']) . '"' : '' ?> required>
          <?php else: ?>
            <input type="text" id="f_<?= e($n) ?>" name="<?= e($n) ?>" value="<?= e($val) ?>"
                   maxlength="<?= (int) ($f['max'] ?? 100) ?>" required>
          <?php endif; ?>
        <?php endforeach; ?>
        <p style="margin-top:18px">
          <button class="btn">Simpan</button>
          <a class="btn light" href="<?= e($self) ?>">Batal</a>
        </p>
      </form>
    </div>
    <?php
    render_footer();
    exit;
}

// ---- daftar (dibaca dari VIEW) ----
$st = $pdo->query("SELECT * FROM `{$c['view']}` ORDER BY `$pk`");
$colNames = [];
for ($i = 0; $i < $st->columnCount(); $i++) {
    $colNames[] = $st->getColumnMeta($i)['name'];
}
$rows = $st->fetchAll();
?>
<div class="toolbar">
  <input type="text" id="cari" placeholder="Cari data..." oninput="cariData(this.value)">
  <a class="btn" href="<?= e($self) ?>&act=form">+ Tambah <?= e($c['title']) ?></a>
</div>
<?php if (!empty($c['hint'])): ?><p class="hint" style="margin:-6px 0 12px"><?= e($c['hint']) ?></p><?php endif; ?>
<div class="card" style="padding:0">
  <div class="tablewrap">
  <table id="tbl">
    <tr>
      <?php foreach ($colNames as $cn): ?>
        <th class="<?= in_array($cn, ['harga'], true) ? 'num' : '' ?>"><?= $cn === $pk ? 'ID' : e(ucwords(str_replace('_', ' ', $cn))) ?></th>
      <?php endforeach; ?>
      <th></th>
    </tr>
    <?php foreach ($rows as $r): $id = (int) $r[$pk]; ?>
      <tr>
        <?php foreach ($colNames as $cn): $v = $r[$cn]; ?>
          <?php if ($cn === 'status'): ?>
            <td><span class="badge <?= $v === 'Aktif' ? 'b-ok' : 'b-off' ?>"><?= e($v) ?></span></td>
          <?php elseif ($cn === 'harga'): ?>
            <td class="num"><?= rupiah($v) ?></td>
          <?php else: ?>
            <td><?= e($v) ?></td>
          <?php endif; ?>
        <?php endforeach; ?>
        <td class="act">
          <?php if ($key === 'margin' && ($r['status'] ?? '') !== 'Aktif'): ?>
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="do" value="aktifkan"><input type="hidden" name="id" value="<?= $id ?>">
              <button class="btn small amber">Aktifkan</button>
            </form>
          <?php endif; ?>
          <a class="btn small light" href="<?= e($self) ?>&act=form&id=<?= $id ?>">Ubah</a>
          <form method="post" onsubmit="return confirm('Hapus data ini?')"><?= csrf_field() ?>
            <input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn small danger">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= count($colNames) + 1 ?>" class="empty">Belum ada data.</td></tr><?php endif; ?>
  </table>
  </div>
</div>
<script>
function cariData(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#tbl tr').forEach(function (tr, i) {
    if (i === 0) return;
    tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
  });
}
</script>
<?php render_footer();
