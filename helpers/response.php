<?php
/**
 * Helper response JSON.
 * Semua endpoint memakai fungsi ini supaya format output selalu sama:
 * { "status": bool, "message": string, "data": array|object|null }
 */

if (!function_exists('sendResponse')) {
    function sendResponse($status, $message, $data = null, $httpCode = 200)
    {
        http_response_code($httpCode);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "status"  => $status,
            "message" => $message,
            "data"    => $data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
}
