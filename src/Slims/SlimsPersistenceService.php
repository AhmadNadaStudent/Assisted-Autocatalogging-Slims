<?php
namespace AssistedAutocataloguing\Slims;

use PDO;
use Exception;

/**
 * Handles Safe Atomic Storage of Bibliographic Data into SLiMS
 */
class SlimsPersistenceService
{
    private PDO $pdo;
    private mixed $dbs; // SLiMS mysqli object if present in runtime

    public function __construct(PDO $pdo, mixed $dbs = null)
    {
        $this->pdo = $pdo;
        $this->dbs = $dbs;
    }

    /**
     * Check if a bibliographic record with this ISBN already exists
     */
    public function findExistingByIsbn(string $isbn): ?array
    {
        $clean = trim($isbn);
        if (empty($clean)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT biblio_id, title, publish_year FROM biblio WHERE isbn_issn = ? LIMIT 1');
        $stmt->execute([$clean]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Persist mapped bibliographic data into SLiMS
     */
    public function save(array $mappedResult, bool $checkDuplicate = true): array
    {
        if (empty($mappedResult['valid']) || empty($mappedResult['biblio'])) {
            return [
                'success' => false,
                'error' => $mappedResult['error'] ?? 'Data bibliografi tidak valid untuk disimpan',
                'biblio_id' => null,
            ];
        }

        $biblio = $mappedResult['biblio'];

        // Duplicate check
        if ($checkDuplicate && !empty($biblio['isbn_issn'])) {
            $existing = $this->findExistingByIsbn($biblio['isbn_issn']);
            if ($existing) {
                return [
                    'success' => false,
                    'error' => 'Buku dengan ISBN ' . $biblio['isbn_issn'] . ' sudah terdaftar di sistem SLiMS: "' .
                        $existing['title'] . '" (Biblio ID: ' . $existing['biblio_id'] . ').',
                    'biblio_id' => (int)$existing['biblio_id'],
                    'is_duplicate' => true,
                ];
            }
        }

        $this->pdo->beginTransaction();

        try {
            // 1. Insert into `biblio` table
            $columns = array_keys($biblio);
            $placeholders = array_fill(0, count($columns), '?');
            $sql = 'INSERT INTO biblio (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($biblio));

            $biblioId = (int)$this->pdo->lastInsertId();
            if ($biblioId <= 0) {
                throw new Exception('Gagal mendapatkan biblio_id dari database');
            }

            // 2. Insert into `biblio_author` table
            if (!empty($mappedResult['authors'])) {
                $authorStmt = $this->pdo->prepare('INSERT INTO biblio_author (biblio_id, author_id, level) VALUES (?, ?, ?)');
                foreach ($mappedResult['authors'] as $author) {
                    $authorStmt->execute([$biblioId, $author['author_id'], $author['level']]);
                }
            }

            // 3. Insert into `biblio_topic` table
            if (!empty($mappedResult['topics'])) {
                $topicStmt = $this->pdo->prepare('INSERT INTO biblio_topic (biblio_id, topic_id, level) VALUES (?, ?, ?)');
                foreach ($mappedResult['topics'] as $topic) {
                    $topicStmt->execute([$biblioId, $topic['topic_id'], $topic['level']]);
                }
            }

            $this->pdo->commit();

            // 4. Trigger SLiMS indexing engine if available
            $this->triggerSlimsIndexing($biblioId);

            // 5. Trigger SLiMS plugin hooks
            $this->triggerSlimsHook($biblioId, $biblio);

            // 6. Write SLiMS staff audit log if function exists
            $this->writeStaffLog($biblioId, $biblio['title'], $biblio['uid']);

            return [
                'success' => true,
                'biblio_id' => $biblioId,
                'title' => $biblio['title'],
                'message' => 'Data bibliografi berhasil disimpan ke database SLiMS!',
                'error' => null,
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => 'Gagal menyimpan ke basis data: ' . $e->getMessage(),
                'biblio_id' => null,
            ];
        }
    }

    private function triggerSlimsIndexing(int $biblioId): void
    {
        try {
            if (class_exists('biblio_indexer')) {
                $dbConn = $this->dbs;
                if ($dbConn) {
                    $indexer = new \biblio_indexer($dbConn);
                    $indexer->makeIndex($biblioId);
                    $indexer->makeIndexWord($biblioId);
                }
            }
        } catch (Exception $e) {
            // Indexing error should not fail the save transaction
            @error_log('SLiMS Indexer error: ' . $e->getMessage());
        }
    }

    private function triggerSlimsHook(int $biblioId, array $biblioData): void
    {
        try {
            if (class_exists('\\SLiMS\\Plugins')) {
                $data = array_merge($biblioData, ['biblio_id' => $biblioId]);
                \SLiMS\Plugins::getInstance()->execute(\SLiMS\Plugins::BIBLIOGRAPHY_AFTER_SAVE, ['data' => &$data]);
            }
        } catch (Exception $e) {
            @error_log('SLiMS Plugin Hook error: ' . $e->getMessage());
        }
    }

    private function writeStaffLog(int $biblioId, string $title, int $uid): void
    {
        try {
            if (function_exists('writeLog')) {
                $realname = $_SESSION['realname'] ?? 'Staff';
                writeLog('staff', $uid, 'bibliography', "{$realname} insert bibliographic data ({$title}) with biblio_id ({$biblioId}) via Assisted Autocataloguing");
            }
        } catch (Exception $e) {
            // Logging failure ignored
        }
    }
}
