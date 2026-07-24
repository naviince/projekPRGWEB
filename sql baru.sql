-- ==========================================================================================
-- SKRIP PENYUSUNAN DATA TRANSAKSI PEMBATALAN MASSAL (JULI 2026)
-- SPOTLIGHT STUDIO — SINKRONISASI METRIK UNTUK DEMO SIDANG
-- ==========================================================================================
USE SpotLight;
GO

-- ==========================================================================================
-- KATEGORI 1: BELUM BAYAR DP (2 Booking Batal)
-- Status_Order = 4, Tanpa ada data pembayaran DP sama sekali.
-- ==========================================================================================

-- Skenario Batal 1: Pelanggan 1 (Bintang Basev) - Ruangan 1 (Minimalis)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 1, '2026-07-02', '10:00', '10:30', 'Jadwal Batal - Belum Bayar DP 1', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 1, 1, 1, 1, @JList, 'Booking Kasual Batal', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-02 10:00:00' WHERE ID_Order = @OrderID;
    EXEC sp_BatalkanOrderBooking @OrderID, 'system';
END
GO

-- Skenario Batal 2: Pelanggan 2 (Elisa Larasati) - Ruangan 2 (Cinta)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 2, '2026-07-03', '13:00', '14:00', 'Jadwal Batal - Belum Bayar DP 2', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 2, 2, 2, 2, @JList, 'Booking Romantis Batal', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-03 13:00:00' WHERE ID_Order = @OrderID;
    EXEC sp_BatalkanOrderBooking @OrderID, 'system';
END
GO


-- ==========================================================================================
-- KATEGORI 2: DP DITOLAK (2 Booking Batal)
-- Status_Order = 4, Pembayaran DP diunggah namun ditolak verifikator (Status_Pembayaran = 2)
-- ==========================================================================================

-- Skenario Batal 3: Pelanggan 3 (Amar Faiz) - Ruangan 3 (Kehangatan)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 3, '2026-07-04', '11:00', '12:30', 'Jadwal Batal - DP Ditolak 1', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 3, 3, 3, 3, @JList, 'Booking Keluarga Batal', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-04 11:00:00' WHERE ID_Order = @OrderID;
    
    EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 300000, 'bukti_palsu_1.jpg', 'customer';
    SELECT @DP_ID = MAX(ID_Pembayaran) FROM Pembayaran;
    UPDATE Pembayaran SET Tanggal_Upload = '2026-07-04 11:15:00' WHERE ID_Pembayaran = @DP_ID;
    
    EXEC sp_VerifikasiPembayaran @DP_ID, 2, 1, 'admin'; -- 2 = Ditolak
    EXEC sp_BatalkanOrderBooking @OrderID, 'admin';
END
GO

-- Skenario Batal 4: Pelanggan 4 (Nabila Tul) - Ruangan 4 (Prestasi)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 4, '2026-07-05', '09:00', '10:00', 'Jadwal Batal - DP Ditolak 2', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 4, 4, 4, 4, @JList, 'Booking Wisuda Batal', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-05 09:00:00' WHERE ID_Order = @OrderID;
    
    EXEC sp_InsertPembayaran @OrderID, 'DP', 'QRIS', 225000, 'bukti_palsu_2.jpg', 'customer';
    SELECT @DP_ID = MAX(ID_Pembayaran) FROM Pembayaran;
    UPDATE Pembayaran SET Tanggal_Upload = '2026-07-05 09:15:00' WHERE ID_Pembayaran = @DP_ID;
    
    EXEC sp_VerifikasiPembayaran @DP_ID, 2, 1, 'admin'; -- 2 = Ditolak
    EXEC sp_BatalkanOrderBooking @OrderID, 'admin';
END
GO


-- ==========================================================================================
-- KATEGORI 3: DIBATALKAN PELANGGAN (2 Booking Batal)
-- Status_Order = 4, Pembayaran DP valid (Status_Pembayaran = 1) namun dibatalkan manual
-- ==========================================================================================

-- Skenario Batal 5: Pelanggan 5 (Thoriq Al) - Ruangan 5 (Sinergi)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 5, '2026-07-06', '10:00', '12:00', 'Jadwal Batal - Plg Cancel 1', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 5, 5, 5, 5, @JList, 'Booking Grup Batal', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-06 10:00:00' WHERE ID_Order = @OrderID;
    
    EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 500000, 'bukti_valid_1.jpg', 'customer';
    SELECT @DP_ID = MAX(ID_Pembayaran) FROM Pembayaran;
    UPDATE Pembayaran SET Tanggal_Upload = '2026-07-06 10:30:00' WHERE ID_Pembayaran = @DP_ID;
    
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin'; -- 1 = Valid
    EXEC sp_BatalkanOrderBooking @OrderID, 'customer';
END
GO

-- Skenario Batal 6: Pelanggan 1 (Bintang Basev) - Ruangan 1 (Minimalis)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 1, '2026-07-07', '13:00', '14:00', 'Jadwal Batal - Plg Cancel 2', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 1, 2, 1, 1, @JList, 'Booking Romantis Batal', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-07 13:00:00' WHERE ID_Order = @OrderID;
    
    EXEC sp_InsertPembayaran @OrderID, 'DP', 'QRIS', 175000, 'bukti_valid_2.jpg', 'customer';
    SELECT @DP_ID = MAX(ID_Pembayaran) FROM Pembayaran;
    UPDATE Pembayaran SET Tanggal_Upload = '2026-07-07 13:30:00' WHERE ID_Pembayaran = @DP_ID;
    
    EXEC sp_VerifikasiPembayaran @DP_ID, 1, 1, 'admin'; -- 1 = Valid
    EXEC sp_BatalkanOrderBooking @OrderID, 'customer';
END
GO


-- ==========================================================================================
-- KATEGORI 4: DIBATALKAN SISTEM (2 Booking Batal)
-- Status_Order = 4, Pembayaran DP diunggah namun statusnya masih Menunggu Verifikasi (0)
-- ==========================================================================================

-- Skenario Batal 7: Pelanggan 2 (Elisa Larasati) - Ruangan 1 (Minimalis)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 1, '2026-07-08', '15:00', '15:30', 'Jadwal Batal - Sistem Cancel 1', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 2, 1, 1, 1, @JList, 'Booking Mandiri Batal', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-08 15:00:00' WHERE ID_Order = @OrderID;
    
    EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 100000, 'bukti_pending_1.jpg', 'customer';
    SELECT @DP_ID = MAX(ID_Pembayaran) FROM Pembayaran;
    UPDATE Pembayaran SET Tanggal_Upload = '2026-07-08 15:15:00' WHERE ID_Pembayaran = @DP_ID;
    
    -- Status Pembayaran dibiarkan 0 (Menunggu Verifikasi), lalu dibatalkan sistem karena kadaluarsa
    EXEC sp_BatalkanOrderBooking @OrderID, 'system';
END
GO

-- Skenario Batal 8: Pelanggan 3 (Amar Faiz) - Ruangan 3 (Kehangatan)
BEGIN
    DECLARE @JadwalID INT, @OrderID INT, @DP_ID INT;
    DECLARE @JList ListJadwalType;
    
    EXEC sp_InsertJadwalStudio 3, '2026-07-09', '14:30', '16:00', 'Jadwal Batal - Sistem Cancel 2', 'admin';
    SELECT @JadwalID = MAX(ID_Jadwal) FROM Jadwal_Studio;
    
    INSERT INTO @JList (ID_Jadwal) VALUES (@JadwalID);
    EXEC sp_CreateOrderBooking 3, 3, 3, 3, @JList, 'Booking Keluarga Batal 2', 'customer';
    SELECT @OrderID = MAX(ID_Order) FROM [Order];
    
    UPDATE [Order] SET Tanggal_Booking = '2026-07-09 14:30:00' WHERE ID_Order = @OrderID;
    
    EXEC sp_InsertPembayaran @OrderID, 'DP', 'Transfer Bank', 300000, 'bukti_pending_2.jpg', 'customer';
    SELECT @DP_ID = MAX(ID_Pembayaran) FROM Pembayaran;
    UPDATE Pembayaran SET Tanggal_Upload = '2026-07-09 14:45:00' WHERE ID_Pembayaran = @DP_ID;
    
    -- Status Pembayaran dibiarkan 0 (Menunggu Verifikasi), lalu dibatalkan sistem karena kadaluarsa
    EXEC sp_BatalkanOrderBooking @OrderID, 'system';
END
GO

PRINT 'Penyuntikan 8 data pembatalan dinamis di bulan Juli 2026 berhasil diselesaikan!';
GO