USE db_uts_penjualan;

-- ===== DML master: satuan =====
INSERT INTO satuan (nama_satuan, status) VALUES ('Lusin', 1);
SELECT * FROM satuan;
UPDATE satuan SET nama_satuan = 'Dus' WHERE nama_satuan = 'Lusin';
SELECT * FROM v_satuan;
DELETE FROM satuan WHERE nama_satuan = 'Dus';

-- ===== DML master: barang =====
INSERT INTO barang (jenis, nama, idsatuan, status, harga)
VALUES ('L', 'Sabun Mandi', 1, 1, 5000);
SELECT * FROM barang;
UPDATE barang SET harga = 5500 WHERE nama = 'Sabun Mandi';
SELECT * FROM v_barang;
DELETE FROM barang WHERE nama = 'Sabun Mandi';

-- ===== DML master: vendor =====
INSERT INTO vendor (nama_vendor, badan_hukum, status)
VALUES ('UD Maju Terus', 'P', 'A');
SELECT * FROM vendor;
UPDATE vendor SET status = 'N' WHERE nama_vendor = 'UD Maju Terus';
SELECT * FROM v_vendor;
DELETE FROM vendor WHERE nama_vendor = 'UD Maju Terus';

-- ===== DML master: role =====
INSERT INTO role (nama_role) VALUES ('Gudang');
SELECT * FROM role;
UPDATE role SET nama_role = 'Staf Gudang' WHERE nama_role = 'Gudang';
SELECT * FROM v_role;

-- ===== DML master: user =====
INSERT INTO `user` (username, password, idrole)
VALUES ('gudang1', SHA2('gudang123', 256),
        (SELECT idrole FROM role WHERE nama_role = 'Staf Gudang'));
SELECT * FROM `user`;
UPDATE `user` SET username = 'gudang01' WHERE username = 'gudang1';
SELECT * FROM v_user;
DELETE FROM `user` WHERE username = 'gudang01';
-- role uji coba dihapus setelah user-nya dihapus
DELETE FROM role WHERE nama_role = 'Staf Gudang';

-- ===== DML master: margin_penjualan =====
INSERT INTO margin_penjualan (persen, status, iduser) VALUES (60, 0, 1);
SELECT * FROM margin_penjualan;
UPDATE margin_penjualan SET persen = 65 WHERE persen = 60;
CALL sp_aktifkan_margin(5);   -- aktifkan margin 50%, lainnya nonaktif
SELECT * FROM v_margin_penjualan;
CALL sp_aktifkan_margin(3);   -- kembalikan ke margin 30%
DELETE FROM margin_penjualan WHERE persen = 65;

