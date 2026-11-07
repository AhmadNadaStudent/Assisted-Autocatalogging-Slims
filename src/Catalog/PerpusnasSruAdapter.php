<?php
namespace AssistedAutocataloguing\Catalog;

/**
 * Adapter for Perpusnas OPAC SRU Service
 */
class PerpusnasSruAdapter implements CatalogProviderInterface
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $baseUrl = 'https://opac.perpusnas.go.id/sru.aspx', int $timeout = 10)
    {
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
    }

    public function getName(): string
    {
        return 'perpusnas_sru';
    }

    public function getLabel(): string
    {
        return 'Perpusnas OPAC SRU';
    }

    public function searchByIsbn(string $isbn): array
    {
        $cleanIsbn = IsbnValidator::clean($isbn);
        $url = $this->baseUrl . '?version=1.1&operation=searchRetrieve&query=' . urlencode($cleanIsbn) . '&maximumRecords=1';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 403 || str_contains($response ?: '', 'Just a moment...') || str_contains($response ?: '', 'cf_chl')) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Layanan OPAC SRU Perpusnas dibatasi oleh proteksi Cloudflare (HTTP 403). Gunakan sumber OneSearch.',
            ];
        }

        if ($response === false || $httpCode >= 400) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Koneksi ke Perpusnas OPAC SRU gagal: ' . ($error ?: "HTTP $httpCode"),
            ];
        }

        // Parse XML MARC/SRU if available
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Format XML SRU tidak valid',
            ];
        }

        // Check records count
        $namespaces = $xml->getNamespaces(true);
        $records = $xml->xpath('//recordData') ?: $xml->xpath('//zs:recordData');
        if (empty($records)) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Data tidak ditemukan di OPAC SRU',
            ];
        }

        return [
            'found' => true,
            'metadata' => [
                'isbn' => $cleanIsbn,
            ],
            'source' => $this->getLabel(),
            'raw' => $response,
            'error' => null,
        ];
    }

    public function testConnection(): array
    {
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isCf = str_contains($res ?: '', 'Just a moment...') || str_contains($res ?: '', 'cf_chl');

        return [
            'ok' => ($code >= 200 && $code < 400 && !$isCf),
            'http_code' => $code,
            'message' => $isCf ? 'Terproteksi Cloudflare Bot Challenge (HTTP 403)' : (($code >= 200 && $code < 400) ? 'Terkoneksi' : "HTTP $code"),
        ];
    }
}
