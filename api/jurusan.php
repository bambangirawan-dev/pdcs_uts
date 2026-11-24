<?php
/**
 * Endpoint CRUD jurusan (tugas UTS)
 * URL : /uts/api/jurusan.php
 *
 * GET    /api/jurusan.php           -> semua jurusan + jumlah mahasiswa & mata kuliah
 * GET    /api/jurusan.php?id=1      -> detail jurusan + daftar mahasiswa & mata kuliahnya
 * POST   /api/jurusan.php           -> tambah jurusan (body JSON: kode_jurusan, nama_jurusan, fakultas)
 * PUT    /api/jurusan.php?id=1      -> ubah jurusan   (body JSON: field yang diubah)
 * DELETE /api/jurusan.php?id=1      -> hapus jurusan
 *
 * Aturan foreign key saat jurusan dihapus:
 * - mahasiswa.jurusan_id menjadi NULL (ON DELETE SET NULL)
 * - matakuliah milik jurusan ikut terhapus (ON DELETE CASCADE)
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";
require_once __DIR__ . "/../helpers/request.php";

setCors("GET, POST, PUT, DELETE");

const JURUSAN_RULES = ["kode_jurusan" => 32, "nama_jurusan" => 64, "fakultas" => 32];

switch ($_SERVER["REQUEST_METHOD"]) {
    case "GET":
        $id = getId();
        if ($id === null) {
            listJurusan($koneksi);
        }
        showJurusan($koneksi, $id);

    case "POST":
        createJurusan($koneksi);

    case "PUT":
        updateJurusan($koneksi, requireId());

    case "DELETE":
        deleteJurusan($koneksi, requireId());

    default:
        header("Allow: GET, POST, PUT, DELETE, OPTIONS");
        sendResponse(false, "Method tidak diizinkan", null, 405);
}

// ---------------------------------------------------------------------------

function fetchAllPrepared($koneksi, $sql, $types = "", ...$params)
{
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }
    if ($types !== "") {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        sendResponse(false, "query gagal: " . mysqli_stmt_error($stmt), null, 500);
    }

    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function findJurusan($koneksi, $id)
{
    $rows = fetchAllPrepared(
        $koneksi,
        "SELECT id, kode_jurusan, nama_jurusan, fakultas FROM jurusan WHERE id = ?",
        "i",
        $id
    );
    return $rows[0] ?? null;
}

function listJurusan($koneksi)
{
    $data = fetchAllPrepared(
        $koneksi,
        "SELECT j.id, j.kode_jurusan, j.nama_jurusan, j.fakultas,
                (SELECT COUNT(*) FROM mahasiswa m WHERE m.jurusan_id = j.id) AS jumlah_mahasiswa,
                (SELECT COUNT(*) FROM matakuliah mk WHERE mk.jurusan_id = j.id) AS jumlah_matakuliah
         FROM jurusan j
         ORDER BY j.id DESC"
    );
    sendResponse(true, "berhasil", $data, 200);
}

function showJurusan($koneksi, $id)
{
    $jurusan = findJurusan($koneksi, $id);
    if (!$jurusan) {
        sendResponse(false, "Jurusan dengan id $id tidak ditemukan", null, 404);
    }

    $jurusan["mahasiswa"] = fetchAllPrepared(
        $koneksi,
        "SELECT id, nama, nim, created_at FROM mahasiswa WHERE jurusan_id = ? ORDER BY id",
        "i",
        $id
    );
    $jurusan["matakuliah"] = fetchAllPrepared(
        $koneksi,
        "SELECT id, kode_mk, nama_mk, sks, semester FROM matakuliah WHERE jurusan_id = ? ORDER BY semester, id",
        "i",
        $id
    );

    sendResponse(true, "berhasil", $jurusan, 200);
}

function failOnDuplicateKode($stmt)
{
    if (mysqli_stmt_errno($stmt) === 1062) {
        sendResponse(false, "Kode jurusan sudah dipakai", null, 409);
    }
    sendResponse(false, "query gagal: " . mysqli_stmt_error($stmt), null, 500);
}

function createJurusan($koneksi)
{
    $data = validateText(getInput(), JURUSAN_RULES);

    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO jurusan (kode_jurusan, nama_jurusan, fakultas) VALUES (?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "sss", $data["kode_jurusan"], $data["nama_jurusan"], $data["fakultas"]);

    if (!mysqli_stmt_execute($stmt)) {
        failOnDuplicateKode($stmt);
    }

    $id = mysqli_insert_id($koneksi);
    sendResponse(true, "Jurusan berhasil ditambahkan", findJurusan($koneksi, $id), 201);
}

function updateJurusan($koneksi, $id)
{
    $lama = findJurusan($koneksi, $id);
    if (!$lama) {
        sendResponse(false, "Jurusan dengan id $id tidak ditemukan", null, 404);
    }

    $data = validateText(getInput(), JURUSAN_RULES, true);
    if (!$data) {
        sendResponse(false, "Tidak ada data yang diubah. Kirim kode_jurusan, nama_jurusan, atau fakultas", null, 400);
    }

    $baru = array_merge($lama, $data);

    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE jurusan SET kode_jurusan = ?, nama_jurusan = ?, fakultas = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, "sssi", $baru["kode_jurusan"], $baru["nama_jurusan"], $baru["fakultas"], $id);

    if (!mysqli_stmt_execute($stmt)) {
        failOnDuplicateKode($stmt);
    }

    sendResponse(true, "Jurusan berhasil diubah", findJurusan($koneksi, $id), 200);
}

function deleteJurusan($koneksi, $id)
{
    $jurusan = findJurusan($koneksi, $id);
    if (!$jurusan) {
        sendResponse(false, "Jurusan dengan id $id tidak ditemukan", null, 404);
    }

    // Hitung dulu data terkait supaya response menjelaskan efek foreign key.
    $terkait = fetchAllPrepared(
        $koneksi,
        "SELECT (SELECT COUNT(*) FROM mahasiswa WHERE jurusan_id = ?) AS mahasiswa_tanpa_jurusan,
                (SELECT COUNT(*) FROM matakuliah WHERE jurusan_id = ?) AS matakuliah_terhapus",
        "ii",
        $id,
        $id
    )[0];

    $stmt = mysqli_prepare($koneksi, "DELETE FROM jurusan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        sendResponse(false, "query gagal: " . mysqli_stmt_error($stmt), null, 500);
    }

    $jurusan["dampak"] = $terkait;
    sendResponse(true, "Jurusan berhasil dihapus", $jurusan, 200);
}
