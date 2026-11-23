-- =====================================================================
-- UTS: Implementasi PDM menjadi database (MySQL / Laragon)
-- Database : db_uts_penjualan
-- Isi      : 15 tabel, 3 trigger kartu stok, 19 view (6 view master + 12 view aktif/nonaktif + 1 view margin aktif),
--            Stored Procedure lengkap (margin, status aktif/nonaktif, stok),
--            data master (6 master), data transaksi contoh
-- Cara pakai: jalankan seluruh file ini di HeidiSQL (Laragon) sekaligus
-- =====================================================================

DROP DATABASE IF EXISTS db_uts_penjualan;
CREATE DATABASE db_uts_penjualan CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_uts_penjualan;

-- =====================================================================
-- STEP 1 : TABEL (urutan: master dulu, baru transaksi)
-- =====================================================================

-- ---------- 6 DATA MASTER ----------
CREATE TABLE satuan (
  idsatuan     INT NOT NULL AUTO_INCREMENT,
  nama_satuan  VARCHAR(45) NOT NULL,
  status       TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (idsatuan)
) ENGINE=InnoDB;

CREATE TABLE barang (
  idbarang  INT NOT NULL AUTO_INCREMENT,
  jenis     CHAR(1) NOT NULL,
  nama      VARCHAR(45) NOT NULL,
  idsatuan  INT NOT NULL,
  status    TINYINT NOT NULL DEFAULT 1,
  harga     INT NOT NULL,
  PRIMARY KEY (idbarang),
  CONSTRAINT fk_barang_satuan FOREIGN KEY (idsatuan) REFERENCES satuan (idsatuan)
) ENGINE=InnoDB;

CREATE TABLE vendor (
  idvendor      INT NOT NULL AUTO_INCREMENT,
  nama_vendor   VARCHAR(100) NOT NULL,
  badan_hukum   CHAR(1) NOT NULL,
  status        CHAR(1) NOT NULL DEFAULT 'A',
  PRIMARY KEY (idvendor)
) ENGINE=InnoDB;

CREATE TABLE role (
  idrole     INT NOT NULL AUTO_INCREMENT,
  nama_role  VARCHAR(100) NOT NULL,
  status     TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (idrole)
) ENGINE=InnoDB;

CREATE TABLE `user` (
  iduser    INT NOT NULL AUTO_INCREMENT,
  username  VARCHAR(45) NOT NULL,
  password  VARCHAR(100) NOT NULL,
  idrole    INT NOT NULL,
  status    TINYINT NOT NULL DEFAULT 1,
  PRIMARY KEY (iduser),
  CONSTRAINT fk_user_role FOREIGN KEY (idrole) REFERENCES role (idrole)
) ENGINE=InnoDB;

CREATE TABLE margin_penjualan (
  idmargin_penjualan  INT NOT NULL AUTO_INCREMENT,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  persen              DOUBLE NOT NULL,
  status              TINYINT NOT NULL DEFAULT 0,   -- 1 = aktif, 0 = nonaktif
  iduser              INT NOT NULL,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (idmargin_penjualan),
  CONSTRAINT fk_margin_user FOREIGN KEY (iduser) REFERENCES `user` (iduser)
) ENGINE=InnoDB;

-- ---------- PENGADAAN ----------
CREATE TABLE pengadaan (
  idpengadaan      BIGINT NOT NULL AUTO_INCREMENT,
  `timestamp`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_iduser      INT NOT NULL,
  status           CHAR(1) NOT NULL DEFAULT 'P',   -- P=proses, S=selesai
  vendor_idvendor  INT NOT NULL,
  subtotal_nilai   INT NOT NULL DEFAULT 0,
  ppn              INT NOT NULL DEFAULT 0,
  total_nilai      INT NOT NULL DEFAULT 0,
  PRIMARY KEY (idpengadaan),
  CONSTRAINT fk_pengadaan_user   FOREIGN KEY (user_iduser)     REFERENCES `user` (iduser),
  CONSTRAINT fk_pengadaan_vendor FOREIGN KEY (vendor_idvendor) REFERENCES vendor (idvendor)
) ENGINE=InnoDB;

CREATE TABLE detail_pengadaan (
  iddetail_pengadaan  BIGINT NOT NULL AUTO_INCREMENT,
  harga_satuan        INT NOT NULL,
  jumlah              INT NOT NULL,
  sub_total           INT NOT NULL,
  idbarang            INT NOT NULL,
  idpengadaan         BIGINT NOT NULL,
  PRIMARY KEY (iddetail_pengadaan),
  CONSTRAINT fk_dpengadaan_barang    FOREIGN KEY (idbarang)    REFERENCES barang (idbarang),
  CONSTRAINT fk_dpengadaan_pengadaan FOREIGN KEY (idpengadaan) REFERENCES pengadaan (idpengadaan)
) ENGINE=InnoDB;

-- ---------- PENERIMAAN ----------
CREATE TABLE penerimaan (
  idpenerimaan  BIGINT NOT NULL AUTO_INCREMENT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        CHAR(1) NOT NULL DEFAULT 'S',
  idpengadaan   BIGINT NOT NULL,
  iduser        INT NOT NULL,
  PRIMARY KEY (idpenerimaan),
  CONSTRAINT fk_penerimaan_pengadaan FOREIGN KEY (idpengadaan) REFERENCES pengadaan (idpengadaan),
  CONSTRAINT fk_penerimaan_user      FOREIGN KEY (iduser)      REFERENCES `user` (iduser)
) ENGINE=InnoDB;

CREATE TABLE detail_penerimaan (
  iddetail_penerimaan  BIGINT NOT NULL AUTO_INCREMENT,
  idpenerimaan         BIGINT NOT NULL,
  barang_idbarang      INT NOT NULL,
  jumlah_terima        INT NOT NULL,
  harga_satuan_terima  INT NOT NULL,
  sub_total_terima     INT NOT NULL,
  PRIMARY KEY (iddetail_penerimaan),
  CONSTRAINT fk_dpenerimaan_penerimaan FOREIGN KEY (idpenerimaan)    REFERENCES penerimaan (idpenerimaan),
  CONSTRAINT fk_dpenerimaan_barang     FOREIGN KEY (barang_idbarang) REFERENCES barang (idbarang)
) ENGINE=InnoDB;

-- ---------- RETUR ----------
CREATE TABLE retur (
  idretur       BIGINT NOT NULL AUTO_INCREMENT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  idpenerimaan  BIGINT NOT NULL,
  iduser        INT NOT NULL,
  PRIMARY KEY (idretur),
  CONSTRAINT fk_retur_penerimaan FOREIGN KEY (idpenerimaan) REFERENCES penerimaan (idpenerimaan),
  CONSTRAINT fk_retur_user       FOREIGN KEY (iduser)       REFERENCES `user` (iduser)
) ENGINE=InnoDB;

CREATE TABLE detail_retur (
  iddetail_retur       INT NOT NULL AUTO_INCREMENT,
  jumlah               INT NOT NULL,
  alasan               VARCHAR(200) NOT NULL,
  idretur              BIGINT NOT NULL,
  iddetail_penerimaan  BIGINT NOT NULL,
  PRIMARY KEY (iddetail_retur),
  CONSTRAINT fk_dretur_retur       FOREIGN KEY (idretur)             REFERENCES retur (idretur),
  CONSTRAINT fk_dretur_dpenerimaan FOREIGN KEY (iddetail_penerimaan) REFERENCES detail_penerimaan (iddetail_penerimaan)
) ENGINE=InnoDB;

-- ---------- PENJUALAN ----------
CREATE TABLE penjualan (
  idpenjualan         INT NOT NULL AUTO_INCREMENT,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal_nilai      INT NOT NULL DEFAULT 0,
  ppn                 INT NOT NULL DEFAULT 0,
  total_nilai         INT NOT NULL DEFAULT 0,
  iduser              INT NOT NULL,
  idmargin_penjualan  INT NOT NULL,
  PRIMARY KEY (idpenjualan),
  CONSTRAINT fk_penjualan_user   FOREIGN KEY (iduser)             REFERENCES `user` (iduser),
  CONSTRAINT fk_penjualan_margin FOREIGN KEY (idmargin_penjualan) REFERENCES margin_penjualan (idmargin_penjualan)
) ENGINE=InnoDB;

CREATE TABLE detail_penjualan (
  iddetail_penjualan     BIGINT NOT NULL AUTO_INCREMENT,
  harga_satuan           INT NOT NULL,
  jumlah                 INT NOT NULL,
  subtotal               INT NOT NULL,
  penjualan_idpenjualan  INT NOT NULL,
  idbarang               INT NOT NULL,
  PRIMARY KEY (iddetail_penjualan),
  CONSTRAINT fk_dpenjualan_penjualan FOREIGN KEY (penjualan_idpenjualan) REFERENCES penjualan (idpenjualan),
  CONSTRAINT fk_dpenjualan_barang    FOREIGN KEY (idbarang)              REFERENCES barang (idbarang)
) ENGINE=InnoDB;

-- ---------- KARTU STOK (diisi otomatis oleh trigger) ----------
CREATE TABLE kartu_stok (
  idkartu_stok     BIGINT NOT NULL AUTO_INCREMENT,
  jenis_transaksi  CHAR(1) NOT NULL,        -- M=masuk(penerimaan), K=keluar(penjualan), R=retur
  masuk            INT NOT NULL DEFAULT 0,
  keluar           INT NOT NULL DEFAULT 0,
  stock            INT NOT NULL DEFAULT 0,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  idtransaksi      INT NOT NULL,
  idbarang         INT NOT NULL,
  PRIMARY KEY (idkartu_stok),
  CONSTRAINT fk_kartustok_barang FOREIGN KEY (idbarang) REFERENCES barang (idbarang)
) ENGINE=InnoDB;

-- =====================================================================
-- STEP 2 : TRIGGER & STORED PROCEDURE
-- =====================================================================
DELIMITER $$

-- Penerimaan barang -> stok MASUK
CREATE TRIGGER trg_penerimaan_kartu_stok
AFTER INSERT ON detail_penerimaan
FOR EACH ROW
BEGIN
  DECLARE stok_terakhir INT DEFAULT 0;
  SET stok_terakhir = IFNULL((SELECT stock FROM kartu_stok
                              WHERE idbarang = NEW.barang_idbarang
                              ORDER BY idkartu_stok DESC LIMIT 1), 0);
  INSERT INTO kartu_stok (jenis_transaksi, masuk, keluar, stock, created_at, idtransaksi, idbarang)
  VALUES ('M', NEW.jumlah_terima, 0, stok_terakhir + NEW.jumlah_terima, NOW(),
          NEW.idpenerimaan, NEW.barang_idbarang);
END$$

-- Penjualan barang -> stok KELUAR
CREATE TRIGGER trg_penjualan_kartu_stok
AFTER INSERT ON detail_penjualan
FOR EACH ROW
BEGIN
  DECLARE stok_terakhir INT DEFAULT 0;
  SET stok_terakhir = IFNULL((SELECT stock FROM kartu_stok
                              WHERE idbarang = NEW.idbarang
                              ORDER BY idkartu_stok DESC LIMIT 1), 0);
  INSERT INTO kartu_stok (jenis_transaksi, masuk, keluar, stock, created_at, idtransaksi, idbarang)
  VALUES ('K', 0, NEW.jumlah, stok_terakhir - NEW.jumlah, NOW(),
          NEW.penjualan_idpenjualan, NEW.idbarang);
END$$

-- Retur ke vendor -> stok KELUAR
CREATE TRIGGER trg_retur_kartu_stok
AFTER INSERT ON detail_retur
FOR EACH ROW
BEGIN
  DECLARE v_idbarang INT;
  DECLARE stok_terakhir INT DEFAULT 0;
  SET v_idbarang = (SELECT barang_idbarang FROM detail_penerimaan
                    WHERE iddetail_penerimaan = NEW.iddetail_penerimaan);
  SET stok_terakhir = IFNULL((SELECT stock FROM kartu_stok
                              WHERE idbarang = v_idbarang
                              ORDER BY idkartu_stok DESC LIMIT 1), 0);
  INSERT INTO kartu_stok (jenis_transaksi, masuk, keluar, stock, created_at, idtransaksi, idbarang)
  VALUES ('R', 0, NEW.jumlah, stok_terakhir - NEW.jumlah, NOW(),
          NEW.idretur, v_idbarang);
END$$

-- SP 1: Aktifkan 1 margin saja (yang lain otomatis nonaktif)
CREATE PROCEDURE sp_aktifkan_margin (IN p_idmargin INT)
BEGIN
  UPDATE margin_penjualan
  SET status = IF(idmargin_penjualan = p_idmargin, 1, 0);
END$$

-- SP 2: Ubah status Aktif / Nonaktif untuk salah satu dari 6 data master
CREATE PROCEDURE sp_set_status_master (
  IN p_master VARCHAR(30),
  IN p_id     INT,
  IN p_status TINYINT
)
BEGIN
  CASE p_master
    WHEN 'satuan' THEN
      UPDATE satuan SET status = p_status WHERE idsatuan = p_id;
    WHEN 'barang' THEN
      UPDATE barang SET status = p_status WHERE idbarang = p_id;
    WHEN 'vendor' THEN
      UPDATE vendor SET status = IF(p_status = 1, 'A', 'N') WHERE idvendor = p_id;
    WHEN 'role' THEN
      UPDATE role SET status = p_status WHERE idrole = p_id;
    WHEN 'user' THEN
      UPDATE `user` SET status = p_status WHERE iduser = p_id;
    WHEN 'margin' THEN
      IF p_status = 1 THEN
        CALL sp_aktifkan_margin(p_id);
      ELSE
        UPDATE margin_penjualan SET status = 0 WHERE idmargin_penjualan = p_id;
      END IF;
  END CASE;
END$$

-- SP 3: Tambah stok barang via Pengadaan + Penerimaan (otomatis memicu trigger kartu_stok)
CREATE PROCEDURE sp_tambah_stok (
  IN p_iduser       INT,
  IN p_idvendor     INT,
  IN p_idbarang     INT,
  IN p_jumlah       INT,
  IN p_harga_satuan INT
)
BEGIN
  DECLARE v_subtotal INT;
  DECLARE v_ppn INT;
  DECLARE v_total INT;
  DECLARE v_idpengadaan BIGINT;
  DECLARE v_idpenerimaan BIGINT;

  SET v_subtotal = p_jumlah * p_harga_satuan;
  SET v_ppn      = ROUND(v_subtotal * 0.11);
  SET v_total    = v_subtotal + v_ppn;

  INSERT INTO pengadaan (user_iduser, status, vendor_idvendor, subtotal_nilai, ppn, total_nilai)
  VALUES (p_iduser, 'S', p_idvendor, v_subtotal, v_ppn, v_total);
  SET v_idpengadaan = LAST_INSERT_ID();

  INSERT INTO detail_pengadaan (harga_satuan, jumlah, sub_total, idbarang, idpengadaan)
  VALUES (p_harga_satuan, p_jumlah, v_subtotal, p_idbarang, v_idpengadaan);

  INSERT INTO penerimaan (status, idpengadaan, iduser)
  VALUES ('S', v_idpengadaan, p_iduser);
  SET v_idpenerimaan = LAST_INSERT_ID();

  INSERT INTO detail_penerimaan (idpenerimaan, barang_idbarang, jumlah_terima, harga_satuan_terima, sub_total_terima)
  VALUES (v_idpenerimaan, p_idbarang, p_jumlah, p_harga_satuan, v_subtotal);
END$$

-- SP 4: Lihat stok akhir semua barang atau per barang (p_idbarang = 0 untuk semua)
CREATE PROCEDURE sp_cek_stok_barang (IN p_idbarang INT)
BEGIN
  SELECT b.idbarang, b.nama AS nama_barang, s.nama_satuan,
         IF(b.status = 1, 'Aktif', 'Nonaktif') AS status,
         IFNULL((SELECT ks.stock FROM kartu_stok ks
                  WHERE ks.idbarang = b.idbarang
                  ORDER BY ks.idkartu_stok DESC LIMIT 1), 0) AS stok_akhir
  FROM barang b
  JOIN satuan s ON s.idsatuan = b.idsatuan
  WHERE (p_idbarang = 0 OR b.idbarang = p_idbarang)
  ORDER BY b.nama;
END$$

DELIMITER ;

-- =====================================================================
-- STEP 3 : VIEW DATA MASTER (6 view semua + 12 view aktif/nonaktif + 1 margin aktif)
-- =====================================================================

-- ---------- 6 VIEW SEMUA DATA MASTER ----------
CREATE VIEW v_satuan AS
SELECT idsatuan, nama_satuan,
       IF(status = 1, 'Aktif', 'Nonaktif') AS status
FROM satuan;

CREATE VIEW v_barang AS
SELECT b.idbarang, b.nama AS nama_barang,
       CASE b.jenis WHEN 'M' THEN 'Makanan' WHEN 'N' THEN 'Minuman' ELSE 'Lainnya' END AS jenis,
       s.nama_satuan, b.harga,
       IF(b.status = 1, 'Aktif', 'Nonaktif') AS status
FROM barang b
JOIN satuan s ON s.idsatuan = b.idsatuan;

CREATE VIEW v_vendor AS
SELECT idvendor, nama_vendor,
       CASE badan_hukum WHEN 'P' THEN 'PT' WHEN 'C' THEN 'CV' ELSE 'Perorangan' END AS badan_hukum,
       IF(status = 'A', 'Aktif', 'Nonaktif') AS status
FROM vendor;

CREATE VIEW v_role AS
SELECT idrole, nama_role,
       IF(status = 1, 'Aktif', 'Nonaktif') AS status
FROM role;

CREATE VIEW v_user AS            -- password sengaja tidak ditampilkan
SELECT u.iduser, u.username, r.nama_role,
       IF(u.status = 1, 'Aktif', 'Nonaktif') AS status
FROM `user` u
JOIN role r ON r.idrole = u.idrole;

CREATE VIEW v_margin_penjualan AS
SELECT m.idmargin_penjualan, CONCAT(m.persen, '%') AS margin,
       IF(m.status = 1, 'Aktif', 'Nonaktif') AS status,
       u.username AS dibuat_oleh, m.created_at, m.updated_at
FROM margin_penjualan m
JOIN `user` u ON u.iduser = m.iduser;

CREATE VIEW v_margin_aktif AS
SELECT idmargin_penjualan, persen
FROM margin_penjualan
WHERE status = 1;

-- ---------- 12 VIEW AKTIF & NONAKTIF (2 per Master x 6 Master) ----------
-- 1 & 2. Satuan
CREATE VIEW v_satuan_aktif AS
SELECT idsatuan, nama_satuan, 'Aktif' AS status
FROM satuan WHERE status = 1;

CREATE VIEW v_satuan_nonaktif AS
SELECT idsatuan, nama_satuan, 'Nonaktif' AS status
FROM satuan WHERE status = 0;

-- 3 & 4. Barang
CREATE VIEW v_barang_aktif AS
SELECT b.idbarang, b.nama AS nama_barang,
       CASE b.jenis WHEN 'M' THEN 'Makanan' WHEN 'N' THEN 'Minuman' ELSE 'Lainnya' END AS jenis,
       s.nama_satuan, b.harga, 'Aktif' AS status
FROM barang b
JOIN satuan s ON s.idsatuan = b.idsatuan
WHERE b.status = 1;

CREATE VIEW v_barang_nonaktif AS
SELECT b.idbarang, b.nama AS nama_barang,
       CASE b.jenis WHEN 'M' THEN 'Makanan' WHEN 'N' THEN 'Minuman' ELSE 'Lainnya' END AS jenis,
       s.nama_satuan, b.harga, 'Nonaktif' AS status
FROM barang b
JOIN satuan s ON s.idsatuan = b.idsatuan
WHERE b.status = 0;

-- 5 & 6. Vendor
CREATE VIEW v_vendor_aktif AS
SELECT idvendor, nama_vendor,
       CASE badan_hukum WHEN 'P' THEN 'PT' WHEN 'C' THEN 'CV' ELSE 'Perorangan' END AS badan_hukum,
       'Aktif' AS status
FROM vendor WHERE status = 'A';

CREATE VIEW v_vendor_nonaktif AS
SELECT idvendor, nama_vendor,
       CASE badan_hukum WHEN 'P' THEN 'PT' WHEN 'C' THEN 'CV' ELSE 'Perorangan' END AS badan_hukum,
       'Nonaktif' AS status
FROM vendor WHERE status <> 'A';

-- 7 & 8. Role
CREATE VIEW v_role_aktif AS
SELECT idrole, nama_role, 'Aktif' AS status
FROM role WHERE status = 1;

CREATE VIEW v_role_nonaktif AS
SELECT idrole, nama_role, 'Nonaktif' AS status
FROM role WHERE status = 0;

-- 9 & 10. User
CREATE VIEW v_user_aktif AS
SELECT u.iduser, u.username, r.nama_role, 'Aktif' AS status
FROM `user` u
JOIN role r ON r.idrole = u.idrole
WHERE u.status = 1;

CREATE VIEW v_user_nonaktif AS
SELECT u.iduser, u.username, r.nama_role, 'Nonaktif' AS status
FROM `user` u
JOIN role r ON r.idrole = u.idrole
WHERE u.status = 0;

-- 11 & 12. Margin Penjualan
CREATE VIEW v_margin_penjualan_aktif AS
SELECT m.idmargin_penjualan, CONCAT(m.persen, '%') AS margin,
       'Aktif' AS status, u.username AS dibuat_oleh, m.created_at, m.updated_at
FROM margin_penjualan m
JOIN `user` u ON u.iduser = m.iduser
WHERE m.status = 1;

CREATE VIEW v_margin_penjualan_nonaktif AS
SELECT m.idmargin_penjualan, CONCAT(m.persen, '%') AS margin,
       'Nonaktif' AS status, u.username AS dibuat_oleh, m.created_at, m.updated_at
FROM margin_penjualan m
JOIN `user` u ON u.iduser = m.iduser
WHERE m.status = 0;

-- =====================================================================
-- STEP 4 : ISI DATA MASTER
-- =====================================================================
INSERT INTO satuan (nama_satuan, status) VALUES
 ('Pcs', 1), ('Botol', 1), ('Karung', 1), ('Box', 0);

INSERT INTO vendor (nama_vendor, badan_hukum, status) VALUES
 ('PT Sumber Pangan Makmur', 'P', 'A'),
 ('CV Berkah Jaya',          'C', 'A'),
 ('UD Lama Nonaktif',        'O', 'N');

INSERT INTO role (nama_role, status) VALUES
 ('Admin', 1), ('Kasir', 1), ('Supervisor', 0);

INSERT INTO `user` (username, password, idrole, status) VALUES
 ('admin', SHA2('admin123', 256), 1, 1),
 ('kasir', SHA2('kasir123', 256), 2, 1),
 ('staff_lama', SHA2('staff123', 256), 2, 0);

-- 4 produk (jenis: M=Makanan, N=Minuman, L=Lainnya)
INSERT INTO barang (jenis, nama, idsatuan, status, harga) VALUES
 ('M', 'Indomie Goreng', 1, 1, 3000),
 ('N', 'Aqua 600ml',     2, 1, 3500),
 ('M', 'Beras 5kg',      3, 1, 65000),
 ('L', 'Tisu Lama',      1, 0, 5000);

-- 5 margin: 10,20,30,40,50 -> hanya 1 yang aktif
INSERT INTO margin_penjualan (persen, status, iduser) VALUES
 (10, 0, 1), (20, 0, 1), (30, 0, 1), (40, 0, 1), (50, 0, 1);
CALL sp_aktifkan_margin(3);      -- aktifkan margin 30%

-- =====================================================================
-- STEP 5 : DATA TRANSAKSI CONTOH (untuk menguji trigger)
-- =====================================================================

-- Pengadaan ke vendor 1 (subtotal 1.125.000 + ppn 11% = 1.248.750)
INSERT INTO pengadaan (user_iduser, status, vendor_idvendor, subtotal_nilai, ppn, total_nilai)
VALUES (1, 'S', 1, 1125000, 123750, 1248750);

INSERT INTO detail_pengadaan (harga_satuan, jumlah, sub_total, idbarang, idpengadaan) VALUES
 (3000, 100, 300000, 1, 1),
 (3500,  50, 175000, 2, 1),
 (65000, 10, 650000, 3, 1);

-- Penerimaan -> trigger mengisi kartu_stok (masuk)
INSERT INTO penerimaan (status, idpengadaan, iduser) VALUES ('S', 1, 1);

INSERT INTO detail_penerimaan (idpenerimaan, barang_idbarang, jumlah_terima, harga_satuan_terima, sub_total_terima) VALUES
 (1, 1, 100,  3000, 300000),
 (1, 2,  50,  3500, 175000),
 (1, 3,  10, 65000, 650000);

-- Penjualan memakai margin aktif (30%) -> trigger mengisi kartu_stok (keluar)
-- Indomie 3000*1.3 = 3900 ; Aqua 3500*1.3 = 4550
INSERT INTO penjualan (subtotal_nilai, ppn, total_nilai, iduser, idmargin_penjualan)
VALUES (61750, 6793, 68543, 2, 3);

INSERT INTO detail_penjualan (harga_satuan, jumlah, subtotal, penjualan_idpenjualan, idbarang) VALUES
 (3900, 10, 39000, 1, 1),
 (4550,  5, 22750, 1, 2);

-- Retur 2 karung beras ke vendor -> trigger mengisi kartu_stok (keluar)
INSERT INTO retur (idpenerimaan, iduser) VALUES (1, 1);
INSERT INTO detail_retur (jumlah, alasan, idretur, iddetail_penerimaan)
VALUES (2, 'Kemasan sobek / rusak', 1, 3);

-- =====================================================================
-- STEP 6 : QUERY PENGUJIAN (12 View Aktif/Nonaktif + SP)
-- =====================================================================
-- SELECT * FROM v_satuan_aktif;
-- SELECT * FROM v_satuan_nonaktif;
-- SELECT * FROM v_barang_aktif;
-- SELECT * FROM v_barang_nonaktif;
-- SELECT * FROM v_vendor_aktif;
-- SELECT * FROM v_vendor_nonaktif;
-- SELECT * FROM v_role_aktif;
-- SELECT * FROM v_role_nonaktif;
-- SELECT * FROM v_user_aktif;
-- SELECT * FROM v_user_nonaktif;
-- SELECT * FROM v_margin_penjualan_aktif;
-- SELECT * FROM v_margin_penjualan_nonaktif;
-- CALL sp_cek_stok_barang(0);
