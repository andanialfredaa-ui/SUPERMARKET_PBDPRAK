APLIKASI SUPERMARKET (PHP + MySQL) - untuk Laragon
====================================================

CARA PASANG
1. Laragon: klik "Start All" (Apache dan MySQL harus jalan).
2. Pastikan database sudah ada: jalankan uts_database_penjualan.sql di HeidiSQL
   (database db_uts_penjualan, berisi tabel, trigger, view, dan data contoh).
3. Salin folder "supermarket" ini ke:  C:\laragon\www\
   sehingga ada  C:\laragon\www\supermarket\index.php
4. Buka browser:  http://localhost/supermarket
   (jika Laragon memakai auto virtual host: http://supermarket.test)

AKUN CONTOH
  admin / admin123  -> semua menu (termasuk Data Master)
  kasir / kasir123  -> Kasir dan Kartu Stok saja

PENGATURAN DATABASE
  Buka config.php. Bawaan Laragon: user root, password kosong, port 3306.
  Ubah DB_USER / DB_PASS / DB_PORT jika berbeda.

FITUR
  - Login dengan role (Admin dan Kasir)
  - 6 Data Master (satuan, barang, vendor, role, user, margin penjualan):
    daftar dibaca dari VIEW (v_satuan, v_barang, v_vendor, v_role, v_user,
    v_margin_penjualan), tambah/ubah/hapus ke tabel aslinya.
  - Margin penjualan: tombol "Aktifkan" memanggil procedure sp_aktifkan_margin
    (hanya satu margin aktif).
  - Kasir: harga jual = harga dasar + margin aktif, ditambah PPN 11%.
    Simpan transaksi -> trigger mengisi kartu_stok otomatis (stok keluar).
  - Kartu Stok: riwayat masuk/keluar dan stok terakhir tiap barang.
  - Nota: bisa dicetak.

STRUKTUR FILE
  config.php      pengaturan database
  lib.php         fungsi bantu (login, csrf, format rupiah)
  layout.php      kerangka halaman (sidebar)
  login.php / logout.php
  index.php       dashboard
  master.php      CRUD 6 data master (satu file untuk semua master)
  kasir.php       transaksi penjualan
  nota.php        struk
<<<<<<< HEAD
  penerimaan.php  tambah stok / penerimaan barang
=======
>>>>>>> a6735c9e4901add495a1f14cc63b8bc56d26ab18
  kartu_stok.php  kartu stok
  assets/style.css

TAMPILAN (tema pink polka)
  Font disimpan lokal di assets/fonts (tidak butuh internet):
    Pinyon Script (judul dan logo), Syncopate (label huruf lebar), DM Sans (teks).
  Warna dan font bisa diubah di bagian :root pada assets/style.css.
  Animasi: titik polka melayang, bintang berkelip, pita berjalan, angka dashboard
  menghitung naik, percikan bintang saat klik tombol. Otomatis mati jika
  pengaturan "kurangi animasi" di komputer aktif.
