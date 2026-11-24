<?php
/**
 * Endpoint CRUD mahasiswa
 * URL : /uts/api/mahasiswa.php
 *
 * GET    /api/mahasiswa.php          -> semua mahasiswa (LEFT JOIN jurusan)
 * GET    /api/mahasiswa.php?id=1     -> detail satu mahasiswa
 * POST   /api/mahasiswa.php          -> tambah mahasiswa  (body JSON: nama, nim, jurusan_id)
 * PUT    /api/mahasiswa.php?id=1     -> ubah mahasiswa    (body JSON: field yang diubah)
 * DELETE /api/mahasiswa.php?id=1     -> hapus mahasiswa
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";
require_once __DIR__ . "/../helpers/request.php";

setCors("GET, POST, PUT, DELETE");

const SELECT_MAHASISWA = "SELECT m.id, m.nama, m.nim, m.jurusan_id, j.nama_jurusan AS jurusan, m.created_at
                          FROM mahasiswa m
                          LEFT JOIN jurusan j ON m.jurusan_id = j.id";

switch ($_SERVER["REQUEST_METHOD"]) {
    case "GET":
        $id = getId();
        if ($id === null) {
            listMahasiswa($koneksi);
        }
        showMahasiswa($koneksi, $id);

    case "POST":
        createMahasiswa($koneksi);

    case "PUT":
        updateMahasiswa($koneksi, requireId());

    case "DELETE":
        deleteMahasiswa($koneksi, requireId());

    default:
        header("Allow: GET, POST, PUT, DELETE, OPTIONS");
        sendResponse(false, "Method tidak diizinkan", null, 405);
}

// ---------------------------------------------------------------------------

function findMahasiswa($koneksi, $id)
{
    $stmt = mysqli_prepare($koneksi, SELECT_MAHASISWA . " WHERE m.id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function listMahasiswa($koneksi)
{
    $stmt = mysqli_prepare($koneksi, SELECT_MAHASISWA . " ORDER BY m.id DESC");
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }

    $data = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    sendResponse(true, "berhasil", $data, 200);
}

function showMahasiswa($koneksi, $id)
{
    $mhs = findMahasiswa($koneksi, $id);
    if (!$mhs) {
        sendResponse(false, "Mahasiswa dengan id $id tidak ditemukan", null, 404);
    }
    sendResponse(true, "berhasil", $mhs, 200);
}

/**
 * Baca jurusan_id dari input. Boleh null (mahasiswa belum punya jurusan).
 * Kalau diisi, jurusan harus ada di tabel jurusan.
 */
function readJurusanId($koneksi, $input)
{
    $value = $input["jurusan_id"];
    if ($value === null || $value === "") {
        return null;
    }

    $jurusanId = filter_var($value, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($jurusanId === false) {
        sendResponse(false, "Validasi gagal", ["jurusan_id" => "harus berupa angka atau null"], 422);
    }

    $stmt = mysqli_prepare($koneksi, "SELECT id FROM jurusan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $jurusanId);
    mysqli_stmt_execute($stmt);
    $ada = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$ada) {
        sendResponse(false, "Validasi gagal", ["jurusan_id" => "jurusan dengan id $jurusanId tidak ditemukan"], 422);
    }

    return $jurusanId;
}

function failOnDuplicate($koneksi, $stmt)
{
    if (mysqli_stmt_errno($stmt) === 1062) {
        sendResponse(false, "NIM sudah terdaftar", null, 409);
    }
    sendResponse(false, "query gagal: " . mysqli_stmt_error($stmt), null, 500);
}

function createMahasiswa($koneksi)
{
    $input = getInput();
    $data  = validateText($input, ["nama" => 32, "nim" => 32]);
    $jurusanId = array_key_exists("jurusan_id", $input) ? readJurusanId($koneksi, $input) : null;

    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO mahasiswa (jurusan_id, nama, nim, created_at) VALUES (?, ?, ?, CURDATE())"
    );
    mysqli_stmt_bind_param($stmt, "iss", $jurusanId, $data["nama"], $data["nim"]);

    if (!mysqli_stmt_execute($stmt)) {
        failOnDuplicate($koneksi, $stmt);
    }

    $id = mysqli_insert_id($koneksi);
    sendResponse(true, "Mahasiswa berhasil ditambahkan", findMahasiswa($koneksi, $id), 201);
}

function updateMahasiswa($koneksi, $id)
{
    $lama = findMahasiswa($koneksi, $id);
    if (!$lama) {
        sendResponse(false, "Mahasiswa dengan id $id tidak ditemukan", null, 404);
    }

    $input = getInput();
    $data  = validateText($input, ["nama" => 32, "nim" => 32], true);
    $gantiJurusan = array_key_exists("jurusan_id", $input);

    if (!$data && !$gantiJurusan) {
        sendResponse(false, "Tidak ada data yang diubah. Kirim nama, nim, atau jurusan_id", null, 400);
    }

    $nama      = $data["nama"] ?? $lama["nama"];
    $nim       = $data["nim"] ?? $lama["nim"];
    $jurusanId = $gantiJurusan ? readJurusanId($koneksi, $input) : $lama["jurusan_id"];

    $stmt = mysqli_prepare($koneksi, "UPDATE mahasiswa SET jurusan_id = ?, nama = ?, nim = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "issi", $jurusanId, $nama, $nim, $id);

    if (!mysqli_stmt_execute($stmt)) {
        failOnDuplicate($koneksi, $stmt);
    }

    sendResponse(true, "Mahasiswa berhasil diubah", findMahasiswa($koneksi, $id), 200);
}

function deleteMahasiswa($koneksi, $id)
{
    $mhs = findMahasiswa($koneksi, $id);
    if (!$mhs) {
        sendResponse(false, "Mahasiswa dengan id $id tidak ditemukan", null, 404);
    }

    $stmt = mysqli_prepare($koneksi, "DELETE FROM mahasiswa WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        sendResponse(false, "query gagal: " . mysqli_stmt_error($stmt), null, 500);
    }

    sendResponse(true, "Mahasiswa berhasil dihapus", $mhs, 200);
}
