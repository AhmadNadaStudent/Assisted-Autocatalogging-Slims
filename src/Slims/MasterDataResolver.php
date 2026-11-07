<?php
namespace AssistedAutocataloguing\Slims;

use PDO;

/**
 * Resolves and auto-creates SLiMS master records (Author, Publisher, Place, Topic)
 */
class MasterDataResolver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Resolve Publisher ID by name (case-insensitive)
     */
    public function resolvePublisher(?string $name): ?int
    {
        $name = trim($name ?? '');
        if (empty($name)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT publisher_id FROM mst_publisher WHERE LOWER(publisher_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['publisher_id'];
        }

        // Insert new publisher
        $insert = $this->pdo->prepare('INSERT INTO mst_publisher (publisher_name, input_date, last_update) VALUES (?, CURDATE(), CURDATE())');
        $insert->execute([$name]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Resolve Place ID by name
     */
    public function resolvePlace(?string $name): ?int
    {
        $name = trim($name ?? '');
        if (empty($name)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT place_id FROM mst_place WHERE LOWER(place_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['place_id'];
        }

        $insert = $this->pdo->prepare('INSERT INTO mst_place (place_name, input_date, last_update) VALUES (?, CURDATE(), CURDATE())');
        $insert->execute([$name]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Resolve Author ID by name
     */
    public function resolveAuthor(string $name, string $type = 'p'): ?int
    {
        $name = trim($name);
        if (empty($name)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT author_id FROM mst_author WHERE LOWER(author_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['author_id'];
        }

        $insert = $this->pdo->prepare('INSERT INTO mst_author (author_name, authority_type, input_date, last_update) VALUES (?, ?, CURDATE(), CURDATE())');
        $insert->execute([$name, $type]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Resolve Topic ID by name
     */
    public function resolveTopic(string $topic, string $type = 't'): ?int
    {
        $topic = trim($topic);
        if (empty($topic)) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT topic_id FROM mst_topic WHERE LOWER(topic) = LOWER(?) LIMIT 1');
        $stmt->execute([$topic]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['topic_id'];
        }

        $insert = $this->pdo->prepare('INSERT INTO mst_topic (topic, topic_type, classification, input_date, last_update) VALUES (?, ?, ?, CURDATE(), CURDATE())');
        $insert->execute([$topic, $type, '']);

        return (int)$this->pdo->lastInsertId();
    }
}
