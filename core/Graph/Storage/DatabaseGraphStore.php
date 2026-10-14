<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Graph\Storage;

use NetFusion\Core\Graph\Contracts\GraphStoreInterface;
use NetFusion\Core\Graph\Contracts\NodeInterface;
use NetFusion\Core\Graph\Contracts\EdgeInterface;
use NetFusion\Core\Graph\Models\Node;
use NetFusion\Core\Graph\Models\Edge;
use NetFusion\Core\Graph\Exceptions\NodeNotFoundException;
use PDO;

class DatabaseGraphStore implements GraphStoreInterface
{
    public function __construct(
        protected PDO $pdo,
        protected string $nodesTable = 'nf_graph_nodes',
        protected string $edgesTable = 'nf_graph_edges'
    ) {
    }

    public function addNode(NodeInterface $node): static
    {
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO {$this->nodesTable} (id, labels, properties, updated_at)
            VALUES (:id, :labels, :properties, :updated_at)
        ");

        $stmt->execute([
            ':id' => $node->getId(),
            ':labels' => json_encode($node->getLabels(), JSON_THROW_ON_ERROR),
            ':properties' => json_encode($node->getProperties(), JSON_THROW_ON_ERROR),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this;
    }

    public function getNode(string $id): ?NodeInterface
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->nodesTable} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $labels = json_decode((string)$row['labels'], true) ?: [];
        $properties = json_decode((string)$row['properties'], true) ?: [];

        return new Node($row['id'], $labels, $properties);
    }

    public function hasNode(string $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->nodesTable} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    public function removeNode(string $id): bool
    {
        if (!$this->hasNode($id)) {
            return false;
        }

        // Delete connected edges
        $delEdges = $this->pdo->prepare("DELETE FROM {$this->edgesTable} WHERE source_id = :id OR target_id = :id");
        $delEdges->execute([':id' => $id]);

        $stmt = $this->pdo->prepare("DELETE FROM {$this->nodesTable} WHERE id = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function addEdge(EdgeInterface $edge): static
    {
        if (!$this->hasNode($edge->getSourceId())) {
            throw new NodeNotFoundException($edge->getSourceId());
        }
        if (!$this->hasNode($edge->getTargetId())) {
            throw new NodeNotFoundException($edge->getTargetId());
        }

        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO {$this->edgesTable} (id, source_id, target_id, type, directed, weight, properties, updated_at)
            VALUES (:id, :source_id, :target_id, :type, :directed, :weight, :properties, :updated_at)
        ");

        $stmt->execute([
            ':id' => $edge->getId(),
            ':source_id' => $edge->getSourceId(),
            ':target_id' => $edge->getTargetId(),
            ':type' => $edge->getType(),
            ':directed' => $edge->isDirected() ? 1 : 0,
            ':weight' => $edge->getWeight(),
            ':properties' => json_encode($edge->getProperties(), JSON_THROW_ON_ERROR),
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this;
    }

    public function getEdge(string $id): ?EdgeInterface
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->edgesTable} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateEdge($row);
    }

    public function hasEdge(string $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->edgesTable} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    public function removeEdge(string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->edgesTable} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function getOutEdges(string $nodeId, ?string $type = null): array
    {
        if ($type !== null) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->edgesTable}
                WHERE (source_id = :id OR (target_id = :id AND directed = 0)) AND type = :type
            ");
            $stmt->execute([':id' => $nodeId, ':type' => $type]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->edgesTable}
                WHERE source_id = :id OR (target_id = :id AND directed = 0)
            ");
            $stmt->execute([':id' => $nodeId]);
        }

        $edges = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $edges[] = $this->hydrateEdge($row);
        }
        return $edges;
    }

    public function getInEdges(string $nodeId, ?string $type = null): array
    {
        if ($type !== null) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->edgesTable}
                WHERE (target_id = :id OR (source_id = :id AND directed = 0)) AND type = :type
            ");
            $stmt->execute([':id' => $nodeId, ':type' => $type]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$this->edgesTable}
                WHERE target_id = :id OR (source_id = :id AND directed = 0)
            ");
            $stmt->execute([':id' => $nodeId]);
        }

        $edges = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $edges[] = $this->hydrateEdge($row);
        }
        return $edges;
    }

    public function getAdjacentNodes(string $nodeId, ?string $type = null, string $direction = 'both'): array
    {
        $edges = [];
        if ($direction === 'out' || $direction === 'both') {
            $edges = array_merge($edges, $this->getOutEdges($nodeId, $type));
        }
        if ($direction === 'in' || $direction === 'both') {
            $edges = array_merge($edges, $this->getInEdges($nodeId, $type));
        }

        $neighborIds = [];
        foreach ($edges as $edge) {
            $otherId = ($edge->getSourceId() === $nodeId) ? $edge->getTargetId() : $edge->getSourceId();
            $neighborIds[$otherId] = true;
        }

        $nodes = [];
        foreach (array_keys($neighborIds) as $nId) {
            $n = $this->getNode($nId);
            if ($n !== null) {
                $nodes[] = $n;
            }
        }
        return $nodes;
    }

    public function getNodes(?string $label = null): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->nodesTable}");
        $nodes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels = json_decode((string)$row['labels'], true) ?: [];
            if ($label === null || in_array($label, $labels, true)) {
                $properties = json_decode((string)$row['properties'], true) ?: [];
                $nodes[$row['id']] = new Node($row['id'], $labels, $properties);
            }
        }
        return $nodes;
    }

    public function getEdges(?string $type = null): array
    {
        if ($type !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->edgesTable} WHERE type = :type");
            $stmt->execute([':type' => $type]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM {$this->edgesTable}");
        }

        $edges = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $edges[$row['id']] = $this->hydrateEdge($row);
        }
        return $edges;
    }

    public function countNodes(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->nodesTable}");
        return (int)$stmt->fetchColumn();
    }

    public function countEdges(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->edgesTable}");
        return (int)$stmt->fetchColumn();
    }

    public function clear(): void
    {
        $this->pdo->exec("DELETE FROM {$this->edgesTable}");
        $this->pdo->exec("DELETE FROM {$this->nodesTable}");
    }

    protected function hydrateEdge(array $row): EdgeInterface
    {
        $properties = json_decode((string)$row['properties'], true) ?: [];
        return new Edge(
            id: (string)$row['id'],
            sourceId: (string)$row['source_id'],
            targetId: (string)$row['target_id'],
            type: (string)$row['type'],
            directed: (bool)$row['directed'],
            weight: (float)$row['weight'],
            properties: $properties
        );
    }
}
