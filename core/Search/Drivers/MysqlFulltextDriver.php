<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/Drivers/MysqlFulltextDriver.php
| Module    : Search
| Namespace : NetFusion\Core\Search\Drivers
| Purpose   : Native MySQL FULLTEXT search driver with BOOLEAN MODE ranking.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Search\Drivers;

use NetFusion\Core\Search\Contracts\SearchDriverInterface;
use PDO;
use PDOException;

/**
 * Class MysqlFulltextDriver
 *
 * Full-text search engine utilizing MySQL / MariaDB InnoDB FULLTEXT indexes
 * with MATCH ... AGAINST (... IN BOOLEAN MODE) relevance ranking.
 *
 * @package NetFusion\Core\Search\Drivers
 */
class MysqlFulltextDriver implements SearchDriverInterface
{
    /**
     * Active PDO instance.
     */
    protected PDO $pdo;

    /**
     * Central search index table name.
     */
    protected string $table = 'netfusion_search_index';

    /**
     * Cache whether table has been initialized.
     */
    protected bool $tableInitialized = false;

    /**
     * @param PDO    $pdo
     * @param string $table
     */
    public function __construct(PDO $pdo, string $table = 'netfusion_search_index')
    {
        $this->pdo   = $pdo;
        $this->table = $table;
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $index, string $query, array $options = []): array
    {
        $startTime = microtime(true);
        $this->ensureTableExists();

        $limit   = isset($options['limit']) ? (int) $options['limit'] : 15;
        $offset  = isset($options['offset']) ? (int) $options['offset'] : 0;
        $filters = $options['filters'] ?? [];

        $booleanQuery = $this->formatBooleanQuery($query);
        if ($booleanQuery === '') {
            return [
                'hits'  => [],
                'total' => 0,
                'took'  => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        try {
            // Count query
            $countSql = "SELECT COUNT(*) FROM `{$this->table}` 
                         WHERE `index_name` = :index 
                           AND MATCH(`title`, `content`) AGAINST(:query IN BOOLEAN MODE)";
            $stmt = $this->pdo->prepare($countSql);
            $stmt->execute([':index' => $index, ':query' => $booleanQuery]);
            $total = (int) $stmt->fetchColumn();

            // Results query
            $sql = "SELECT `document_id`, MATCH(`title`, `content`) AGAINST(:query IN BOOLEAN MODE) AS score, `data` 
                    FROM `{$this->table}` 
                    WHERE `index_name` = :index 
                      AND MATCH(`title`, `content`) AGAINST(:query IN BOOLEAN MODE) 
                    ORDER BY score DESC 
                    LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':index', $index);
            $stmt->bindValue(':query', $booleanQuery);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            // Fallback to LIKE if FULLTEXT fails
            $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $whereParts = [];
            $params = [':index' => $index];
            foreach ($words as $i => $word) {
                $param = ":w{$i}";
                $whereParts[] = "(`title` LIKE {$param} OR `content` LIKE {$param})";
                $params[$param] = '%' . $word . '%';
            }
            $whereStr = ! empty($whereParts) ? implode(' AND ', $whereParts) : '1=1';

            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `index_name` = :index AND {$whereStr}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT `document_id`, 1.0 AS score, `data` FROM `{$this->table}` WHERE `index_name` = :index AND {$whereStr} LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $hits = [];
        foreach ($rows as $row) {
            $doc = json_decode((string) ($row['data'] ?? '{}'), true) ?: [];
            if (! empty($filters) && ! $this->matchesFilters($doc, $filters)) {
                continue;
            }

            $hits[] = [
                'id'       => $row['document_id'],
                'score'    => round((float) ($row['score'] ?? 1.0), 4),
                'document' => $doc,
            ];
        }

        $took = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'hits'  => $hits,
            'total' => $total,
            'took'  => $took,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function index(string $index, array $documents): void
    {
        $this->ensureTableExists();

        $sql = "REPLACE INTO `{$this->table}` (`id`, `index_name`, `document_id`, `title`, `content`, `data`) 
                VALUES (:id, :index_name, :document_id, :title, :content, :data)";
        $stmt = $this->pdo->prepare($sql);

        $this->pdo->beginTransaction();
        try {
            foreach ($documents as $doc) {
                $docId = (string) ($doc['id'] ?? $doc['ID'] ?? '');
                if ($docId === '') {
                    continue;
                }

                $primaryKey = md5($index . ':' . $docId);
                $title = (string) ($doc['title'] ?? $doc['name'] ?? '');
                $contentParts = [];
                foreach ($doc as $k => $v) {
                    if ($k !== 'id' && $k !== 'title' && $k !== 'name') {
                        if (is_scalar($v)) {
                            $contentParts[] = (string) $v;
                        } elseif (is_array($v)) {
                            $contentParts[] = json_encode($v, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
                $content = implode(' ', $contentParts);
                $jsonData = json_encode($doc, JSON_UNESCAPED_UNICODE);

                $stmt->execute([
                    ':id'          => $primaryKey,
                    ':index_name'  => $index,
                    ':document_id' => $docId,
                    ':title'       => $title,
                    ':content'     => $content,
                    ':data'        => $jsonData,
                ]);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $index, array|int|string $ids): void
    {
        $this->ensureTableExists();
        $idList = is_array($ids) ? $ids : [$ids];
        if (empty($idList)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $sql = "DELETE FROM `{$this->table}` WHERE `index_name` = ? AND `document_id` IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$index], array_map('strval', $idList)));
    }

    /**
     * {@inheritdoc}
     */
    public function flush(string $index): void
    {
        $this->ensureTableExists();
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `index_name` = :index");
        $stmt->execute([':index' => $index]);
    }

    /**
     * {@inheritdoc}
     */
    public function createIndex(string $index, array $fields = []): void
    {
        $this->ensureTableExists();
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(string $index): void
    {
        $this->flush($index);
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(string $index): bool
    {
        $this->ensureTableExists();
        $stmt = $this->pdo->prepare("SELECT 1 FROM `{$this->table}` WHERE `index_name` = :index LIMIT 1");
        $stmt->execute([':index' => $index]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $index): int
    {
        $this->ensureTableExists();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `index_name` = :index");
        $stmt->execute([':index' => $index]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ensure database table and fulltext index exist.
     *
     * @return void
     */
    protected function ensureTableExists(): void
    {
        if ($this->tableInitialized) {
            return;
        }

        $driverName = '';
        try {
            $driverName = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable) {
            // Ignore
        }

        if ($driverName === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS \"{$this->table}\" (
                id TEXT PRIMARY KEY,
                index_name TEXT,
                document_id TEXT,
                title TEXT,
                content TEXT,
                data TEXT,
                created_at TEXT
            );";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `id` VARCHAR(191) NOT NULL PRIMARY KEY,
                `index_name` VARCHAR(64) NOT NULL,
                `document_id` VARCHAR(128) NOT NULL,
                `title` VARCHAR(255) NULL,
                `content` LONGTEXT NULL,
                `data` LONGTEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_nf_index_name` (`index_name`),
                KEY `idx_nf_doc_id` (`index_name`, `document_id`),
                FULLTEXT KEY `ft_nf_search` (`title`, `content`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        }

        try {
            $this->pdo->exec($sql);
        } catch (PDOException) {
            // Ignore if already exists or permission issues
        }

        $this->tableInitialized = true;
    }

    /**
     * Format query string into MySQL BOOLEAN MODE operators.
     *
     * @param string $query
     * @return string
     */
    protected function formatBooleanQuery(string $query): string
    {
        $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($words as $word) {
            $cleaned = preg_replace('/[^\p{L}\p{N}\*]+/u', '', $word);
            if ($cleaned === null || $cleaned === '') {
                continue;
            }
            if (! str_ends_with($cleaned, '*')) {
                $tokens[] = '+' . $cleaned . '*';
            } else {
                $tokens[] = '+' . $cleaned;
            }
        }

        return implode(' ', $tokens);
    }

    /**
     * Check if document matches filters.
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed> $filters
     * @return bool
     */
    protected function matchesFilters(array $document, array $filters): bool
    {
        foreach ($filters as $key => $expected) {
            if (! array_key_exists($key, $document)) {
                return false;
            }

            $val = $document[$key];
            if (is_array($expected)) {
                if (! in_array($val, $expected, false)) {
                    return false;
                }
            } elseif ((string) $val !== (string) $expected) {
                return false;
            }
        }

        return true;
    }
}
