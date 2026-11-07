<?php
namespace AssistedAutocataloguing\Catalog;

/**
 * Service to seamlessly fetch book cover images based on ISBN
 * Uses Google Books content image service without requiring explicit API quotas
 */
class GoogleBooksCoverService
{
    private int $timeout;

    public function __construct(int $timeout = 6)
    {
        $this->timeout = $timeout;
    }

    /**
     * Fetch cover image from Google Books and save to SLiMS docs directory
     *
     * @param string $isbn Cleaned 10 or 13 digit ISBN
     * @param string $saveDir SLiMS images/docs directory path
     * @return string|null Saved filename or null if not available
     */
    public function fetchAndSaveCover(string $isbn, string $saveDir): ?string
    {
        $cleanIsbn = IsbnValidator::clean($isbn);
        if (empty($cleanIsbn)) {
            return null;
        }

        if (!is_dir($saveDir) || !is_writable($saveDir)) {
            return null;
        }

        $url = "https://books.google.com/books/content?vid=isbn{$cleanIsbn}&printsec=frontcover&img=1&zoom=1";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // Valid covers are JPEGs and larger than 2KB (Google's "not available" placeholder is a ~1.2KB PNG)
        if ($httpCode === 200 && strlen($data) > 2000 && stripos($mime, 'image/jpeg') !== false) {
            $filename = 'cover_' . $cleanIsbn . '.jpg';
            $targetPath = rtrim($saveDir, '/\\') . '/' . $filename;
            
            $saved = @file_put_contents($targetPath, $data);
            if ($saved !== false && file_exists($targetPath)) {
                return $filename;
            }
        }

        return null;
    }
}
