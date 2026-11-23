-- =====================================================================
-- 100 DATA BARANG (20 JENIS x 5 BARANG = 100 BARANG)
-- Cocok untuk tabel `barang` di database `db_uts_penjualan`
-- Catatan: setelah di-INSERT ke `barang`, barang otomatis muncul di
--          halaman web (Master Barang, Kasir, Penerimaan, Kartu Stok).
-- =====================================================================

USE db_uts_penjualan;

INSERT INTO barang (jenis, nama, idsatuan, status, harga) VALUES
  -- [M] Makanan Instan & Mi
  ('M', 'Indomie Ayam Bawang', 1, 1, 2900),
  ('M', 'Indomie Soto Mie', 1, 1, 2950),
  ('M', 'Mie Sedaap Goreng', 1, 1, 2850),
  ('M', 'Pop Mie Ayam', 1, 1, 5500),
  ('M', 'Sarimi Isi 2 Ayam Kecap', 1, 1, 4200),
  -- [N] Minuman Air & Teh
  ('N', 'Le Minerale 600ml', 2, 1, 3200),
  ('N', 'Teh Botol Sosro Kotak 300ml', 2, 1, 4000),
  ('N', 'Fruit Tea Apel 350ml', 2, 1, 4500),
  ('N', 'Frestea Teh Hijau Madu 350ml', 2, 1, 4200),
  ('N', 'Floridina Coco Orange 350ml', 2, 1, 3500),
  -- [S] Susu & Olahan Susu
  ('S', 'Ultra Milk Coklat 250ml', 2, 1, 6500),
  ('S', 'Ultra Milk Full Cream 250ml', 2, 1, 6500),
  ('S', 'Frisian Flag Bendera Putih 370g', 2, 1, 14500),
  ('S', 'Indomilk Susu Cair Stroberi 190ml', 2, 1, 4800),
  ('S', 'Bear Brand Susu Steril 189ml', 2, 1, 9800),
  -- [K] Kopi & Minuman Serbuk
  ('K', 'Kapal Api Special Mix Sachet', 1, 1, 1800),
  ('K', 'Good Day Cappuccino Sachet', 1, 1, 2200),
  ('K', 'Luwak White Koffie Sachet', 1, 1, 2000),
  ('K', 'Nescafe Classic Sachet 2g', 1, 1, 1500),
  ('K', 'Chocolatos Drink Coklat Sachet', 1, 1, 2300),
  -- [R] Roti & Biskuit
  ('R', 'Roma Sari Gandum Sandwich', 1, 1, 8500),
  ('R', 'Oreo Vanilla Roll 133g', 1, 1, 9500),
  ('R', 'Better Sandwich Coklat', 1, 1, 2000),
  ('R', 'Tango Wafer Coklat 130g', 1, 1, 8800),
  ('R', 'Sari Roti Tawar Kupas', 1, 1, 15000),
  -- [C] Camilan & Snack Gurih
  ('C', 'Chitato Sapi Panggang 68g', 1, 1, 10500),
  ('C', 'Qtela Singkong Balado 60g', 1, 1, 7500),
  ('C', 'Taro Net Seaweed 36g', 1, 1, 4500),
  ('C', 'Kusuka Keripik Singkong BBQ', 1, 1, 6500),
  ('C', 'Kacang Garuda Kulit 100g', 1, 1, 9200),
  -- [B] Beras & Bahan Pokok
  ('B', 'Beras Ramos Super 5kg', 3, 1, 68000),
  ('B', 'Beras Ketan Putih 1kg', 1, 1, 18500),
  ('B', 'Gula Pasir Gulaku Kuning 1kg', 1, 1, 17500),
  ('B', 'Tepung Terigu Segitiga Biru 1kg', 1, 1, 12500),
  ('B', 'Garam Meja Refina 500g', 1, 1, 4500),
  -- [Y] Minyak & Mentega
  ('Y', 'Minyak Goreng Bimoli 1L', 2, 1, 18500),
  ('Y', 'Minyak Goreng Tropical 2L', 2, 1, 36000),
  ('Y', 'Minyak Goreng Sania 1L', 2, 1, 18000),
  ('Y', 'Blue Band Cake & Cookie 200g', 1, 1, 11500),
  ('Y', 'Palmboom Margarine 200g', 1, 1, 8500),
  -- [P] Penyegar & Soda
  ('P', 'Pocari Sweat 500ml', 2, 1, 7500),
  ('P', 'Mizone Lychee Lemon 500ml', 2, 1, 5500),
  ('P', 'Coca-Cola Original 390ml', 2, 1, 5800),
  ('P', 'Sprite Lemon Lime 390ml', 2, 1, 5600),
  ('P', 'Fanta Stroberi 390ml', 2, 1, 5600),
  -- [D] Bumbu Dapur & Saus
  ('D', 'Kecap Manis Bango 275ml', 2, 1, 16500),
  ('D', 'Saos Sambal ABC Ekstra Pedas 135ml', 2, 1, 7800),
  ('D', 'Saos Tiram Saori 133ml', 2, 1, 12500),
  ('D', 'Masako Rasa Ayam Sachet', 1, 1, 800),
  ('D', 'Royco Bumbu Pelezat Sapi Sachet', 1, 1, 850),
  -- [E] Es Krim & Dessert Beku
  ('E', 'Walls Cornetto Silver Queen', 1, 1, 12000),
  ('E', 'Walls Feast Coklat', 1, 1, 6500),
  ('E', 'Campina Hula-Hula Kacang Hijau', 1, 1, 5500),
  ('E', 'Aice Chocolate Crispy', 1, 1, 4000),
  ('E', 'Joyday Crunchy Chocolate', 1, 1, 5000),
  -- [A] Coklat & Permen
  ('A', 'SilverQueen Chunky Bar Cashew 95g', 1, 1, 23500),
  ('A', 'Beng-Beng Wafer Coklat', 1, 1, 2500),
  ('A', 'Top Coklat Wafer Roll', 1, 1, 1500),
  ('A', 'Relaxa Barley Mint', 1, 1, 6500),
  ('A', 'Kopiko Blanca Candy', 1, 1, 6000),
  -- [H] Kebersihan Rumah Tangga
  ('H', 'Sunlight Jeruk Nipis 650ml', 2, 1, 13500),
  ('H', 'Mama Lemon Jeruk Nipis 680ml', 2, 1, 12500),
  ('H', 'Super Pel Pembersih Lantai 770ml', 2, 1, 14500),
  ('H', 'Bayclin Pemutih Pakaian 500ml', 2, 1, 11000),
  ('H', 'Vixal Pembersih Porselen 780ml', 2, 1, 18500),
  -- [W] Deterjen & Pewangi
  ('W', 'Rinso Molto Cair 700ml', 2, 1, 19500),
  ('W', 'So Klin Liquid Royale 720ml', 2, 1, 15500),
  ('W', 'Molto Pewangi Trika 820ml', 2, 1, 16500),
  ('W', 'Attack Jaz1 Pesona Segar 800g', 1, 1, 18000),
  ('W', 'Daia Putih Bunga 850g', 1, 1, 16500),
  -- [T] Tisu & Perlengkapan Kertas
  ('T', 'Paseo Soft Pack 250 Sheets', 1, 1, 13500),
  ('T', 'Nice Facial Tissue 180 Sheets', 1, 1, 11500),
  ('T', 'Tessa Toilet Roll 4s', 1, 1, 14000),
  ('T', 'Multi Facial Tissue 200s', 1, 1, 10500),
  ('T', 'Kertas Nasi Coklat 50 Lembar', 1, 1, 9500),
  -- [F] Perawatan Diri & Mandi
  ('F', 'Lifebuoy Sabun Cair Total 400ml', 2, 1, 22500),
  ('F', 'Biore Body Foam Relaxing 400ml', 2, 1, 23000),
  ('F', 'Sunsilk Soft & Smooth Shampoo 170ml', 2, 1, 21500),
  ('F', 'Clear Men Cool Sport 160ml', 2, 1, 24000),
  ('F', 'Pepsodent White 190g', 1, 1, 13000),
  -- [O] Deodoran & Parfum
  ('O', 'Rexona Roll On Free Spirit 45ml', 2, 1, 19500),
  ('O', 'Dove Roll On Original 40ml', 2, 1, 21000),
  ('O', 'Nivea Men Cool Kick Roll On 50ml', 2, 1, 22500),
  ('O', 'Marina Cologne Fresh Bouquet 150ml', 2, 1, 14500),
  ('O', 'Pucelle Eau De Luxe 100ml', 2, 1, 26500),
  -- [I] Bayi & Anak
  ('I', 'MamyPoko Pants X-tra Kering M30', 1, 1, 48000),
  ('I', 'Sweety Bronze Comfort NB20', 1, 1, 32000),
  ('I', 'Zwitsal Baby Bath Hair & Body 450ml', 2, 1, 29500),
  ('I', 'Cussons Baby Powder Soft 100g', 1, 1, 11500),
  ('I', 'Kodomo Toothpaste Strawberry', 1, 1, 8500),
  -- [G] Obat Ringan & Kesehatan
  ('G', 'Tolak Angin Cair Madu Sachet', 1, 1, 4200),
  ('G', 'Antangin JRG Cair Sachet', 1, 1, 3800),
  ('G', 'Woods Peppermint Expectorant 60ml', 2, 1, 18500),
  ('G', 'Betadine Antiseptic Solution 15ml', 2, 1, 16000),
  ('G', 'Hansaplast Kain Elastis Isi 10', 1, 1, 9500),
  -- [L] Perlengkapan Lainnya
  ('L', 'Baterai ABC Alkaline AA Isi 2', 1, 1, 14500),
  ('L', 'Lilin Putih Batang Isi 10', 1, 1, 12000),
  ('L', 'Korek Api Gas Warna', 1, 1, 3500),
  ('L', 'Spons Cuci Piring Busa', 1, 1, 4500),
  ('L', 'Karet Gelang Benang 100g', 1, 1, 7500);
