<?php
namespace AssistedAutocataloguing\Catalog;

/**
 * Service to fetch book previews and covers from Google Books
 */
class GoogleBooksService
{
    private int $timeout;

    public function __construct(int $timeout = 6)
    {
        $this->timeout = $timeout;
    }

    /**
     * Get complete Google Books data (preview links, preview type, cover file)
     *
     * @param string $isbn Cleaned ISBN
     * @param string $saveDir SLiMS images/docs directory
     * @return array
     */
    public function getBookData(string $isbn, string $saveDir): array
    {
        $cleanIsbn = IsbnValidator::clean($isbn);
        if (empty($cleanIsbn)) {
            return [
                'found' => false,
                'message' => 'ISBN tidak valid',
            ];
        }

        // 1. Fetch Google Books Volumes API for synopsis/description & metadata
        $volumesUrl = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$cleanIsbn}&maxResults=1";
        $ch = curl_init($volumesUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $volResp = curl_exec($ch);
        curl_close($ch);

        $description = '';
        $volumeInfo = null;
        if (!empty($volResp)) {
            $volData = @json_decode($volResp, true);
            if (!empty($volData['items'][0]['volumeInfo'])) {
                $volumeInfo = $volData['items'][0]['volumeInfo'];
                $description = trim($volumeInfo['description'] ?? '');
            }
        }

        // 2. Fetch Google Books viewapi metadata for embed & preview
        $viewApiUrl = "https://books.google.com/books?bibkeys=ISBN:{$cleanIsbn}&jscmd=viewapi";
        $ch = curl_init($viewApiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $bookInfo = null;
        if (!empty($resp) && preg_match('/var\s+_GBSBookInfo\s*=\s*(.*?);/s', $resp, $m)) {
            $parsed = @json_decode($m[1], true);
            $key = "ISBN:{$cleanIsbn}";
            if (!empty($parsed[$key])) {
                $bookInfo = $parsed[$key];
            }
        }

        // 3. Fetch and save cover image to SLiMS docs
        $coverFile = $this->fetchAndSaveCover($cleanIsbn, $saveDir);

        if (!$bookInfo && !$coverFile && !$volumeInfo) {
            return [
                'found' => false,
                'message' => 'Pratinjau tidak ditemukan di Google Books untuk ISBN ini.',
            ];
        }

        $previewType = $bookInfo['preview'] ?? ($coverFile ? 'noview' : 'noview');
        $previewUrl = $bookInfo['preview_url'] ?? ($volumeInfo['previewLink'] ?? ("https://books.google.com/books?vid=ISBN" . $cleanIsbn));
        $thumbnailUrl = $bookInfo['thumbnail_url'] ?? ($volumeInfo['imageLinks']['thumbnail'] ?? '');

        return [
            'found' => true,
            'isbn' => $cleanIsbn,
            'description' => $description,
            'preview_type' => $previewType,
            'embeddable' => !empty($bookInfo['embeddable']),
            'preview_url' => $previewUrl,
            'info_url' => $bookInfo['info_url'] ?? ($volumeInfo['infoLink'] ?? $previewUrl),
            'thumbnail_url' => $thumbnailUrl,
            'cover_file' => $coverFile,
            'cover_url' => $coverFile ? ("../images/docs/" . $coverFile) : $thumbnailUrl,
        ];
    }

    /**
     * Download high-res cover image from Google Books
     */
    public function fetchAndSaveCover(string $cleanIsbn, string $saveDir): ?string
    {
        if (!is_dir($saveDir) || !is_writable($saveDir)) {
            return null;
        }

        $url = "https://books.google.com/books/content?vid=isbn{$cleanIsbn}&printsec=frontcover&img=1&zoom=1";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

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
