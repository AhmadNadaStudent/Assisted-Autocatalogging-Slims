<?php
namespace AssistedAutocataloguing\Catalog;

/**
 * ISBN Validation and Normalization Utility
 */
class IsbnValidator
{
    /**
     * Clean and remove hyphens, spaces, and formatting
     */
    public static function clean(string $isbn): string
    {
        $clean = strtoupper(trim($isbn));
        return preg_replace('/[^0-9X]/', '', $clean);
    }

    /**
     * Validate ISBN-10 or ISBN-13
     */
    public static function isValid(string $isbn): bool
    {
        $cleaned = self::clean($isbn);
        $len = strlen($cleaned);

        if ($len === 10) {
            return self::isValidIsbn10($cleaned);
        }

        if ($len === 13) {
            return self::isValidIsbn13($cleaned);
        }

        return false;
    }

    /**
     * Validate ISBN-10 check digit
     */
    public static function isValidIsbn10(string $isbn10): bool
    {
        if (strlen($isbn10) !== 10) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            if (!ctype_digit($isbn10[$i])) {
                return false;
            }
            $sum += ((int)$isbn10[$i]) * (10 - $i);
        }

        $last = $isbn10[9];
        $checksum = ($last === 'X') ? 10 : (ctype_digit($last) ? (int)$last : -1);
        if ($checksum === -1) {
            return false;
        }

        $sum += $checksum;
        return ($sum % 11) === 0;
    }

    /**
     * Validate ISBN-13 check digit
     */
    public static function isValidIsbn13(string $isbn13): bool
    {
        if (strlen($isbn13) !== 13 || !ctype_digit($isbn13)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$isbn13[$i];
            $sum += ($i % 2 === 0) ? $digit : ($digit * 3);
        }

        $remainder = $sum % 10;
        $checkDigit = ($remainder === 0) ? 0 : (10 - $remainder);

        return $checkDigit === (int)$isbn13[12];
    }

    /**
     * Convert ISBN-10 to ISBN-13
     */
    public static function toIsbn13(string $isbn): string
    {
        $cleaned = self::clean($isbn);
        if (strlen($cleaned) === 13 && self::isValidIsbn13($cleaned)) {
            return $cleaned;
        }

        if (strlen($cleaned) !== 10 || !self::isValidIsbn10($cleaned)) {
            return $cleaned;
        }

        $core = '978' . substr($cleaned, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$core[$i];
            $sum += ($i % 2 === 0) ? $digit : ($digit * 3);
        }

        $remainder = $sum % 10;
        $checkDigit = ($remainder === 0) ? 0 : (10 - $remainder);

        return $core . $checkDigit;
    }
}
