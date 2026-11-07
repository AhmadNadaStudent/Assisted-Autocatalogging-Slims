<?php
namespace AssistedAutocataloguing\Catalog;

/**
 * Adapter for Indonesia OneSearch (Jejaring Perpustakaan Nasional RI)
 */
class PerpusnasOneSearchAdapter implements CatalogProviderInterface
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $baseUrl = 'https://onesearch.id', int $timeout = 15)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    public function getName(): string
    {
        return 'onesearch';
    }

    public function getLabel(): string
    {
        return 'Indonesia OneSearch (Perpusnas)';
    }

    public function searchByIsbn(string $isbn): array
    {
        $cleanIsbn = IsbnValidator::clean($isbn);
        if (empty($cleanIsbn)) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Format ISBN tidak valid',
            ];
        }

        // 1. Search for records by ISBN
        $searchUrl = $this->baseUrl . '/Search/Results?lookfor=' . urlencode($cleanIsbn) . '&type=ISN';
        $searchHtml = $this->fetchUrl($searchUrl);

        if ($searchHtml === false) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Gagal menghubungi server OneSearch (koneksi timeout / jaringan bermasalah)',
            ];
        }

        // Check if no results were found
        if (stripos($searchHtml, 'Tidak ditemukan') !== false || stripos($searchHtml, 'no-results') !== false) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Tidak ditemukan data katalog untuk ISBN ' . $isbn,
            ];
        }

        // Find record link
        if (!preg_match('#href="(/Record/[a-zA-Z0-9\._\-:]+)"#i', $searchHtml, $matches)) {
            return [
                'found' => false,
                'metadata' => [],
                'source' => $this->getLabel(),
                'raw' => null,
                'error' => 'Data tidak ditemukan di katalog OneSearch',
            ];
        }

        $recordPath = $matches[1];
        $recordUrl = $this->baseUrl . $recordPath;

        // 2. Fetch record details (Staff / MARC / Dublin Core view)
        $detailsUrl = $this->baseUrl . $recordPath . '/Details';
        $detailsHtml = $this->fetchUrl($detailsUrl);

        if ($detailsHtml === false) {
            // Fallback to record main page
            $detailsHtml = $this->fetchUrl($recordUrl);
        }

        $metadata = $this->parseDetails($detailsHtml ?: $searchHtml, $cleanIsbn);

        return [
            'found' => !empty($metadata['title']),
            'metadata' => $metadata,
            'source' => $this->getLabel(),
            'record_url' => $recordUrl,
            'raw' => [
                'record_id' => basename($recordPath),
                'details_url' => $detailsUrl,
            ],
            'error' => empty($metadata['title']) ? 'Gagal mengekstrak metadata dari respons katalog' : null,
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
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return [
            'ok' => ($code >= 200 && $code < 400),
            'http_code' => $code,
            'message' => ($code >= 200 && $code < 400) ? 'Terkoneksi dengan baik' : ($err ?: "HTTP $code"),
        ];
    }

    private function parseDetails(string $html, string $cleanIsbn): array
    {
        $metadata = [
            'title' => '',
            'sor' => '',
            'authors' => [],
            'publisher' => '',
            'publish_place' => '',
            'publish_year' => '',
            'isbn' => $cleanIsbn,
            'edition' => '',
            'collation' => '',
            'series_title' => '',
            'call_number' => '',
            'classification' => '',
            'subjects' => [],
            'notes' => '',
            'language' => 'Indonesia',
        ];

        // 1. Try to parse Dublin Core XML from `fullrecord` if present
        if (preg_match('#&lt;\?xml version=.*?&lt;/dc&gt;#s', $html, $xmlMatches)) {
            $rawXml = html_entity_decode($xmlMatches[0], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $this->parseDublinCoreXml($rawXml, $metadata);
        }

        // 2. Parse from citation table rows or meta tags if any field is still empty
        if (empty($metadata['title'])) {
            if (preg_match('#<th>title</th>\s*<td>(.*?)<br#is', $html, $m)) {
                $metadata['title'] = trim(html_entity_decode(strip_tags($m[1])));
            } elseif (preg_match('#<a class="title"[^>]*>(.*?)</a>#is', $html, $m)) {
                $metadata['title'] = trim(html_entity_decode(strip_tags($m[1])));
            }
        }

        if (empty($metadata['authors'])) {
            if (preg_match_all('#<th>author</th>\s*<td>(.*?)<br#is', $html, $m)) {
                foreach ($m[1] as $authorStr) {
                    $cleaned = trim(html_entity_decode(strip_tags($authorStr)));
                    if (!empty($cleaned)) {
                        $metadata['authors'][] = $cleaned;
                    }
                }
            } elseif (preg_match('#property="author"[^>]*>(.*?)</span>#is', $html, $m)) {
                $cleaned = trim(html_entity_decode(strip_tags($m[1])));
                if (!empty($cleaned)) {
                    $metadata['authors'][] = $cleaned;
                }
            }
        }

        if (empty($metadata['publisher'])) {
            if (preg_match('#<th>publisher</th>\s*<td>(.*?)<br#is', $html, $m)) {
                $metadata['publisher'] = trim(html_entity_decode(strip_tags($m[1])));
            } elseif (preg_match('#property="name"[^>]*>(.*?)</span>#is', $html, $m)) {
                $metadata['publisher'] = trim(html_entity_decode(strip_tags($m[1])));
            }
        }

        if (empty($metadata['publish_year'])) {
            if (preg_match('#<th>publishDate</th>\s*<td>(.*?)<br#is', $html, $m)) {
                $metadata['publish_year'] = trim(strip_tags($m[1]));
            } elseif (preg_match('#property="publicationDate"[^>]*>(.*?)</span>#is', $html, $m)) {
                $metadata['publish_year'] = trim(strip_tags($m[1]));
            }
        }

        if (empty($metadata['subjects'])) {
            if (preg_match_all('#<th>topic</th>\s*<td>(.*?)</td>#is', $html, $m)) {
                foreach ($m[1] as $topicBlock) {
                    $lines = explode('<br', $topicBlock);
                    foreach ($lines as $line) {
                        $c = trim(html_entity_decode(strip_tags($line)));
                        if (!empty($c) && !in_array($c, $metadata['subjects'])) {
                            // Check if line looks like DDC (standard 000-999 or Indonesian Islamic 2X0-2X9)
                            if (preg_match('/^([0-9]{3}|2[xX][0-9])(\.[0-9]+|\s+[0-9]+|\/[0-9]+)?$/', $c)) {
                                if (empty($metadata['classification'])) {
                                    $metadata['classification'] = strtoupper(str_replace([' ', '/'], '.', $c));
                                }
                            } else {
                                $metadata['subjects'][] = $c;
                            }
                        }
                    }
                }
            }
        }

        if (empty($metadata['collation']) && preg_match('#<span class="label[^>]*iconlabel other[^>]*>(.*?)</span>#is', $html, $m)) {
            $metadata['collation'] = trim(html_entity_decode(strip_tags($m[1])));
        }

        if (empty($metadata['notes'])) {
            if (preg_match('#<th>(?:description|summary|abstrak|abstract|catatan)</th>\s*<td>(.*?)</td>#is', $html, $m)) {
                $metadata['notes'] = trim(html_entity_decode(strip_tags($m[1])));
            }
        }

        if (empty($metadata['sor']) && !empty($metadata['authors'])) {
            $metadata['sor'] = implode('; ', $metadata['authors']);
        }

        // Auto-generate standard library call number if classification and author are present
        if (!empty($metadata['classification']) && empty($metadata['call_number'])) {
            $authorName = !empty($metadata['authors'][0]) ? $metadata['authors'][0] : ($metadata['sor'] ?? '');
            $authorCode = !empty($authorName) ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $authorName), 0, 3)) : '';
            $titleCode = !empty($metadata['title']) ? strtolower(substr(preg_replace('/[^a-zA-Z]/', '', $metadata['title']), 0, 1)) : '';
            if (!empty($authorCode) && !empty($titleCode)) {
                $metadata['call_number'] = "{$metadata['classification']} {$authorCode} {$titleCode}";
            }
        }

        return $metadata;
    }

    private function parseDublinCoreXml(string $xmlString, array &$metadata): void
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        if ($xml === false) {
            return;
        }

        if (isset($xml->title) && !empty((string)$xml->title)) {
            $metadata['title'] = trim((string)$xml->title);
        }

        if (isset($xml->creator)) {
            foreach ($xml->creator as $creator) {
                $c = trim((string)$creator);
                if (!empty($c) && !in_array($c, $metadata['authors'])) {
                    $metadata['authors'][] = $c;
                }
            }
        }

        if (isset($xml->publisher) && !empty((string)$xml->publisher)) {
            $metadata['publisher'] = trim((string)$xml->publisher);
        }

        if (isset($xml->date) && !empty((string)$xml->date)) {
            $metadata['publish_year'] = trim((string)$xml->date);
        }

        if (isset($xml->coverage) && !empty((string)$xml->coverage)) {
            $metadata['publish_place'] = trim((string)$xml->coverage);
        }

        if (isset($xml->language) && !empty((string)$xml->language)) {
            $metadata['language'] = trim((string)$xml->language);
        }

        if (isset($xml->description) && !empty((string)$xml->description) && empty($metadata['notes'])) {
            $metadata['notes'] = trim((string)$xml->description);
        }

        if (isset($xml->subject)) {
            foreach ($xml->subject as $sub) {
                $s = trim((string)$sub);
                if (!empty($s) && !in_array($s, $metadata['subjects'])) {
                    if (preg_match('/^([0-9]{3}|2[xX][0-9])(\.[0-9]+|\s+[0-9]+|\/[0-9]+)?$/', $s)) {
                        if (empty($metadata['classification'])) {
                            $metadata['classification'] = strtoupper(str_replace([' ', '/'], '.', $s));
                        }
                    } else {
                        $metadata['subjects'][] = $s;
                    }
                }
            }
        }

        if (isset($xml->type)) {
            foreach ($xml->type as $t) {
                $tStr = trim((string)$t);
                if (stripos($tStr, 'Other:') === 0 && empty($metadata['collation'])) {
                    $metadata['collation'] = trim(substr($tStr, 6));
                }
            }
        }
    }

    private function fetchUrl(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return false;
        }

        return $response;
    }
}
