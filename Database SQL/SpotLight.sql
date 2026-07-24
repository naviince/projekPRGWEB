-- =====================================================
-- 0. INSTALASI / RESET DATABASE
-- =====================================================
USE master;
GO

IF EXISTS (SELECT name FROM sys.databases WHERE name = N'SpotLight')
BEGIN
    ALTER DATABASE SpotLight SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE SpotLight;
END
GO

CREATE DATABASE SpotLight;
GO

USE SpotLight;
GO

-- ======================================================
-- 1. PEMBUATAN TABEL LOG HISTORY (AUDIT TRAIL)
-- ======================================================
CREATE TABLE Log_History (
    ID_Log          INT PRIMARY KEY IDENTITY(1,1),
    Nama_Tabel      VARCHAR(100) NOT NULL,
    ID_Record       VARCHAR(50) NOT NULL,
    Aksi            VARCHAR(20) NOT NULL,       -- INSERT / UPDATE / DELETE
    Data_Lama       NVARCHAR(MAX) NULL,
    Data_Baru       NVARCHAR(MAX) NULL,
    Executed_By     VARCHAR(50) NOT NULL DEFAULT 'system',
    Executed_Date   DATETIME NOT NULL DEFAULT GETDATE()
);
GO

-- =====================================================
-- 2. PEMBUATAN STRUKTUR TABEL MASTER
-- =====================================================

-- MASTER 1: KARYAWAN
CREATE TABLE Karyawan (
    ID_Karyawan INT IDENTITY(1,1) PRIMARY KEY,
    NIK VARCHAR(20) NOT NULL UNIQUE,
    Nama_Karyawan VARCHAR(100) NOT NULL,
    Username_Karyawan VARCHAR(50) NOT NULL UNIQUE,
    Email_Karyawan VARCHAR(100) NOT NULL UNIQUE,
    Password_Karyawan VARCHAR(255) NOT NULL,
    Jenis_Kelamin VARCHAR(15) NOT NULL,
    Tanggal_Lahir DATE NOT NULL,
    Role_Karyawan VARCHAR(20) NOT NULL,
    No_Hp VARCHAR(15) NOT NULL,
    Alamat VARCHAR(255) NULL,
    Foto_Profil VARCHAR(255) DEFAULT 'default.jpg',

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CHK_Karyawan_JK CHECK (Jenis_Kelamin IN ('Laki-laki', 'Perempuan')),
    CONSTRAINT CHK_Karyawan_Role CHECK (Role_Karyawan IN ('Admin', 'Fotografer', 'Owner')),
    CONSTRAINT CHK_Karyawan_Status CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Karyawan_Email CHECK (Email_Karyawan LIKE '%_@_%._%'),
    CONSTRAINT CHK_Karyawan_NoHp CHECK (
        LEFT(No_Hp, 3) = '+62'
        AND LEN(No_Hp) BETWEEN 12 AND 16
        AND SUBSTRING(No_Hp, 2, LEN(No_Hp) - 1) NOT LIKE '%[^0-9]%'
    ),
    CONSTRAINT CHK_Karyawan_Password CHECK (
        LEN(Password_Karyawan) >= 8
        AND Password_Karyawan LIKE '%[A-Za-z]%'
        AND Password_Karyawan LIKE '%[0-9]%'
        AND Password_Karyawan LIKE '%[^A-Za-z0-9]%'
    )
);
GO

-- MASTER 2: PELANGGAN
CREATE TABLE Pelanggan (
    ID_Pelanggan INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Pelanggan VARCHAR(100) NOT NULL,
    Username_Pelanggan VARCHAR(50) NOT NULL UNIQUE,
    Email_Pelanggan VARCHAR(100) NOT NULL UNIQUE,
    Password_Pelanggan VARCHAR(255) NOT NULL,
    Jenis_Kelamin VARCHAR(15) NOT NULL,
    Tanggal_Lahir DATE NOT NULL,
    No_Hp VARCHAR(15) NOT NULL,
    Alamat VARCHAR(255),
    Foto_Profil VARCHAR(255) DEFAULT 'default.jpg',

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CHK_Pelanggan_JK CHECK (Jenis_Kelamin IN ('Laki-laki', 'Perempuan')),
    CONSTRAINT CHK_Pelanggan_Status CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Pelanggan_Email CHECK (Email_Pelanggan LIKE '%_@_%._%'),
    CONSTRAINT CHK_Pelanggan_NoHp CHECK (
        LEFT(No_Hp, 3) = '+62'
        AND LEN(No_Hp) BETWEEN 12 AND 16
        AND SUBSTRING(No_Hp, 2, LEN(No_Hp) - 1) NOT LIKE '%[^0-9]%'
    ),
    CONSTRAINT CHK_Pelanggan_Password CHECK (
        LEN(Password_Pelanggan) >= 8
        AND Password_Pelanggan LIKE '%[A-Za-z]%'
        AND Password_Pelanggan LIKE '%[0-9]%'
        AND Password_Pelanggan LIKE '%[^A-Za-z0-9]%'
    )
);
GO

-- MASTER 3: PAKET FOTO
CREATE TABLE Paket_Foto (
    ID_Paket INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Paket VARCHAR(100) NOT NULL,
    Durasi_Waktu INT NOT NULL,           
    Harga_Paket DECIMAL(12,2) NOT NULL,
    Deskripsi VARCHAR(255) NULL,
    Kapasitas_Orang INT NOT NULL,        
    Foto_Paket VARCHAR(255) DEFAULT 'default_paket.jpg',

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CHK_Paket_Status CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Paket_Durasi CHECK (Durasi_Waktu > 0),
    CONSTRAINT CHK_Paket_Harga CHECK (Harga_Paket >= 0),
    CONSTRAINT CHK_Paket_Kapasitas CHECK (Kapasitas_Orang > 0)
);
GO

-- MASTER 4: RUANGAN
CREATE TABLE Ruangan (
    ID_Ruangan INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Ruangan VARCHAR(100) NOT NULL,
    Deskripsi VARCHAR(255) NULL,
    Foto_Ruangan VARCHAR(255) DEFAULT 'default_ruangan.jpg',

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CHK_Ruangan_Status CHECK (Status IN (0, 1))
);
GO

-- TABEL JUNCTION MASTER: PAKET_RUANGAN (BANYAK-KE-BANYAK)
CREATE TABLE Paket_Ruangan (
    ID_Paket INT NOT NULL,
    ID_Ruangan INT NOT NULL,

    CONSTRAINT PK_Paket_Ruangan PRIMARY KEY (ID_Paket, ID_Ruangan),
    CONSTRAINT FK_PaketRuangan_Paket FOREIGN KEY (ID_Paket) REFERENCES Paket_Foto(ID_Paket) ON DELETE CASCADE,
    CONSTRAINT FK_PaketRuangan_Ruangan FOREIGN KEY (ID_Ruangan) REFERENCES Ruangan(ID_Ruangan) ON DELETE CASCADE,
    CONSTRAINT UQ_Paket_Ruangan_Unique UNIQUE (ID_Paket, ID_Ruangan)
);
GO

-- MASTER 5: TEMA FOTO
CREATE TABLE Tema_Foto (
    ID_Tema INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Tema VARCHAR(100) NOT NULL,
    Kategori_Tema VARCHAR(50),
    Deskripsi VARCHAR(255),
    Foto_Tema VARCHAR(255) DEFAULT 'default_tema.jpg',

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CHK_Status_Tema CHECK (Status IN (0, 1))
);
GO

-- TABEL JUNCTION MASTER: RUANGAN_TEMA
CREATE TABLE Ruangan_Tema (
    ID_Ruangan INT NOT NULL,
    ID_Tema INT NOT NULL,

    CONSTRAINT PK_Ruangan_Tema PRIMARY KEY (ID_Ruangan, ID_Tema),
    CONSTRAINT FK_RuanganTema_Ruangan FOREIGN KEY (ID_Ruangan) REFERENCES Ruangan(ID_Ruangan),
    CONSTRAINT FK_RuanganTema_Tema FOREIGN KEY (ID_Tema) REFERENCES Tema_Foto(ID_Tema)
);
GO

-- MASTER 6: PROPERTI
CREATE TABLE Properti (
    ID_Properti INT IDENTITY(1,1) PRIMARY KEY,
    ID_Ruangan INT NOT NULL,
    Nama_Properti VARCHAR(100) NOT NULL,
    Kategori_Properti VARCHAR(50),
    Deskripsi VARCHAR(255) NULL,
    Foto_Properti VARCHAR(255) DEFAULT 'default_properti.jpg',

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT FK_Properti_Ruangan FOREIGN KEY (ID_Ruangan) REFERENCES Ruangan(ID_Ruangan),
    CONSTRAINT CHK_Properti_Status CHECK (Status IN (0, 1))
);
GO

-- MASTER 7: BARANG CETAK
CREATE TABLE Barang_Cetak (
    ID_Barang INT IDENTITY(1,1) PRIMARY KEY,
    Nama_Barang VARCHAR(100) NOT NULL,
    Deskripsi VARCHAR(255) NULL,
    Harga_Barang DECIMAL(12,2) NOT NULL,
    Stok_Barang INT NOT NULL,
    Stok_Minimum INT NOT NULL DEFAULT 5,
    Foto_Barang VARCHAR(255) DEFAULT 'default_barang.jpg',

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT CHK_Barang_Status CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Barang_Harga CHECK (Harga_Barang >= 0),
    CONSTRAINT CHK_Barang_Stok CHECK (Stok_Barang >= 0),
    CONSTRAINT CHK_Barang_StokMinimum CHECK (Stok_Minimum >= 0)
);
GO

-- MASTER 8: JADWAL STUDIO
CREATE TABLE Jadwal_Studio (
    ID_Jadwal INT IDENTITY(1,1) PRIMARY KEY,
    ID_Ruangan INT NOT NULL,
    Tanggal_Jadwal DATE NOT NULL,
    Jam_Mulai TIME NOT NULL,
    Jam_Selesai TIME NOT NULL,
    Keterangan VARCHAR(255) NULL,
    Status_Jadwal INT NOT NULL DEFAULT 0,  -- 0=Tersedia, 1=Booked, 2=Maintenance

    Status INT NOT NULL DEFAULT 1,
    Is_Deleted BIT NOT NULL DEFAULT 0,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,
    Deleted_By VARCHAR(50) NULL,
    Deleted_Date DATETIME NULL,

    CONSTRAINT FK_Jadwal_Ruangan FOREIGN KEY (ID_Ruangan) REFERENCES Ruangan(ID_Ruangan),
    CONSTRAINT UQ_Jadwal_Ruangan_Waktu UNIQUE (ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai),
    CONSTRAINT CHK_Jadwal_StatusData CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Jadwal_StatusJadwal CHECK (Status_Jadwal IN (0, 1, 2)),
    CONSTRAINT CHK_Jadwal_Jam CHECK (Jam_Mulai < Jam_Selesai)
);
GO

-- =====================================================
-- 3. PEMBUATAN STRUKTUR TABEL TRANSAKSI
-- =====================================================

-- TRANSAKSI 1: ORDER / BOOKING
CREATE TABLE [Order] (
    ID_Order INT IDENTITY(1,1) PRIMARY KEY,
    ID_Pelanggan INT NOT NULL,
    ID_Paket INT NOT NULL,
    ID_Ruangan INT NOT NULL,
    ID_Tema INT NOT NULL,
    Tanggal_Booking DATETIME NOT NULL DEFAULT GETDATE(),

    Total_Paket DECIMAL(12,2) NOT NULL DEFAULT 0,
    Total_Barang_Cetak DECIMAL(12,2) NOT NULL DEFAULT 0,
    Total_Harga AS (Total_Paket + Total_Barang_Cetak) PERSISTED,

    Keterangan VARCHAR(255) NULL,
    Rating INT NULL,
    Review VARCHAR(255) NULL,
    Status_Order INT NOT NULL DEFAULT 0, -- 0=Menunggu DP, 1=DP Terverifikasi, 2=Selesai Sesi, 3=Lunas, 4=Dibatalkan

    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_Order_Pelanggan FOREIGN KEY (ID_Pelanggan) REFERENCES Pelanggan(ID_Pelanggan),
    CONSTRAINT FK_Order_Ruangan_Paket FOREIGN KEY (ID_Paket, ID_Ruangan) REFERENCES Paket_Ruangan(ID_Paket, ID_Ruangan),
    CONSTRAINT FK_Order_Ruangan_Tema FOREIGN KEY (ID_Ruangan, ID_Tema) REFERENCES Ruangan_Tema(ID_Ruangan, ID_Tema),
    CONSTRAINT CHK_Order_StatusData CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Order_StatusOrder CHECK (Status_Order IN (0, 1, 2, 3, 4)),
    CONSTRAINT CHK_Order_Total CHECK (Total_Paket >= 0 AND Total_Barang_Cetak >= 0),
    CONSTRAINT CHK_Order_Rating CHECK (Rating IS NULL OR Rating BETWEEN 1 AND 5)
);
GO

-- DETAIL TRANSAKSI: ORDER_JADWAL (Multi-Jadwal Booking)
CREATE TABLE Order_Jadwal (
    ID_Order INT NOT NULL,
    ID_Jadwal INT NOT NULL,

    CONSTRAINT PK_Order_Jadwal PRIMARY KEY (ID_Order, ID_Jadwal),
    CONSTRAINT FK_OrderJadwal_Order FOREIGN KEY (ID_Order) REFERENCES [Order](ID_Order),
    CONSTRAINT FK_OrderJadwal_Jadwal FOREIGN KEY (ID_Jadwal) REFERENCES Jadwal_Studio(ID_Jadwal)
);
GO

-- TRANSAKSI 2: PEMBAYARAN
CREATE TABLE Pembayaran (
    ID_Pembayaran INT IDENTITY(1,1) PRIMARY KEY,
    ID_Order INT NOT NULL,
    Tipe_Pembayaran VARCHAR(20) NOT NULL,  -- 'DP' atau 'Pelunasan'
    Metode_Pembayaran VARCHAR(50) NOT NULL,
    Jumlah_Bayar DECIMAL(12,2) NOT NULL,
    Bukti_Transfer VARCHAR(255) NULL,
    Tanggal_Upload DATETIME NOT NULL DEFAULT GETDATE(),
    ID_Karyawan_Verifikator INT NULL,
    Status_Pembayaran INT NOT NULL DEFAULT 0,  -- 0=Menunggu Verifikasi, 1=Valid, 2=Ditolak

    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_Pembayaran_Order FOREIGN KEY (ID_Order) REFERENCES [Order](ID_Order),
    CONSTRAINT FK_Pembayaran_Karyawan FOREIGN KEY (ID_Karyawan_Verifikator) REFERENCES Karyawan(ID_Karyawan),
    CONSTRAINT CHK_Pembayaran_StatusData CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Pembayaran_Tipe CHECK (Tipe_Pembayaran IN ('DP', 'Pelunasan')),
    CONSTRAINT CHK_Pembayaran_StatusPembayaran CHECK (Status_Pembayaran IN (0, 1, 2)),
    CONSTRAINT CHK_Pembayaran_Jumlah CHECK (Jumlah_Bayar > 0)
);
GO

-- TRANSAKSI 3: SESI FOTO
CREATE TABLE Sesi_Foto (
    ID_Sesi_Foto INT IDENTITY(1,1) PRIMARY KEY,
    ID_Order INT NOT NULL,
    ID_Karyawan INT NOT NULL,  -- Fotografer
    Waktu_Mulai DATETIME NULL,
    Waktu_Selesai DATETIME NULL,
    File_Hasil VARCHAR(255) NULL,
    Tanggal_Upload_Hasil DATETIME NULL,
    Status_Sesi INT NOT NULL DEFAULT 0,  -- 0=Menunggu, 1=Selesai, 2=Dibatalkan

    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_Sesi_Order FOREIGN KEY (ID_Order) REFERENCES [Order](ID_Order),
    CONSTRAINT FK_Sesi_Karyawan FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    CONSTRAINT CHK_Sesi_StatusData CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Sesi_StatusSesi CHECK (Status_Sesi IN (0, 1, 2)),
    CONSTRAINT CHK_Sesi_Waktu CHECK (
        Waktu_Mulai IS NULL OR Waktu_Selesai IS NULL OR Waktu_Mulai < Waktu_Selesai
    )
);
GO

-- =====================================================
-- Hasil_Foto: 1 Sesi_Foto -> banyak file hasil (foto + opsional ZIP).
-- Sesi_Foto.File_Hasil dibiarkan ada tapi TIDAK dipakai lagi -- sumber
-- kebenaran hasil foto sekarang di tabel ini.
-- =====================================================
CREATE TABLE Hasil_Foto (
    ID_Hasil_Foto INT IDENTITY(1,1) PRIMARY KEY,
    ID_Sesi_Foto INT NOT NULL,
    Nama_File VARCHAR(255) NOT NULL,
    Tipe_File VARCHAR(10) NOT NULL,      -- 'image' atau 'archive' (zip/rar)
    Ukuran_Bytes BIGINT NOT NULL DEFAULT 0,
    Urutan INT NOT NULL DEFAULT 0,

    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_HasilFoto_Sesi FOREIGN KEY (ID_Sesi_Foto) REFERENCES Sesi_Foto(ID_Sesi_Foto),
    CONSTRAINT CHK_HasilFoto_Status CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_HasilFoto_Tipe CHECK (Tipe_File IN ('image', 'archive'))
);
GO

CREATE INDEX IX_HasilFoto_Sesi ON Hasil_Foto(ID_Sesi_Foto, Status);
GO

-- TRANSAKSI 4: PENJUALAN BARANG CETAK
CREATE TABLE Penjualan (
    ID_Penjualan INT IDENTITY(1,1) PRIMARY KEY,
    ID_Order INT NOT NULL,
    ID_Karyawan_Admin INT NULL,
    Tanggal_Penjualan DATETIME NOT NULL DEFAULT GETDATE(),
    Total_Penjualan DECIMAL(12,2) NOT NULL DEFAULT 0,
    Status_Penjualan INT NOT NULL DEFAULT 0,  -- 0=Proses, 1=Selesai

    Status INT NOT NULL DEFAULT 1,
    Created_By VARCHAR(50) NOT NULL DEFAULT 'system',
    Created_Date DATETIME NOT NULL DEFAULT GETDATE(),
    Modified_By VARCHAR(50) NULL,
    Modified_Date DATETIME NULL,

    CONSTRAINT FK_Penjualan_Order FOREIGN KEY (ID_Order) REFERENCES [Order](ID_Order),
    CONSTRAINT FK_Penjualan_Admin FOREIGN KEY (ID_Karyawan_Admin) REFERENCES Karyawan(ID_Karyawan),
    CONSTRAINT CHK_Penjualan_StatusData CHECK (Status IN (0, 1)),
    CONSTRAINT CHK_Penjualan_StatusPenjualan CHECK (Status_Penjualan IN (0, 1)),
    CONSTRAINT CHK_Penjualan_Total CHECK (Total_Penjualan >= 0)
);
GO

-- DETAIL TRANSAKSI: DETAIL PENJUALAN BARANG CETAK
CREATE TABLE Detail_Penjualan_Barang_Cetak (
    ID_Detail INT IDENTITY(1,1) PRIMARY KEY,
    ID_Penjualan INT NOT NULL,
    ID_Barang INT NOT NULL,
    Jumlah INT NOT NULL,
    Harga_Satuan DECIMAL(12,2) NOT NULL,
    Subtotal AS (Jumlah * Harga_Satuan) PERSISTED,

    CONSTRAINT FK_DetailPenjualan_Penjualan FOREIGN KEY (ID_Penjualan) REFERENCES Penjualan(ID_Penjualan),
    CONSTRAINT FK_DetailPenjualan_Barang FOREIGN KEY (ID_Barang) REFERENCES Barang_Cetak(ID_Barang),
    CONSTRAINT UQ_Detail_Penjualan_Barang UNIQUE (ID_Penjualan, ID_Barang),
    CONSTRAINT CHK_DetailPenjualan_Jumlah CHECK (Jumlah > 0),
    CONSTRAINT CHK_DetailPenjualan_Harga CHECK (Harga_Satuan >= 0)
);
GO

-- ======================================================
-- 4. TYPE UNTUK MEMPROSES INPUT MULTI-JADWAL DI STORED PROCEDURE
-- ======================================================
IF TYPE_ID('ListJadwalType') IS NOT NULL
    DROP TYPE ListJadwalType;
GO
CREATE TYPE ListJadwalType AS TABLE (
    ID_Jadwal INT
);
GO


-- ======================================================
-- 5. STORED PROCEDURES (SP) - CRUD LENGKAP UNTUK DATA MASTER
-- ======================================================

-- ==================== MASTER 1: KARYAWAN ====================
IF OBJECT_ID('sp_InsertKaryawan', 'P') IS NOT NULL DROP PROCEDURE sp_InsertKaryawan;
GO
CREATE PROCEDURE sp_InsertKaryawan
    @NIK VARCHAR(20), @Nama VARCHAR(100), @Username VARCHAR(50), @Email VARCHAR(100),
    @Password VARCHAR(255), @JK VARCHAR(15), @TglLahir DATE, @Role VARCHAR(20),
    @NoHp VARCHAR(15), @Alamat VARCHAR(255) = NULL, @Foto VARCHAR(255) = 'default.jpg', @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Karyawan (NIK, Nama_Karyawan, Username_Karyawan, Email_Karyawan, Password_Karyawan, Jenis_Kelamin, Tanggal_Lahir, Role_Karyawan, No_Hp, Alamat, Foto_Profil, Created_By)
    VALUES (@NIK, @Nama, @Username, @Email, @Password, @JK, @TglLahir, @Role, @NoHp, @Alamat, @Foto, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Karyawan;
END
GO

IF OBJECT_ID('sp_UpdateKaryawan', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateKaryawan;
GO
CREATE PROCEDURE sp_UpdateKaryawan
    @ID INT, @Nama VARCHAR(100), @Email VARCHAR(100), @JK VARCHAR(15), @TglLahir DATE,
    @Role VARCHAR(20), @NoHp VARCHAR(15), @Alamat VARCHAR(255) = NULL, @Foto VARCHAR(255) = 'default.jpg',
    @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Karyawan SET Nama_Karyawan = @Nama, Email_Karyawan = @Email, Jenis_Kelamin = @JK, Tanggal_Lahir = @TglLahir, Role_Karyawan = @Role, No_Hp = @NoHp, Alamat = @Alamat, Foto_Profil = @Foto, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Karyawan = @ID;
END
GO

IF OBJECT_ID('sp_DeleteKaryawan', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteKaryawan;
GO
CREATE PROCEDURE sp_DeleteKaryawan @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Karyawan SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Karyawan = @ID;
END
GO

IF OBJECT_ID('sp_ReadKaryawan', 'P') IS NOT NULL DROP PROCEDURE sp_ReadKaryawan;
GO
CREATE PROCEDURE sp_ReadKaryawan @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Karyawan WHERE Is_Deleted = 0;
    ELSE
        SELECT * FROM Karyawan WHERE ID_Karyawan = @ID AND Is_Deleted = 0;
END
GO

-- ==================== MASTER 2: PELANGGAN ====================
IF OBJECT_ID('sp_InsertPelanggan', 'P') IS NOT NULL DROP PROCEDURE sp_InsertPelanggan;
GO
CREATE PROCEDURE sp_InsertPelanggan
    @Nama VARCHAR(100), @Username VARCHAR(50), @Email VARCHAR(100), @Password VARCHAR(255),
    @JK VARCHAR(15), @TglLahir DATE, @NoHp VARCHAR(15), @Alamat VARCHAR(255) = NULL,
    @Foto VARCHAR(255) = 'default.jpg', @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Pelanggan (Nama_Pelanggan, Username_Pelanggan, Email_Pelanggan, Password_Pelanggan, Jenis_Kelamin, Tanggal_Lahir, No_Hp, Alamat, Foto_Profil, Created_By)
    VALUES (@Nama, @Username, @Email, @Password, @JK, @TglLahir, @NoHp, @Alamat, @Foto, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Pelanggan;
END
GO

IF OBJECT_ID('sp_UpdatePelanggan', 'P') IS NOT NULL DROP PROCEDURE sp_UpdatePelanggan;
GO
CREATE PROCEDURE sp_UpdatePelanggan
    @ID INT, @Nama VARCHAR(100), @Email VARCHAR(100), @JK VARCHAR(15), @TglLahir DATE,
    @NoHp VARCHAR(15), @Alamat VARCHAR(255) = NULL, @Foto VARCHAR(255) = 'default.jpg',
    @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Pelanggan SET Nama_Pelanggan = @Nama, Email_Pelanggan = @Email, Jenis_Kelamin = @JK, Tanggal_Lahir = @TglLahir, No_Hp = @NoHp, Alamat = @Alamat, Foto_Profil = @Foto, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Pelanggan = @ID;
END
GO

IF OBJECT_ID('sp_DeletePelanggan', 'P') IS NOT NULL DROP PROCEDURE sp_DeletePelanggan;
GO
CREATE PROCEDURE sp_DeletePelanggan @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Pelanggan SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Pelanggan = @ID;
END
GO

IF OBJECT_ID('sp_ReadPelanggan', 'P') IS NOT NULL DROP PROCEDURE sp_ReadPelanggan;
GO
CREATE PROCEDURE sp_ReadPelanggan @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Pelanggan WHERE Is_Deleted = 0;
    ELSE
        SELECT * FROM Pelanggan WHERE ID_Pelanggan = @ID AND Is_Deleted = 0;
END
GO

-- ==================== MASTER 3: PAKET FOTO ====================
IF OBJECT_ID('sp_InsertPaketFoto', 'P') IS NOT NULL DROP PROCEDURE sp_InsertPaketFoto;
GO
CREATE PROCEDURE sp_InsertPaketFoto
    @Nama VARCHAR(100), @Durasi INT, @Harga DECIMAL(12,2), @Deskripsi VARCHAR(255) = NULL,
    @Kapasitas INT, @Foto VARCHAR(255) = 'default_paket.jpg', @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Paket_Foto (Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket, Created_By)
    VALUES (@Nama, @Durasi, @Harga, @Deskripsi, @Kapasitas, @Foto, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Paket;
END
GO

IF OBJECT_ID('sp_UpdatePaketFoto', 'P') IS NOT NULL DROP PROCEDURE sp_UpdatePaketFoto;
GO
CREATE PROCEDURE sp_UpdatePaketFoto
    @ID INT, @Nama VARCHAR(100), @Durasi INT, @Harga DECIMAL(12,2), @Deskripsi VARCHAR(255) = NULL,
    @Kapasitas INT, @Foto VARCHAR(255) = 'default_paket.jpg', @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Paket_Foto SET Nama_Paket = @Nama, Durasi_Waktu = @Durasi, Harga_Paket = @Harga, Deskripsi = @Deskripsi, Kapasitas_Orang = @Kapasitas, Foto_Paket = @Foto, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Paket = @ID;
END
GO

IF OBJECT_ID('sp_DeletePaketFoto', 'P') IS NOT NULL DROP PROCEDURE sp_DeletePaketFoto;
GO
CREATE PROCEDURE sp_DeletePaketFoto @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Paket_Foto SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Paket = @ID;
END
GO

IF OBJECT_ID('sp_ReadPaketFoto', 'P') IS NOT NULL DROP PROCEDURE sp_ReadPaketFoto;
GO
CREATE PROCEDURE sp_ReadPaketFoto @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Paket_Foto WHERE Is_Deleted = 0;
    ELSE
        SELECT * FROM Paket_Foto WHERE ID_Paket = @ID AND Is_Deleted = 0;
END
GO

-- =====================================================
-- PATCH: Fix Foto_Paket tidak muncul di halaman Customer > Hasil Foto Saya
-- Root cause: sp_ReadHasilFotoRingkasanCustomer sudah JOIN ke Paket_Foto (PK)
-- tapi kolom PK.Foto_Paket tidak ikut di-SELECT, sehingga $row['Foto_Paket']
-- di hasil_foto.php selalu NULL dan fallback ke ikon kamera.
--
-- Perubahan: HANYA menambahkan 1 baris "PK.Foto_Paket" ke SELECT list.
-- Semua JOIN, WHERE, ORDER BY, dan logic lainnya tetap SAMA PERSIS
-- seperti stored procedure aslinya.
-- =====================================================

IF OBJECT_ID('sp_ReadHasilFotoRingkasanCustomer', 'P') IS NOT NULL
    DROP PROCEDURE sp_ReadHasilFotoRingkasanCustomer;
GO

CREATE PROCEDURE sp_ReadHasilFotoRingkasanCustomer
    @ID_Pelanggan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.Tanggal_Upload_Hasil,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        PK.Foto_Paket,          -- <== PATCH: kolom ini ditambahkan
        R.Nama_Ruangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai,
        O.Total_Harga,
        HF.Total_Foto,
        HF.Total_Ukuran
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    OUTER APPLY (
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    CROSS APPLY (
        SELECT COUNT(*) AS Total_Foto, ISNULL(SUM(CAST(Ukuran_Bytes AS BIGINT)), 0) AS Total_Ukuran
        FROM Hasil_Foto WHERE ID_Sesi_Foto = S.ID_Sesi_Foto AND Status = 1
    ) HF
    WHERE O.ID_Pelanggan = @ID_Pelanggan
      AND O.Status = 1
      AND O.Status_Order = 3 -- Lunas
      AND S.Status = 1
      AND HF.Total_Foto > 0
    ORDER BY S.Tanggal_Upload_Hasil DESC;
END
GO

PRINT 'Patch berhasil: PK.Foto_Paket ditambahkan ke sp_ReadHasilFotoRingkasanCustomer.';
GO

-- ==================== MASTER 4: RUANGAN ====================
IF OBJECT_ID('sp_InsertRuangan', 'P') IS NOT NULL DROP PROCEDURE sp_InsertRuangan;
GO
CREATE PROCEDURE sp_InsertRuangan
    @Nama VARCHAR(100), @Deskripsi VARCHAR(255) = NULL,
    @Foto VARCHAR(255) = 'default_ruangan.jpg', @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Ruangan (Nama_Ruangan, Deskripsi, Foto_Ruangan, Created_By)
    VALUES (@Nama, @Deskripsi, @Foto, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Ruangan;
END
GO

IF OBJECT_ID('sp_UpdateRuangan', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateRuangan;
GO
CREATE PROCEDURE sp_UpdateRuangan
    @ID INT, @Nama VARCHAR(100), @Deskripsi VARCHAR(255) = NULL,
    @Foto VARCHAR(255) = 'default_ruangan.jpg', @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Ruangan SET Nama_Ruangan = @Nama, Deskripsi = @Deskripsi, Foto_Ruangan = @Foto, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Ruangan = @ID;
END
GO

IF OBJECT_ID('sp_DeleteRuangan', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteRuangan;
GO
CREATE PROCEDURE sp_DeleteRuangan @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Ruangan SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Ruangan = @ID;
END
GO

IF OBJECT_ID('sp_ReadRuangan', 'P') IS NOT NULL DROP PROCEDURE sp_ReadRuangan;
GO
CREATE PROCEDURE sp_ReadRuangan @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Ruangan WHERE Is_Deleted = 0;
    ELSE
        SELECT * FROM Ruangan WHERE ID_Ruangan = @ID AND Is_Deleted = 0;
END
GO

-- ==================== JUNCTION: PAKET_RUANGAN ====================
IF OBJECT_ID('sp_InsertPaketRuangan', 'P') IS NOT NULL DROP PROCEDURE sp_InsertPaketRuangan;
GO
CREATE PROCEDURE sp_InsertPaketRuangan
    @ID_Paket INT, @ID_Ruangan INT
AS
BEGIN
    INSERT INTO Paket_Ruangan (ID_Paket, ID_Ruangan) VALUES (@ID_Paket, @ID_Ruangan);
END
GO

-- ==================== PEMBANTU: CEK DUPLIKAT RUANGAN ====================
IF OBJECT_ID('sp_CekDuplikatRuangan', 'P') IS NOT NULL DROP PROCEDURE sp_CekDuplikatRuangan;
GO
CREATE PROCEDURE sp_CekDuplikatRuangan
    @Nama VARCHAR(100),
    @ExcludeID INT = NULL
AS
BEGIN
    IF @ExcludeID IS NULL
        SELECT COUNT(*) AS total FROM Ruangan WHERE Nama_Ruangan = @Nama AND Is_Deleted = 0;
    ELSE
        SELECT COUNT(*) AS total FROM Ruangan WHERE Nama_Ruangan = @Nama AND ID_Ruangan <> @ExcludeID AND Is_Deleted = 0;
END
GO


-- ==================== MASTER 5: TEMA FOTO ====================
IF OBJECT_ID('sp_InsertTemaFoto', 'P') IS NOT NULL DROP PROCEDURE sp_InsertTemaFoto;
GO
CREATE PROCEDURE sp_InsertTemaFoto
    @Nama VARCHAR(100), @Kategori VARCHAR(50), @Deskripsi VARCHAR(255) = NULL,
    @Foto VARCHAR(255) = 'default_tema.jpg', @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Tema_Foto (Nama_Tema, Kategori_Tema, Deskripsi, Foto_Tema, Created_By)
    VALUES (@Nama, @Kategori, @Deskripsi, @Foto, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Tema;
END
GO

IF OBJECT_ID('sp_UpdateTemaFoto', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateTemaFoto;
GO
CREATE PROCEDURE sp_UpdateTemaFoto
    @ID INT, @Nama VARCHAR(100), @Kategori VARCHAR(50), @Deskripsi VARCHAR(255) = NULL,
    @Foto VARCHAR(255) = 'default_tema.jpg', @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Tema_Foto SET Nama_Tema = @Nama, Kategori_Tema = @Kategori, Deskripsi = @Deskripsi, Foto_Tema = @Foto, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Tema = @ID;
END
GO

IF OBJECT_ID('sp_DeleteTemaFoto', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteTemaFoto;
GO
CREATE PROCEDURE sp_DeleteTemaFoto @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Tema_Foto SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Tema = @ID;
END
GO

IF OBJECT_ID('sp_ReadTemaFoto', 'P') IS NOT NULL DROP PROCEDURE sp_ReadTemaFoto;
GO
CREATE PROCEDURE sp_ReadTemaFoto @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Tema_Foto WHERE Is_Deleted = 0;
    ELSE
        SELECT * FROM Tema_Foto WHERE ID_Tema = @ID AND Is_Deleted = 0;
END
GO

-- ==================== MASTER 6: PROPERTI ====================
IF OBJECT_ID('sp_InsertProperti', 'P') IS NOT NULL DROP PROCEDURE sp_InsertProperti;
GO
CREATE PROCEDURE sp_InsertProperti
    @ID_Ruangan INT, @Nama VARCHAR(100), @Kategori VARCHAR(50) = NULL,
    @Deskripsi VARCHAR(255) = NULL, @Foto VARCHAR(255) = 'default_properti.jpg', @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Properti (ID_Ruangan, Nama_Properti, Kategori_Properti, Deskripsi, Foto_Properti, Created_By)
    VALUES (@ID_Ruangan, @Nama, @Kategori, @Deskripsi, @Foto, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Properti;
END
GO

IF OBJECT_ID('sp_UpdateProperti', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateProperti;
GO
CREATE PROCEDURE sp_UpdateProperti
    @ID INT, @ID_Ruangan INT, @Nama VARCHAR(100), @Kategori VARCHAR(50) = NULL,
    @Deskripsi VARCHAR(255) = NULL, @Foto VARCHAR(255) = 'default_properti.jpg', @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Properti SET ID_Ruangan = @ID_Ruangan, Nama_Properti = @Nama, Kategori_Properti = @Kategori, Deskripsi = @Deskripsi, Foto_Properti = @Foto, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Properti = @ID;
END
GO

IF OBJECT_ID('sp_DeleteProperti', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteProperti;
GO
CREATE PROCEDURE sp_DeleteProperti @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Properti SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Properti = @ID;
END
GO

IF OBJECT_ID('sp_ReadProperti', 'P') IS NOT NULL DROP PROCEDURE sp_ReadProperti;
GO
CREATE PROCEDURE sp_ReadProperti @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT p.*, r.Nama_Ruangan FROM Properti p JOIN Ruangan r ON p.ID_Ruangan = r.ID_Ruangan WHERE p.Is_Deleted = 0;
    ELSE
        SELECT p.*, r.Nama_Ruangan FROM Properti p JOIN Ruangan r ON p.ID_Ruangan = r.ID_Ruangan WHERE p.ID_Properti = @ID AND p.Is_Deleted = 0;
END
GO

-- ==================== MASTER 7: BARANG CETAK ====================
IF OBJECT_ID('sp_InsertBarangCetak', 'P') IS NOT NULL DROP PROCEDURE sp_InsertBarangCetak;
GO
CREATE PROCEDURE sp_InsertBarangCetak
    @Nama VARCHAR(100), @Deskripsi VARCHAR(255) = NULL, @Harga DECIMAL(12,2),
    @Stok INT, @StokMin INT = 5, @Foto VARCHAR(255) = 'default_barang.jpg', @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Barang_Cetak (Nama_Barang, Deskripsi, Harga_Barang, Stok_Barang, Stok_Minimum, Foto_Barang, Created_By)
    VALUES (@Nama, @Deskripsi, @Harga, @Stok, @StokMin, @Foto, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Barang;
END
GO

IF OBJECT_ID('sp_UpdateBarangCetak', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateBarangCetak;
GO
CREATE PROCEDURE sp_UpdateBarangCetak
    @ID INT, @Nama VARCHAR(100), @Deskripsi VARCHAR(255) = NULL, @Harga DECIMAL(12,2),
    @Stok INT, @StokMin INT, @Foto VARCHAR(255) = 'default_barang.jpg', @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Barang_Cetak SET Nama_Barang = @Nama, Deskripsi = @Deskripsi, Harga_Barang = @Harga, Stok_Barang = @Stok, Stok_Minimum = @StokMin, Foto_Barang = @Foto, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Barang = @ID;
END
GO

IF OBJECT_ID('sp_DeleteBarangCetak', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteBarangCetak;
GO
CREATE PROCEDURE sp_DeleteBarangCetak @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Barang_Cetak SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Barang = @ID;
END
GO

IF OBJECT_ID('sp_ReadBarangCetak', 'P') IS NOT NULL DROP PROCEDURE sp_ReadBarangCetak;
GO
CREATE PROCEDURE sp_ReadBarangCetak @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Barang_Cetak WHERE Is_Deleted = 0;
    ELSE
        SELECT * FROM Barang_Cetak WHERE ID_Barang = @ID AND Is_Deleted = 0;
END
GO

-- ==================== MASTER 8: JADWAL STUDIO ====================
IF OBJECT_ID('sp_InsertJadwalStudio', 'P') IS NOT NULL DROP PROCEDURE sp_InsertJadwalStudio;
GO
CREATE PROCEDURE sp_InsertJadwalStudio
    @ID_Ruangan INT, @Tanggal DATE, @JamMulai TIME, @JamSelesai TIME,
    @Keterangan VARCHAR(255) = NULL, @CreatedBy VARCHAR(50)
AS
BEGIN
    -- Logika validasi tabrakan jadwal langsung dieksekusi di SP sebelum insert
    IF EXISTS (
        SELECT 1 FROM Jadwal_Studio
        WHERE ID_Ruangan = @ID_Ruangan
          AND Tanggal_Jadwal = @Tanggal
          AND Is_Deleted = 0
          AND (
                (@JamMulai >= Jam_Mulai AND @JamMulai < Jam_Selesai) OR
                (@JamSelesai > Jam_Mulai AND @JamSelesai <= Jam_Selesai) OR
                (Jam_Mulai >= @JamMulai AND Jam_Mulai < @JamSelesai)
              )
    )
    BEGIN
        RAISERROR('Gagal: Jadwal bertabrakan dengan jadwal studio yang sudah ada!', 16, 1);
        RETURN;
    END

    INSERT INTO Jadwal_Studio (ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Keterangan, Created_By)
    VALUES (@ID_Ruangan, @Tanggal, @JamMulai, @JamSelesai, @Keterangan, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Jadwal;
END
GO

IF OBJECT_ID('sp_UpdateJadwalStudio', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateJadwalStudio;
GO
CREATE PROCEDURE sp_UpdateJadwalStudio
    @ID INT, @ID_Ruangan INT, @Tanggal DATE, @JamMulai TIME, @JamSelesai TIME,
    @Keterangan VARCHAR(255) = NULL, @StatusJadwal INT, @Status INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Jadwal_Studio SET ID_Ruangan = @ID_Ruangan, Tanggal_Jadwal = @Tanggal, Jam_Mulai = @JamMulai, Jam_Selesai = @JamSelesai, Keterangan = @Keterangan, Status_Jadwal = @StatusJadwal, Status = @Status, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Jadwal = @ID;
END
GO

IF OBJECT_ID('sp_DeleteJadwalStudio', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteJadwalStudio;
GO
CREATE PROCEDURE sp_DeleteJadwalStudio @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Jadwal_Studio SET Is_Deleted = 1, Deleted_By = @DeletedBy, Deleted_Date = GETDATE() WHERE ID_Jadwal = @ID;
END
GO

IF OBJECT_ID('sp_ReadJadwalStudio', 'P') IS NOT NULL DROP PROCEDURE sp_ReadJadwalStudio;
GO
CREATE PROCEDURE sp_ReadJadwalStudio @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT js.*, r.Nama_Ruangan FROM Jadwal_Studio js JOIN Ruangan r ON js.ID_Ruangan = r.ID_Ruangan WHERE js.Is_Deleted = 0;
    ELSE
        SELECT js.*, r.Nama_Ruangan FROM Jadwal_Studio js JOIN Ruangan r ON js.ID_Ruangan = r.ID_Ruangan WHERE js.ID_Jadwal = @ID AND js.Is_Deleted = 0;
END
GO


-- ======================================================
-- 6. STORED PROCEDURES (SP) - CRUD LENGKAP UNTUK TRANSAKSI
-- ======================================================

-- ==================== TRANSAKSI 1: ORDER BOOKING ====================
IF OBJECT_ID('sp_CreateOrderBooking', 'P') IS NOT NULL DROP PROCEDURE sp_CreateOrderBooking;
GO
CREATE PROCEDURE sp_CreateOrderBooking
    @ID_Pelanggan   INT,
    @ID_Paket       INT,
    @ID_Ruangan     INT,
    @ID_Tema        INT,
    @JadwalList     ListJadwalType READONLY, -- Type List Jadwal (Multi-Jadwal)
    @Keterangan     VARCHAR(255) = NULL,
    @Created_By     VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Validasi 1: Paket & Ruangan harus sesuai (Diperbarui merujuk ke tabel junction Paket_Ruangan)
    IF NOT EXISTS (SELECT 1 FROM Paket_Ruangan WHERE ID_Ruangan = @ID_Ruangan AND ID_Paket = @ID_Paket)
    BEGIN
        RAISERROR('Gagal: Ruangan yang dipilih tidak sesuai dengan paket foto!', 16, 1);
        RETURN;
    END

    -- Validasi 2: Ruangan & Tema harus sesuai
    IF NOT EXISTS (SELECT 1 FROM Ruangan_Tema WHERE ID_Ruangan = @ID_Ruangan AND ID_Tema = @ID_Tema)
    BEGIN
        RAISERROR('Gagal: Tema yang dipilih tidak tersedia untuk ruangan tersebut!', 16, 1);
        RETURN;
    END

    -- Validasi 3: Memastikan semua jadwal yang dipilih tersedia
    IF EXISTS (
        SELECT 1 
        FROM @JadwalList l
        JOIN Jadwal_Studio js ON l.ID_Jadwal = js.ID_Jadwal
        WHERE js.Status_Jadwal <> 0 OR js.Is_Deleted = 1
    )
    BEGIN
        RAISERROR('Gagal: Salah satu jadwal yang dipilih sudah terpesan atau tidak tersedia!', 16, 1);
        RETURN;
    END

    DECLARE @HargaPaket DECIMAL(12,2);
    SELECT @HargaPaket = Harga_Paket FROM Paket_Foto WHERE ID_Paket = @ID_Paket;

    BEGIN TRANSACTION;
    BEGIN TRY
        -- Insert Order
        INSERT INTO [Order] (ID_Pelanggan, ID_Paket, ID_Ruangan, ID_Tema, 
                             Total_Paket, Total_Barang_Cetak, Keterangan, Status_Order, Created_By)
        VALUES (@ID_Pelanggan, @ID_Paket, @ID_Ruangan, @ID_Tema, 
                @HargaPaket, 0, @Keterangan, 0, @Created_By);

        DECLARE @NewOrderID INT = SCOPE_IDENTITY();

        -- Insert Relasi Jadwal (Order_Jadwal)
        INSERT INTO Order_Jadwal (ID_Order, ID_Jadwal)
        SELECT @NewOrderID, ID_Jadwal FROM @JadwalList;

        -- Update Status Jadwal di Jadwal_Studio ke Booked (1)
        UPDATE js
        SET Status_Jadwal = 1,
            Modified_By = @Created_By,
            Modified_Date = GETDATE()
        FROM Jadwal_Studio js
        JOIN @JadwalList l ON js.ID_Jadwal = l.ID_Jadwal;

        COMMIT TRANSACTION;
        SELECT @NewOrderID AS ID_Order;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO

IF OBJECT_ID('sp_ReadOrder', 'P') IS NOT NULL DROP PROCEDURE sp_ReadOrder;
GO
CREATE PROCEDURE sp_ReadOrder @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM [Order] WHERE Status = 1;
    ELSE
        SELECT * FROM [Order] WHERE ID_Order = @ID AND Status = 1;
END
GO

IF OBJECT_ID('sp_UpdateOrder', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateOrder;
GO
CREATE PROCEDURE sp_UpdateOrder
    @ID INT, @Keterangan VARCHAR(255) = NULL, @Rating INT = NULL, @Review VARCHAR(255) = NULL, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE [Order] SET Keterangan = @Keterangan, Rating = @Rating, Review = @Review, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Order = @ID;
END
GO

IF OBJECT_ID('sp_BatalkanOrderBooking', 'P') IS NOT NULL DROP PROCEDURE sp_BatalkanOrderBooking;
GO
CREATE PROCEDURE sp_BatalkanOrderBooking
    @ID_Order       INT,
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    BEGIN TRANSACTION;
    BEGIN TRY
        -- Update Status Order ke Dibatalkan (4)
        UPDATE [Order]
        SET Status_Order = 4, 
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Order = @ID_Order;

        -- Kembalikan semua status Jadwal terkait ke Tersedia (0)
        UPDATE js
        SET js.Status_Jadwal = 0
        FROM Jadwal_Studio js
        JOIN Order_Jadwal oj ON js.ID_Jadwal = oj.ID_Jadwal
        WHERE oj.ID_Order = @ID_Order;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO

-- ==================== TRANSAKSI 2: PEMBAYARAN ====================
IF OBJECT_ID('sp_InsertPembayaran', 'P') IS NOT NULL DROP PROCEDURE sp_InsertPembayaran;
GO
CREATE PROCEDURE sp_InsertPembayaran
    @ID_Order INT, @Tipe VARCHAR(20), @Metode VARCHAR(50), @Jumlah DECIMAL(12,2),
    @Bukti VARCHAR(255) = NULL, @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Pembayaran (ID_Order, Tipe_Pembayaran, Metode_Pembayaran, Jumlah_Bayar, Bukti_Transfer, Created_By)
    VALUES (@ID_Order, @Tipe, @Metode, @Jumlah, @Bukti, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Pembayaran;
END
GO

IF OBJECT_ID('sp_ReadPembayaran', 'P') IS NOT NULL DROP PROCEDURE sp_ReadPembayaran;
GO
CREATE PROCEDURE sp_ReadPembayaran @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Pembayaran WHERE Status = 1;
    ELSE
        SELECT * FROM Pembayaran WHERE ID_Pembayaran = @ID AND Status = 1;
END
GO

IF OBJECT_ID('sp_VerifikasiPembayaran', 'P') IS NOT NULL DROP PROCEDURE sp_VerifikasiPembayaran;
GO
CREATE PROCEDURE sp_VerifikasiPembayaran
    @ID_Pembayaran      INT,
    @Status_Verifikasi  INT, -- 1 = Valid, 2 = Ditolak
    @ID_Admin           INT,
    @Modified_By        VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @ID_Order INT;
    DECLARE @Tipe_Bayar VARCHAR(20);

    SELECT @ID_Order = ID_Order, @Tipe_Bayar = Tipe_Pembayaran
    FROM Pembayaran WHERE ID_Pembayaran = @ID_Pembayaran;

    BEGIN TRANSACTION;
    BEGIN TRY
        UPDATE Pembayaran
        SET Status_Pembayaran = @Status_Verifikasi,
            ID_Karyawan_Verifikator = @ID_Admin,
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Pembayaran = @ID_Pembayaran;

        -- Jika DP Valid
        IF @Tipe_Bayar = 'DP' AND @Status_Verifikasi = 1
        BEGIN
            UPDATE [Order] SET Status_Order = 1, Modified_By = @Modified_By, Modified_Date = GETDATE() WHERE ID_Order = @ID_Order;
        END
        -- Jika DP Ditolak
        ELSE IF @Tipe_Bayar = 'DP' AND @Status_Verifikasi = 2
        BEGIN
            UPDATE [Order] SET Status_Order = 0, Modified_By = @Modified_By, Modified_Date = GETDATE() WHERE ID_Order = @ID_Order;
            -- Kembalikan status jadwal ke tersedia
            UPDATE js
            SET js.Status_Jadwal = 0
            FROM Jadwal_Studio js
            JOIN Order_Jadwal oj ON js.ID_Jadwal = oj.ID_Jadwal
            WHERE oj.ID_Order = @ID_Order;
        END
        -- Jika Pelunasan Valid
        ELSE IF @Tipe_Bayar = 'Pelunasan' AND @Status_Verifikasi = 1
        BEGIN
            DECLARE @Status_Order_Saat_Ini INT;
            SELECT @Status_Order_Saat_Ini = Status_Order FROM [Order] WHERE ID_Order = @ID_Order;

            IF @Status_Order_Saat_Ini = 0
            BEGIN
                -- Bayar LUNAS SEKALIGUS di awal (sebelum sesi foto berjalan) --
                -- setarakan dengan "DP Terverifikasi" (siap dijadwalkan/assign
                -- fotografer), JANGAN langsung Lunas karena sesi fotonya belum
                -- terjadi. Status akan naik ke Lunas (3) otomatis lewat
                -- sp_SelesaiSesiFoto begitu sesi ini nanti selesai.
                UPDATE [Order] SET Status_Order = 1, Modified_By = @Modified_By, Modified_Date = GETDATE() WHERE ID_Order = @ID_Order;
            END
            ELSE
            BEGIN
                -- Pelunasan lanjutan SETELAH sesi foto selesai (alur normal 2 tahap: DP -> sesi -> Pelunasan)
                UPDATE [Order] SET Status_Order = 3, Modified_By = @Modified_By, Modified_Date = GETDATE() WHERE ID_Order = @ID_Order;
            END
        END

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO

IF OBJECT_ID('sp_DeletePembayaran', 'P') IS NOT NULL DROP PROCEDURE sp_DeletePembayaran;
GO
CREATE PROCEDURE sp_DeletePembayaran @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Pembayaran SET Status = 0, Modified_By = @DeletedBy, Modified_Date = GETDATE() WHERE ID_Pembayaran = @ID;
END
GO

-- ==================== TRANSAKSI 3: SESI FOTO ====================
IF OBJECT_ID('sp_MulaiSesiFoto', 'P') IS NOT NULL DROP PROCEDURE sp_MulaiSesiFoto;
GO
CREATE PROCEDURE sp_MulaiSesiFoto
    @ID_Order INT, @ID_Fotografer INT, @CreatedBy VARCHAR(50)
AS
BEGIN
    -- DP Terverifikasi (1) ATAU Lunas (3) sama-sama boleh mulai sesi foto
    IF NOT EXISTS (SELECT 1 FROM [Order] WHERE ID_Order = @ID_Order AND Status_Order IN (1, 3))
    BEGIN
        RAISERROR('Gagal: Sesi foto belum dapat dimulai karena pembayaran (DP atau Pelunasan) belum diverifikasi!', 16, 1);
        RETURN;
    END

    INSERT INTO Sesi_Foto (ID_Order, ID_Karyawan, Waktu_Mulai, Status_Sesi, Created_By)
    VALUES (@ID_Order, @ID_Fotografer, GETDATE(), 0, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Sesi_Foto;
    END
GO

-- Ambil detail 1 sesi foto milik fotografer tertentu, untuk halaman
-- "Proses Sesi" (Sesi/Proses/index.php). Pakai CROSS APPLY ke Order_Jadwal +
-- Jadwal_Studio (bukan O.ID_Jadwal langsung -- kolom itu tidak ada di [Order],
-- ini sumber bug "notfound" yang terjadi sebelumnya).
IF OBJECT_ID('sp_ReadDetailSesiFotografer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadDetailSesiFotografer;
GO
CREATE PROCEDURE sp_ReadDetailSesiFotografer
    @ID_Sesi_Foto INT,
    @ID_Fotografer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto, S.ID_Order, S.ID_Karyawan, S.Waktu_Mulai, S.Waktu_Selesai,
        S.File_Hasil, S.Tanggal_Upload_Hasil, S.Status_Sesi,
        P.Nama_Pelanggan,
        PK.Nama_Paket,
        R.Nama_Ruangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    CROSS APPLY (
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    WHERE S.ID_Sesi_Foto = @ID_Sesi_Foto
      AND S.ID_Karyawan = @ID_Fotografer
      AND S.Status = 1
      AND S.Status_Sesi = 0;
END
GO

-- Tandai sesi (yang SUDAH di-assign lewat assign_fotografer.php) sebagai mulai
-- berjalan. Hanya UPDATE Waktu_Mulai pada baris Sesi_Foto yang sudah ada --
-- BUKAN insert baru (beda dengan sp_MulaiSesiFoto di atas, yang dipakai untuk
-- alur lain). Guard memastikan sesi memang milik fotografer ybs dan masih
-- di status Menunggu, supaya tidak bisa "mulai ulang" sesi yang sudah jalan.
IF OBJECT_ID('sp_MulaiProsesSesiFoto', 'P') IS NOT NULL DROP PROCEDURE sp_MulaiProsesSesiFoto;
GO
CREATE PROCEDURE sp_MulaiProsesSesiFoto
    @ID_Sesi_Foto INT,
    @ID_Fotografer INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (
        SELECT 1 FROM Sesi_Foto
        WHERE ID_Sesi_Foto = @ID_Sesi_Foto AND ID_Karyawan = @ID_Fotografer AND Status = 1 AND Status_Sesi = 0
    )
    BEGIN
        RAISERROR('Sesi tidak ditemukan, bukan milik fotografer ini, atau sudah tidak dalam status Menunggu.', 16, 1);
        RETURN;
    END

    UPDATE Sesi_Foto
    SET Waktu_Mulai = GETDATE(),
        Modified_By = @Modified_By,
        Modified_Date = GETDATE()
    WHERE ID_Sesi_Foto = @ID_Sesi_Foto;
END
GO

-- Ambil detail 1 sesi foto (termasuk info file hasil) milik fotografer
-- tertentu, untuk halaman upload_hasil.php. Sama seperti SP-SP sebelumnya,
-- pakai CROSS APPLY ke Order_Jadwal + Jadwal_Studio, bukan O.ID_Jadwal
-- langsung (kolom itu tidak ada di [Order]).
IF OBJECT_ID('sp_ReadDetailSesiHasilFotografer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadDetailSesiHasilFotografer;
GO
CREATE PROCEDURE sp_ReadDetailSesiHasilFotografer
    @ID_Sesi_Foto INT,
    @ID_Fotografer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.File_Hasil,
        S.Tanggal_Upload_Hasil,
        S.Status_Sesi,
        S.Waktu_Mulai,
        S.Waktu_Selesai,
        O.Keterangan AS Keterangan_Order,
        P.Nama_Pelanggan,
        P.Email_Pelanggan,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        R.Nama_Ruangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    CROSS APPLY (
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    WHERE S.ID_Sesi_Foto = @ID_Sesi_Foto
      AND S.ID_Karyawan = @ID_Fotografer
      AND S.Status = 1;
END
GO

-- Daftar sesi foto yang SUDAH SELESAI tapi BELUM diupload hasilnya
-- (File_Hasil IS NULL), untuk halaman Sesi/Upload/index.php.
IF OBJECT_ID('sp_ReadListSesiBelumUploadFotografer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadListSesiBelumUploadFotografer;
GO
CREATE PROCEDURE sp_ReadListSesiBelumUploadFotografer
    @ID_Fotografer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.File_Hasil,
        S.Tanggal_Upload_Hasil,
        S.Waktu_Selesai,
        P.Nama_Pelanggan,
        PK.Nama_Paket,
        R.Nama_Ruangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai,
        O.Status_Order
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    CROSS APPLY (
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    WHERE S.ID_Karyawan = @ID_Fotografer
      AND S.Status = 1
      AND S.Status_Sesi = 1
      AND S.File_Hasil IS NULL
      AND O.Status_Order <> 4
    ORDER BY S.Waktu_Selesai DESC;
END
GO


IF OBJECT_ID('sp_ReadSesiFoto', 'P') IS NOT NULL DROP PROCEDURE sp_ReadSesiFoto;
GO
CREATE PROCEDURE sp_ReadSesiFoto @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Sesi_Foto WHERE Status = 1;
    ELSE
        SELECT * FROM Sesi_Foto WHERE ID_Sesi_Foto = @ID AND Status = 1;
END
GO

IF OBJECT_ID('sp_SelesaiSesiFoto', 'P') IS NOT NULL DROP PROCEDURE sp_SelesaiSesiFoto;
GO
CREATE PROCEDURE sp_SelesaiSesiFoto
    @ID_Sesi_Foto   INT,
    @File_Hasil     VARCHAR(255) = NULL,  -- opsional: sesi bisa "diselesaikan" dulu (mis. dari halaman Proses fotografer), file hasil menyusul lewat upload_hasil.php
    @Modified_By    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @ID_Order INT;
    SELECT @ID_Order = ID_Order FROM Sesi_Foto WHERE ID_Sesi_Foto = @ID_Sesi_Foto;

    -- Cek apakah order ini sudah LUNAS SEKALIGUS di awal (bayar penuh sebelum
    -- sesi berjalan): ada baris Pelunasan valid TANPA baris DP sama sekali.
    -- Kalau iya, begitu sesi selesai order langsung Lunas (3), BUKAN "Menunggu
    -- Pelunasan" (2) -- karena memang tidak akan pernah ada pelunasan susulan.
    DECLARE @Sudah_Lunas_Sekaligus BIT = 0;
    IF EXISTS (
        SELECT 1 FROM Pembayaran
        WHERE ID_Order = @ID_Order AND Tipe_Pembayaran = 'Pelunasan' AND Status_Pembayaran = 1 AND Status = 1
    ) AND NOT EXISTS (
        SELECT 1 FROM Pembayaran
        WHERE ID_Order = @ID_Order AND Tipe_Pembayaran = 'DP' AND Status = 1
    )
    BEGIN
        SET @Sudah_Lunas_Sekaligus = 1;
    END

    BEGIN TRANSACTION;
    BEGIN TRY
        UPDATE Sesi_Foto
        SET Waktu_Selesai = GETDATE(),
            File_Hasil = COALESCE(@File_Hasil, File_Hasil),
            Tanggal_Upload_Hasil = CASE WHEN @File_Hasil IS NOT NULL THEN GETDATE() ELSE Tanggal_Upload_Hasil END,
            Status_Sesi = 1, -- 1 = Selesai
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Sesi_Foto = @ID_Sesi_Foto;

        UPDATE [Order]
        SET Status_Order = CASE WHEN @Sudah_Lunas_Sekaligus = 1 THEN 3 ELSE 2 END, -- 3=Lunas (sudah lunas di awal) / 2=Selesai Sesi, Menunggu Pelunasan
            Modified_By = @Modified_By,
            Modified_Date = GETDATE()
        WHERE ID_Order = @ID_Order;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO

IF OBJECT_ID('sp_DeleteSesiFoto', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteSesiFoto;
GO
CREATE PROCEDURE sp_DeleteSesiFoto @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Sesi_Foto SET Status_Sesi = 2, Status = 0, Modified_By = @DeletedBy, Modified_Date = GETDATE() WHERE ID_Sesi_Foto = @ID;
END
GO

-- Auto-expire sesi foto yang MASIH MENUNGGU (Status_Sesi = 0) tapi seluruh
-- slot jadwalnya sudah lewat waktu. TIDAK melakukan hard DELETE -- sesi
-- di-soft-cancel (Status_Sesi = 2/Dibatalkan) dan dicatat ke Log_History
-- supaya tetap ada jejak audit (siapa yang seharusnya memotret, kapan, dsb).
-- Hard delete dihindari karena akan merusak riwayat/laporan dan referensi
-- FK dari tabel lain -- praktik umum sistem bisnis nyata adalah soft-cancel.
IF OBJECT_ID('sp_AutoExpireSesiFoto', 'P') IS NOT NULL DROP PROCEDURE sp_AutoExpireSesiFoto;
GO
CREATE PROCEDURE sp_AutoExpireSesiFoto
    @Executed_By VARCHAR(50) = 'system'
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @Expired TABLE (ID_Sesi_Foto INT);

    -- Sesi dianggap kadaluarsa jika order-nya masih punya minimal 1 slot
    -- jadwal aktif, TAPI tidak ada satupun slot yang waktu selesainya >= sekarang
    -- (artinya seluruh slot yang dipesan sudah lewat dan sesi belum diproses).
    INSERT INTO @Expired (ID_Sesi_Foto)
    SELECT S.ID_Sesi_Foto
    FROM Sesi_Foto S
    WHERE S.Status_Sesi = 0 AND S.Status = 1
      AND EXISTS (
          SELECT 1 FROM Order_Jadwal OJ
          JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
          WHERE OJ.ID_Order = S.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
      )
      AND NOT EXISTS (
          SELECT 1 FROM Order_Jadwal OJ2
          JOIN Jadwal_Studio J2 ON OJ2.ID_Jadwal = J2.ID_Jadwal
          WHERE OJ2.ID_Order = S.ID_Order AND J2.Status = 1 AND J2.Is_Deleted = 0
            AND CAST(J2.Tanggal_Jadwal AS DATETIME) + CAST(J2.Jam_Selesai AS DATETIME) >= GETDATE()
      );

    IF EXISTS (SELECT 1 FROM @Expired)
    BEGIN
        BEGIN TRANSACTION;
        BEGIN TRY
            UPDATE S
            SET S.Status_Sesi = 2, -- 2 = Dibatalkan
                S.Modified_By = @Executed_By,
                S.Modified_Date = GETDATE()
            FROM Sesi_Foto S
            JOIN @Expired E ON S.ID_Sesi_Foto = E.ID_Sesi_Foto;

            INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, Executed_By)
            SELECT 'Sesi_Foto', CAST(ID_Sesi_Foto AS VARCHAR(50)), 'AUTO_EXPIRE',
                   'Status_Sesi: 0 (Menunggu)',
                   'Status_Sesi: 2 (Dibatalkan otomatis oleh sistem - jadwal kadaluarsa)',
                   @Executed_By
            FROM @Expired;

            COMMIT TRANSACTION;
        END TRY
        BEGIN CATCH
            ROLLBACK TRANSACTION;
            THROW;
        END CATCH
    END
END
GO

-- Ambil daftar sesi foto yang SUDAH SELESAI (Status_Sesi = 1) milik seorang
-- fotografer, untuk halaman "Sesi Selesai". Sama seperti
-- sp_ReadSesiTerjadwalFotografer, pakai CROSS APPLY ke Order_Jadwal +
-- Jadwal_Studio (bukan O.ID_Jadwal langsung -- kolom itu tidak ada di [Order]).
IF OBJECT_ID('sp_ReadSesiSelesaiFotografer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadSesiSelesaiFotografer;
GO
CREATE PROCEDURE sp_ReadSesiSelesaiFotografer
    @ID_Fotografer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.File_Hasil,
        S.Tanggal_Upload_Hasil,
        S.Waktu_Mulai,
        S.Waktu_Selesai,
        P.Nama_Pelanggan,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        R.Nama_Ruangan,
        O.Keterangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai,
        SlotCount.Total_Slot
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    CROSS APPLY (
        -- Slot jadwal paling awal untuk order ini (dipakai untuk tampilan tanggal sesi)
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    CROSS APPLY (
        -- Total slot jadwal aktif pada order ini, untuk indikator multi-slot di UI
        SELECT COUNT(*) AS Total_Slot
        FROM Order_Jadwal OJ2
        JOIN Jadwal_Studio J2 ON OJ2.ID_Jadwal = J2.ID_Jadwal
        WHERE OJ2.ID_Order = O.ID_Order AND J2.Status = 1 AND J2.Is_Deleted = 0
    ) SlotCount
    WHERE S.ID_Karyawan = @ID_Fotografer
      AND S.Status = 1
      AND S.Status_Sesi = 1
    ORDER BY S.Waktu_Selesai DESC;
END
GO


-- Ambil daftar sesi foto yang MASIH MENUNGGU (Status_Sesi = 0) milik seorang
-- fotografer, lengkap dengan slot jadwal terdekat. Menggunakan CROSS APPLY ke
-- Order_Jadwal + Jadwal_Studio karena satu Order bisa punya banyak slot jadwal
-- (multi-jadwal booking) -- Order TIDAK punya kolom ID_Jadwal langsung.
IF OBJECT_ID('sp_ReadSesiTerjadwalFotografer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadSesiTerjadwalFotografer;
GO
CREATE PROCEDURE sp_ReadSesiTerjadwalFotografer
    @ID_Fotografer INT
AS
BEGIN
    SET NOCOUNT ON;

    -- Bersihkan dulu sesi yang sudah kadaluarsa sebelum menampilkan daftar,
    -- supaya fotografer tidak melihat sesi "hantu" yang waktunya sudah lewat.
    EXEC sp_AutoExpireSesiFoto @Executed_By = 'system';

    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.Status_Sesi,
        P.Nama_Pelanggan,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        R.Nama_Ruangan,
        O.Keterangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai,
        SlotCount.Total_Slot
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    CROSS APPLY (
        -- Slot jadwal paling dekat/awal untuk order ini (dipakai untuk tampilan tanggal & countdown)
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    CROSS APPLY (
        -- Total slot jadwal aktif pada order ini, untuk indikator multi-slot di UI
        SELECT COUNT(*) AS Total_Slot
        FROM Order_Jadwal OJ2
        JOIN Jadwal_Studio J2 ON OJ2.ID_Jadwal = J2.ID_Jadwal
        WHERE OJ2.ID_Order = O.ID_Order AND J2.Status = 1 AND J2.Is_Deleted = 0
    ) SlotCount
    WHERE S.ID_Karyawan = @ID_Fotografer
      AND S.Status = 1
      AND S.Status_Sesi = 0
      AND O.Status = 1
      AND O.Status_Order <> 4
    ORDER BY Slot.Tanggal_Jadwal ASC, Slot.Jam_Mulai ASC;
END
GO

-- Penjaga kuota di level DATABASE (bukan cuma PHP) -- kalau ada jalur lain
-- yang insert langsung ke Hasil_Foto (API lain, query manual, dsb), kuota
-- tetap ditegakkan. Batas disamakan dengan aturan bisnis di upload_hasil.php:
-- maks 300MB TOTAL per sesi, dan maks 200 file per sesi (mencegah spam
-- ribuan file kecil yang bikin folder/DB berantakan meski totalnya < 300MB).
CREATE TRIGGER tr_HasilFoto_ValidasiKuota
ON Hasil_Foto
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (
        SELECT 1
        FROM (SELECT DISTINCT ID_Sesi_Foto FROM inserted) i
        WHERE (SELECT ISNULL(SUM(CAST(Ukuran_Bytes AS BIGINT)), 0) FROM Hasil_Foto WHERE ID_Sesi_Foto = i.ID_Sesi_Foto AND Status = 1) > 314572800
           OR (SELECT COUNT(*) FROM Hasil_Foto WHERE ID_Sesi_Foto = i.ID_Sesi_Foto AND Status = 1) > 200
    )
    BEGIN
        RAISERROR('Kuota hasil foto untuk sesi ini terlampaui (maksimal 300 MB atau 200 file per sesi).', 16, 1);
    END
END
GO

-- Hitung total ukuran (byte) file hasil foto aktif untuk 1 sesi. Dipakai
-- ulang di beberapa tempat (validasi kuota, tampilan sisa kuota, laporan)
-- supaya rumusnya konsisten di satu tempat saja.
IF OBJECT_ID('fn_TotalUkuranHasilFoto', 'FN') IS NOT NULL DROP FUNCTION fn_TotalUkuranHasilFoto;
GO
CREATE FUNCTION fn_TotalUkuranHasilFoto(@ID_Sesi_Foto INT)
RETURNS BIGINT
AS
BEGIN
    DECLARE @Total BIGINT;
    SELECT @Total = ISNULL(SUM(CAST(Ukuran_Bytes AS BIGINT)), 0)
    FROM Hasil_Foto WHERE ID_Sesi_Foto = @ID_Sesi_Foto AND Status = 1;
    RETURN @Total;
END
GO

-- Insert 1 baris file hasil foto (dipanggil berulang dari PHP dalam 1
-- transaksi untuk upload banyak file sekaligus). Trigger tr_HasilFoto_
-- ValidasiKuota otomatis menegakkan kuota di setiap panggilan ini.
IF OBJECT_ID('sp_InsertHasilFoto', 'P') IS NOT NULL DROP PROCEDURE sp_InsertHasilFoto;
GO
CREATE PROCEDURE sp_InsertHasilFoto
    @ID_Sesi_Foto INT,
    @Nama_File VARCHAR(255),
    @Tipe_File VARCHAR(10),
    @Ukuran_Bytes BIGINT,
    @Urutan INT,
    @Created_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO Hasil_Foto (ID_Sesi_Foto, Nama_File, Tipe_File, Ukuran_Bytes, Urutan, Created_By, Created_Date)
    VALUES (@ID_Sesi_Foto, @Nama_File, @Tipe_File, @Ukuran_Bytes, @Urutan, @Created_By, GETDATE());

    UPDATE Sesi_Foto
    SET Tanggal_Upload_Hasil = GETDATE(), Modified_By = @Created_By, Modified_Date = GETDATE()
    WHERE ID_Sesi_Foto = @ID_Sesi_Foto;

    SELECT SCOPE_IDENTITY() AS ID_Hasil_Foto;
END
GO

-- Ambil semua file hasil foto aktif untuk 1 sesi (dipakai halaman upload
-- fotografer).
IF OBJECT_ID('sp_ReadHasilFotoBySesi', 'P') IS NOT NULL DROP PROCEDURE sp_ReadHasilFotoBySesi;
GO
CREATE PROCEDURE sp_ReadHasilFotoBySesi
    @ID_Sesi_Foto INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT ID_Hasil_Foto, Nama_File, Tipe_File, Ukuran_Bytes, Urutan, Created_Date
    FROM Hasil_Foto
    WHERE ID_Sesi_Foto = @ID_Sesi_Foto AND Status = 1
    ORDER BY Urutan ASC, Created_Date ASC;
END
GO

-- Soft-delete 1 file hasil foto, tervalidasi kepemilikan lewat fotografer
-- yang menangani sesi tsb. Mengembalikan Nama_File supaya PHP tahu file fisik
-- mana yang harus dihapus dari disk.
IF OBJECT_ID('sp_DeleteHasilFoto', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteHasilFoto;
GO
CREATE PROCEDURE sp_DeleteHasilFoto
    @ID_Hasil_Foto INT,
    @ID_Sesi_Foto INT,
    @ID_Fotografer INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Nama_File VARCHAR(255);

    SELECT @Nama_File = HF.Nama_File
    FROM Hasil_Foto HF
    JOIN Sesi_Foto S ON HF.ID_Sesi_Foto = S.ID_Sesi_Foto
    WHERE HF.ID_Hasil_Foto = @ID_Hasil_Foto
      AND HF.ID_Sesi_Foto = @ID_Sesi_Foto
      AND HF.Status = 1
      AND S.ID_Karyawan = @ID_Fotografer;

    IF @Nama_File IS NULL
    BEGIN
        RAISERROR('File tidak ditemukan atau Anda tidak berhak menghapusnya.', 16, 1);
        RETURN;
    END

    UPDATE Hasil_Foto
    SET Status = 0, Modified_By = @Modified_By, Modified_Date = GETDATE()
    WHERE ID_Hasil_Foto = @ID_Hasil_Foto;

    SELECT @Nama_File AS Nama_File;
END
GO

-- Ambil semua file hasil foto aktif milik 1 Order (bisa lintas sesi kalau
-- order punya beberapa sesi foto), dipakai halaman CUSTOMER untuk lihat &
-- download semua hasil fotonya. Validasi kepemilikan lewat ID_Pelanggan
-- supaya customer tidak bisa mengakses hasil foto milik order orang lain.
IF OBJECT_ID('sp_ReadHasilFotoByOrder', 'P') IS NOT NULL DROP PROCEDURE sp_ReadHasilFotoByOrder;
GO
CREATE PROCEDURE sp_ReadHasilFotoByOrder
    @ID_Order INT,
    @ID_Pelanggan INT
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM [Order] WHERE ID_Order = @ID_Order AND ID_Pelanggan = @ID_Pelanggan AND Status = 1)
    BEGIN
        RETURN; -- Order bukan milik customer ini -> result set kosong, jangan bocorkan data
    END

    SELECT HF.ID_Hasil_Foto, HF.ID_Sesi_Foto, HF.Nama_File, HF.Tipe_File, HF.Ukuran_Bytes, HF.Urutan, HF.Created_Date
    FROM Hasil_Foto HF
    JOIN Sesi_Foto S ON HF.ID_Sesi_Foto = S.ID_Sesi_Foto
    WHERE S.ID_Order = @ID_Order AND HF.Status = 1 AND S.Status = 1
    ORDER BY HF.Urutan ASC, HF.Created_Date ASC;
END
GO

-- Ambil detail 1 sesi foto (termasuk info file hasil) milik fotografer
-- tertentu, untuk halaman upload_hasil.php. Sama seperti SP-SP sebelumnya,
-- pakai CROSS APPLY ke Order_Jadwal + Jadwal_Studio, bukan O.ID_Jadwal
-- langsung (kolom itu tidak ada di [Order]).
IF OBJECT_ID('sp_ReadDetailSesiHasilFotografer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadDetailSesiHasilFotografer;
GO
CREATE PROCEDURE sp_ReadDetailSesiHasilFotografer
    @ID_Sesi_Foto INT,
    @ID_Fotografer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.File_Hasil,
        S.Tanggal_Upload_Hasil,
        S.Status_Sesi,
        S.Waktu_Mulai,
        S.Waktu_Selesai,
        O.Keterangan AS Keterangan_Order,
        P.Nama_Pelanggan,
        P.Email_Pelanggan,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        R.Nama_Ruangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    OUTER APPLY (
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    WHERE S.ID_Sesi_Foto = @ID_Sesi_Foto
      AND S.ID_Karyawan = @ID_Fotografer
      AND S.Status = 1;
END
GO

-- Riwayat upload milik fotografer: SATU BARIS PER SESI (bukan per file),
-- dengan jumlah & total ukuran foto yang sudah diupload untuk sesi itu.
-- Menggantikan pendekatan lama yang asumsi "1 sesi = 1 file" (S.File_Hasil)
-- -- sekarang sesi bisa punya banyak file lewat tabel Hasil_Foto. Sama
-- seperti SP lain, pakai OUTER APPLY ke Order_Jadwal (bukan O.ID_Jadwal
-- langsung, kolom itu tidak ada di [Order]).
IF OBJECT_ID('sp_ReadRiwayatUploadFotografer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadRiwayatUploadFotografer;
GO
CREATE PROCEDURE sp_ReadRiwayatUploadFotografer
    @ID_Fotografer INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.Waktu_Mulai,
        S.Waktu_Selesai,
        S.Tanggal_Upload_Hasil,
        P.Nama_Pelanggan,
        PK.Nama_Paket,
        R.Nama_Ruangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai,
        O.Status_Order,
        HF.Total_Foto,
        HF.Total_Ukuran
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Pelanggan P ON O.ID_Pelanggan = P.ID_Pelanggan
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    OUTER APPLY (
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    CROSS APPLY (
        SELECT COUNT(*) AS Total_Foto, ISNULL(SUM(CAST(Ukuran_Bytes AS BIGINT)), 0) AS Total_Ukuran
        FROM Hasil_Foto WHERE ID_Sesi_Foto = S.ID_Sesi_Foto AND Status = 1
    ) HF
    WHERE S.ID_Karyawan = @ID_Fotografer
      AND S.Status = 1
      AND HF.Total_Foto > 0
    ORDER BY S.Tanggal_Upload_Hasil DESC;
END
GO

-- Ringkasan hasil foto per ORDER untuk halaman Customer "Hasil Foto Saya".
-- SATU BARIS PER SESI (bisa lebih dari 1 kalau order punya banyak sesi),
-- dengan jumlah & total ukuran foto -- menggantikan pendekatan lama yang
-- asumsi "1 sesi = 1 file" (S.File_Hasil). Hanya order yg SUDAH LUNAS
-- (Status_Order = 3) yang muncul, sesuai aturan akses customer. Pakai
-- OUTER APPLY ke Order_Jadwal (bukan O.ID_Jadwal langsung, kolom itu tidak
-- ada di [Order]).
IF OBJECT_ID('sp_ReadHasilFotoRingkasanCustomer', 'P') IS NOT NULL DROP PROCEDURE sp_ReadHasilFotoRingkasanCustomer;
GO
CREATE PROCEDURE sp_ReadHasilFotoRingkasanCustomer
    @ID_Pelanggan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT
        S.ID_Sesi_Foto,
        S.ID_Order,
        S.Tanggal_Upload_Hasil,
        PK.Nama_Paket,
        PK.Durasi_Waktu,
        R.Nama_Ruangan,
        Slot.Tanggal_Jadwal,
        Slot.Jam_Mulai,
        Slot.Jam_Selesai,
        O.Total_Harga,
        HF.Total_Foto,
        HF.Total_Ukuran
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    JOIN Paket_Foto PK ON O.ID_Paket = PK.ID_Paket
    JOIN Ruangan R ON O.ID_Ruangan = R.ID_Ruangan
    OUTER APPLY (
        SELECT TOP 1 J.Tanggal_Jadwal, J.Jam_Mulai, J.Jam_Selesai
        FROM Order_Jadwal OJ
        JOIN Jadwal_Studio J ON OJ.ID_Jadwal = J.ID_Jadwal
        WHERE OJ.ID_Order = O.ID_Order AND J.Status = 1 AND J.Is_Deleted = 0
        ORDER BY J.Tanggal_Jadwal ASC, J.Jam_Mulai ASC
    ) Slot
    CROSS APPLY (
        SELECT COUNT(*) AS Total_Foto, ISNULL(SUM(CAST(Ukuran_Bytes AS BIGINT)), 0) AS Total_Ukuran
        FROM Hasil_Foto WHERE ID_Sesi_Foto = S.ID_Sesi_Foto AND Status = 1
    ) HF
    WHERE O.ID_Pelanggan = @ID_Pelanggan
      AND O.Status = 1
      AND O.Status_Order = 3 -- Lunas
      AND S.Status = 1
      AND HF.Total_Foto > 0
    ORDER BY S.Tanggal_Upload_Hasil DESC;
END
GO

-- Hitung sesi yang SUDAH ada hasil foto tapi order-nya BELUM Lunas.
-- Secara normal ini akan selalu 0 (upload_hasil.php mewajibkan Status_Order
-- = 3 dulu sebelum fotografer bisa upload), tapi tetap dijaga sebagai info
-- defensif kalau ada intervensi manual/perubahan alur di masa depan.
IF OBJECT_ID('sp_HitungHasilFotoMenungguCustomer', 'P') IS NOT NULL DROP PROCEDURE sp_HitungHasilFotoMenungguCustomer;
GO
CREATE PROCEDURE sp_HitungHasilFotoMenungguCustomer
    @ID_Pelanggan INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT COUNT(*) AS Total_Menunggu
    FROM Sesi_Foto S
    JOIN [Order] O ON S.ID_Order = O.ID_Order
    WHERE O.ID_Pelanggan = @ID_Pelanggan
      AND O.Status = 1
      AND O.Status_Order IN (1, 2) -- DP Terverifikasi / Selesai Sesi (belum Lunas)
      AND S.Status = 1
      AND EXISTS (SELECT 1 FROM Hasil_Foto HF WHERE HF.ID_Sesi_Foto = S.ID_Sesi_Foto AND HF.Status = 1);
END
GO


-- ==================== TRANSAKSI 4: PENJUALAN BARANG CETAK ====================
IF OBJECT_ID('sp_CreatePenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_CreatePenjualan;
GO
CREATE PROCEDURE sp_CreatePenjualan
    @ID_Order INT, @ID_Admin INT, @CreatedBy VARCHAR(50)
AS
BEGIN
    INSERT INTO Penjualan (ID_Order, ID_Karyawan_Admin, Total_Penjualan, Status_Penjualan, Created_By)
    VALUES (@ID_Order, @ID_Admin, 0, 0, @CreatedBy);
    SELECT SCOPE_IDENTITY() AS ID_Penjualan;
END
GO

IF OBJECT_ID('sp_ReadPenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_ReadPenjualan;
GO
CREATE PROCEDURE sp_ReadPenjualan @ID INT = NULL
AS
BEGIN
    IF @ID IS NULL
        SELECT * FROM Penjualan WHERE Status = 1;
    ELSE
        SELECT * FROM Penjualan WHERE ID_Penjualan = @ID AND Status = 1;
END
GO

IF OBJECT_ID('sp_UpdatePenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_UpdatePenjualan;
GO
CREATE PROCEDURE sp_UpdatePenjualan
    @ID INT, @StatusPenjualan INT, @ModifiedBy VARCHAR(50)
AS
BEGIN
    UPDATE Penjualan SET Status_Penjualan = @StatusPenjualan, Modified_By = @ModifiedBy, Modified_Date = GETDATE()
    WHERE ID_Penjualan = @ID;
END
GO

IF OBJECT_ID('sp_DeletePenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_DeletePenjualan;
GO
CREATE PROCEDURE sp_DeletePenjualan @ID INT, @DeletedBy VARCHAR(50)
AS
BEGIN
    UPDATE Penjualan SET Status = 0, Modified_By = @DeletedBy, Modified_Date = GETDATE() WHERE ID_Penjualan = @ID;
END
GO

-- =====================================================
-- SP BISNIS: transisi status Penjualan Barang Cetak dengan validasi state
-- dan penyesuaian stok yang benar. Dipakai oleh action.php menggantikan
-- raw UPDATE tanpa transaksi/penyesuaian stok.
-- =====================================================

-- Tandai penjualan SELESAI (barang sudah diambil customer). Hanya boleh dari
-- status Proses (0) pada data yang masih aktif -- mencegah race condition
-- (2 admin klik "selesai" bersamaan) dan mencegah update pada data yang
-- sudah di-soft-delete.
IF OBJECT_ID('sp_TandaiSelesaiPenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_TandaiSelesaiPenjualan;
GO
CREATE PROCEDURE sp_TandaiSelesaiPenjualan
    @ID_Penjualan INT,
    @ID_Admin INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Penjualan WHERE ID_Penjualan = @ID_Penjualan AND Status = 1)
    BEGIN
        RAISERROR('Data penjualan tidak ditemukan atau sudah dihapus.', 16, 1);
        RETURN;
    END
    IF EXISTS (SELECT 1 FROM Penjualan WHERE ID_Penjualan = @ID_Penjualan AND Status_Penjualan <> 0)
    BEGIN
        RAISERROR('Penjualan ini sudah berstatus Selesai, tidak bisa diproses ulang.', 16, 1);
        RETURN;
    END

    UPDATE Penjualan
    SET Status_Penjualan = 1, ID_Karyawan_Admin = @ID_Admin, Modified_By = @Modified_By, Modified_Date = GETDATE()
    WHERE ID_Penjualan = @ID_Penjualan;
END
GO

-- Soft-delete (batalkan) penjualan. Hanya boleh selama masih berstatus
-- Proses (0) -- penjualan yang sudah Selesai/diambil customer tidak boleh
-- dibatalkan dari sini. Stok barang yang sempat dipotong saat penjualan
-- dibuat DIKEMBALIKAN, karena barangnya jadi batal terjual.
IF OBJECT_ID('sp_SoftDeletePenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_SoftDeletePenjualan;
GO
CREATE PROCEDURE sp_SoftDeletePenjualan
    @ID_Penjualan INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Penjualan WHERE ID_Penjualan = @ID_Penjualan AND Status = 1)
    BEGIN
        RAISERROR('Data penjualan tidak ditemukan atau sudah dihapus sebelumnya.', 16, 1);
        RETURN;
    END
    IF EXISTS (SELECT 1 FROM Penjualan WHERE ID_Penjualan = @ID_Penjualan AND Status_Penjualan = 1)
    BEGIN
        RAISERROR('Penjualan yang sudah Selesai tidak dapat dihapus.', 16, 1);
        RETURN;
    END

    BEGIN TRANSACTION;
    BEGIN TRY
        -- Kembalikan stok setiap barang dalam penjualan ini
        UPDATE b
        SET b.Stok_Barang = b.Stok_Barang + d.Jumlah
        FROM Barang_Cetak b
        JOIN Detail_Penjualan_Barang_Cetak d ON b.ID_Barang = d.ID_Barang
        WHERE d.ID_Penjualan = @ID_Penjualan;

        UPDATE Penjualan
        SET Status = 0, Modified_By = @Modified_By, Modified_Date = GETDATE()
        WHERE ID_Penjualan = @ID_Penjualan;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO

-- Pulihkan penjualan yang sebelumnya di-soft-delete. Stok DIPOTONG ULANG
-- (dikembalikan ke kondisi "terjual") -- tapi divalidasi dulu apakah stok
-- saat ini cukup, karena barang yang sama bisa saja sudah terjual ke
-- transaksi lain sejak penjualan ini dihapus. Kalau stok tidak cukup,
-- pemulihan DIBATALKAN (tidak dipaksakan sampai jadi minus).
IF OBJECT_ID('sp_RestorePenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_RestorePenjualan;
GO
CREATE PROCEDURE sp_RestorePenjualan
    @ID_Penjualan INT,
    @Modified_By VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM Penjualan WHERE ID_Penjualan = @ID_Penjualan AND Status = 0)
    BEGIN
        RAISERROR('Data penjualan tidak ditemukan atau memang belum dihapus.', 16, 1);
        RETURN;
    END

    IF EXISTS (
        SELECT 1
        FROM Detail_Penjualan_Barang_Cetak d
        JOIN Barang_Cetak b ON d.ID_Barang = b.ID_Barang
        WHERE d.ID_Penjualan = @ID_Penjualan AND b.Stok_Barang < d.Jumlah
    )
    BEGIN
        RAISERROR('Stok salah satu barang tidak lagi mencukupi untuk memulihkan penjualan ini.', 16, 1);
        RETURN;
    END

    BEGIN TRANSACTION;
    BEGIN TRY
        UPDATE b
        SET b.Stok_Barang = b.Stok_Barang - d.Jumlah
        FROM Barang_Cetak b
        JOIN Detail_Penjualan_Barang_Cetak d ON b.ID_Barang = d.ID_Barang
        WHERE d.ID_Penjualan = @ID_Penjualan;

        UPDATE Penjualan
        SET Status = 1, Modified_By = @Modified_By, Modified_Date = GETDATE()
        WHERE ID_Penjualan = @ID_Penjualan;

        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END
GO
-- ==================== DETAIL TRANSAKSI: PENJUALAN BARANG CETAK ====================
IF OBJECT_ID('sp_InsertDetailPenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_InsertDetailPenjualan;
GO
CREATE PROCEDURE sp_InsertDetailPenjualan
    @ID_Penjualan INT, @ID_Barang INT, @Jumlah INT
AS
BEGIN
    DECLARE @HargaSatuan DECIMAL(12,2);
    DECLARE @StokSisa INT;

    SELECT @HargaSatuan = Harga_Barang, @StokSisa = Stok_Barang 
    FROM Barang_Cetak WHERE ID_Barang = @ID_Barang AND Status = 1;

    IF @StokSisa < @Jumlah
    BEGIN
        RAISERROR('Stok barang cetak tidak mencukupi untuk memproses pesanan!', 16, 1);
        RETURN;
    END

    IF EXISTS (SELECT 1 FROM Detail_Penjualan_Barang_Cetak WHERE ID_Penjualan = @ID_Penjualan AND ID_Barang = @ID_Barang)
    BEGIN
        UPDATE Detail_Penjualan_Barang_Cetak
        SET Jumlah = Jumlah + @Jumlah
        WHERE ID_Penjualan = @ID_Penjualan AND ID_Barang = @ID_Barang;
    END
    ELSE
    BEGIN
        INSERT INTO Detail_Penjualan_Barang_Cetak (ID_Penjualan, ID_Barang, Jumlah, Harga_Satuan)
        VALUES (@ID_Penjualan, @ID_Barang, @Jumlah, @HargaSatuan);
    END
END
GO

IF OBJECT_ID('sp_ReadDetailPenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_ReadDetailPenjualan;
GO
CREATE PROCEDURE sp_ReadDetailPenjualan @ID_Penjualan INT
AS
BEGIN
    SELECT dp.*, bc.Nama_Barang 
    FROM Detail_Penjualan_Barang_Cetak dp
    JOIN Barang_Cetak bc ON dp.ID_Barang = bc.ID_Barang
    WHERE dp.ID_Penjualan = @ID_Penjualan;
END
GO

IF OBJECT_ID('sp_UpdateDetailPenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_UpdateDetailPenjualan;
GO
CREATE PROCEDURE sp_UpdateDetailPenjualan
    @ID_Detail INT, @Jumlah INT
AS
BEGIN
    UPDATE Detail_Penjualan_Barang_Cetak SET Jumlah = @Jumlah WHERE ID_Detail = @ID_Detail;
END
GO

IF OBJECT_ID('sp_DeleteDetailPenjualan', 'P') IS NOT NULL DROP PROCEDURE sp_DeleteDetailPenjualan;
GO
CREATE PROCEDURE sp_DeleteDetailPenjualan @ID_Detail INT
AS
BEGIN
    DELETE FROM Detail_Penjualan_Barang_Cetak WHERE ID_Detail = @ID_Detail;
END
GO


-- ======================================================
-- 7. USER DEFINED FUNCTIONS (UDF) - REPORTING & DASHBOARD (TAHUN 2024, 2025, 2026)
-- ======================================================

-- 1. UDF: Statistik Laporan Pendapatan Studio per Tahun
IF OBJECT_ID('fn_LaporanPendapatanTahunan', 'IF') IS NOT NULL DROP FUNCTION fn_LaporanPendapatanTahunan;
GO
CREATE FUNCTION fn_LaporanPendapatanTahunan
(
    @Tahun INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        MONTH(p.Tanggal_Upload) AS Bulan_Angka,
        DATENAME(MONTH, p.Tanggal_Upload) AS Bulan_Nama,
        SUM(CASE WHEN p.Tipe_Pembayaran = 'DP' THEN p.Jumlah_Bayar ELSE 0 END) AS Total_DP,
        SUM(CASE WHEN p.Tipe_Pembayaran = 'Pelunasan' THEN p.Jumlah_Bayar ELSE 0 END) AS Total_Pelunasan,
        SUM(p.Jumlah_Bayar) AS Total_Pendapatan
    FROM Pembayaran p
    WHERE p.Status_Pembayaran = 1
      AND YEAR(p.Tanggal_Upload) = @Tahun
    GROUP BY MONTH(p.Tanggal_Upload), DATENAME(MONTH, p.Tanggal_Upload)
);
GO

-- 2. UDF: Dashboard Summary Card (Dapat difilter per Tahun)
IF OBJECT_ID('fn_DashboardRingkasanTahunan', 'IF') IS NOT NULL DROP FUNCTION fn_DashboardRingkasanTahunan;
GO
CREATE FUNCTION fn_DashboardRingkasanTahunan
(
    @Tahun INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        (SELECT COUNT(*) FROM [Order] WHERE Status_Order = 0 AND Status = 1 AND YEAR(Tanggal_Booking) = @Tahun) AS Menunggu_DP,
        (SELECT COUNT(*) FROM [Order] WHERE Status_Order = 1 AND Status = 1 AND YEAR(Tanggal_Booking) = @Tahun) AS DP_Selesai,
        (SELECT COUNT(*) FROM [Order] WHERE Status_Order = 3 AND Status = 1 AND YEAR(Tanggal_Booking) = @Tahun) AS Booking_Lunas,
        (SELECT ISNULL(SUM(Total_Harga), 0) FROM [Order] WHERE Status_Order = 3 AND Status = 1 AND YEAR(Tanggal_Booking) = @Tahun) AS Total_Omset,
        (SELECT COUNT(*) FROM [Order] WHERE Status_Order = 4 AND Status = 1 AND YEAR(Tanggal_Booking) = @Tahun) AS Booking_Dibatalkan
);
GO

-- 3. UDF: Kontribusi Omset Paket Foto per Tahun (Chart)
IF OBJECT_ID('fn_PopularitasPaketTahunan', 'IF') IS NOT NULL DROP FUNCTION fn_PopularitasPaketTahunan;
GO
CREATE FUNCTION fn_PopularitasPaketTahunan
(
    @Tahun INT
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        pf.Nama_Paket,
        COUNT(o.ID_Order) AS Total_Booking,
        SUM(o.Total_Harga) AS Total_Kontribusi_Pendapatan
    FROM [Order] o
    JOIN Paket_Foto pf ON o.ID_Paket = pf.ID_Paket
    WHERE o.Status = 1 
      AND o.Status_Order <> 4
      AND YEAR(o.Tanggal_Booking) = @Tahun
    GROUP BY pf.Nama_Paket
);
GO

-- 4. UDF: Detail Booking dengan Penggabungan Jadwal Multi-Slot (Invoicing)
IF OBJECT_ID('fn_DetailOrderLengkap', 'IF') IS NOT NULL DROP FUNCTION fn_DetailOrderLengkap;
GO
CREATE FUNCTION fn_DetailOrderLengkap(@ID_Order INT)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        o.ID_Order,
        o.Tanggal_Booking,
        p.Nama_Pelanggan,
        p.Email_Pelanggan,
        pf.Nama_Paket,
        pf.Harga_Paket,
        r.Nama_Ruangan,
        t.Nama_Tema,
        STRING_AGG(CONVERT(VARCHAR, js.Tanggal_Jadwal, 23) + ' (' + LEFT(CONVERT(VARCHAR, js.Jam_Mulai), 5) + ' - ' + LEFT(CONVERT(VARCHAR, js.Jam_Selesai), 5) + ')', ', ') AS Slot_Waktu_Terpilih,
        o.Total_Paket,
        o.Total_Barang_Cetak,
        o.Total_Harga,
        o.Status_Order,
        o.Rating,
        o.Review
    FROM [Order] o
    JOIN Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
    JOIN Paket_Foto pf ON o.ID_Paket = pf.ID_Paket
    JOIN Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
    JOIN Tema_Foto t ON o.ID_Tema = t.ID_Tema
    JOIN Order_Jadwal oj ON o.ID_Order = oj.ID_Order
    JOIN Jadwal_Studio js ON oj.ID_Jadwal = js.ID_Jadwal
    WHERE o.ID_Order = @ID_Order
    GROUP BY o.ID_Order, o.Tanggal_Booking, p.Nama_Pelanggan, p.Email_Pelanggan, pf.Nama_Paket, pf.Harga_Paket, r.Nama_Ruangan, t.Nama_Tema, o.Total_Paket, o.Total_Barang_Cetak, o.Total_Harga, o.Status_Order, o.Rating, o.Review
);
GO

-- =====================================================
-- PATCH: STORED PROCEDURE LAPORAN PENDAPATAN
-- Tujuan: satu sumber logika buat "pendapatan" -- HANYA dari
-- pembayaran Pelunasan yang sudah Valid (uang penuh sudah diterima).
-- DP TIDAK dihitung sebagai pendapatan final (baru dianggap cash-in
-- parsial, belum revenue yang diakui).
--
-- Dipakai bareng oleh:
--   - Laporan/Pendapatan/index.php (tampilan halaman)
--   - Laporan/Pendapatan/export_pdf.php (nanti)
--   - Laporan/Pendapatan/export_excel.php (nanti)
--
-- PENTING: Status_Order = 3 adalah LUNAS (bukan 2). Status_Order = 2
-- adalah "Selesai Sesi / Menunggu Pelunasan" -- BUKAN pendapatan final.
-- Ini konsisten dengan sp_VerifikasiPembayaran, assign_fotografer.php,
-- dan upload_hasil.php yang sudah dibangun sebelumnya.
-- =====================================================

-- =====================================================
-- sp_LaporanPendapatanSummary: ringkasan angka (total, jumlah order,
-- jumlah pelanggan unik, rata-rata, pendapatan hari ini)
-- =====================================================
IF OBJECT_ID('sp_LaporanPendapatanSummary', 'P') IS NOT NULL
    DROP PROCEDURE sp_LaporanPendapatanSummary;
GO

CREATE PROCEDURE sp_LaporanPendapatanSummary
    @Tgl_Mulai DATE,
    @Tgl_Selesai DATE
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        ISNULL(SUM(p.Jumlah_Bayar), 0) AS Total_Pendapatan,
        COUNT(DISTINCT p.ID_Order) AS Jumlah_Order,
        COUNT(DISTINCT o.ID_Pelanggan) AS Jumlah_Pelanggan,
        CASE WHEN COUNT(DISTINCT p.ID_Order) > 0
             THEN ISNULL(SUM(p.Jumlah_Bayar), 0) / COUNT(DISTINCT p.ID_Order)
             ELSE 0 END AS Rata_Rata,
        (
            SELECT ISNULL(SUM(p2.Jumlah_Bayar), 0)
            FROM Pembayaran p2
            INNER JOIN [Order] o2 ON p2.ID_Order = o2.ID_Order
            WHERE p2.Tipe_Pembayaran = 'Pelunasan'
              AND p2.Status_Pembayaran = 1
              AND p2.Status = 1 AND o2.Status = 1
              AND o2.Status_Order = 3
              AND CAST(p2.Tanggal_Upload AS DATE) = CAST(GETDATE() AS DATE)
        ) AS Pendapatan_Hari_Ini
    FROM Pembayaran p
    INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
    WHERE p.Tipe_Pembayaran = 'Pelunasan'
      AND p.Status_Pembayaran = 1     -- Valid
      AND p.Status = 1 AND o.Status = 1
      AND o.Status_Order = 3          -- LUNAS (bukan 2!)
      AND CAST(p.Tanggal_Upload AS DATE) BETWEEN @Tgl_Mulai AND @Tgl_Selesai;
END
GO

-- =====================================================
-- sp_LaporanPendapatanDetail: daftar transaksi pelunasan valid dalam
-- rentang tanggal, dengan pagination opsional (pass @Limit besar buat
-- ambil semua data sekaligus, misal buat preview/export).
-- =====================================================
IF OBJECT_ID('sp_LaporanPendapatanDetail', 'P') IS NOT NULL
    DROP PROCEDURE sp_LaporanPendapatanDetail;
GO

CREATE PROCEDURE sp_LaporanPendapatanDetail
    @Tgl_Mulai DATE,
    @Tgl_Selesai DATE,
    @Offset INT = 0,
    @Limit INT = 1000000
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        p.ID_Pembayaran,
        p.ID_Order,
        p.Jumlah_Bayar,
        p.Metode_Pembayaran,
        p.Tanggal_Upload,
        pl.Nama_Pelanggan,
        pl.No_Hp,
        pl.Email_Pelanggan,
        o.Status_Order,
        k.Nama_Karyawan AS Nama_Verifikator,
        COUNT(*) OVER() AS Total_Records
    FROM Pembayaran p
    INNER JOIN [Order] o ON p.ID_Order = o.ID_Order
    INNER JOIN Pelanggan pl ON o.ID_Pelanggan = pl.ID_Pelanggan
    LEFT JOIN Karyawan k ON p.ID_Karyawan_Verifikator = k.ID_Karyawan
    WHERE p.Tipe_Pembayaran = 'Pelunasan'
      AND p.Status_Pembayaran = 1     -- Valid
      AND p.Status = 1 AND o.Status = 1
      AND o.Status_Order = 3          -- LUNAS (bukan 2!)
      AND CAST(p.Tanggal_Upload AS DATE) BETWEEN @Tgl_Mulai AND @Tgl_Selesai
    ORDER BY p.Tanggal_Upload DESC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END
GO

-- =====================================================
-- PATCH: STORED PROCEDURE LAPORAN STOK BARANG CETAK
-- SpotLight Studio — Revisi Juli 2026
-- =====================================================

-- =====================================================
-- 1. SP RINGKASAN (Summary Card & Stat Cards)
-- =====================================================
IF OBJECT_ID('sp_LaporanStokBarangSummary', 'P') IS NOT NULL
    DROP PROCEDURE sp_LaporanStokBarangSummary;
GO

CREATE PROCEDURE sp_LaporanStokBarangSummary
    @Tgl_Mulai DATE,
    @Tgl_Selesai DATE
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        (SELECT COUNT(*) FROM Barang_Cetak WHERE Status = 1 AND Is_Deleted = 0) AS Total_Jenis_Barang,
        (SELECT COUNT(*) FROM Barang_Cetak WHERE Status = 1 AND Is_Deleted = 0 AND Stok_Barang <= Stok_Minimum AND Stok_Barang > 0) AS Total_Stok_Menipis,
        (SELECT COUNT(*) FROM Barang_Cetak WHERE Status = 1 AND Is_Deleted = 0 AND Stok_Barang = 0) AS Total_Stok_Habis,
        (SELECT ISNULL(SUM(CAST(Stok_Barang AS BIGINT) * Harga_Barang), 0) FROM Barang_Cetak WHERE Status = 1 AND Is_Deleted = 0) AS Total_Nilai_Aset,
        (SELECT ISNULL(SUM(d.Jumlah), 0) 
         FROM Detail_Penjualan_Barang_Cetak d
         INNER JOIN Penjualan p ON d.ID_Penjualan = p.ID_Penjualan
         WHERE p.Status = 1 AND p.Status_Penjualan = 1
           AND CAST(p.Tanggal_Penjualan AS DATE) BETWEEN @Tgl_Mulai AND @Tgl_Selesai) AS Total_Unit_Terjual,
        (SELECT ISNULL(SUM(d.Subtotal), 0)
         FROM Detail_Penjualan_Barang_Cetak d
         INNER JOIN Penjualan p ON d.ID_Penjualan = p.ID_Penjualan
         WHERE p.Status = 1 AND p.Status_Penjualan = 1
           AND CAST(p.Tanggal_Penjualan AS DATE) BETWEEN @Tgl_Mulai AND @Tgl_Selesai) AS Total_Omzet_Terjual;
END
GO

-- =====================================================
-- 2. SP DETAIL (Tabel + Search + Filter + Sort + Pagination)
-- =====================================================
IF OBJECT_ID('sp_LaporanStokBarangDetail', 'P') IS NOT NULL
    DROP PROCEDURE sp_LaporanStokBarangDetail;
GO

CREATE PROCEDURE sp_LaporanStokBarangDetail
    @Tgl_Mulai DATE,
    @Tgl_Selesai DATE,
    @Search NVARCHAR(100) = NULL,
    @Status_Filter VARCHAR(20) = NULL,
    @Sort_By VARCHAR(50) = 'terjual_desc',
    @Offset INT = 0,
    @Limit INT = 1000000
AS
BEGIN
    SET NOCOUNT ON;

    WITH BarangData AS (
        SELECT
            bc.ID_Barang,
            bc.Nama_Barang,
            bc.Harga_Barang,
            bc.Stok_Barang,
            bc.Stok_Minimum,
            bc.Foto_Barang,
            ISNULL(SUM(CASE WHEN p.Status = 1 AND p.Status_Penjualan = 1 
                            AND CAST(p.Tanggal_Penjualan AS DATE) BETWEEN @Tgl_Mulai AND @Tgl_Selesai 
                       THEN dp.Jumlah ELSE 0 END), 0) AS Total_Terjual,
            ISNULL(SUM(CASE WHEN p.Status = 1 AND p.Status_Penjualan = 1 
                            AND CAST(p.Tanggal_Penjualan AS DATE) BETWEEN @Tgl_Mulai AND @Tgl_Selesai 
                       THEN dp.Subtotal ELSE 0 END), 0) AS Total_Pendapatan,
            (bc.Stok_Barang * bc.Harga_Barang) AS Nilai_Persediaan,
            CASE 
                WHEN bc.Stok_Barang = 0 THEN 'habis'
                WHEN bc.Stok_Barang <= bc.Stok_Minimum THEN 'menipis'
                ELSE 'aman'
            END AS Status_Persediaan
        FROM Barang_Cetak bc
        LEFT JOIN Detail_Penjualan_Barang_Cetak dp ON bc.ID_Barang = dp.ID_Barang
        LEFT JOIN Penjualan p ON dp.ID_Penjualan = p.ID_Penjualan
        WHERE bc.Status = 1 AND bc.Is_Deleted = 0
          AND (@Search IS NULL OR bc.Nama_Barang LIKE '%' + @Search + '%' OR CAST(bc.ID_Barang AS VARCHAR(20)) LIKE '%' + @Search + '%')
          AND (@Status_Filter IS NULL OR 
               (@Status_Filter = 'aman' AND bc.Stok_Barang > bc.Stok_Minimum) OR
               (@Status_Filter = 'menipis' AND bc.Stok_Barang <= bc.Stok_Minimum AND bc.Stok_Barang > 0) OR
               (@Status_Filter = 'habis' AND bc.Stok_Barang = 0))
        GROUP BY bc.ID_Barang, bc.Nama_Barang, bc.Harga_Barang, bc.Stok_Barang, bc.Stok_Minimum, bc.Foto_Barang
    )
    SELECT *, COUNT(*) OVER() AS Total_Records
    FROM BarangData
    ORDER BY
        CASE WHEN @Sort_By = 'stok_desc' THEN Stok_Barang END DESC,
        CASE WHEN @Sort_By = 'stok_asc' THEN Stok_Barang END ASC,
        CASE WHEN @Sort_By = 'terjual_desc' THEN Total_Terjual END DESC,
        CASE WHEN @Sort_By = 'terjual_asc' THEN Total_Terjual END ASC,
        CASE WHEN @Sort_By = 'harga_desc' THEN Harga_Barang END DESC,
        CASE WHEN @Sort_By = 'harga_asc' THEN Harga_Barang END ASC,
        CASE WHEN @Sort_By = 'nama_asc' THEN Nama_Barang END ASC,
        CASE WHEN @Sort_By = 'nama_desc' THEN Nama_Barang END DESC,
        CASE WHEN @Sort_By = 'nilai_desc' THEN Nilai_Persediaan END DESC,
        CASE WHEN @Sort_By = 'nilai_asc' THEN Nilai_Persediaan END ASC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END
GO

-- =====================================================
-- 3. TRIGGER DEFENSIF: CEGAH STOK NEGATIF
-- =====================================================
IF OBJECT_ID('tr_BarangCetak_PreventNegativeStok', 'TR') IS NOT NULL
    DROP TRIGGER tr_BarangCetak_PreventNegativeStok;
GO

CREATE TRIGGER tr_BarangCetak_PreventNegativeStok
ON Barang_Cetak
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (SELECT 1 FROM inserted WHERE Stok_Barang < 0)
    BEGIN
        RAISERROR('Stok barang tidak boleh negatif! Periksa kembali transaksi penjualan.', 16, 1);
        ROLLBACK TRANSACTION;
    END
END
GO

PRINT 'Patch Laporan Stok Barang Cetak berhasil diinstall!';
GO



-- ======================================================
-- 8. TRIGGERS - LOGIKA BISNIS & TRANSAKSI AUDIT TRAIL LOG HISTORY
-- ======================================================

-- a). TRIGGER BISNIS: Deteksi & Cegah Jadwal Bentrok pada multi-booking (Create & Update Order_Jadwal)
IF OBJECT_ID('tr_OrderJadwal_Validate', 'TR') IS NOT NULL DROP TRIGGER tr_OrderJadwal_Validate;
GO
CREATE TRIGGER tr_OrderJadwal_Validate
ON Order_Jadwal
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (
        SELECT 1
        FROM inserted i
        JOIN Order_Jadwal oj ON i.ID_Jadwal = oj.ID_Jadwal AND i.ID_Order <> oj.ID_Order
        JOIN [Order] o ON oj.ID_Order = o.ID_Order
        WHERE o.Status = 1 AND o.Status_Order <> 4
    )
    BEGIN
        RAISERROR('Gagal: Salah satu slot jadwal telah dipesan oleh customer aktif lain!', 16, 1);
        ROLLBACK TRANSACTION;
    END
END
GO

-- b). TRIGGER BISNIS: Detail Penjualan Insert -> Update Stok & Total Penjualan
IF OBJECT_ID('tr_DetailPenjualan_Insert', 'TR') IS NOT NULL DROP TRIGGER tr_DetailPenjualan_Insert;
GO
CREATE TRIGGER tr_DetailPenjualan_Insert
ON Detail_Penjualan_Barang_Cetak
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Kasus INSERT / UPDATE
    IF EXISTS (SELECT 1 FROM inserted)
    BEGIN
        -- Potong Stok
        UPDATE bc
        SET bc.Stok_Barang = bc.Stok_Barang - (i.Jumlah - ISNULL(d.Jumlah, 0))
        FROM Barang_Cetak bc
        JOIN inserted i ON bc.ID_Barang = i.ID_Barang
        LEFT JOIN deleted d ON i.ID_Detail = d.ID_Detail;

        -- Rekalkulasi Total di Penjualan
        UPDATE p
        SET p.Total_Penjualan = (SELECT SUM(Subtotal) FROM Detail_Penjualan_Barang_Cetak WHERE ID_Penjualan = p.ID_Penjualan)
        FROM Penjualan p
        WHERE p.ID_Penjualan IN (SELECT ID_Penjualan FROM inserted);

        -- Sinkronisasi Total Barang Cetak di Order
        UPDATE o
        SET o.Total_Barang_Cetak = p.Total_Penjualan
        FROM [Order] o
        JOIN Penjualan p ON o.ID_Order = p.ID_Order
        WHERE p.ID_Penjualan IN (SELECT ID_Penjualan FROM inserted);
    END
END
GO

-- =====================================================
-- SPOTLIGHT STUDIO - SQL PATCH: LAPORAN PEMBATALAN (REVISED)
-- Fix: Remove Jadwal_Studio JOIN (ID_Jadwal not in [Order] table)
-- =====================================================

-- =====================================================
-- 1. DROP EXISTING
-- =====================================================
IF OBJECT_ID('dbo.sp_LaporanPembatalanSummary', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_LaporanPembatalanSummary;
GO

IF OBJECT_ID('dbo.sp_LaporanPembatalanDetail', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_LaporanPembatalanDetail;
GO

IF OBJECT_ID('dbo.sp_LaporanPembatalanCount', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_LaporanPembatalanCount;
GO

IF OBJECT_ID('dbo.tr_Order_AfterUpdate_LogBatal', 'TR') IS NOT NULL
    DROP TRIGGER dbo.tr_Order_AfterUpdate_LogBatal;
GO

-- =====================================================
-- 2. TABEL LOG PEMBATALAN (opsional, untuk audit trail)
-- =====================================================
IF OBJECT_ID('dbo.Log_Pembatalan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Log_Pembatalan (
        ID_Log          INT IDENTITY(1,1) PRIMARY KEY,
        ID_Order        INT NOT NULL,
        Alasan_Batal    VARCHAR(50) NOT NULL,
        Keterangan      NVARCHAR(500) NULL,
        Dibuat_Oleh     VARCHAR(100) NULL,
        Tanggal_Log     DATETIME DEFAULT GETDATE()
    );
END
GO

-- =====================================================
-- 3. TRIGGER: Log otomatis saat Order dibatalkan
-- =====================================================
CREATE TRIGGER dbo.tr_Order_AfterUpdate_LogBatal
ON dbo.[Order]
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.Log_Pembatalan (ID_Order, Alasan_Batal, Keterangan, Dibuat_Oleh, Tanggal_Log)
    SELECT 
        i.ID_Order,
        CASE 
            WHEN i.Status_Order = 4 AND NOT EXISTS (
                SELECT 1 FROM dbo.Pembayaran p 
                WHERE p.ID_Order = i.ID_Order AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1
            ) THEN 'Belum Bayar DP'
            WHEN i.Status_Order = 4 AND EXISTS (
                SELECT 1 FROM dbo.Pembayaran p 
                WHERE p.ID_Order = i.ID_Order AND p.Tipe_Pembayaran = 'DP' 
                AND p.Status = 1 AND p.Status_Pembayaran = 2
            ) THEN 'DP Ditolak'
            WHEN i.Status_Order = 4 AND EXISTS (
                SELECT 1 FROM dbo.Pembayaran p 
                WHERE p.ID_Order = i.ID_Order AND p.Tipe_Pembayaran = 'DP' 
                AND p.Status = 1 AND p.Status_Pembayaran = 1
            ) THEN 'Dibatalkan Pelanggan'
            ELSE 'Dibatalkan Sistem'
        END,
        i.Keterangan,
        'System Trigger',
        GETDATE()
    FROM inserted i
    INNER JOIN deleted d ON i.ID_Order = d.ID_Order
    WHERE i.Status_Order = 4 AND d.Status_Order != 4
      AND i.Status = 1;
END
GO

-- =====================================================
-- 4. STORED PROCEDURE: SUMMARY
-- =====================================================
CREATE PROCEDURE dbo.sp_LaporanPembatalanSummary
    @TglMulai   DATE,
    @TglSelesai DATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Total Booking Batal
    SELECT COUNT(DISTINCT o.ID_Order) AS Total_Batal
    FROM dbo.[Order] o
    WHERE o.Status = 1 
      AND o.Status_Order = 4
      AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai;

    -- Belum Bayar DP
    SELECT COUNT(DISTINCT o.ID_Order) AS Total_BelumBayarDP
    FROM dbo.[Order] o
    WHERE o.Status = 1 
      AND o.Status_Order = 4
      AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai
      AND NOT EXISTS (
          SELECT 1 FROM dbo.Pembayaran p 
          WHERE p.ID_Order = o.ID_Order AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1
      );

    -- DP Ditolak
    SELECT COUNT(DISTINCT o.ID_Order) AS Total_DPDitolak
    FROM dbo.[Order] o
    INNER JOIN dbo.Pembayaran p ON o.ID_Order = p.ID_Order 
        AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1 AND p.Status_Pembayaran = 2
    WHERE o.Status = 1 
      AND o.Status_Order = 4
      AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai;

    -- Dibatalkan Pelanggan
    SELECT COUNT(DISTINCT o.ID_Order) AS Total_DibatalkanPelanggan
    FROM dbo.[Order] o
    INNER JOIN dbo.Pembayaran p ON o.ID_Order = p.ID_Order 
        AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1 AND p.Status_Pembayaran = 1
    WHERE o.Status = 1 
      AND o.Status_Order = 4
      AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai;

    -- Dibatalkan Sistem
    SELECT COUNT(DISTINCT o.ID_Order) AS Total_DibatalkanSistem
    FROM dbo.[Order] o
    WHERE o.Status = 1 
      AND o.Status_Order = 4
      AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai
      AND EXISTS (
          SELECT 1 FROM dbo.Pembayaran p 
          WHERE p.ID_Order = o.ID_Order AND p.Tipe_Pembayaran = 'DP' AND p.Status = 1
      )
      AND NOT EXISTS (
          SELECT 1 FROM dbo.Pembayaran p 
          WHERE p.ID_Order = o.ID_Order AND p.Tipe_Pembayaran = 'DP' 
          AND p.Status = 1 AND p.Status_Pembayaran IN (1, 2)
      );
END
GO

-- =====================================================
-- 5. STORED PROCEDURE: DETAIL (NO Jadwal_Studio JOIN)
-- =====================================================
CREATE PROCEDURE dbo.sp_LaporanPembatalanDetail
    @TglMulai       DATE,
    @TglSelesai     DATE,
    @Search         NVARCHAR(100) = '',
    @AlasanFilter   VARCHAR(30) = '',
    @SortBy         VARCHAR(30) = 'terbaru',
    @Offset         INT = 0,
    @Limit          INT = 10
AS
BEGIN
    SET NOCOUNT ON;

    WITH CTE_Batal AS (
        SELECT 
            o.ID_Order,
            o.Tanggal_Booking,
            o.Total_Harga,
            o.Status_Order,
            o.Keterangan AS Keterangan_Order,
            p.ID_Pelanggan,
            p.Nama_Pelanggan,
            p.No_Hp,
            pk.Nama_Paket,
            r.Nama_Ruangan,
            t.Nama_Tema,
            pb.ID_Pembayaran,
            pb.Jumlah_Bayar,
            pb.Metode_Pembayaran,
            pb.Bukti_Transfer,
            pb.Status_Pembayaran,
            pb.Tanggal_Upload,
            k.Nama_Karyawan AS Nama_Verifikator,
            CASE 
                WHEN NOT EXISTS (
                    SELECT 1 FROM dbo.Pembayaran px 
                    WHERE px.ID_Order = o.ID_Order AND px.Tipe_Pembayaran = 'DP' AND px.Status = 1
                ) THEN 'Belum Bayar DP'
                WHEN EXISTS (
                    SELECT 1 FROM dbo.Pembayaran px 
                    WHERE px.ID_Order = o.ID_Order AND px.Tipe_Pembayaran = 'DP' 
                    AND px.Status = 1 AND px.Status_Pembayaran = 2
                ) THEN 'DP Ditolak'
                WHEN EXISTS (
                    SELECT 1 FROM dbo.Pembayaran px 
                    WHERE px.ID_Order = o.ID_Order AND px.Tipe_Pembayaran = 'DP' 
                    AND px.Status = 1 AND px.Status_Pembayaran = 1
                ) THEN 'Dibatalkan Pelanggan'
                ELSE 'Dibatalkan Sistem'
            END AS Alasan_Batal
        FROM dbo.[Order] o
        INNER JOIN dbo.Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
        INNER JOIN dbo.Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
        INNER JOIN dbo.Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
        INNER JOIN dbo.Tema_Foto t ON o.ID_Tema = t.ID_Tema
        LEFT JOIN dbo.Pembayaran pb ON o.ID_Order = pb.ID_Order 
            AND pb.Tipe_Pembayaran = 'DP' AND pb.Status = 1
        LEFT JOIN dbo.Karyawan k ON pb.ID_Karyawan_Verifikator = k.ID_Karyawan
        WHERE o.Status = 1 
          AND o.Status_Order = 4
          AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai
    )
    SELECT * FROM CTE_Batal
    WHERE 
        (@Search = '' OR 
         Nama_Pelanggan LIKE '%' + @Search + '%' OR
         CAST(ID_Order AS VARCHAR) LIKE '%' + @Search + '%' OR
         No_Hp LIKE '%' + @Search + '%' OR
         Nama_Paket LIKE '%' + @Search + '%'
        )
        AND (@AlasanFilter = '' OR 
             (@AlasanFilter = 'belum_bayar_dp' AND Alasan_Batal = 'Belum Bayar DP') OR
             (@AlasanFilter = 'dp_ditolak' AND Alasan_Batal = 'DP Ditolak') OR
             (@AlasanFilter = 'dibatalkan_pelanggan' AND Alasan_Batal = 'Dibatalkan Pelanggan') OR
             (@AlasanFilter = 'dibatalkan_sistem' AND Alasan_Batal = 'Dibatalkan Sistem')
        )
    ORDER BY
        CASE WHEN @SortBy = 'terbaru' THEN Tanggal_Booking END DESC,
        CASE WHEN @SortBy = 'terlama' THEN Tanggal_Booking END ASC,
        CASE WHEN @SortBy = 'nama_asc' THEN Nama_Pelanggan END ASC,
        CASE WHEN @SortBy = 'nama_desc' THEN Nama_Pelanggan END DESC,
        CASE WHEN @SortBy = 'harga_tertinggi' THEN Total_Harga END DESC,
        CASE WHEN @SortBy = 'harga_terendah' THEN Total_Harga END ASC,
        CASE WHEN @SortBy = 'jadwal_terdekat' THEN Tanggal_Booking END ASC,
        CASE WHEN @SortBy = 'jadwal_terjauh' THEN Tanggal_Booking END DESC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END
GO

-- =====================================================
-- 6. STORED PROCEDURE: COUNT TOTAL (NO Jadwal_Studio)
-- =====================================================
CREATE PROCEDURE dbo.sp_LaporanPembatalanCount
    @TglMulai       DATE,
    @TglSelesai     DATE,
    @Search         NVARCHAR(100) = '',
    @AlasanFilter   VARCHAR(30) = ''
AS
BEGIN
    SET NOCOUNT ON;

    WITH CTE_Batal AS (
        SELECT 
            o.ID_Order,
            p.Nama_Pelanggan,
            p.No_Hp,
            pk.Nama_Paket,
            CASE 
                WHEN NOT EXISTS (
                    SELECT 1 FROM dbo.Pembayaran px 
                    WHERE px.ID_Order = o.ID_Order AND px.Tipe_Pembayaran = 'DP' AND px.Status = 1
                ) THEN 'Belum Bayar DP'
                WHEN EXISTS (
                    SELECT 1 FROM dbo.Pembayaran px 
                    WHERE px.ID_Order = o.ID_Order AND px.Tipe_Pembayaran = 'DP' 
                    AND px.Status = 1 AND px.Status_Pembayaran = 2
                ) THEN 'DP Ditolak'
                WHEN EXISTS (
                    SELECT 1 FROM dbo.Pembayaran px 
                    WHERE px.ID_Order = o.ID_Order AND px.Tipe_Pembayaran = 'DP' 
                    AND px.Status = 1 AND px.Status_Pembayaran = 1
                ) THEN 'Dibatalkan Pelanggan'
                ELSE 'Dibatalkan Sistem'
            END AS Alasan_Batal
        FROM dbo.[Order] o
        INNER JOIN dbo.Pelanggan p ON o.ID_Pelanggan = p.ID_Pelanggan
        INNER JOIN dbo.Paket_Foto pk ON o.ID_Paket = pk.ID_Paket
        INNER JOIN dbo.Ruangan r ON o.ID_Ruangan = r.ID_Ruangan
        INNER JOIN dbo.Tema_Foto t ON o.ID_Tema = t.ID_Tema
        LEFT JOIN dbo.Pembayaran pb ON o.ID_Order = pb.ID_Order 
            AND pb.Tipe_Pembayaran = 'DP' AND pb.Status = 1
        WHERE o.Status = 1 
          AND o.Status_Order = 4
          AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai
    )
    SELECT COUNT(*) AS TotalRecords
    FROM CTE_Batal
    WHERE 
        (@Search = '' OR 
         Nama_Pelanggan LIKE '%' + @Search + '%' OR
         CAST(ID_Order AS VARCHAR) LIKE '%' + @Search + '%' OR
         No_Hp LIKE '%' + @Search + '%' OR
         Nama_Paket LIKE '%' + @Search + '%'
        )
        AND (@AlasanFilter = '' OR 
             (@AlasanFilter = 'belum_bayar_dp' AND Alasan_Batal = 'Belum Bayar DP') OR
             (@AlasanFilter = 'dp_ditolak' AND Alasan_Batal = 'DP Ditolak') OR
             (@AlasanFilter = 'dibatalkan_pelanggan' AND Alasan_Batal = 'Dibatalkan Pelanggan') OR
             (@AlasanFilter = 'dibatalkan_sistem' AND Alasan_Batal = 'Dibatalkan Sistem')
        );
END
GO

-- c). TRIGGER AUDIT LOG: Log History Otomatis untuk Transaksi Order
IF OBJECT_ID('tr_Log_Order', 'TR') IS NOT NULL DROP TRIGGER tr_Log_Order;
GO
CREATE TRIGGER tr_Log_Order
ON [Order]
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS(SELECT 1 FROM inserted) AND NOT EXISTS(SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, Executed_By)
        SELECT 'Order', CAST(ID_Order AS VARCHAR(50)), 'INSERT', NULL, 'Pemesanan dibuat untuk Pelanggan ID: ' + CAST(ID_Pelanggan AS VARCHAR(10)), Created_By
        FROM inserted;
    END
    ELSE IF EXISTS(SELECT 1 FROM inserted) AND EXISTS(SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, Executed_By)
        SELECT 'Order', CAST(i.ID_Order AS VARCHAR(50)), 'UPDATE', 
               'Status Lama: ' + CAST(d.Status_Order AS VARCHAR(5)), 
               'Status Baru: ' + CAST(i.Status_Order AS VARCHAR(5)), 
               ISNULL(i.Modified_By, 'system')
        FROM inserted i
        JOIN deleted d ON i.ID_Order = d.ID_Order;
    END
END
GO

-- d). TRIGGER AUDIT LOG: Log History Otomatis untuk Transaksi Pembayaran
IF OBJECT_ID('tr_Log_Pembayaran', 'TR') IS NOT NULL DROP TRIGGER tr_Log_Pembayaran;
GO
CREATE TRIGGER tr_Log_Pembayaran
ON Pembayaran
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS(SELECT 1 FROM inserted) AND NOT EXISTS(SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, Executed_By)
        SELECT 'Pembayaran', CAST(ID_Pembayaran AS VARCHAR(50)), 'INSERT', NULL, 'Bukti ' + Tipe_Pembayaran + ' diunggah sebesar Rp. ' + CAST(Jumlah_Bayar AS VARCHAR(20)), Created_By
        FROM inserted;
    END
    ELSE IF EXISTS(SELECT 1 FROM inserted) AND EXISTS(SELECT 1 FROM deleted)
    BEGIN
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, Executed_By)
        SELECT 'Pembayaran', CAST(i.ID_Pembayaran AS VARCHAR(50)), 'UPDATE', 
               'Verifikasi Lama: ' + CAST(d.Status_Pembayaran AS VARCHAR(5)), 
               'Verifikasi Baru: ' + CAST(i.Status_Pembayaran AS VARCHAR(5)), 
               ISNULL(i.Modified_By, 'system')
        FROM inserted i
        JOIN deleted d ON i.ID_Pembayaran = d.ID_Pembayaran;
    END
END
GO


-- =====================================================
-- SPOTLIGHT STUDIO - SQL PATCH: LAPORAN PAKET TERFAVORIT
-- Fokus: Best Seller Paket Foto (Tanpa Omzet)
-- Tanggal: 19 Juli 2026
-- =====================================================

-- Drop existing
IF OBJECT_ID('dbo.sp_LaporanPaketTerfavoritSummary', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_LaporanPaketTerfavoritSummary;
GO

IF OBJECT_ID('dbo.sp_LaporanPaketTerfavoritDetail', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_LaporanPaketTerfavoritDetail;
GO

IF OBJECT_ID('dbo.sp_LaporanPaketTerfavoritCount', 'P') IS NOT NULL
    DROP PROCEDURE dbo.sp_LaporanPaketTerfavoritCount;
GO

-- =====================================================
-- 1. SP SUMMARY
-- =====================================================
CREATE PROCEDURE dbo.sp_LaporanPaketTerfavoritSummary
    @TglMulai DATE,
    @TglSelesai DATE
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @TotalPaket INT, @TotalBooking INT;
    DECLARE @BestSeller VARCHAR(100), @BestSellerBooking INT;
    DECLARE @RatingTertinggi VARCHAR(100), @RatingNilai DECIMAL(3,2);

    -- Total Paket Aktif
    SELECT @TotalPaket = COUNT(*) 
    FROM Paket_Foto 
    WHERE Status = 1 AND Is_Deleted = 0;

    -- Total Booking (bukan batal) dalam periode
    SELECT @TotalBooking = COUNT(*) 
    FROM [Order] 
    WHERE Status = 1 AND Status_Order <> 4
      AND CAST(Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai;

    -- Best Seller
    SELECT TOP 1 @BestSeller = pk.Nama_Paket, @BestSellerBooking = COUNT(o.ID_Order)
    FROM Paket_Foto pk
    LEFT JOIN [Order] o ON pk.ID_Paket = o.ID_Paket 
        AND o.Status = 1 AND o.Status_Order <> 4
        AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai
    WHERE pk.Status = 1 AND pk.Is_Deleted = 0
    GROUP BY pk.ID_Paket, pk.Nama_Paket
    ORDER BY COUNT(o.ID_Order) DESC;

    -- Rating Tertinggi
    SELECT TOP 1 @RatingTertinggi = pk.Nama_Paket, @RatingNilai = AVG(CAST(o.Rating AS DECIMAL(3,2)))
    FROM Paket_Foto pk
    LEFT JOIN [Order] o ON pk.ID_Paket = o.ID_Paket 
        AND o.Status = 1 AND o.Rating IS NOT NULL
        AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai
    WHERE pk.Status = 1 AND pk.Is_Deleted = 0
    GROUP BY pk.ID_Paket, pk.Nama_Paket
    HAVING COUNT(o.Rating) > 0
    ORDER BY AVG(CAST(o.Rating AS DECIMAL(3,2))) DESC;

    SELECT 
        ISNULL(@TotalPaket, 0) AS Total_Paket_Aktif,
        ISNULL(@TotalBooking, 0) AS Total_Booking,
        ISNULL(@BestSeller, 'Belum ada data') AS Best_Seller,
        ISNULL(@BestSellerBooking, 0) AS Best_Seller_Booking,
        ISNULL(@RatingTertinggi, 'Belum dinilai') AS Rating_Tertinggi,
        ISNULL(@RatingNilai, 0) AS Rating_Nilai;
END
GO

-- =====================================================
-- 2. SP DETAIL
-- =====================================================
CREATE PROCEDURE dbo.sp_LaporanPaketTerfavoritDetail
    @TglMulai DATE,
    @TglSelesai DATE,
    @Search NVARCHAR(100) = '',
    @SortBy VARCHAR(30) = 'booking_desc',
    @Offset INT = 0,
    @Limit INT = 10
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @TotalBooking INT;
    SELECT @TotalBooking = COUNT(*) 
    FROM [Order] 
    WHERE Status = 1 AND Status_Order <> 4
      AND CAST(Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai;

    WITH PaketData AS (
        SELECT 
            pk.ID_Paket,
            pk.Nama_Paket,
            pk.Durasi_Waktu,
            pk.Kapasitas_Orang,
            pk.Harga_Paket,
            COUNT(o.ID_Order) AS Jumlah_Booking,
            COUNT(CASE WHEN o.Status_Order = 4 THEN 1 END) AS Jumlah_Batal,
            AVG(CAST(o.Rating AS DECIMAL(3,2))) AS Rata_Rata_Rating,
            @TotalBooking AS Total_Seluruh_Booking
        FROM Paket_Foto pk
        LEFT JOIN [Order] o ON pk.ID_Paket = o.ID_Paket 
            AND o.Status = 1 
            AND CAST(o.Tanggal_Booking AS DATE) BETWEEN @TglMulai AND @TglSelesai
        WHERE pk.Status = 1 AND pk.Is_Deleted = 0
          AND (@Search = '' OR 
               pk.Nama_Paket LIKE '%' + @Search + '%' OR
               CAST(pk.ID_Paket AS VARCHAR(20)) LIKE '%' + @Search + '%')
        GROUP BY pk.ID_Paket, pk.Nama_Paket, pk.Durasi_Waktu, pk.Kapasitas_Orang, pk.Harga_Paket
    )
    SELECT *, COUNT(*) OVER() AS Total_Records
    FROM PaketData
    ORDER BY
        CASE WHEN @SortBy = 'booking_desc' THEN Jumlah_Booking END DESC,
        CASE WHEN @SortBy = 'booking_asc' THEN Jumlah_Booking END ASC,
        CASE WHEN @SortBy = 'nama_asc' THEN Nama_Paket END ASC,
        CASE WHEN @SortBy = 'nama_desc' THEN Nama_Paket END DESC,
        CASE WHEN @SortBy = 'harga_desc' THEN Harga_Paket END DESC,
        CASE WHEN @SortBy = 'harga_asc' THEN Harga_Paket END ASC,
        CASE WHEN @SortBy = 'rating_desc' THEN Rata_Rata_Rating END DESC,
        CASE WHEN @SortBy = 'rating_asc' THEN Rata_Rata_Rating END ASC,
        CASE WHEN @SortBy = 'batal_desc' THEN Jumlah_Batal END DESC,
        CASE WHEN @SortBy = 'batal_asc' THEN Jumlah_Batal END ASC
    OFFSET @Offset ROWS FETCH NEXT @Limit ROWS ONLY;
END
GO

-- =====================================================
-- 3. SP COUNT
-- =====================================================
CREATE PROCEDURE dbo.sp_LaporanPaketTerfavoritCount
    @TglMulai DATE,
    @TglSelesai DATE,
    @Search NVARCHAR(100) = ''
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS TotalRecords
    FROM Paket_Foto pk
    WHERE pk.Status = 1 AND pk.Is_Deleted = 0
      AND (@Search = '' OR 
           pk.Nama_Paket LIKE '%' + @Search + '%' OR
           CAST(pk.ID_Paket AS VARCHAR(20)) LIKE '%' + @Search + '%');
END
GO

PRINT 'Patch Laporan Paket Terfavorit berhasil diinstall!';
GO


-- =====================================================
-- 9. PENGISIAN DATA DUMMY TAHUN 2024, 2025, & 2026
-- =====================================================

-- PENGISIAN MASTER KARYAWAN
INSERT INTO Karyawan (NIK, Nama_Karyawan, Username_Karyawan, Email_Karyawan, Password_Karyawan, Jenis_Kelamin, Tanggal_Lahir, Role_Karyawan, No_Hp, Alamat, Foto_Profil, Status) VALUES
('NIK202601', 'Fikri Sunanta', 'fikri_admin', 'admin@spotlight.com', '12345!@as', 'Laki-laki', '2006-01-31', 'Admin', '+6287871438459', 'Cikarang Pusat', 'default.jpg', 1),
('NIK202602', 'Elvina Paramita', 'elvina_owner', 'owner@spotlight.com', '12345!@as', 'Perempuan', '1990-11-20', 'Owner', '+6281111111112', 'Jakarta', 'default.jpg', 1),
('NIK202603', 'Radin Satya', 'radin_admin', 'admin1@spotlight.com', '12345!@as', 'Laki-laki', '2001-03-12', 'Admin', '+6281111111113', 'Bekasi', 'default.jpg', 1),
('NIK202604', 'Aliifah Nada', 'aliifah_admin', 'admin2@spotlight.com', '12345!@as', 'Perempuan', '2004-07-25', 'Admin', '+6281111111114', 'Depok', 'default.jpg', 1),
('NIK202605', 'Ilfaj Al Nur', 'ilfaj_foto', 'foto3@spotlight.com', '12345!@as', 'Laki-laki', '1999-09-05', 'Fotografer', '+6281111111115', 'Bogor', 'default.jpg', 1);
GO

-- PENGISIAN MASTER PELANGGAN
INSERT INTO Pelanggan (Nama_Pelanggan, Username_Pelanggan, Email_Pelanggan, Password_Pelanggan, Jenis_Kelamin, Tanggal_Lahir, No_Hp, Alamat, Foto_Profil, Status) VALUES
('Bintang Basev', 'bintang_basev', 'customer1@gmail.com', '12345!@as', 'Laki-laki', '2005-04-12', '+6282111111111', 'Bekasi', 'default.jpg', 1),
('Elisa Larasati', 'elisa_larasati', 'customer2@gmail.com', '12345!@as', 'Perempuan', '2012-08-18', '+6282111111112', 'Jakarta', 'default.jpg', 1),
('Amar Faiz', 'amar_faiz', 'customer3@gmail.com', '12345!@as', 'Laki-laki', '1995-12-01', '+6282111111113', 'Depok', 'default.jpg', 1),
('Nabila Tul', 'nabila_tul', 'customer4@gmail.com', '12345!@as', 'Perempuan', '2000-02-14', '+6282111111114', 'Bogor', 'default.jpg', 1),
('Thoriq Al', 'thoriq_al', 'customer5@gmail.com', '12345!@as', 'Laki-laki', '2015-06-30', '+6282111111115', 'Bekasi', 'default.jpg', 1);
GO

-- PENGISIAN MASTER PAKET FOTO (Bahasa Indonesia)
INSERT INTO Paket_Foto (Nama_Paket, Durasi_Waktu, Harga_Paket, Deskripsi, Kapasitas_Orang, Foto_Paket, Status, Created_By) VALUES
('Paket Mandiri', 30, 200000, 'Sesi foto personal atau kasual singkat', 1, 'mandiri.jpg', 1, 'admin'),
('Paket Romantis', 60, 350000, 'Sesi foto pasangan dengan konsep romantis', 2, 'romantis.jpg', 1, 'admin'),
('Paket Keluarga', 90, 600000, 'Sesi foto keluarga inti/besar', 8, 'keluarga.jpg', 1, 'admin'),
('Paket Wisuda', 60, 450000, 'Sesi kelulusan individu atau grup kecil', 5, 'wisuda.jpg', 1, 'admin'),
('Paket Grup', 120, 1000000, 'Sesi foto korporat, komunitas, atau tim kerja', 20, 'grup.jpg', 1, 'admin');
GO

-- PENGISIAN MASTER RUANGAN
INSERT INTO Ruangan (Nama_Ruangan, Deskripsi, Foto_Ruangan, Status, Created_By) VALUES
('Studio Minimalis', 'Ruangan bernuansa bersih dengan warna pastel lembut', 'studio_minimalis.jpg', 1, 'admin'),
('Studio Cinta', 'Dilengkapi dengan dekorasi bunga dan suasana hangat', 'studio_cinta.jpg', 1, 'admin'),
('Studio Kehangatan', 'Area luas dengan perabot rumah tangga bernuansa kekeluargaan', 'studio_kehangatan.jpg', 1, 'admin'),
('Studio Prestasi', 'Latar formal khusus wisuda lengkap dengan rak buku', 'studio_prestasi.jpg', 1, 'admin'),
('Studio Sinergi', 'Suasana rapat profesional yang formal dan berkelas', 'studio_sinergi.jpg', 1, 'admin');
GO

-- PENGISIAN HUBUNGAN MANY-TO-MANY (PAKET_RUANGAN)
-- Menghubungkan ruangan fisik ke berbagai paket foto yang relevan
INSERT INTO Paket_Ruangan (ID_Paket, ID_Ruangan) VALUES
(1, 1), -- Paket Mandiri -> Studio Minimalis
(2, 2), -- Paket Romantis -> Studio Cinta
(3, 3), -- Paket Keluarga -> Studio Kehangatan
(4, 4), -- Paket Wisuda -> Studio Prestasi
(5, 5), -- Paket Grup -> Studio Sinergi
-- Relasi Bisnis Nyata Tambahan (1 ruangan bisa digunakan untuk beberapa paket setting serupa):
(2, 1), -- Paket Romantis -> Studio Minimalis
(4, 1), -- Paket Wisuda -> Studio Minimalis
(1, 3); -- Paket Mandiri -> Studio Kehangatan
GO

-- PENGISIAN MASTER TEMA FOTO
INSERT INTO Tema_Foto (Nama_Tema, Kategori_Tema, Deskripsi, Foto_Tema, Status, Created_By) VALUES
('Kasual Modern', 'Dalam Ruangan', 'Gaya santai masa kini dengan warna cerah', 'tema_kasual.jpg', 1, 'admin'),
('Cinta Sejati', 'Konsep Khusus', 'Konsep pasangan romantis dan intim', 'tema_cinta.jpg', 1, 'admin'),
('Harmoni Keluarga', 'Konsep Khusus', 'Konsep keluarga hangat bercengkrama bersama', 'tema_keluarga.jpg', 1, 'admin'),
('Kebaya & Toga', 'Konsep Khusus', 'Konsep kelulusan formal tradisional', 'tema_wisuda.jpg', 1, 'admin'),
('Profil Korporat', 'Profesional', 'Konsep bisnis formal untuk personal branding', 'tema_corporate.jpg', 1, 'admin'),
('Klasik Formal', 'Dalam Ruangan', 'Konsep formal dengan latar polos elegan', 'tema_formal.jpg', 1, 'admin');
GO

-- RUANGAN_TEMA MAP
INSERT INTO Ruangan_Tema (ID_Ruangan, ID_Tema) VALUES
(1, 1), (1, 6),
(2, 1), (2, 2),
(3, 3), (3, 6),
(4, 4), (4, 6),
(5, 5), (5, 6);
GO

-- MASTER PROPERTI
INSERT INTO Properti (ID_Ruangan, Nama_Properti, Kategori_Properti, Deskripsi, Foto_Properti, Status, Created_By) VALUES
(1, 'Kursi Kayu Estetik', 'Mebel', 'Kursi kayu polos gaya skandinavia', 'kursi_kayu.jpg', 1, 'admin'),
(1, 'Tanaman Hias Kering', 'Dekorasi', 'Tanaman kering untuk hiasan sudut ruangan', 'tanaman_kering.jpg', 1, 'admin'),
(2, 'Sofa Merah Muda', 'Mebel', 'Sofa beludru lembut untuk berpasangan', 'sofa_pink.jpg', 1, 'admin'),
(2, 'Bunga Mawar Replika', 'Dekorasi', 'Bunga mawar imitasi berkualitas premium', 'bunga_replika.jpg', 1, 'admin'),
(3, 'Sofa Keluarga Besar', 'Mebel', 'Sofa luas kapasitas hingga 6 orang', 'sofa_besar.jpg', 1, 'admin'),
(3, 'Karpet Bulu Tebal', 'Dekorasi', 'Karpet tebal untuk duduk bersantai di lantai', 'karpet_bulu.jpg', 1, 'admin'),
(4, 'Rak Buku Klasik', 'Mebel', 'Rak buku kayu besar sebagai latar belakang formal', 'rak_buku.jpg', 1, 'admin'),
(4, 'Bunga Tangan Kelulusan', 'Dekorasi', 'Buket bunga imitasi berwarna wisuda', 'buket_wisuda.jpg', 1, 'admin'),
(5, 'Meja Rapat Oval', 'Mebel', 'Meja kayu kokoh untuk foto ber-grup', 'meja_rapat.jpg', 1, 'admin'),
(5, 'Kursi Direktur Kulit', 'Mebel', 'Kursi kulit hitam eksklusif', 'kursi_direktur.jpg', 1, 'admin');
GO

-- MASTER BARANG CETAK
INSERT INTO Barang_Cetak (Nama_Barang, Deskripsi, Harga_Barang, Stok_Barang, Stok_Minimum, Foto_Barang, Status, Created_By) VALUES
('Cetak Foto Ukuran 4R', 'Cetak cetak foto kertas mengkilap 4R', 10000, 200, 10, 'cetak_4r.jpg', 1, 'admin'),
('Cetak Foto Ukuran 8R', 'Cetak cetak foto kertas mengkilap 8R', 20000, 150, 10, 'cetak_8r.jpg', 1, 'admin'),
('Album Foto Eksklusif', 'Album binder hardcover premium isi 20 halaman', 100000, 50, 5, 'album_foto.jpg', 1, 'admin'),
('Bingkai Foto Kayu Minimalis', 'Bingkai kayu minimalis ukuran 8R', 50000, 60, 5, 'bingkai_foto.jpg', 1, 'admin'),
('Photobook Kustom', 'Buku foto cetak kustom ukuran 20x20 cm', 200000, 30, 3, 'photobook.jpg', 1, 'admin');
GO

-- MASTER JADWAL STUDIO (TAHUN 2024, 2025, 2026)
-- Tahun 2024
INSERT INTO Jadwal_Studio (ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Keterangan, Status_Jadwal, Status, Created_By) VALUES
(1, '2024-03-10', '09:00', '09:30', 'Slot Pagi 2024 - Studio Minimalis', 0, 1, 'admin'),
(1, '2024-03-10', '09:30', '10:00', 'Slot Pagi 2024 - Studio Minimalis', 0, 1, 'admin'),
(2, '2024-07-15', '13:00', '14:00', 'Slot Siang 2024 - Studio Cinta', 0, 1, 'admin'),
(2, '2024-07-15', '14:00', '15:00', 'Slot Siang 2024 - Studio Cinta', 0, 1, 'admin'),
(4, '2024-10-20', '10:00', '11:00', 'Slot Pagi 2024 - Studio Prestasi', 0, 1, 'admin'),
(4, '2024-10-20', '11:00', '12:00', 'Slot Pagi 2024 - Studio Prestasi', 0, 1, 'admin');

-- Tahun 2025
INSERT INTO Jadwal_Studio (ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Keterangan, Status_Jadwal, Status, Created_By) VALUES
(3, '2025-02-14', '10:00', '11:30', 'Slot Imlek 2025 - Studio Kehangatan', 0, 1, 'admin'),
(3, '2025-02-14', '13:00', '14:30', 'Slot Siang 2025 - Studio Kehangatan', 0, 1, 'admin'),
(3, '2025-02-14', '14:30', '16:00', 'Slot Siang 2025 - Studio Kehangatan', 0, 1, 'admin'),
(5, '2025-06-25', '09:00', '11:00', 'Slot Grup 2025 - Studio Sinergi', 0, 1, 'admin'),
(5, '2025-06-25', '13:00', '15:00', 'Slot Grup 2025 - Studio Sinergi', 0, 1, 'admin'),
(1, '2025-09-05', '15:00', '15:30', 'Slot Sore 2025 - Studio Minimalis', 0, 1, 'admin');

-- Tahun 2026
INSERT INTO Jadwal_Studio (ID_Ruangan, Tanggal_Jadwal, Jam_Mulai, Jam_Selesai, Keterangan, Status_Jadwal, Status, Created_By) VALUES
(2, '2026-03-20', '10:00', '11:00', 'Slot Pagi 2026 - Studio Cinta', 0, 1, 'admin'),
(2, '2026-03-20', '11:00', '12:00', 'Slot Pagi 2026 - Studio Cinta', 0, 1, 'admin'),
(4, '2026-06-15', '09:00', '10:00', 'Slot Pagi 2026 - Studio Prestasi', 0, 1, 'admin'),
(4, '2026-06-15', '10:00', '11:00', 'Slot Pagi 2026 - Studio Prestasi', 0, 1, 'admin'),
(3, '2026-07-08', '11:00', '12:30', 'Slot Siang 2026 - Studio Kehangatan', 0, 1, 'admin'),
(3, '2026-07-08', '13:00', '14:30', 'Slot Siang 2026 - Studio Kehangatan', 0, 1, 'admin'),
(5, '2026-07-09', '10:00', '12:00', 'Slot Pagi 2026 - Studio Sinergi', 0, 1, 'admin');
GO


-- =====================================================
-- MEMASUKKAN TRANSAKSI DUMMY VIA SP RESMI (PATUH ATURAN)
-- =====================================================

-- ------------------ TRANSAKSI TAHUN 2024 ------------------

-- 1. Order 1 (2024): 2 Slot Waktu Terpilih (ID_Jadwal 1 dan 2)
DECLARE @Jadwal1 ListJadwalType;
INSERT INTO @Jadwal1 VALUES (1), (2);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 1, @ID_Paket = 1, @ID_Ruangan = 1, @ID_Tema = 1, @JadwalList = @Jadwal1, @Keterangan = 'Booking 2 slot 2024', @Created_By = 'customer';
GO

-- Upload DP & Pelunasan Order 1
EXEC sp_InsertPembayaran @ID_Order = 1, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 110000, @Bukti = 'bukti_dp_1.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 1, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_MulaiSesiFoto @ID_Order = 1, @ID_Fotografer = 5, @CreatedBy = 'admin';
EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = 1, @File_Hasil = 'hasil_foto_1.zip', @Modified_By = 'admin';

EXEC sp_InsertPembayaran @ID_Order = 1, @Tipe = 'Pelunasan', @Metode = 'Transfer Bank', @Jumlah = 110000, @Bukti = 'bukti_lunas_1.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 2, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

-- Tambah Barang Cetak untuk Order 1
EXEC sp_CreatePenjualan @ID_Order = 1, @ID_Admin = 1, @CreatedBy = 'admin';
EXEC sp_InsertDetailPenjualan @ID_Penjualan = 1, @ID_Barang = 2, @Jumlah = 1; -- Cetak 8R
GO

-- 2. Order 2 (2024): Paket Romantis (1 Slot: ID_Jadwal 3)
DECLARE @Jadwal2 ListJadwalType;
INSERT INTO @Jadwal2 VALUES (3);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 2, @ID_Paket = 2, @ID_Ruangan = 2, @ID_Tema = 2, @JadwalList = @Jadwal2, @Keterangan = 'Foto couple 2024', @Created_By = 'customer';
GO

EXEC sp_InsertPembayaran @ID_Order = 2, @Tipe = 'DP', @Metode = 'QRIS', @Jumlah = 250000, @Bukti = 'bukti_dp_2.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 3, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_MulaiSesiFoto @ID_Order = 2, @ID_Fotografer = 5, @CreatedBy = 'admin';
EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = 2, @File_Hasil = 'hasil_foto_2.zip', @Modified_By = 'admin';

EXEC sp_InsertPembayaran @ID_Order = 2, @Tipe = 'Pelunasan', @Metode = 'QRIS', @Jumlah = 250000, @Bukti = 'bukti_lunas_2.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 4, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_CreatePenjualan @ID_Order = 2, @ID_Admin = 1, @CreatedBy = 'admin';
EXEC sp_InsertDetailPenjualan @ID_Penjualan = 2, @ID_Barang = 3, @Jumlah = 1; -- Album
EXEC sp_InsertDetailPenjualan @ID_Penjualan = 2, @ID_Barang = 4, @Jumlah = 1; -- Bingkai
GO


-- ------------------ TRANSAKSI TAHUN 2025 ------------------

-- 3. Order 3 (2025): Paket Keluarga - 2 Slot (ID_Jadwal 7 & 8)
DECLARE @Jadwal3 ListJadwalType;
INSERT INTO @Jadwal3 VALUES (7), (8);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 3, @ID_Paket = 3, @ID_Ruangan = 3, @ID_Tema = 3, @JadwalList = @Jadwal3, @Keterangan = 'Foto Keluarga Besar 2025', @Created_By = 'customer';
GO

EXEC sp_InsertPembayaran @ID_Order = 3, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 400000, @Bukti = 'bukti_dp_3.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 5, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_MulaiSesiFoto @ID_Order = 3, @ID_Fotografer = 5, @CreatedBy = 'admin';
EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = 3, @File_Hasil = 'hasil_foto_3.zip', @Modified_By = 'admin';

EXEC sp_InsertPembayaran @ID_Order = 3, @Tipe = 'Pelunasan', @Metode = 'Transfer Bank', @Jumlah = 400000, @Bukti = 'bukti_lunas_3.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 6, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_CreatePenjualan @ID_Order = 3, @ID_Admin = 1, @CreatedBy = 'admin';
EXEC sp_InsertDetailPenjualan @ID_Penjualan = 3, @ID_Barang = 5, @Jumlah = 1; -- Photobook
GO

-- 4. Order 4 (2025): Paket Grup - 1 Slot (ID_Jadwal 10)
DECLARE @Jadwal4 ListJadwalType;
INSERT INTO @Jadwal4 VALUES (10);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 4, @ID_Paket = 5, @ID_Ruangan = 5, @ID_Tema = 5, @JadwalList = @Jadwal4, @Keterangan = 'Foto Korporat 2025', @Created_By = 'customer';
GO

EXEC sp_InsertPembayaran @ID_Order = 4, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 500000, @Bukti = 'bukti_dp_4.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 7, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_MulaiSesiFoto @ID_Order = 4, @ID_Fotografer = 5, @CreatedBy = 'admin';
EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = 4, @File_Hasil = 'hasil_foto_4.zip', @Modified_By = 'admin';

EXEC sp_InsertPembayaran @ID_Order = 4, @Tipe = 'Pelunasan', @Metode = 'Transfer Bank', @Jumlah = 500000, @Bukti = 'bukti_lunas_4.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 8, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';
GO


-- ------------------ TRANSAKSI TAHUN 2026 ------------------

-- 5. Order 5 (2026): Paket Romantis - Lunas - 2 Slot (ID_Jadwal 13 & 14)
DECLARE @Jadwal5 ListJadwalType;
INSERT INTO @Jadwal5 VALUES (13), (14);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 5, @ID_Paket = 2, @ID_Ruangan = 2, @ID_Tema = 2, @JadwalList = @Jadwal5, @Keterangan = 'Romantis Outdoor 2026', @Created_By = 'customer';
GO

EXEC sp_InsertPembayaran @ID_Order = 5, @Tipe = 'DP', @Metode = 'QRIS', @Jumlah = 200000, @Bukti = 'bukti_dp_5.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 9, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_MulaiSesiFoto @ID_Order = 5, @ID_Fotografer = 5, @CreatedBy = 'admin';
EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = 5, @File_Hasil = 'hasil_foto_5.zip', @Modified_By = 'admin';

EXEC sp_InsertPembayaran @ID_Order = 5, @Tipe = 'Pelunasan', @Metode = 'QRIS', @Jumlah = 200000, @Bukti = 'bukti_lunas_5.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 10, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_CreatePenjualan @ID_Order = 5, @ID_Admin = 1, @CreatedBy = 'admin';
EXEC sp_InsertDetailPenjualan @ID_Penjualan = 5, @ID_Barang = 4, @Jumlah = 1; -- Bingkai
GO

-- 6. Order 6 (2026): Paket Wisuda - Selesai Sesi (Menunggu Pelunasan) - 1 Slot (ID_Jadwal 15)
DECLARE @Jadwal6 ListJadwalType;
INSERT INTO @Jadwal6 VALUES (15);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 1, @ID_Paket = 4, @ID_Ruangan = 4, @ID_Tema = 4, @JadwalList = @Jadwal6, @Keterangan = 'Foto Wisuda 2026', @Created_By = 'customer';
GO

EXEC sp_InsertPembayaran @ID_Order = 6, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 225000, @Bukti = 'bukti_dp_6.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 11, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';

EXEC sp_MulaiSesiFoto @ID_Order = 6, @ID_Fotografer = 5, @CreatedBy = 'admin';
EXEC sp_SelesaiSesiFoto @ID_Sesi_Foto = 6, @File_Hasil = 'hasil_wisuda_preview.zip', @Modified_By = 'admin';
GO

-- 7. Order 7 (2026): Paket Keluarga - DP Terverifikasi (Sesi Mendatang) - 2 Slot (ID_Jadwal 17 & 18)
DECLARE @Jadwal7 ListJadwalType;
INSERT INTO @Jadwal7 VALUES (17), (18);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 2, @ID_Paket = 3, @ID_Ruangan = 3, @ID_Tema = 3, @JadwalList = @Jadwal7, @Keterangan = 'Keluarga Bahagia 2026', @Created_By = 'customer';
GO

EXEC sp_InsertPembayaran @ID_Order = 7, @Tipe = 'DP', @Metode = 'Transfer Bank', @Jumlah = 300000, @Bukti = 'bukti_dp_7.jpg', @CreatedBy = 'customer';
EXEC sp_VerifikasiPembayaran @ID_Pembayaran = 12, @Status_Verifikasi = 1, @ID_Admin = 1, @Modified_By = 'admin';
GO

-- 8. Order 8 (2026): Paket Grup - Baru Dibuat (Menunggu DP) - 1 Slot (ID_Jadwal 19)
DECLARE @Jadwal8 ListJadwalType;
INSERT INTO @Jadwal8 VALUES (19);
EXEC sp_CreateOrderBooking @ID_Pelanggan = 3, @ID_Paket = 5, @ID_Ruangan = 5, @ID_Tema = 5, @JadwalList = @Jadwal8, @Keterangan = 'Grup Baru 2026', @Created_By = 'customer';
GO


-- ======================================================
-- 10. VERIFIKASI AKHIR DATABASE & INTEGRITAS SISTEM
-- ======================================================
SELECT 'DATABASE SpotLight SELESAI DIKONFIGURASI SECARA LENGKAP!' AS Status;

-- Verifikasi Jumlah Objek untuk Sidang
SELECT 'Stored Procedures' AS Objek, COUNT(*) AS Total FROM sys.procedures WHERE name LIKE 'sp_%';
SELECT 'User Defined Functions' AS Objek, COUNT(*) AS Total FROM sys.objects WHERE type IN ('FN', 'IF', 'TF') AND name LIKE 'fn_%';
SELECT 'Triggers' AS Objek, COUNT(*) AS Total FROM sys.triggers WHERE name LIKE 'tr_%';
GO


BACKUP DATABASE SpotLight
TO DISK = 'C:\Program Files\Microsoft SQL Server\MSSQL16.MSSQLSERVER\MSSQL\Backup\SpotLight_Kel02.bak'
WITH INIT;