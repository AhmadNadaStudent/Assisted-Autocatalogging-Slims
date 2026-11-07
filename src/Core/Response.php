<?php
namespace AssistedAutocataloguing\Core;

/**
 * HTTP Response Handler
 */
class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message, array $data = [], int $statusCode = 200): void
    {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, array $extra = []): void
    {
        $payload = array_merge([
            'status' => 'error',
            'message' => $message,
        ], $extra);

        self::json($payload, $statusCode);
    }
}
