-- DATA FIKTIF untuk demonstrasi UTS, bukan data mahasiswa sebenarnya.
-- Impor setelah uts_rekap_nilai_mahasiswa.sql.
-- NIM yang sudah ada dilewati; data yang ada tidak ditimpa.
SET NAMES utf8mb4;
SET time_zone = '+07:00';
USE db_nilai_mahasiswa;

INSERT IGNORE INTO nilai_mahasiswa
    (nama, nim, nilai_akhir, nilai_huruf, predikat, created_at, updated_at, foto)
VALUES
    ('Mahasiswa Contoh Satu',      '9900000001', 95.00, 'A', 'Sangat Memuaskan',  '2026-10-28 07:00:00', NULL, NULL),
    ('Mahasiswa Contoh Dua',       '9900000002', 86.75, 'A', 'Sangat Memuaskan',  '2026-10-28 07:01:00', NULL, NULL),
    ('Mahasiswa Contoh Tiga',      '9900000003', 80.50, 'B', 'Memuaskan',         '2026-10-28 07:02:00', NULL, NULL),
    ('Mahasiswa Contoh Empat',     '9900000004', 72.25, 'B', 'Memuaskan',         '2026-10-28 07:03:00', NULL, NULL),
    ('Mahasiswa Contoh Lima',      '9900000005', 65.50, 'C', 'Cukup Memuaskan',   '2026-10-28 07:04:00', NULL, NULL),
    ('Mahasiswa Contoh Enam',      '9900000006', 57.00, 'C', 'Cukup Memuaskan',   '2026-10-28 07:05:00', NULL, NULL),
    ('Mahasiswa Contoh Tujuh',     '9900000007', 50.50, 'D', 'Kurang Memuaskan',  '2026-10-28 07:06:00', NULL, NULL),
    ('Mahasiswa Contoh Delapan',   '9900000008', 40.00, 'D', 'Kurang Memuaskan',  '2026-10-28 07:07:00', NULL, NULL),
    ('Mahasiswa Contoh Sembilan',  '9900000009', 34.50, 'E', 'Tidak Lulus',       '2026-10-28 07:08:00', NULL, NULL),
    ('Mahasiswa Contoh Sepuluh',   '9900000010', 20.00, 'E', 'Tidak Lulus',       '2026-10-28 07:09:00', NULL, NULL),
    ('Mahasiswa Contoh Sebelas',   '9900000011', 90.00, 'A', 'Sangat Memuaskan',  '2026-10-28 07:10:00', NULL, NULL),
    ('Mahasiswa Contoh Dua Belas', '9900000012', 77.00, 'B', 'Memuaskan',         '2026-10-28 07:11:00', NULL, NULL),
    ('Mahasiswa Contoh Tiga Belas','9900000013', 61.00, 'C', 'Cukup Memuaskan',   '2026-10-28 07:12:00', NULL, NULL),
    ('Mahasiswa Contoh Empat Belas','9900000014',45.00, 'D', 'Kurang Memuaskan',  '2026-10-28 07:13:00', NULL, NULL);
