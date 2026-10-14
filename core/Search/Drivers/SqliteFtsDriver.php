<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Search/Drivers/SqliteFtsDriver.php
| Module    : Search
| Namespace : NetFusion\Core\Search\Drivers
| Purpose   : Native SQLite FTS5 / FTS4 full-text search driver with BM25 ranking.
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
 * Class SqliteFtsDriver
 *
 * Full-text search engine utilizing SQLite's native FTS5 / FTS4 virtual tables
 * with Okapi BM25 ranking, unindexed JSON payload storage, and graceful standard
 * table fallback.
 *
 * @package NetFusion\Core\Search\Drivers
 */
class SqliteFtsDriver implements SearchDriverInterface
{
    /**
     * Active PDO instance.
     */
    protected PDO $pdo;

    /**
     * Detected search module type ('fts5', 'fts4', or 'like').
     *
     * @var array<string, string>
     */
    protected array $moduleType = [];

    /**
     * @param PDO|string|null $pdoOrPath PDO instance or path to SQLite file (defaults to :memory:)
     */
    public function __construct(PDO|string|null $pdoOrPath = null)
    {
        if ($pdoOrPath instanceof PDO) {
            $this->pdo = $pdoOrPath;
        } elseif (is_string($pdoOrPath) && $pdoOrPath !== '') {
            $dir = dirname($pdoOrPath);
            if (! is_dir($dir) && $dir !== '.' && $dir !== ':memory:') {
                @mkdir($dir, 0755, true);
            }
            $this->pdo = new PDO('sqlite:' . $pdoOrPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $this->pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $index, string $query, array $options = []): array
    {
        $startTime = microtime(true);
        $this->ensureTableExists($index);

        $table = $this->tableName($index);
        $module = $this->moduleType[$index] ?? 'fts5';
        $limit = isset($options['limit']) ? (int) $options['limit'] : 15;
        $offset = isset($options['offset']) ? (int) $options['offset'] : 0;
        $filters = $options['filters'] ?? [];

        $sanitizedQuery = $this->sanitizeFtsQuery($query);
        if ($sanitizedQuery === '') {
            return [
                'hits'  => [],
                'total' => 0,
                'took'  => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        try {
            if ($module === 'fts5') {
                $countSql = "SELECT COUNT(*) FROM \"{$table}\" WHERE \"{$table}\" MATCH :query";
                $stmt = $this->pdo->prepare($countSql);
                $stmt->execute([':query' => $sanitizedQuery]);
                $total = (int) $stmt->fetchColumn();

                $sql = "SELECT id, -bm25(\"{$table}\") AS score, data 
                        FROM \"{$table}\" 
                        WHERE \"{$table}\" MATCH :query 
                        ORDER BY score DESC 
                        LIMIT :limit OFFSET :offset";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':query', $sanitizedQuery);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } elseif ($module === 'fts4') {
                $countSql = "SELECT COUNT(*) FROM \"{$table}\" WHERE \"{$table}\" MATCH :query";
                $stmt = $this->pdo->prepare($countSql);
                $stmt->execute([':query' => $sanitizedQuery]);
                $total = (int) $stmt->fetchColumn();

                $sql = "SELECT id, 1.0 AS score, data 
                        FROM \"{$table}\" 
                        WHERE \"{$table}\" MATCH :query 
                        LIMIT :limit OFFSET :offset";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':query', $sanitizedQuery);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                // LIKE Fallback
                $words = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $whereClauses = [];
                $params = [];
                foreach ($words as $i => $word) {
                    $param = ":w{$i}";
                    $whereClauses[] = "(title LIKE {$param} OR content LIKE {$param})";
                    $params[$param] = '%' . $word . '%';
                }
                $whereStr = ! empty($whereClauses) ? implode(' AND ', $whereClauses) : '1=1';

                $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM \"{$table}\" WHERE {$whereStr}");
                $countStmt->execute($params);
                $total = (int) $countStmt->fetchColumn();

                $sql = "SELECT id, 1.0 AS score, data FROM \"{$table}\" WHERE {$whereStr} LIMIT :limit OFFSET :offset";
                $stmt = $this->pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (PDOException $e) {
            return [
                'hits'  => [],
                'total' => 0,
                'took'  => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        $hits = [];
        foreach ($rows as $row) {
            $doc = json_decode((string) ($row['data'] ?? '{}'), true) ?: [];
            // Apply filter check if provided
            if (! empty($filters) && ! $this->matchesFilters($doc, $filters)) {
                continue;
            }

            $hits[] = [
                'id'       => $row['id'],
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
        $this->ensureTableExists($index);
        $table = $this->tableName($index);

        $this->pdo->beginTransaction();
        try {
            $deleteStmt = $this->pdo->prepare("DELETE FROM \"{$table}\" WHERE id = :id");
            $insertStmt = $this->pdo->prepare(
                "INSERT INTO \"{$table}\" (id, title, content, data) VALUES (:id, :title, :content, :data)"
            );

            foreach ($documents as $doc) {
                $id = (string) ($doc['id'] ?? $doc['ID'] ?? '');
                if ($id === '') {
                    continue;
                }

                // Extract title & text content
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

                $deleteStmt->execute([':id' => $id]);
                $insertStmt->execute([
                    ':id'      => $id,
                    ':title'   => $title,
                    ':content' => $content,
                    ':data'    => $jsonData,
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
        $this->ensureTableExists($index);
        $table = $this->tableName($index);
        $idList = is_array($ids) ? $ids : [$ids];
        if (empty($idList)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM \"{$table}\" WHERE id IN ({$placeholders})");
        $stmt->execute(array_values($idList));
    }

    /**
     * {@inheritdoc}
     */
    public function flush(string $index): void
    {
        $this->ensureTableExists($index);
        $table = $this->tableName($index);
        $this->pdo->exec("DELETE FROM \"{$table}\"");
    }

    /**
     * {@inheritdoc}
     */
    public function createIndex(string $index, array $fields = []): void
    {
        $this->ensureTableExists($index);
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(string $index): void
    {
        $table = $this->tableName($index);
        $this->pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
        unset($this->moduleType[$index]);
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex(string $index): bool
    {
        $table = $this->tableName($index);
        $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type IN ('table', 'virtual') AND name = :name");
        $stmt->execute([':name' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * {@inheritdoc}
     */
    public function count(string $index): int
    {
        if (! $this->hasIndex($index)) {
            return 0;
        }

        $table = $this->tableName($index);
        return (int) $this->pdo->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();
    }

    /**
     * Get underlying PDO connection.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Ensure virtual table exists with best available SQLite engine.
     *
     * @param string $index
     * @return void
     */
    protected function ensureTableExists(string $index): void
    {
        if (isset($this->moduleType[$index])) {
            return;
        }

        $table = $this->tableName($index);

        // 1. Try SQLite FTS5
        try {
            $this->pdo->exec(
                "CREATE VIRTUAL TABLE IF NOT EXISTS \"{$table}\" USING fts5(
                    id UNINDEXED,
                    title,
                    content,
                    data UNINDEXED,
                    tokenize='unicode61'
                )"
            );
            $this->moduleType[$index] = 'fts5';
            return;
        } catch (PDOException) {
            // FTS5 not available, try FTS4
        }

        // 2. Try SQLite FTS4
        try {
            $this->pdo->exec(
                "CREATE VIRTUAL TABLE IF NOT EXISTS \"{$table}\" USING fts4(
                    id,
                    title,
                    content,
                    data
                )"
            );
            $this->moduleType[$index] = 'fts4';
            return;
        } catch (PDOException) {
            // FTS4 not available, fall back to standard table
        }

        // 3. Fallback Standard Table
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                id TEXT PRIMARY KEY,
                title TEXT,
                content TEXT,
                data TEXT
            )"
        );
        $this->moduleType[$index] = 'like';
    }

    /**
     * Get sanitized internal table name for the index.
     *
     * @param string $index
     * @return string
     */
    protected function tableName(string $index): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $index);
        return 'nf_search_' . $clean;
    }

    /**
     * Sanitize user search query for FTS MATCH syntax.
     *
     * @param string $query
     * @return string
     */
    protected function sanitizeFtsQuery(string $query): string
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $clean = preg_replace('/[^\p{L}\p{N}\*]+/u', ' ', $normalized);
        if ($clean === null || $clean === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];

        foreach ($words as $word) {
            $trimmed = trim($word);
            if ($trimmed === '' || $trimmed === '*') {
                continue;
            }
            if (str_ends_with($trimmed, '*')) {
                $base = rtrim($trimmed, '*');
                if ($base !== '') {
                    $parts[] = '"' . str_replace('"', '""', $base) . '"*';
                }
            } else {
                $parts[] = '"' . str_replace('"', '""', $trimmed) . '"*';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Check if a document matches the given key-value filters.
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
