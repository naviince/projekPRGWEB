-- ==========================================================================================
-- SKRIP DATA TAMBAHAN HISTORIS (TAHUN 2024, 2025, & 2026)
-- SPOTLIGHT STUDIO — UNTUK KEBUTUHAN DEMO SIDANG
-- ==========================================================================================
USE SpotLight;
GO

-- Buat Tabel Sementara untuk Menangkap ID yang dihasilkan oleh Stored Procedure
IF OBJECT_ID('tempdb..#TmpID') IS NOT NULL DROP TABLE #TmpID;
CREATE TABLE #TmpID (ID INT);
GO


-- ==========================================================================================
-- BAGIAN 1: TRANSAKSI TAHUN 2024 (Awal Operasional, Volume Rendah)
-- ==========================================================================================

-- SKENARIO A (Selesai Lunas + Cetak Barang + Ulasan): Elisa Larasati (ID=2)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @PembayaranDP_ID INT, @SesiID INT, @PembayaranLunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    -- 1. Buat Jadwal Studio
    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 1, @Tanggal = '2024-04-12', @JamMulai = '10:00', @JamSelesai = '11:00', @Keterangan = 'Slot Tambahan April 2024', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    -- 2. Buat Order Booking
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 2, @ID_Paket = 1, @ID_Ruangan = 1, @ID_Tema = 1, @JadwalList = @JList, @Keterangan = 'Sesi Foto Kasual April 2024', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    -- Atur tanggal booking ke masa lalu (2024)
    UPDATE [Order] SET Tanggal_Booking = '2024-04-10 14:00:00' WHERE ID_Order = @OrderID;

    -- 3. Pembayaran DP (Setengah Harga Paket Mandiri: 100.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 100000, @Bukti = 'bukti_dp_a.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranDP_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Pembayaran SET Tanggal_Upload = '2024-04-10 14:30:00' WHERE ID_Pembayaran = @PembayaranDP_ID;

    -- Verifikasi DP oleh Admin
    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranDP_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    -- 4. Mulai Sesi Foto
    INSERT INTO @TmpTable EXEC sp_MulaiSesiFoto @ID_Order = @OrderID, @ID_Fotografer = 5, @CreatedBy = 'admin';
    SELECT @SesiID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Sesi_Foto SET Waktu_Mulai = '2024-04-12 10:00:00' WHERE ID_Sesi_Foto = @SesiID;

    -- Simpan detail hasil foto individual ke Hasil_Foto
    EXEC sp_InsertHasilFoto @ID_Sesi_Foto = @SesiID, @Nama_File = 'img_01_2024.jpg', @Tipe_File = 'image', @Ukuran_Bytes = 2048000, @Urutan = 1, @Created_By = 'foto_admin';
    EXEC sp_InsertHasilFoto @ID_Sesi_Foto = @SesiID, @Nama_File = 'img_02_2024.jpg', @Tipe_File = 'image', @Ukuran_Bytes = 2150400, @Urutan = 2, @Created_By = 'foto_admin';

    -- Selesaikan Sesi Foto
    EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = @SesiID, @File_Hasil = 'hasil_a.zip', @Modified_By = 'admin';
    UPDATE Sesi_Foto SET Waktu_Selesai = '2024-04-12 11:00:00', Tanggal_Upload_Hasil = '2024-04-12 15:00:00' WHERE ID_Sesi_Foto = @SesiID;

    -- 5. Pelunasan Sisa Pembayaran (100.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'Pelunasan', @Metode = 'Transfer Bank', @Jumlah = 100000, @Bukti = 'bukti_lunas_a.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranLunas_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Pembayaran SET Tanggal_Upload = '2024-04-12 16:00:00' WHERE ID_Pembayaran = @PembayaranLunas_ID;

    -- Verifikasi Pelunasan oleh Admin
    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranLunas_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    -- 6. Penjualan Tambahan (Barang Cetak: 2 lembar 4R = 20.000)
    INSERT INTO @TmpTable EXEC sp_CreatePenjualan @ID_Order = @OrderID, @ID_Admin = 1, @CreatedBy = 'admin';
    SELECT @PenjualanID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Penjualan SET Tanggal_Penjualan = '2024-04-12 16:30:00', Status_Penjualan = 1 WHERE ID_Penjualan = @PenjualanID;

    EXEC sp_InsertDetailPenjualan @ID_Penjualan = @PenjualanID, @ID_Barang = 1, @Jumlah = 2;

    -- 7. Ulasan dari Pelanggan
    EXEC sp_UpdateOrder @ID = @OrderID, @Keterangan = 'Sesi Foto Kasual April 2024', @Rating = 5, @Review = 'Pelayanan ramah, foto jernih banget!', @ModifiedBy = 'customer';
END
GO


-- SKENARIO B (Selesai Lunas - Tanpa Cetak): Amar Faiz (ID=3)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @PembayaranDP_ID INT, @SesiID INT, @PembayaranLunas_ID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 2, @Tanggal = '2024-08-15', @JamMulai = '10:00', @JamSelesai = '11:00', @Keterangan = 'Slot Romantis Agustus 2024', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 3, @ID_Paket = 2, @ID_Ruangan = 2, @ID_Tema = 2, @JadwalList = @JList, @Keterangan = 'Sesi Prewedding Kasual', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    UPDATE [Order] SET Tanggal_Booking = '2024-08-12 10:00:00' WHERE ID_Order = @OrderID;

    -- DP (175.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'DP', @Metode = 'QRIS', @Jumlah = 175000, @Bukti = 'bukti_dp_b.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranDP_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Pembayaran SET Tanggal_Upload = '2024-08-12 11:00:00' WHERE ID_Pembayaran = @PembayaranDP_ID;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranDP_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    -- Sesi
    INSERT INTO @TmpTable EXEC sp_MulaiSesiFoto @ID_Order = @OrderID, @ID_Fotografer = 5, @CreatedBy = 'admin';
    SELECT @SesiID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Sesi_Foto SET Waktu_Mulai = '2024-08-15 10:00:00' WHERE ID_Sesi_Foto = @SesiID;

    EXEC sp_InsertHasilFoto @ID_Sesi_Foto = @SesiID, @Nama_File = 'img_prewed_01.jpg', @Tipe_File = 'image', @Ukuran_Bytes = 3100000, @Urutan = 1, @Created_By = 'foto_admin';

    EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = @SesiID, @File_Hasil = 'prewed_final.zip', @Modified_By = 'admin';
    UPDATE Sesi_Foto SET Waktu_Selesai = '2024-08-15 11:00:00', Tanggal_Upload_Hasil = '2024-08-15 14:00:00' WHERE ID_Sesi_Foto = @SesiID;

    -- Pelunasan (175.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'Pelunasan', @Metode = 'QRIS', @Jumlah = 175000, @Bukti = 'bukti_lunas_b.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranLunas_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Pembayaran SET Tanggal_Upload = '2024-08-15 15:00:00' WHERE ID_Pembayaran = @PembayaranLunas_ID;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranLunas_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    EXEC sp_UpdateOrder @ID = @OrderID, @Keterangan = 'Sesi Prewedding Kasual', @Rating = 4, @Review = 'Studio bersih dan fotografer sangat mengarahkan gaya dengan baik.', @ModifiedBy = 'customer';
END
GO


-- SKENARIO C (Pemesanan Dibatalkan/Kadaluarsa - Tanpa DP): Nabila Tul (ID=4)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 4, @Tanggal = '2024-11-10', @JamMulai = '14:00', @JamSelesai = '15:00', @Keterangan = 'Slot Wisuda November 2024', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 4, @ID_Paket = 4, @ID_Ruangan = 4, @ID_Tema = 4, @JadwalList = @JList, @Keterangan = 'Booking Wisuda Batal', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    UPDATE [Order] SET Tanggal_Booking = '2024-11-05 10:00:00' WHERE ID_Order = @OrderID;

    -- Simulasi Pembatalan oleh sistem karena melewati batas bayar DP
    EXEC sp_BatalkanOrderBooking @ID_Order = @OrderID, @Modified_By = 'system';
END
GO


-- ==========================================================================================
-- BAGIAN 2: TRANSAKSI TAHUN 2025 (Tahap Pertumbuhan, Volume Sedang)
-- ==========================================================================================

-- SKENARIO D (Selesai Lunas + Cetak Nilai Besar + Ulasan): Thoriq Al (ID=5)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @PembayaranDP_ID INT, @SesiID INT, @PembayaranLunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 3, @Tanggal = '2025-05-15', @JamMulai = '11:00', @JamSelesai = '12:30', @Keterangan = 'Sesi Keluarga Besar Mei 2025', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 5, @ID_Paket = 3, @ID_Ruangan = 3, @ID_Tema = 3, @JadwalList = @JList, @Keterangan = 'Foto Keluarga Hari Ibu', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    UPDATE [Order] SET Tanggal_Booking = '2025-05-10 09:00:00' WHERE ID_Order = @OrderID;

    -- DP (300.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 300000, @Bukti = 'bukti_dp_d.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranDP_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Pembayaran SET Tanggal_Upload = '2025-05-10 10:00:00' WHERE ID_Pembayaran = @PembayaranDP_ID;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranDP_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    -- Sesi
    INSERT INTO @TmpTable EXEC sp_MulaiSesiFoto @ID_Order = @OrderID, @ID_Fotografer = 5, @CreatedBy = 'admin';
    SELECT @SesiID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Sesi_Foto SET Waktu_Mulai = '2025-05-15 11:00:00' WHERE ID_Sesi_Foto = @SesiID;

    EXEC sp_InsertHasilFoto @ID_Sesi_Foto = @SesiID, @Nama_File = 'img_fam_01.jpg', @Tipe_File = 'image', @Ukuran_Bytes = 4500000, @Urutan = 1, @Created_By = 'foto_admin';
    EXEC sp_InsertHasilFoto @ID_Sesi_Foto = @SesiID, @Nama_File = 'img_fam_02.jpg', @Tipe_File = 'image', @Ukuran_Bytes = 4300000, @Urutan = 2, @Created_By = 'foto_admin';

    EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = @SesiID, @File_Hasil = 'family_2025.zip', @Modified_By = 'admin';
    UPDATE Sesi_Foto SET Waktu_Selesai = '2025-05-15 12:30:00', Tanggal_Upload_Hasil = '2025-05-16 10:00:00' WHERE ID_Sesi_Foto = @SesiID;

    -- Pelunasan (300.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'Pelunasan', @Metode = 'Transfer Bank', @Jumlah = 300000, @Bukti = 'bukti_lunas_d.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranLunas_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Pembayaran SET Tanggal_Upload = '2025-05-16 11:00:00' WHERE ID_Pembayaran = @PembayaranLunas_ID;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranLunas_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    -- Cetak Album & Bingkai (Nilai Aset Tinggi: Photobook: 200.000 + 2 Bingkai: 100.000 = total 300.000)
    INSERT INTO @TmpTable EXEC sp_CreatePenjualan @ID_Order = @OrderID, @ID_Admin = 1, @CreatedBy = 'admin';
    SELECT @PenjualanID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Penjualan SET Tanggal_Penjualan = '2025-05-16 13:00:00', Status_Penjualan = 1 WHERE ID_Penjualan = @PenjualanID;

    EXEC sp_InsertDetailPenjualan @ID_Penjualan = @PenjualanID, @ID_Barang = 5, @Jumlah = 1; -- Photobook
    EXEC sp_InsertDetailPenjualan @ID_Penjualan = @PenjualanID, @ID_Barang = 4, @Jumlah = 2; -- Bingkai

    EXEC sp_UpdateOrder @ID = @OrderID, @Keterangan = 'Foto Keluarga Hari Ibu', @Rating = 5, @Review = 'Photobook sangat premium, bapak-ibu saya suka sekali.', @ModifiedBy = 'customer';
END
GO


-- SKENARIO E (DP Ditolak -> Dibatalkan): Elisa Larasati (ID=2)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @PembayaranDP_ID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 2, @Tanggal = '2025-09-12', @JamMulai = '13:00', @JamSelesai = '14:00', @Keterangan = 'Sesi Romantis September 2025', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 2, @ID_Paket = 2, @ID_Ruangan = 2, @ID_Tema = 2, @JadwalList = @JList, @Keterangan = 'Sesi Couple Autumn', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    UPDATE [Order] SET Tanggal_Booking = '2025-09-10 11:00:00' WHERE ID_Order = @OrderID;

    -- Pelanggan mengunggah bukti palsu / salah
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'DP', @Metode = 'QRIS', @Jumlah = 175000, @Bukti = 'bukti_dp_palsu.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranDP_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    -- Admin Menolak Pembayaran (Status = 2)
    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranDP_ID, @Status_Verifikasi = 2, @ID_Admin = 1, @Modified_By = 'admin';

    -- Skenario lanjutan: Karena tidak diperbaiki, pesanan akhirnya dibatalkan
    EXEC sp_BatalkanOrderBooking @ID_Order = @OrderID, @Modified_By = 'admin';
END
GO


-- ==========================================================================================
-- BAGIAN 3: TRANSAKSI TAHUN 2026 (Tahap Matang, Volume Tinggi & Antrean Aktif)
-- ==========================================================================================

-- SKENARIO F (Selesai Lunas): Bintang Basev (ID=1)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @PembayaranDP_ID INT, @SesiID INT, @PembayaranLunas_ID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 1, @Tanggal = '2026-02-10', @JamMulai = '09:00', @JamSelesai = '10:00', @Keterangan = 'Sesi Minimalis Februari 2026', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 1, @ID_Paket = 1, @ID_Ruangan = 1, @ID_Tema = 1, @JadwalList = @JList, @Keterangan = 'Foto Personal Profil', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    UPDATE [Order] SET Tanggal_Booking = '2026-02-08 15:00:00' WHERE ID_Order = @OrderID;

    -- DP (100.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'DP', @Metode = 'QRIS', @Jumlah = 100000, @Bukti = 'bukti_dp_f.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranDP_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranDP_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    -- Sesi
    INSERT INTO @TmpTable EXEC sp_MulaiSesiFoto @ID_Order = @OrderID, @ID_Fotografer = 5, @CreatedBy = 'admin';
    SELECT @SesiID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    UPDATE Sesi_Foto SET Waktu_Mulai = '2026-02-10 09:00:00' WHERE ID_Sesi_Foto = @SesiID;

    EXEC sp_InsertHasilFoto @ID_Sesi_Foto = @SesiID, @Nama_File = 'img_profile_2026.jpg', @Tipe_File = 'image', @Ukuran_Bytes = 2500000, @Urutan = 1, @Created_By = 'foto_admin';

    EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = @SesiID, @File_Hasil = 'profile_lunas.zip', @Modified_By = 'admin';
    UPDATE Sesi_Foto SET Waktu_Selesai = '2026-02-10 10:00:00', Tanggal_Upload_Hasil = '2026-02-10 12:00:00' WHERE ID_Sesi_Foto = @SesiID;

    -- Lunas (100.000)
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'Pelunasan', @Metode = 'QRIS', @Jumlah = 100000, @Bukti = 'bukti_lunas_f.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranLunas_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranLunas_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';
    
    EXEC sp_UpdateOrder @ID = @OrderID, @Keterangan = 'Foto Personal Profil', @Rating = 5, @Review = 'Sangat memuaskan dan cepat layanannya.', @ModifiedBy = 'customer';
END
GO


-- SKENARIO G (Antrean Aktif: Selesai Sesi, Menunggu Pelunasan): Elisa Larasati (ID=2)
-- Sangat bagus didemokan untuk menunjukkan proses filter antrean kasir di aplikasi.
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @PembayaranDP_ID INT, @SesiID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    -- Jadwal baru bertepatan dengan tanggal di bulan berjalan (Juli 2026)
    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 4, @Tanggal = '2026-07-20', @JamMulai = '11:00', @JamSelesai = '12:00', @Keterangan = 'Slot Kelulusan Juli 2026', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 2, @ID_Paket = 4, @ID_Ruangan = 4, @ID_Tema = 4, @JadwalList = @JList, @Keterangan = 'Wisuda Sarjana Elisa', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    UPDATE [Order] SET Tanggal_Booking = '2026-07-18 10:00:00' WHERE ID_Order = @OrderID;

    -- Bayar DP (225.000) & diverifikasi
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 225000, @Bukti = 'bukti_dp_g.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranDP_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranDP_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

    -- Sesi Berjalan & Selesai Diupload Hasilnya (Menunggu Pelunasan customer)
    INSERT INTO @TmpTable EXEC sp_MulaiSesiFoto @ID_Order = @OrderID, @ID_Fotografer = 5, @CreatedBy = 'admin';
    SELECT @SesiID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    EXEC sp_InsertHasilFoto @ID_Sesi_Foto = @SesiID, @Nama_File = 'preview_elisa_01.jpg', @Tipe_File = 'image', @Ukuran_Bytes = 1800000, @Urutan = 1, @Created_By = 'foto_admin';

    EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = @SesiID, @File_Hasil = 'elisa_preview.zip', @Modified_By = 'admin';
END
GO


-- SKENARIO H (Mendatang: DP Terverifikasi, Menunggu Jadwal Sesi): Amar Faiz (ID=3)
-- Menunjukkan jadwal yang akan datang di dashboard Admin/Fotografer.
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @PembayaranDP_ID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    -- Jadwal mendatang di Agustus 2026
    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 3, @Tanggal = '2026-08-05', @JamMulai = '10:00', @JamSelesai = '11:30', @Keterangan = 'Foto Keluarga Mendatang', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 3, @ID_Paket = 3, @ID_Ruangan = 3, @ID_Tema = 3, @JadwalList = @JList, @Keterangan = 'Keluarga Amar Sesi 2', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    -- Upload DP & Verifikasi
    INSERT INTO @TmpTable EXEC sp_InsertPembayaran @ID_Order = @OrderID, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 300000, @Bukti = 'bukti_dp_h.jpg', @CreatedBy = 'customer';
    SELECT @PembayaranDP_ID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    EXEC sp_VerifikasiPembayaran @ID_Pembayaran = @PembayaranDP_ID, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';
END
GO


-- SKENARIO I (Baru Dibuat: Menunggu Pembayaran DP): Nabila Tul (ID=4)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT;
    DECLARE @JList ListJadwalType;
    DECLARE @TmpTable TABLE (ID INT);

    -- Sesi mendatang di Agustus 2026
    INSERT INTO @TmpTable EXEC sp_InsertJadwalStudio @ID_Ruangan = 5, @Tanggal = '2026-08-12', @JamMulai = '09:00', @JamSelesai = '11:00', @Keterangan = 'Slot Korporat Agustus', @CreatedBy = 'admin';
    SELECT @JadwalID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @TmpTable EXEC sp_CreateOrderBooking @ID_Pelanggan = 4, @ID_Paket = 5, @ID_Ruangan = 5, @ID_Tema = 5, @JadwalList = @JList, @Keterangan = 'Sesi Tim Kantor Baru', @Created_By = 'customer';
    SELECT @OrderID = ID FROM @TmpTable;
    DELETE FROM @TmpTable;
    -- Biarkan berstatus 0 (Menunggu DP) untuk antrean verifikasi administrasi di aplikasi.
END
GO

-- Bersihkan tabel temporer
DROP TABLE #TmpID;
GO

PRINT 'Penyuntikan data dummy historis 2024, 2025, dan 2026 berhasil diselesaikan!';
GO

-- 1. Jalankan Fungsi Ringkasan Dashboard untuk Membandingkan Tren Tahunan
SELECT 'Dashboard 2024' AS Periode, * FROM fn_DashboardRingkasanTahunan(2024)
UNION ALL
SELECT 'Dashboard 2025' AS Periode, * FROM fn_DashboardRingkasanTahunan(2025)
UNION ALL
SELECT 'Dashboard 2026' AS Periode, * FROM fn_DashboardRingkasanTahunan(2026);

-- 2. Uji Coba Laporan Pendapatan Berdasarkan Rentang Waktu (Melalui SP Resmi Laporan)
EXEC sp_LaporanPendapatanSummary '2024-01-01', '2026-12-31';

-- 3. Cek Status Stok Barang Cetak setelah Terpotong Otomatis oleh Triggers Transaksi
SELECT Nama_Barang, Stok_Barang, Stok_Minimum, Harga_Barang FROM Barang_Cetak;

-- ==========================================================================================
-- SKRIP TRANSAKSI MASSAL TAMBAHAN (HISTORIS 2024, 2025, & 2026)
-- SPOTLIGHT STUDIO — UNTUK MAKSIMALISASI GRAFIK & LAPORAN DASHBOARD
-- ==========================================================================================
USE SpotLight;
GO

-- ==========================================================================================
-- TAHUN 2024: 5 TRANSAKSI BARU (Skenario 1 - 5)
-- ==========================================================================================

-- Skenario 1: Paket Mandiri - Lunas (Pelanggan 1 - Bintang Basev)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 1, '2024-01-15', '09:00', '09:30', 'Slot Pagi Jan 2024', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 1, 1, 1, 1, @JList, 'Foto Profil LinkedIn', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2024-01-10 09:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 100000, 'dp_1.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    UPDATE Pembayaran SET Tanggal_Upload = '2024-01-10 10:00:00' WHERE ID_Pembayaran = @DP_ID;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    UPDATE Sesi_Foto SET Waktu_Mulai = '2024-01-15 09:00:00' WHERE ID_Sesi_Foto = @SesiID;
    EXEC sp_InsertHasilFoto @SesiID, 'res_01.jpg', 'image', 2100000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'res_final.zip', 'admin';
    UPDATE Sesi_Foto SET Waktu_Selesai = '2024-01-15 09:30:00', Tanggal_Upload_Hasil = '2024-01-15 11:00:00' WHERE ID_Sesi_Foto = @SesiID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'Transfer Bank', 100000, 'lunas_1.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    UPDATE Pembayaran SET Tanggal_Upload = '2024-01-15 12:00:00' WHERE ID_Pembayaran = @Lunas_ID;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    EXEC sp_UpdateOrder @OrderID, 'Foto Profil LinkedIn', 5, 'Hasil sangat memuaskan dan cepat.', 'customer';
END
GO

-- Skenario 2: Paket Romantis - Lunas + Cetak Bingkai (Pelanggan 2 - Elisa)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 2, '2024-02-20', '13:00', '14:00', 'Slot Romantis Feb 2024', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 2, 2, 2, 2, @JList, 'Foto Anniversary', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2024-02-15 14:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'QRIS', 175000, 'dp_2.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'couple_01.jpg', 'image', 3200000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'couple_all.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'QRIS', 175000, 'lunas_2.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_CreatePenjualan @OrderID, 1, 'admin';
    SELECT @PenjualanID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertDetailPenjualan @PenjualanID, 4, 1; -- Bingkai Foto

    EXEC sp_UpdateOrder @OrderID, 'Foto Anniversary', 4, 'Bagus, dekorasi ruangannya indah.', 'customer';
END
GO

-- Skenario 3: Paket Wisuda - Lunas + Cetak Album (Pelanggan 3 - Amar Faiz)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 4, '2024-05-12', '10:00', '11:00', 'Slot Wisuda Mei 2024', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 3, 4, 4, 4, @JList, 'Wisuda Amar', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2024-05-05 10:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 225000, 'dp_3.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'grad_01.jpg', 'image', 4100000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'grad_final.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'Transfer Bank', 225000, 'lunas_3.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_CreatePenjualan @OrderID, 1, 'admin';
    SELECT @PenjualanID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertDetailPenjualan @PenjualanID, 3, 1; -- Album Foto

    EXEC sp_UpdateOrder @OrderID, 'Wisuda Amar', 5, 'Album fotonya mewah sekali.', 'customer';
END
GO

-- Skenario 4: Paket Keluarga - Lunas (Pelanggan 4 - Nabila)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 3, '2024-08-25', '14:00', '15:30', 'Slot Keluarga Agst 2024', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 4, 3, 3, 3, @JList, 'Foto Keluarga Besar', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2024-08-20 09:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 300000, 'dp_4.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'fam_01.jpg', 'image', 3800000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'fam_final.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'Transfer Bank', 300000, 'lunas_4.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    EXEC sp_UpdateOrder @OrderID, 'Foto Keluarga Besar', 4, 'Sangat ramah terhadap anak-anak.', 'customer';
END
GO

-- Skenario 5: Paket Grup - Batal Sistem (Pelanggan 5 - Thoriq Al)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 5, '2024-12-10', '10:00', '12:00', 'Slot Grup Des 2024', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 5, 5, 5, 5, @JList, 'Sesi Grup Batal', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2024-12-05 10:00:00' WHERE ID_Order = @OrderID;
    EXEC sp_BatalkanOrderBooking @OrderID, 'system';
END
GO


-- ==========================================================================================
-- TAHUN 2025: 5 TRANSAKSI BARU (Skenario 6 - 10)
-- ==========================================================================================

-- Skenario 6: Paket Mandiri - Lunas + Cetak 8R (Pelanggan 2 - Elisa)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 1, '2025-01-20', '11:00', '11:30', 'Slot Pagi Jan 2025', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 2, 1, 1, 1, @JList, 'Foto Profil Baru Elisa', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2025-01-18 11:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'QRIS', 100000, 'dp_6.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'profile_elisa.jpg', 'image', 2400000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'profile_elisa.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'QRIS', 100000, 'lunas_6.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_CreatePenjualan @OrderID, 1, 'admin';
    SELECT @PenjualanID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertDetailPenjualan @PenjualanID, 2, 2; -- Cetak 8R x2

    EXEC sp_UpdateOrder @OrderID, 'Foto Profil Baru Elisa', 5, 'Cepat dan cetakannya tajam.', 'customer';
END
GO

-- Skenario 7: Paket Romantis - Lunas (Pelanggan 3 - Amar Faiz)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 2, '2025-03-15', '14:00', '15:00', 'Slot Romantis Mar 2025', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 3, 2, 2, 2, @JList, 'Prewedding Amar', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2025-03-10 10:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 175000, 'dp_7.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'prewed_amar.jpg', 'image', 3500000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'prewed_amar.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'Transfer Bank', 175000, 'lunas_7.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    EXEC sp_UpdateOrder @OrderID, 'Prewedding Amar', 5, 'Fotografer sangat profesional mengarahkan pose.', 'customer';
END
GO

-- Skenario 8: Paket Wisuda - Lunas + Cetak Photobook (Pelanggan 5 - Thoriq Al)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 4, '2025-06-22', '13:00', '14:00', 'Slot Wisuda Juni 2025', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 5, 4, 4, 6, @JList, 'Wisuda Thoriq Klasik', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2025-06-15 15:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 225000, 'dp_8.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'wisuda_thoriq.jpg', 'image', 4400000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'wisuda_thoriq.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'Transfer Bank', 225000, 'lunas_8.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_CreatePenjualan @OrderID, 1, 'admin';
    SELECT @PenjualanID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertDetailPenjualan @PenjualanID, 5, 1; -- Photobook

    EXEC sp_UpdateOrder @OrderID, 'Wisuda Thoriq Klasik', 3, 'Kualitas cetakan buku baik, namun pengiriman agak lama.', 'customer';
END
GO

-- Skenario 9: Paket Keluarga - DP Ditolak (Pelanggan 1 - Bintang Basev)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 3, '2025-10-18', '11:00', '12:30', 'Slot Keluarga Okt 2025', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 1, 3, 3, 3, @JList, 'Foto Keluarga Bintang', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2025-10-15 09:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 300000, 'dp_salah.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;

    -- Ditolak admin karena bukti transfer tidak valid
    EXEC sp_VerifikasiPembayaran @DP_ID, 2, 1, 'admin';
END
GO

-- Skenario 10: Paket Grup - Lunas (Pelanggan 4 - Nabila)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 5, '2025-12-15', '09:00', '11:00', 'Slot Corporate Des 2025', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 4, 5, 5, 5, @JList, 'Sesi Foto Direksi', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2025-12-10 14:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 500000, 'dp_10.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'corp_01.jpg', 'image', 5100000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'corp_all.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'Transfer Bank', 500000, 'lunas_10.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    EXEC sp_UpdateOrder @OrderID, 'Sesi Foto Direksi', 5, 'Sangat profesional, background studio formal berkelas.', 'customer';
END
GO


-- ==========================================================================================
-- TAHUN 2026: 5 TRANSAKSI BARU (Skenario 11 - 15)
-- ==========================================================================================

-- Skenario 11: Paket Romantis - Lunas (Pelanggan 5 - Thoriq Al)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 2, '2026-02-14', '15:00', '16:00', 'Slot Romantis Feb 2026', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 5, 2, 2, 2, @JList, 'Valentine Photo', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2026-02-10 11:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'QRIS', 175000, 'dp_11.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'val_01.jpg', 'image', 3400000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'val_final.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'QRIS', 175000, 'lunas_11.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    EXEC sp_UpdateOrder @OrderID, 'Valentine Photo', 5, 'Suka sekali dengan warna pastel studionya!', 'customer';
END
GO

-- Skenario 12: Paket Keluarga - Lunas + Cetak Album Premium (Pelanggan 1 - Bintang Basev)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT, @PenjualanID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 3, '2026-05-18', '13:00', '14:30', 'Slot Keluarga Mei 2026', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 1, 3, 3, 3, @JList, 'Keluarga Bintang 2026', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2026-05-12 14:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 300000, 'dp_12.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'fam_2026.jpg', 'image', 4200000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'fam_2026.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'Transfer Bank', 300000, 'lunas_12.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_CreatePenjualan @OrderID, 1, 'admin';
    SELECT @PenjualanID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertDetailPenjualan @PenjualanID, 3, 1; -- Album Foto

    EXEC sp_UpdateOrder @OrderID, 'Keluarga Bintang 2026', 5, 'Bintang 5! Respon admin cepat.', 'customer';
END
GO

-- Skenario 13: Paket Wisuda - Lunas (Pelanggan 2 - Elisa)
-- Transaksi di bulan Juli 2026 (Bulan saat ini)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT, @Lunas_ID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 1, '2026-07-10', '13:00', '13:30', 'Slot Wisuda Juli 2026', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 2, 4, 1, 1, @JList, 'Wisuda Elisa Minimalis', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2026-07-05 10:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'QRIS', 225000, 'dp_13.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'elisa_grad.jpg', 'image', 2800000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'elisa_grad.zip', 'admin';

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'Pelunasan', 'QRIS', 225000, 'lunas_13.jpg', 'customer';
    SELECT @Lunas_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @Lunas_ID, 1, 1, 'admin';

    EXEC sp_UpdateOrder @OrderID, 'Wisuda Elisa Minimalis', 4, 'Hasilnya estetik dengan studio minimalis.', 'customer';
END
GO

-- Skenario 14: Paket Mandiri - Selesai Sesi, Menunggu Pelunasan (Pelanggan 1 - Bintang Basev)
-- Antrean Aktif untuk Kasir (Juli 2026)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT, @SesiID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 1, '2026-07-21', '10:00', '10:30', 'Slot Mandiri Baru', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 1, 1, 1, 1, @JList, 'Personal Shoot Bintang', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    UPDATE [Order] SET Tanggal_Booking = '2026-07-19 09:00:00' WHERE ID_Order = @OrderID;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 100000, 'dp_14.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';

    INSERT INTO @Tmp EXEC sp_MulaiSesiFoto @OrderID, 5, 'admin';
    SELECT @SesiID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_InsertHasilFoto @SesiID, 'bintang_shoot.jpg', 'image', 1900000, 1, 'foto_admin';
    EXEC sp_SelesaiSesiFoto @SesiID, 'bintang_shoot.zip', 'admin';
    -- Status saat ini: Selesai Sesi, Menunggu Pelunasan (Status_Order = 2)
END
GO

-- Skenario 15: Paket Romantis - DP Terverifikasi, Menunggu Jadwal (Pelanggan 4 - Nabila)
-- Antrean Sesi Mendatang (Agustus 2026)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType; DECLARE @Tmp TABLE (ID INT);

    INSERT INTO @Tmp EXEC sp_InsertJadwalStudio 2, '2026-08-10', '11:00', '12:00', 'Slot Romantis Agst', 'admin';
    SELECT @JadwalID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    INSERT INTO @Tmp EXEC sp_CreateOrderBooking 4, 2, 2, 2, @JList, 'Prewedding Nabila', 'customer';
    SELECT @OrderID = ID FROM @Tmp; DELETE FROM @Tmp;

    INSERT INTO @Tmp EXEC sp_InsertPembayaran @OrderID, 'DP', 'QRIS', 175000, 'dp_15.jpg', 'customer';
    SELECT @DP_ID = ID FROM @Tmp; DELETE FROM @Tmp;
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin';
    -- Status saat ini: DP Terverifikasi, Menunggu Sesi (Status_Order = 1)
END
GO

PRINT 'Penyuntikan 15 transaksi massal tambahan berhasil diselesaikan!';
GO