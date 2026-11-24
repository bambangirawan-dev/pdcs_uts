<?php
/**
 * Helper untuk membaca request: CORS, body JSON, parameter id, dan validasi.
 */

require_once __DIR__ . "/response.php";

/**
 * Header CORS supaya API bisa dipanggil dari frontend lain.
 * Request OPTIONS (preflight) langsung dijawab 204.
 */
function setCors($allowedMethods)
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: " . $allowedMethods . ", OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
        http_response_code(204);
        exit();
    }
}

/**
 * Ambil body request sebagai array.
 * Mendukung JSON (Content-Type: application/json) dan form biasa.
 */
function getInput()
{
    $raw = file_get_contents("php://input");

    if ($raw !== "" && $raw !== false) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        if (!empty($_POST)) {
            return $_POST;
        }
        sendResponse(false, "Body harus berupa JSON yang valid", null, 400);
    }

    return $_POST;
}

/**
 * Ambil ?id= dari URL. Hasilnya null kalau tidak ada.
 * Kalau ada tapi bukan angka positif, langsung 400.
 */
function getId()
{
    if (!isset($_GET["id"]) || $_GET["id"] === "") {
        return null;
    }

    $id = filter_var($_GET["id"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($id === false) {
        sendResponse(false, "Parameter id harus berupa angka positif", null, 400);
    }

    return $id;
}

/**
 * Wajib ada ?id= (dipakai untuk PUT dan DELETE).
 */
function requireId()
{
    $id = getId();
    if ($id === null) {
        sendResponse(false, "Parameter id wajib diisi, contoh: ?id=1", null, 400);
    }
    return $id;
}

/**
 * Cek field teks wajib dan panjang maksimalnya.
 * $rules contoh: ["nama" => 32, "nim" => 32]
 * Kalau $partial = true (PUT), field yang tidak dikirim dilewati.
 * Hasil: array field yang sudah di-trim.
 */
function validateText($input, $rules, $partial = false)
{
    $errors = [];
    $clean  = [];

    foreach ($rules as $field => $maxLength) {
        if (!array_key_exists($field, $input)) {
            if (!$partial) {
                $errors[$field] = "wajib diisi";
            }
            continue;
        }

        if (!is_scalar($input[$field])) {
            $errors[$field] = "harus berupa teks";
            continue;
        }

        $value = trim((string) $input[$field]);
        if ($value === "") {
            $errors[$field] = "tidak boleh kosong";
        } elseif (mb_strlen($value) > $maxLength) {
            $errors[$field] = "maksimal $maxLength karakter";
        } else {
            $clean[$field] = $value;
        }
    }

    if ($errors) {
        sendResponse(false, "Validasi gagal", $errors, 422);
    }

    return $clean;
}
