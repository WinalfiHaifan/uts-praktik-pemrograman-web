# UTS Praktik Pemrograman Web

**Rekap Nilai Mahasiswa** — aplikasi PHP native dan MySQL untuk mengelola nilai mahasiswa.

**Nama:** Winalfi Haifan  
**Akun GitHub:** [WinalfiHaifan](https://github.com/WinalfiHaifan)

## Fitur

- Menambahkan satu atau beberapa mahasiswa sekaligus (1–100 baris).
- Validasi nama, NIM unik 10 digit, dan nilai 0–100 dengan maksimal dua angka desimal.
- Konversi otomatis nilai angka menjadi nilai huruf dan predikat.
- Mengubah dan menghapus data mahasiswa.
- Mengunggah foto opsional (JPG, PNG, atau GIF, maksimal 2 MB).
- Pencarian nama, filter predikat, pengurutan nilai, dan pembagian halaman tabel.
- Tanggal pembuatan dan perubahan data.

## Kebutuhan

- XAMPP atau server PHP 8.x dengan ekstensi `mysqli` dan `fileinfo`.
- MySQL 8.x atau MariaDB; phpMyAdmin dapat dipakai untuk mengimpor SQL.
- Browser modern. Tampilan tidak memerlukan CDN atau framework eksternal.

## Menjalankan melalui XAMPP

1. Unduh repository melalui **Code → Download ZIP**, lalu ekstrak ke folder `htdocs/uts-praktik-pemrograman-web`. Alternatifnya, clone repository:

   ```sh
   git clone https://github.com/WinalfiHaifan/uts-praktik-pemrograman-web.git
   ```

2. Jalankan **Apache** dan **MySQL** pada XAMPP.
3. Buka phpMyAdmin, pilih tab **Import**, lalu impor [`uts_rekap_nilai_mahasiswa.sql`](uts_rekap_nilai_mahasiswa.sql). Berkas ini sudah berisi pembuatan database `db_nilai_mahasiswa` beserta struktur tabel `nilai_mahasiswa`; tidak perlu membuat tabel secara manual. Gunakan database baru untuk instalasi pertama.
4. Opsional: impor [`database/data_contoh.sql`](database/data_contoh.sql) untuk memasukkan **14 data fiktif**. Data mahasiswa asli dan foto lokal tidak disertakan.
5. Salin `config.example.php` menjadi **`config.local.php`**. Sesuaikan host, username, password, dan nama database dengan MySQL di komputer Anda. Berkas konfigurasi lokal diabaikan oleh Git.
6. Buka **http://localhost/uts-praktik-pemrograman-web/**. Pastikan folder `uploads` dapat ditulis oleh PHP jika menggunakan fitur foto.

Jika folder disimpan dengan nama lain, sesuaikan alamat `localhost`. Pada folder kerja pembuat, alamatnya adalah `http://localhost/web5d/uts/`.

Konfigurasi juga dapat diberikan melalui environment `DB_HOST`, `DB_USER`, `DB_PASSWORD`, dan `DB_NAME`; nilai environment didahulukan dari konfigurasi lokal.

## Isi repository

| Berkas | Keterangan |
| --- | --- |
| `index.php` | Halaman masuk aplikasi |
| `uts_rekap_nilai_mahasiswa.php` | Aplikasi utama PHP, form, tabel, dan interaksi |
| `uts_rekap_nilai_mahasiswa.sql` | Ekspor struktur database, siap impor lewat phpMyAdmin |
| `database/data_contoh.sql` | Data fiktif untuk demonstrasi |
| `config.example.php` | Contoh konfigurasi koneksi tanpa kredensial pribadi |
| `uploads/.gitkeep` | Menyediakan folder foto; foto pengguna tidak diunggah |

## Konversi nilai

| Nilai angka | Nilai huruf | Predikat |
| --- | --- | --- |
| 0–34,50 | E | Tidak Lulus |
| >34,50–50,50 | D | Kurang Memuaskan |
| >50,50–65,50 | C | Cukup Memuaskan |
| >65,50–80,50 | B | Memuaskan |
| >80,50–100 | A | Sangat Memuaskan |

## Pengumpulan

- Tautan akun GitHub: **https://github.com/WinalfiHaifan**
- Tautan repository tugas: **https://github.com/WinalfiHaifan/uts-praktik-pemrograman-web**

Repository ini berisi source aplikasi dan database untuk dijalankan melalui PHP/MySQL. Tautan repository merupakan halaman kode di GitHub, bukan alamat aplikasi yang sedang berjalan. GitHub Pages tidak menjalankan PHP/MySQL; lihat [dokumentasi GitHub Pages](https://docs.github.com/en/pages/getting-started-with-github-pages/creating-a-github-pages-site).

## Pemeriksaan

Pemeriksaan pada 28 Oktober 2026: sintaks PHP, impor struktur SQL dan 14 data fiktif ke database baru pada MySQL 8.0.41, akses HTTP ke halaman utama, tambah beberapa data sekaligus, konversi nilai pada batas 80,50/80,51, edit, tanggal perubahan, validasi NIM duplikat, dan hapus data. Pemeriksaan dilakukan pada database sementara yang terpisah dari data lokal.
