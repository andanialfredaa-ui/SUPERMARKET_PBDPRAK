-- =====================================================================
-- FUNCTION (jalankan SETELAH uts_database_penjualan.sql)
-- Database: db_uts_penjualan
-- Isi     : 3 function
--   fn_stok_barang(idbarang)  -> stok terakhir barang dari kartu_stok
--   fn_harga_jual(idbarang)   -> harga dasar + margin penjualan yang aktif
--   fn_hitung_ppn(subtotal)   -> PPN 11% dari subtotal
-- =====================================================================
USE db_uts_penjualan;

DROP FUNCTION IF EXISTS fn_stok_barang;
DROP FUNCTION IF EXISTS fn_harga_jual;
DROP FUNCTION IF EXISTS fn_hitung_ppn;

DELIMITER $$

-- Stok terakhir sebuah barang (baris terakhir pada kartu_stok)
CREATE FUNCTION fn_stok_barang (p_idbarang INT)
RETURNS INT
READS SQL DATA
BEGIN
  DECLARE v_stok INT DEFAULT 0;
  SELECT stock INTO v_stok
    FROM kartu_stok
   WHERE idbarang = p_idbarang
   ORDER BY idkartu_stok DESC
   LIMIT 1;
  RETURN IFNULL(v_stok, 0);
END$$

-- Harga jual = harga dasar barang + margin yang sedang aktif
CREATE FUNCTION fn_harga_jual (p_idbarang INT)
RETURNS INT
READS SQL DATA
BEGIN
  DECLARE v_harga INT DEFAULT 0;
  DECLARE v_persen DOUBLE DEFAULT 0;
  SELECT harga INTO v_harga FROM barang WHERE idbarang = p_idbarang;
  SELECT persen INTO v_persen FROM margin_penjualan WHERE status = 1 LIMIT 1;
  RETURN ROUND(IFNULL(v_harga, 0) * (100 + IFNULL(v_persen, 0)) / 100);
END$$

-- PPN 11% dari subtotal
CREATE FUNCTION fn_hitung_ppn (p_subtotal INT)
RETURNS INT
DETERMINISTIC
BEGIN
  RETURN ROUND(p_subtotal * 11 / 100);
END$$

DELIMITER ;

-- =====================================================================
-- CONTOH PEMAKAIAN (screenshot hasilnya untuk laporan)
-- =====================================================================
-- SELECT fn_stok_barang(1) AS stok_indomie;
-- SELECT idbarang, nama, harga, fn_harga_jual(idbarang) AS harga_jual,
--        fn_stok_barang(idbarang) AS stok FROM barang;
-- SELECT fn_hitung_ppn(100000) AS ppn;
-- SHOW FUNCTION STATUS WHERE Db = 'db_uts_penjualan';
