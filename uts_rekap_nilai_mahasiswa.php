<?php
// Rekap Nilai Mahasiswa - Praktikum UTS
// PHP Native dan MySQLi Procedural, dengan tabel interaktif seperti AdminLTE.

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set("Asia/Pontianak");

// Konfigurasi lokal dipisahkan agar kredensial tidak masuk repository.
$configFile = __DIR__ . "/config.local.php";
$config = is_file($configFile) ? require $configFile : [];
$host = getenv("DB_HOST") ?: ($config["host"] ?? "localhost");
$username = getenv("DB_USER") ?: ($config["username"] ?? "root");
$password = getenv("DB_PASSWORD") !== false ? getenv("DB_PASSWORD") : ($config["password"] ?? "");
$database = getenv("DB_NAME") ?: ($config["database"] ?? "db_nilai_mahasiswa");

try {
    $conn = mysqli_connect($host, $username, $password, $database);
    mysqli_set_charset($conn, "utf8mb4");
    mysqli_query($conn, "SET time_zone = '+07:00'");
} catch (mysqli_sql_exception $e) {
    die("Koneksi database gagal. Periksa pengaturan pada config.local.php atau environment database.");
}

$halaman = basename($_SERVER["SCRIPT_NAME"]);
$jumlah = 0;
$tampilForm = false;
$editData = null;
$pesanError = "";
$data = [];
$error = [];
$editError = [];

// Membersihkan input yang berasal dari form.
function bersihkan($isi)
{
    return is_string($isi) ? trim($isi) : "";
}

// Menghindari karakter HTML berbahaya saat data ditampilkan.
function aman($isi)
{
    return htmlspecialchars((string) $isi, ENT_QUOTES, "UTF-8");
}

// Ketentuan konversi nilai yang dipakai sejak tugas sebelumnya.
function konversiNilai($nilai)
{
    if ($nilai <= 34.50) return ["E", "Tidak Lulus"];
    if ($nilai <= 50.50) return ["D", "Kurang Memuaskan"];
    if ($nilai <= 65.50) return ["C", "Cukup Memuaskan"];
    if ($nilai <= 80.50) return ["B", "Memuaskan"];
    return ["A", "Sangat Memuaskan"];
}

// Mengecek NIM yang sama, kecuali milik data yang sedang diedit.
function nimSudahAda($conn, $nim, $id = 0)
{
    $sql = "SELECT id FROM nilai_mahasiswa WHERE nim = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $nim, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $ada = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $ada;
}

// Validasi dipakai saat tambah data dan edit data.
function validasiData($conn, $nama, $nim, $nilai, $id = 0)
{
    $error = ["nama" => "", "nim" => "", "nilai" => ""];

    if ($nama === "") {
        $error["nama"] = "Nama wajib diisi.";
    } elseif (strlen($nama) > 100 || !preg_match("/^[\p{L} .'-]+$/u", $nama)) {
        $error["nama"] = "Nama maksimal 100 karakter, hanya huruf dan tanda nama umum.";
    }

    if (!preg_match("/^[0-9]{10}$/", $nim)) {
        $error["nim"] = "NIM harus tepat 10 digit angka.";
    } elseif (nimSudahAda($conn, $nim, $id)) {
        $error["nim"] = "NIM sudah terdaftar.";
    }

    if ($nilai === "" || !preg_match("/^\d+(\.\d{1,2})?$/", $nilai)
        || (float) $nilai < 0 || (float) $nilai > 100) {
        $error["nilai"] = "Nilai harus 0–100, maksimal 2 angka desimal.";
    }

    return $error;
}

// Mengambil foto dari form tunggal atau multiple input foto[].
function ambilFoto($i = null)
{
    if (!isset($_FILES["foto"])) return null;
    if ($i === null) return $_FILES["foto"];

    return [
        "tmp_name" => $_FILES["foto"]["tmp_name"][$i] ?? "",
        "error" => $_FILES["foto"]["error"][$i] ?? UPLOAD_ERR_NO_FILE,
        "size" => $_FILES["foto"]["size"][$i] ?? 0
    ];
}

// Foto opsional: JPG, PNG atau GIF dan maksimal 2 MB.
function validasiFoto($foto)
{
    if (!$foto || $foto["error"] === UPLOAD_ERR_NO_FILE) return "";
    if ($foto["error"] !== UPLOAD_ERR_OK) return "Foto gagal diunggah.";
    if ($foto["size"] <= 0 || $foto["size"] > 2 * 1024 * 1024) {
        return "Ukuran foto maksimal 2 MB.";
    }
    if (!is_uploaded_file($foto["tmp_name"])) return "File upload tidak valid.";

    $jenis = @getimagesize($foto["tmp_name"]);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($foto["tmp_name"]);
    if (!$jenis || !in_array($mime, ["image/jpeg", "image/png", "image/gif"], true)) {
        return "Maaf, hanya gambar JPG, PNG, atau GIF yang diperbolehkan.";
    }
    return "";
}

// Menyimpan foto dengan nama acak agar tidak menimpa foto lain.
function simpanFoto($foto)
{
    if (!$foto || $foto["error"] === UPLOAD_ERR_NO_FILE) return null;
    $pesan = validasiFoto($foto);
    if ($pesan !== "") throw new RuntimeException($pesan);

    $folder = __DIR__ . "/uploads";
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        throw new RuntimeException("Folder uploads tidak bisa dibuat.");
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($foto["tmp_name"]);
    $format = ["image/jpeg" => "jpg", "image/png" => "png", "image/gif" => "gif"];
    $namaFile = bin2hex(random_bytes(16)) . "." . $format[$mime];
    if (!move_uploaded_file($foto["tmp_name"], $folder . "/" . $namaFile)) {
        throw new RuntimeException("Foto gagal disimpan.");
    }
    return "uploads/" . $namaFile;
}

// Hanya menerima alamat foto yang dibuat oleh program ini.
function fotoAman($path)
{
    return is_string($path) && preg_match('~^uploads/[a-f0-9]{32}\.(jpg|png|gif)$~', $path);
}

// Mendukung foto lama jika berkas PHP ditempatkan di subfolder /uts.
function alamatFoto($path)
{
    if (!fotoAman($path)) return null;
    if (is_file(__DIR__ . "/" . $path)) return $path;
    if (is_file(dirname(__DIR__) . "/" . $path)) return "../" . $path;
    return null;
}

function hapusFoto($path)
{
    if (!fotoAman($path)) return;
    $file = __DIR__ . "/" . $path;
    if (is_file($file)) unlink($file);
}

$pesan = [
    "simpan" => "Data mahasiswa berhasil disimpan.",
    "update" => "Data mahasiswa berhasil diperbarui.",
    "hapus" => "Data mahasiswa berhasil dihapus."
];
$kodePesan = bersihkan($_GET["msg"] ?? "");
$pesanSukses = $pesan[$kodePesan] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = bersihkan($_POST["aksi"] ?? "");

    // Membuat jumlah baris input mahasiswa.
    if ($aksi === "buat_form") {
        $jumlah = filter_var($_POST["jumlah"] ?? null, FILTER_VALIDATE_INT);
        if ($jumlah === false || $jumlah < 1 || $jumlah > 100) {
            $pesanError = "Jumlah mahasiswa harus 1 sampai 100.";
        } else {
            $tampilForm = true;
        }
    }

    // Simpan beberapa mahasiswa sekaligus menggunakan perulangan for.
    elseif ($aksi === "simpan") {
        $jumlah = filter_var($_POST["jumlah"] ?? null, FILTER_VALIDATE_INT);
        if ($jumlah === false || $jumlah < 1 || $jumlah > 100) {
            $pesanError = "Jumlah mahasiswa tidak valid.";
        } else {
            $tampilForm = true;
            $adaError = false;
            $nimDalamForm = [];

            for ($i = 0; $i < $jumlah; $i++) {
                $nama = bersihkan($_POST["nama"][$i] ?? "");
                $nim = bersihkan($_POST["nim"][$i] ?? "");
                $nilai = str_replace(",", ".", bersihkan($_POST["nilai"][$i] ?? ""));

                $data[$i] = ["nama" => $nama, "nim" => $nim, "nilai" => $nilai];
                $error[$i] = validasiData($conn, $nama, $nim, $nilai);
                $error[$i]["foto"] = validasiFoto(ambilFoto($i));

                if (preg_match("/^[0-9]{10}$/", $nim)) {
                    if (isset($nimDalamForm[$nim])) {
                        $error[$i]["nim"] = "NIM sama dengan baris lain.";
                    }
                    $nimDalamForm[$nim] = true;
                }
                if (implode("", $error[$i]) !== "") $adaError = true;
            }

            if (!$adaError) {
                $fotoBaru = [];
                try {
                    // Transaksi: kalau satu gagal, semua data batch dibatalkan.
                    mysqli_begin_transaction($conn);
                    $sql = "INSERT INTO nilai_mahasiswa
                            (nama, nim, nilai_akhir, nilai_huruf, predikat, foto)
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssdsss", $nama, $nim, $nilai, $huruf, $predikat, $pathFoto);

                    for ($i = 0; $i < $jumlah; $i++) {
                        $nama = $data[$i]["nama"];
                        $nim = $data[$i]["nim"];
                        $nilai = round((float) $data[$i]["nilai"], 2);
                        list($huruf, $predikat) = konversiNilai($nilai);
                        $pathFoto = simpanFoto(ambilFoto($i));
                        if ($pathFoto !== null) $fotoBaru[] = $pathFoto;
                        mysqli_stmt_execute($stmt);
                    }

                    mysqli_stmt_close($stmt);
                    mysqli_commit($conn);
                    header("Location: " . rawurlencode($halaman) . "?msg=simpan");
                    exit;
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    foreach ($fotoBaru as $path) hapusFoto($path);
                    $pesanError = $e instanceof RuntimeException
                        ? $e->getMessage() : "Gagal menyimpan data. Periksa kembali NIM dan database.";
                }
            }
        }
    }

    // Update data dan catat waktu perubahan secara otomatis.
    elseif ($aksi === "update") {
        $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
        $nama = bersihkan($_POST["nama"] ?? "");
        $nim = bersihkan($_POST["nim"] ?? "");
        $nilaiInput = str_replace(",", ".", bersihkan($_POST["nilai"] ?? ""));
        $editData = ["id" => $id, "nama" => $nama, "nim" => $nim, "nilai" => $nilaiInput];

        if ($id === false || $id < 1) {
            $pesanError = "ID tidak valid.";
        } else {
            $editError = validasiData($conn, $nama, $nim, $nilaiInput, $id);
            $editError["foto"] = validasiFoto(ambilFoto());

            if (implode("", $editError) === "") {
                $fotoBaru = null;
                try {
                    $stmt = mysqli_prepare($conn, "SELECT foto FROM nilai_mahasiswa WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    mysqli_stmt_close($stmt);
                    if (!$lama) throw new RuntimeException("Data mahasiswa tidak ditemukan.");

                    $nilai = round((float) $nilaiInput, 2);
                    list($huruf, $predikat) = konversiNilai($nilai);
                    $fotoBaru = simpanFoto(ambilFoto());
                    $pathFoto = $fotoBaru ?? $lama["foto"];

                    $sql = "UPDATE nilai_mahasiswa SET nama = ?, nim = ?, nilai_akhir = ?,
                            nilai_huruf = ?, predikat = ?, foto = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ssdsssi", $nama, $nim, $nilai,
                        $huruf, $predikat, $pathFoto, $id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    if ($fotoBaru !== null) hapusFoto($lama["foto"]);
                    header("Location: " . rawurlencode($halaman) . "?msg=update");
                    exit;
                } catch (Throwable $e) {
                    if ($fotoBaru !== null) hapusFoto($fotoBaru);
                    $pesanError = $e instanceof RuntimeException
                        ? $e->getMessage() : "Gagal memperbarui data. Periksa NIM dan kolom updated_at.";
                }
            }
        }
    }

    // Hapus data berdasarkan ID menggunakan prepared statement.
    elseif ($aksi === "hapus") {
        $id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $pesanError = "ID tidak valid.";
        } else {
            try {
                $stmt = mysqli_prepare($conn, "SELECT foto FROM nilai_mahasiswa WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                $stmt = mysqli_prepare($conn, "DELETE FROM nilai_mahasiswa WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                if ($lama) hapusFoto($lama["foto"]);
                header("Location: " . rawurlencode($halaman) . "?msg=hapus");
                exit;
            } catch (mysqli_sql_exception $e) {
                $pesanError = "Gagal menghapus data.";
            }
        }
    }
}

// Menampilkan isi mahasiswa pada form edit.
if ($editData === null && isset($_GET["edit"])) {
    $id = filter_var($_GET["edit"], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        $pesanError = "ID tidak valid.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM nilai_mahasiswa WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $baris = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($baris) {
            $editData = ["id" => $baris["id"], "nama" => $baris["nama"],
                "nim" => $baris["nim"], "nilai" => $baris["nilai_akhir"], "foto" => $baris["foto"]];
        } else {
            $pesanError = "Mahasiswa tidak ditemukan.";
        }
    }
}

// SELECT dan while untuk menampilkan data seperti contoh W3Schools.
$sql = "SELECT * FROM nilai_mahasiswa ORDER BY id ASC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Nilai Mahasiswa - UTS</title>
<style>
/* Tampilan sederhana seperti tabel admin, tanpa framework tambahan */
* { box-sizing: border-box; }
body {
    margin: 0;
    background: #151c26;
    color: #edf3fa;
    font-family: Arial, sans-serif;
    font-size: 14px;
}
.header { background: #222e3d; border-bottom: 1px solid #34465b; padding: 15px 24px; }
.header-inner { max-width: 1520px; margin: auto; display: flex; align-items: center; gap: 12px; }
.header small { margin-left: auto; color: #b2c3d7; }
.logo { background: #2276c4; padding: 9px 12px; border-radius: 6px; font-weight: bold; }
.container {
    width: calc(100% - 40px);
    max-width: 1520px;
    margin: 22px auto;
    background: #222e3d;
    border: 1px solid #394e63;
    border-radius: 9px;
    padding: 24px 26px 28px;
}
h1 { margin: 0 0 7px; font-size: 25px; }
h2 { margin: 0 0 14px; font-size: 19px; }
.subjudul { margin: 0; color: #a6bad0; font-size: 13px; }
.section { border-top: 1px solid #3b4d60; margin-top: 22px; padding-top: 22px; }
label { font-size: 13px; font-weight: bold; }
input, select {
    min-height: 39px; padding: 9px 11px;
    background: #172333; color: #f1f6ff;
    border: 1px solid #4b6078; border-radius: 5px;
    font: inherit; color-scheme: dark;
}
input::placeholder { color: #95a9bd; }
input:focus, select:focus { outline: 2px solid #449be5; outline-offset: 0; }
button, .btn {
    display: inline-block; padding: 9px 14px; border: none;
    border-radius: 5px; color: white; cursor: pointer;
    font-size: 13px; font-family: inherit; text-decoration: none;
    white-space: nowrap;
}
button:disabled { opacity: .4; cursor: default; }
.biru { background: #217bc9; } .biru:hover { background: #1267b2; }
.kuning { background: #f7bc45; color: #192331; } .kuning:hover { background: #e8ab2f; }
.merah { background: #da5360; } .merah:hover { background: #c4414e; }
.abu { background: #5c6979; } .abu:hover { background: #485565; }
.form-jumlah, .tombol-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.form-jumlah input { width: 90px; }
.pesan { padding: 12px; border-radius: 5px; margin: 16px 0; }
.sukses { background: #1b4036; border: 1px solid #316c56; color: #b4f1d0; }
.gagal { background: #492f39; border: 1px solid #96515f; color: #ffc4ca; }
.error { display: block; margin-top: 5px; font-size: 12px; color: #ffa0a8; }
.error:empty { display: none; }
.petunjuk { margin: 0 0 15px; color: #b2c3d6; font-size: 13px; }
.form-edit { max-width: 510px; margin: auto; padding: 20px; border: 1px solid #496078; border-radius: 7px; }
.form-edit label { display: block; margin: 13px 0 6px; }
.form-edit input { width: 100%; }
.tombol-form { margin-top: 18px; }
.table-wrap { max-width: 100%; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px 12px; border: 1px solid #415268; text-align: center; vertical-align: middle; }
th { background: #304258; font-size: 13px; font-weight: 700; white-space: nowrap; }
tbody tr { background: #243246; }
tbody tr:nth-child(even) { background: #2b3b50; }
tbody tr:hover { background: #344961; }
.input-table { min-width: 850px; }
.input-table input { width: 100%; }
.input-table td:first-child { width: 45px; }
/* Kontrol tabel */
.filter-bar { display: grid; grid-template-columns: 1.5fr 1.25fr 1.2fr .7fr; gap: 12px; margin-bottom: 14px; }
.filter-group { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.filter-group input, .filter-group select { width: 100%; }
.rekap-table { min-width: 1080px; table-layout: auto; }
.rekap-table td { font-size: 13px; }
.rekap-table th:nth-child(2), .rekap-table td:nth-child(2) { text-align: left; min-width: 218px; }
.rekap-table th:nth-child(5), .rekap-table td:nth-child(5) { min-width: 155px; }
.rekap-table th:nth-child(8), .rekap-table td:nth-child(8) { min-width: 128px; }
.rekap-table tr[hidden] { display: none !important; }
.profil { display: flex; align-items: center; gap: 10px; }
.foto {
    display: inline-block; width: 38px; height: 38px; border-radius: 50%;
    border: 2px solid #637f9c; flex-shrink: 0; object-fit: cover;
}
.foto-kosong { display: inline-flex; align-items: center; justify-content: center; background: #526d8a; font-weight: bold; }
.nama { font-weight: 600; }
.huruf { display: inline-block; min-width: 27px; padding: 5px 8px; background: #17516c; color: #a4eaff; border-radius: 5px; font-weight: bold; }
.aksi { display: flex; justify-content: center; align-items: center; gap: 6px; }
.aksi form { margin: 0; }
.aksi .btn, .aksi button { padding: 8px 12px; }
.tanggal { font-size: 12px; white-space: nowrap; }
.tanggal small { display: block; color: #acc1d8; margin-top: 3px; }
/* Hanya nilai angka yang dapat diklik untuk pengurutan */
.sort-header { background: transparent; font-weight: bold; padding: 0; font-size: inherit; }
.sort-header:hover { color: #8ed9ff; }
.sort-header span { color: #79d8ff; font-size: 17px; margin-left: 3px; }
.pager { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
.pager p { margin: 0; font-size: 13px; color: #bed0e4; }
.pager-buttons { display: flex; flex-wrap: wrap; gap: 5px; }
.pager-buttons button { padding: 7px 12px; background: #2b3f56; border: 1px solid #4b6482; }
.pager-buttons button.active { background: #217bc9; border-color: #52a9f3; }
.pager-buttons button:not(:disabled):hover { background: #37618c; }
@media (max-width: 820px) {
    .container { width: calc(100% - 20px); margin: 10px auto; padding: 17px; }
    .filter-bar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .header small { display: none; }
}
@media (max-width: 520px) {
    .filter-bar { grid-template-columns: 1fr; }
    .pager { align-items: flex-start; flex-direction: column; }
}
</style>
</head>
<body>
<header class="header">
    <div class="header-inner"><span class="logo">R</span><strong>Rekap Nilai Mahasiswa</strong><small>Praktikum UTS - PHP &amp; MySQL</small></div>
</header>
<main class="container">
    <h1>Rekap Nilai Mahasiswa</h1>
    <p class="subjudul">Pencarian nama, filter predikat, dan pengurutan nilai akhir.</p>

    <?php if ($pesanSukses !== "") { ?>
        <div class="pesan sukses"><?php echo aman($pesanSukses); ?></div>
    <?php } ?>
    <?php if ($pesanError !== "") { ?>
        <div class="pesan gagal"><?php echo aman($pesanError); ?></div>
    <?php } ?>

    <div class="section">
    <?php if ($editData !== null) { ?>
        <!-- Form edit tetap di halaman yang sama -->
        <div class="form-edit">
            <h2>Edit Nilai Mahasiswa</h2>
            <form method="POST" enctype="multipart/form-data" action="<?php echo aman($halaman); ?>">
                <input type="hidden" name="aksi" value="update">
                <input type="hidden" name="id" value="<?php echo aman($editData["id"]); ?>">
                <label for="editNama">Nama Lengkap</label>
                <input id="editNama" type="text" name="nama" maxlength="100" required
                       value="<?php echo aman($editData["nama"]); ?>">
                <span class="error"><?php echo aman($editError["nama"] ?? ""); ?></span>
                <label for="editNim">NIM</label>
                <input id="editNim" type="text" name="nim" maxlength="10" pattern="[0-9]{10}" required
                       inputmode="numeric" value="<?php echo aman($editData["nim"]); ?>">
                <span class="error"><?php echo aman($editError["nim"] ?? ""); ?></span>
                <label for="editNilai">Nilai Angka</label>
                <input id="editNilai" type="number" name="nilai" min="0" max="100" step="0.01" required
                       value="<?php echo aman($editData["nilai"]); ?>">
                <span class="error"><?php echo aman($editError["nilai"] ?? ""); ?></span>
                <label for="editFoto">Ganti Foto Profil (opsional)</label>
                <?php if (alamatFoto($editData["foto"] ?? null)) { ?>
                    <p><img class="foto" src="<?php echo aman(alamatFoto($editData["foto"])); ?>" alt="Foto saat ini"></p>
                <?php } ?>
                <input id="editFoto" type="file" name="foto" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif">
                <span class="error"><?php echo aman($editError["foto"] ?? ""); ?></span>
                <p class="petunjuk">JPG, PNG, atau GIF, maksimal 2 MB. Kosongkan jika foto tidak ingin diubah.</p>
                <div class="tombol-form">
                    <button class="biru" type="submit">Simpan Perubahan</button>
                    <a class="btn abu" href="<?php echo aman($halaman); ?>">Batal</a>
                </div>
            </form>
        </div>
    <?php } elseif ($tampilForm) { ?>
        <!-- Baris input mahasiswa dibuat menggunakan for -->
        <h2>Tambah <?php echo (int) $jumlah; ?> Mahasiswa</h2>
        <p class="petunjuk">Foto boleh kosong. Tanggal dibuat diisi otomatis ketika data disimpan.</p>
        <form method="POST" enctype="multipart/form-data" action="<?php echo aman($halaman); ?>">
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="jumlah" value="<?php echo (int) $jumlah; ?>">
            <div class="table-wrap">
                <table class="input-table">
                    <thead><tr><th>No.</th><th>NIM</th><th>Nama Lengkap</th><th>Nilai Angka</th><th>Foto (opsional)</th></tr></thead>
                    <tbody>
                    <?php for ($i = 0; $i < $jumlah; $i++) { ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><input type="text" name="nim[]" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" required
                                       value="<?php echo aman($data[$i]["nim"] ?? ""); ?>">
                                <span class="error"><?php echo aman($error[$i]["nim"] ?? ""); ?></span></td>
                            <td><input type="text" name="nama[]" maxlength="100" required
                                       value="<?php echo aman($data[$i]["nama"] ?? ""); ?>">
                                <span class="error"><?php echo aman($error[$i]["nama"] ?? ""); ?></span></td>
                            <td><input type="number" name="nilai[]" min="0" max="100" step="0.01" required
                                       value="<?php echo aman($data[$i]["nilai"] ?? ""); ?>">
                                <span class="error"><?php echo aman($error[$i]["nilai"] ?? ""); ?></span></td>
                            <td><input type="file" name="foto[]" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif">
                                <span class="error"><?php echo aman($error[$i]["foto"] ?? ""); ?></span></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="tombol-form">
                <button type="submit" class="biru">Simpan Semua Data</button>
                <a class="btn abu" href="<?php echo aman($halaman); ?>">Batal</a>
            </div>
        </form>
    <?php } else { ?>
        <h2>Tambah Data Mahasiswa</h2>
        <form method="POST" action="<?php echo aman($halaman); ?>" class="form-jumlah">
            <input type="hidden" name="aksi" value="buat_form">
            <label for="jumlah">Jumlah Mahasiswa:</label>
            <input id="jumlah" type="number" name="jumlah" min="1" max="100" value="1" required>
            <button type="submit" class="biru">Buat Form</button>
        </form>
    <?php } ?>
    </div>

    <!-- Susunan delapan kolom mengikuti instruksi UTS. -->
    <div class="section">
        <h2>Tabel Rekap Nilai</h2>
        <div class="filter-bar">
            <div class="filter-group filter-search">
                <label for="cariNama">Cari berdasarkan Nama Lengkap</label>
                <input type="search" id="cariNama" placeholder="Ketik nama mahasiswa...">
            </div>
            <div class="filter-group">
                <label for="filterPredikat">Pilihan Predikat</label>
                <select id="filterPredikat">
                    <option value="">Semua Predikat</option>
                    <option value="Sangat Memuaskan">Sangat Memuaskan</option>
                    <option value="Memuaskan">Memuaskan</option>
                    <option value="Cukup Memuaskan">Cukup Memuaskan</option>
                    <option value="Kurang Memuaskan">Kurang Memuaskan</option>
                    <option value="Tidak Lulus">Tidak Lulus</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="sortNilai">Urut Nilai Angka</label>
                <select id="sortNilai">
                    <option value="desc">Tertinggi → Terendah</option>
                    <option value="asc">Terendah → Tertinggi</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="jumlahBaris">Tampilkan</label>
                <select id="jumlahBaris">
                    <option value="5">5 data</option>
                    <option value="10">10 data</option>
                    <option value="15">15 data</option>
                </select>
            </div>
        </div>
        <div class="table-wrap">
            <table id="tabelNilai" class="rekap-table">
                <thead><tr>
                    <th>NIM</th>
                    <th>Nama Lengkap</th>
                    <th><button type="button" id="sortKolom" class="sort-header" title="Klik untuk membalik urutan nilai">Nilai Angka <span id="sortIkon">↓</span></button></th>
                    <th>Nilai Huruf</th>
                    <th>Predikat</th>
                    <th>Dibuat Tanggal</th>
                    <th>Diubah Tanggal</th>
                    <th>Aksi</th>
                </tr></thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)) {
                    $dibuat = $row["created_at"] ?? null;
                    $diubah = $row["updated_at"] ?? null;
                    $nama = $row["nama"];
                    $inisial = strtoupper(substr(trim($nama), 0, 1));
                ?>
                <tr>
                    <td><?php echo aman($row["nim"]); ?></td>
                    <td data-search="<?php echo aman($nama); ?>">
                        <div class="profil">
                            <?php if (alamatFoto($row["foto"] ?? null)) { ?>
                                <img class="foto" src="<?php echo aman(alamatFoto($row["foto"])); ?>"
                                     alt="Foto <?php echo aman($nama); ?>">
                            <?php } else { ?>
                                <span class="foto foto-kosong" aria-label="Tanpa foto"><?php echo aman($inisial); ?></span>
                            <?php } ?>
                            <span class="nama"><?php echo aman($nama); ?></span>
                        </div>
                    </td>
                    <td data-order="<?php echo aman($row["nilai_akhir"]); ?>">
                        <?php echo number_format((float) $row["nilai_akhir"], 2, ",", "."); ?>
                    </td>
                    <td><span class="huruf"><?php echo aman($row["nilai_huruf"]); ?></span></td>
                    <td data-search="<?php echo aman($row["predikat"]); ?>"><?php echo aman($row["predikat"]); ?></td>
                    <td data-order="<?php echo aman($dibuat ?? ""); ?>" class="tanggal">
                        <?php if ($dibuat) { ?>
                            <?php echo date("d/m/Y", strtotime($dibuat)); ?>
                            <small><?php echo date("H:i", strtotime($dibuat)); ?> WIB</small>
                        <?php } else { echo "—"; } ?>
                    </td>
                    <td data-order="<?php echo aman($diubah ?? ""); ?>" class="tanggal">
                        <?php if ($diubah) { ?>
                            <?php echo date("d/m/Y", strtotime($diubah)); ?>
                            <small><?php echo date("H:i", strtotime($diubah)); ?> WIB</small>
                        <?php } else { echo "—"; } ?>
                    </td>
                    <td>
                        <div class="aksi">
                            <a class="btn kuning" href="?edit=<?php echo (int) $row["id"]; ?>">Edit</a>
                            <form method="POST" action="<?php echo aman($halaman); ?>"
                                  onsubmit="return confirm('Yakin ingin menghapus mahasiswa ini?');">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?php echo (int) $row["id"]; ?>">
                                <button class="merah" type="submit">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php } ?>
                <tr id="barisKosong" hidden><td colspan="8">Tidak ada mahasiswa yang sesuai.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pager">
            <p id="infoHalaman">Memuat data...</p>
            <nav id="kontrolHalaman" class="pager-buttons" aria-label="Navigasi halaman tabel"></nav>
        </div>
    </div>
</main>
<script>
// Fitur tabel sederhana tanpa library luar, jadi tetap berjalan tanpa internet.
const tabelNilai = document.getElementById('tabelNilai');
const badanTabel = tabelNilai.querySelector('tbody');
const semuaBaris = Array.from(badanTabel.querySelectorAll('tr:not(#barisKosong)'));
const barisKosong = document.getElementById('barisKosong');
const cariNama = document.getElementById('cariNama');
const filterPredikat = document.getElementById('filterPredikat');
const sortNilai = document.getElementById('sortNilai');
const jumlahBaris = document.getElementById('jumlahBaris');
const infoHalaman = document.getElementById('infoHalaman');
const kontrolHalaman = document.getElementById('kontrolHalaman');

let halamanAktif = 1;

function tampilkanTabel() {
    const cari = cariNama.value.trim().toLocaleLowerCase('id');
    const predikat = filterPredikat.value;
    const urutan = sortNilai.value;
    const batas = Number(jumlahBaris.value);

    // Mencari hanya pada nama, lalu memfilter berdasarkan predikat.
    const hasil = semuaBaris.filter(function (baris) {
        const nama = baris.cells[1].textContent.toLocaleLowerCase('id');
        const nilaiPredikat = baris.cells[4].textContent.trim();
        return nama.includes(cari) && (predikat === '' || nilaiPredikat === predikat);
    });

    // Mengurutkan angka, bukan teks (100 harus lebih tinggi daripada 90).
    hasil.sort(function (a, b) {
        const nilaiA = Number(a.cells[2].dataset.order);
        const nilaiB = Number(b.cells[2].dataset.order);
        return urutan === 'asc' ? nilaiA - nilaiB : nilaiB - nilaiA;
    });

    semuaBaris.forEach(function (baris) { baris.hidden = true; });

    const totalHalaman = Math.max(1, Math.ceil(hasil.length / batas));
    if (halamanAktif > totalHalaman) halamanAktif = totalHalaman;
    const awal = (halamanAktif - 1) * batas;
    const akhir = Math.min(awal + batas, hasil.length);

    // Mengubah urutan baris pada tabel dan menampilkan satu halaman saja.
    hasil.forEach(function (baris) { badanTabel.appendChild(baris); });
    hasil.slice(awal, akhir).forEach(function (baris) { baris.hidden = false; });
    badanTabel.appendChild(barisKosong);
    barisKosong.hidden = hasil.length > 0;

    infoHalaman.textContent = hasil.length === 0
        ? 'Tidak ada data yang sesuai dari ' + semuaBaris.length + ' mahasiswa'
        : 'Menampilkan ' + (awal + 1) + '–' + akhir + ' dari ' + hasil.length + ' mahasiswa';

    document.getElementById('sortIkon').textContent = urutan === 'asc' ? '↑' : '↓';
    kontrolHalaman.innerHTML = '';

    function buatTombol(teks, tujuan, nonaktif, aktif) {
        const tombol = document.createElement('button');
        tombol.type = 'button';
        tombol.textContent = teks;
        tombol.disabled = nonaktif;
        if (aktif) {
            tombol.classList.add('active');
            tombol.setAttribute('aria-current', 'page');
        }
        tombol.addEventListener('click', function () {
            halamanAktif = tujuan;
            tampilkanTabel();
        });
        kontrolHalaman.appendChild(tombol);
    }

    buatTombol('‹', halamanAktif - 1, halamanAktif === 1, false);
    for (let i = 1; i <= totalHalaman; i++) {
        buatTombol(String(i), i, false, i === halamanAktif);
    }
    buatTombol('›', halamanAktif + 1, halamanAktif === totalHalaman, false);
}

// Jika pencarian, filter, atau jumlah data berubah, kembali ke halaman pertama.
[cariNama, filterPredikat, sortNilai, jumlahBaris].forEach(function (input) {
    input.addEventListener(input === cariNama ? 'input' : 'change', function () {
        halamanAktif = 1;
        tampilkanTabel();
    });
});

document.getElementById('sortKolom').addEventListener('click', function () {
    sortNilai.value = sortNilai.value === 'desc' ? 'asc' : 'desc';
    halamanAktif = 1;
    tampilkanTabel();
});

tampilkanTabel();
</script>
</body>
</html>
