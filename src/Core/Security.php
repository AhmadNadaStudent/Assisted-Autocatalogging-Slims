<?php
namespace AssistedAutocataloguing\Core;

/**
 * Security, Authentication, Authorization & CSRF Management
 */
class Security
{
    private const CSRF_SESSION_KEY = 'aac_csrf_token';

    /**
     * Verify that the current user has permission to read bibliography
     */
    public static function checkReadPermission(): bool
    {
        if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
            return false;
        }

        if ($_SESSION['uid'] == 1) {
            return true;
        }

        if (class_exists('utility') && method_exists('utility', 'havePrivilege')) {
            return \utility::havePrivilege('bibliography', 'r');
        }

        // Fallback for standalone/testing
        return true;
    }

    /**
     * Verify that the current user has permission to write/save bibliography
     */
    public static function checkWritePermission(): bool
    {
        if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
            return false;
        }

        if ($_SESSION['uid'] == 1) {
            return true;
        }

        if (class_exists('utility') && method_exists('utility', 'havePrivilege')) {
            return \utility::havePrivilege('bibliography', 'w');
        }

        return true;
    }

    /**
     * Get or generate a CSRF token for this session
     */
    public static function getCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION[self::CSRF_SESSION_KEY])) {
            $_SESSION[self::CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_SESSION_KEY];
    }

    /**
     * Validate an incoming CSRF token
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($token) || empty($_SESSION[self::CSRF_SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::CSRF_SESSION_KEY], $token);
    }

    /**
     * Sanitize filename to prevent path traversal
     */
    public static function sanitizeFileName(string $filename): string
    {
        $filename = basename($filename);
        // Replace all non-alphanumeric except dots, dashes and underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
        return ltrim($sanitized, '.');
    }

    /**
     * Clean text input from XSS
     */
    public static function cleanInput(mixed $input): mixed
    {
        if (is_array($input)) {
            return array_map([self::class, 'cleanInput'], $input);
        }

        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }

        return $input;
    }
}
