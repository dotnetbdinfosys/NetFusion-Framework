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

namespace NetFusion\Core\Experimentation\Store;

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Experimentation\Canary\CanaryRollout;
use NetFusion\Core\Experimentation\Contracts\ExperimentStoreInterface;
use NetFusion\Core\Experimentation\Conversion;
use NetFusion\Core\Experimentation\Experiment;
use NetFusion\Core\Experimentation\Exposure;
use NetFusion\Core\Experimentation\Segment\Segment;
use NetFusion\Core\Experimentation\Variant;
use PDO;

class DatabaseExperimentStore implements ExperimentStoreInterface
{
    protected PDO $pdo;

    public function __construct(PDO|Connection $connection)
    {
        $this->pdo = $connection instanceof Connection ? $connection->getPdo() : $connection;
    }

    public function saveExperiment(Experiment $experiment): bool
    {
        $variantsJson = json_encode(array_map(fn(Variant $v) => $v->toArray(), $experiment->variants));
        $segmentJson = $experiment->segment !== null ? json_encode($experiment->segment->toArray()) : null;
        $goalsJson = json_encode($experiment->goals);
        $metaJson = json_encode($experiment->metadata);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO netfusion_experiments (
                `key`, name, status, traffic_allocation, winner_variant, fallback_variant,
                variants_json, segment_json, goals_json, metadata_json, created_at, updated_at
            ) VALUES (
                :key, :name, :status, :traffic_allocation, :winner_variant, :fallback_variant,
                :variants_json, :segment_json, :goals_json, :metadata_json, :created_at, :updated_at
            )
            ON CONFLICT(`key`) DO UPDATE SET
                name = excluded.name,
                status = excluded.status,
                traffic_allocation = excluded.traffic_allocation,
                winner_variant = excluded.winner_variant,
                fallback_variant = excluded.fallback_variant,
                variants_json = excluded.variants_json,
                segment_json = excluded.segment_json,
                goals_json = excluded.goals_json,
                metadata_json = excluded.metadata_json,
                updated_at = excluded.updated_at
        ");

        try {
            return $stmt->execute([
                ':key' => $experiment->key,
                ':name' => $experiment->name,
                ':status' => $experiment->status,
                ':traffic_allocation' => $experiment->trafficAllocation,
                ':winner_variant' => $experiment->winnerVariant,
                ':fallback_variant' => $experiment->fallbackVariant,
                ':variants_json' => $variantsJson,
                ':segment_json' => $segmentJson,
                ':goals_json' => $goalsJson,
                ':metadata_json' => $metaJson,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        } catch (\Throwable) {
            // MySQL fallback for ON CONFLICT
            $mysqlStmt = $this->pdo->prepare("
                REPLACE INTO netfusion_experiments (
                    `key`, name, status, traffic_allocation, winner_variant, fallback_variant,
                    variants_json, segment_json, goals_json, metadata_json, created_at, updated_at
                ) VALUES (
                    :key, :name, :status, :traffic_allocation, :winner_variant, :fallback_variant,
                    :variants_json, :segment_json, :goals_json, :metadata_json, :created_at, :updated_at
                )
            ");
            return $mysqlStmt->execute([
                ':key' => $experiment->key,
                ':name' => $experiment->name,
                ':status' => $experiment->status,
                ':traffic_allocation' => $experiment->trafficAllocation,
                ':winner_variant' => $experiment->winnerVariant,
                ':fallback_variant' => $experiment->fallbackVariant,
                ':variants_json' => $variantsJson,
                ':segment_json' => $segmentJson,
                ':goals_json' => $goalsJson,
                ':metadata_json' => $metaJson,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    public function getExperiment(string $key): ?Experiment
    {
        $stmt = $this->pdo->prepare("SELECT * FROM netfusion_experiments WHERE `key` = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrateExperiment($row);
    }

    public function getAllExperiments(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM netfusion_experiments ORDER BY created_at DESC");
        $experiments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $experiments[] = $this->hydrateExperiment($row);
        }
        return $experiments;
    }

    public function deleteExperiment(string $key): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM netfusion_experiments WHERE `key` = :key");
        return $stmt->execute([':key' => $key]);
    }

    public function saveCanary(CanaryRollout $canary): bool
    {
        $stagesJson = json_encode($canary->stages);
        $segmentJson = $canary->segment !== null ? json_encode($canary->segment->toArray()) : null;
        $metaJson = json_encode($canary->metadata);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO netfusion_experiment_canaries (
                flag_key, percentage, stages_json, segment_json, error_threshold,
                kill_switch_triggered, metadata_json, updated_at
            ) VALUES (
                :flag_key, :percentage, :stages_json, :segment_json, :error_threshold,
                :kill_switch_triggered, :metadata_json, :updated_at
            )
            ON CONFLICT(flag_key) DO UPDATE SET
                percentage = excluded.percentage,
                stages_json = excluded.stages_json,
                segment_json = excluded.segment_json,
                error_threshold = excluded.error_threshold,
                kill_switch_triggered = excluded.kill_switch_triggered,
                metadata_json = excluded.metadata_json,
                updated_at = excluded.updated_at
        ");

        try {
            return $stmt->execute([
                ':flag_key' => $canary->flagKey,
                ':percentage' => $canary->percentage,
                ':stages_json' => $stagesJson,
                ':segment_json' => $segmentJson,
                ':error_threshold' => $canary->errorThresholdPercent,
                ':kill_switch_triggered' => $canary->killSwitchTriggered ? 1 : 0,
                ':metadata_json' => $metaJson,
                ':updated_at' => $now,
            ]);
        } catch (\Throwable) {
            $mysqlStmt = $this->pdo->prepare("
                REPLACE INTO netfusion_experiment_canaries (
                    flag_key, percentage, stages_json, segment_json, error_threshold,
                    kill_switch_triggered, metadata_json, updated_at
                ) VALUES (
                    :flag_key, :percentage, :stages_json, :segment_json, :error_threshold,
                    :kill_switch_triggered, :metadata_json, :updated_at
                )
            ");
            return $mysqlStmt->execute([
                ':flag_key' => $canary->flagKey,
                ':percentage' => $canary->percentage,
                ':stages_json' => $stagesJson,
                ':segment_json' => $segmentJson,
                ':error_threshold' => $canary->errorThresholdPercent,
                ':kill_switch_triggered' => $canary->killSwitchTriggered ? 1 : 0,
                ':metadata_json' => $metaJson,
                ':updated_at' => $now,
            ]);
        }
    }

    public function getCanary(string $flagKey): ?CanaryRollout
    {
        $stmt = $this->pdo->prepare("SELECT * FROM netfusion_experiment_canaries WHERE flag_key = :key LIMIT 1");
        $stmt->execute([':key' => $flagKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $segment = !empty($row['segment_json']) ? Segment::fromArray(json_decode($row['segment_json'], true)) : null;

        return new CanaryRollout(
            flagKey: (string)$row['flag_key'],
            percentage: (int)$row['percentage'],
            stages: !empty($row['stages_json']) ? (array)json_decode($row['stages_json'], true) : [],
            segment: $segment,
            errorThresholdPercent: (float)($row['error_threshold'] ?? 5.0),
            killSwitchTriggered: (bool)($row['kill_switch_triggered'] ?? false),
            metadata: !empty($row['metadata_json']) ? (array)json_decode($row['metadata_json'], true) : []
        );
    }

    public function getAllCanaries(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM netfusion_experiment_canaries ORDER BY flag_key ASC");
        $list = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $segment = !empty($row['segment_json']) ? Segment::fromArray(json_decode($row['segment_json'], true)) : null;
            $list[] = new CanaryRollout(
                flagKey: (string)$row['flag_key'],
                percentage: (int)$row['percentage'],
                stages: !empty($row['stages_json']) ? (array)json_decode($row['stages_json'], true) : [],
                segment: $segment,
                errorThresholdPercent: (float)($row['error_threshold'] ?? 5.0),
                killSwitchTriggered: (bool)($row['kill_switch_triggered'] ?? false),
                metadata: !empty($row['metadata_json']) ? (array)json_decode($row['metadata_json'], true) : []
            );
        }
        return $list;
    }

    public function saveSegment(Segment $segment): bool
    {
        $rulesJson = json_encode(array_map(fn($r) => $r->toArray(), $segment->rules));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO netfusion_experiment_segments (
                name, match_type, description, rules_json, updated_at
            ) VALUES (
                :name, :match_type, :description, :rules_json, :updated_at
            )
            ON CONFLICT(name) DO UPDATE SET
                match_type = excluded.match_type,
                description = excluded.description,
                rules_json = excluded.rules_json,
                updated_at = excluded.updated_at
        ");

        try {
            return $stmt->execute([
                ':name' => $segment->name,
                ':match_type' => $segment->matchType,
                ':description' => $segment->description,
                ':rules_json' => $rulesJson,
                ':updated_at' => $now,
            ]);
        } catch (\Throwable) {
            $mysqlStmt = $this->pdo->prepare("
                REPLACE INTO netfusion_experiment_segments (
                    name, match_type, description, rules_json, updated_at
                ) VALUES (
                    :name, :match_type, :description, :rules_json, :updated_at
                )
            ");
            return $mysqlStmt->execute([
                ':name' => $segment->name,
                ':match_type' => $segment->matchType,
                ':description' => $segment->description,
                ':rules_json' => $rulesJson,
                ':updated_at' => $now,
            ]);
        }
    }

    public function getSegment(string $name): ?Segment
    {
        $stmt = $this->pdo->prepare("SELECT * FROM netfusion_experiment_segments WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $rulesData = !empty($row['rules_json']) ? json_decode($row['rules_json'], true) : [];
        return Segment::fromArray([
            'name' => $row['name'],
            'match_type' => $row['match_type'],
            'description' => $row['description'],
            'rules' => $rulesData,
        ]);
    }

    public function getAllSegments(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM netfusion_experiment_segments ORDER BY name ASC");
        $list = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rulesData = !empty($row['rules_json']) ? json_decode($row['rules_json'], true) : [];
            $list[] = Segment::fromArray([
                'name' => $row['name'],
                'match_type' => $row['match_type'],
                'description' => $row['description'],
                'rules' => $rulesData,
            ]);
        }
        return $list;
    }

    public function recordExposure(Exposure $exposure): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO netfusion_experiment_exposures (
                id, experiment_key, variant_name, identifier, context_json, created_at
            ) VALUES (
                :id, :experiment_key, :variant_name, :identifier, :context_json, :created_at
            )
        ");

        return $stmt->execute([
            ':id' => $exposure->id,
            ':experiment_key' => $exposure->experimentKey,
            ':variant_name' => $exposure->variantName,
            ':identifier' => $exposure->identifier,
            ':context_json' => json_encode($exposure->context),
            ':created_at' => $exposure->timestamp,
        ]);
    }

    public function getExposures(string $experimentKey): array
    {
        $stmt = $this->pdo->prepare("
            SELECT variant_name, COUNT(DISTINCT identifier) as count
            FROM netfusion_experiment_exposures
            WHERE experiment_key = :key
            GROUP BY variant_name
        ");
        $stmt->execute([':key' => $experimentKey]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[(string)$row['variant_name']] = (int)$row['count'];
        }
        return $results;
    }

    public function recordConversion(Conversion $conversion): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO netfusion_experiment_conversions (
                id, experiment_key, variant_name, identifier, goal, value, metadata_json, created_at
            ) VALUES (
                :id, :experiment_key, :variant_name, :identifier, :goal, :value, :metadata_json, :created_at
            )
        ");

        return $stmt->execute([
            ':id' => $conversion->id,
            ':experiment_key' => $conversion->experimentKey,
            ':variant_name' => $conversion->variantName,
            ':identifier' => $conversion->identifier,
            ':goal' => $conversion->goal,
            ':value' => $conversion->value,
            ':metadata_json' => json_encode($conversion->metadata),
            ':created_at' => $conversion->timestamp,
        ]);
    }

    public function getConversions(string $experimentKey, ?string $goal = null): array
    {
        if ($goal !== null) {
            $stmt = $this->pdo->prepare("
                SELECT variant_name, COUNT(DISTINCT identifier) as count
                FROM netfusion_experiment_conversions
                WHERE experiment_key = :key AND goal = :goal
                GROUP BY variant_name
            ");
            $stmt->execute([':key' => $experimentKey, ':goal' => $goal]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT variant_name, COUNT(DISTINCT identifier) as count
                FROM netfusion_experiment_conversions
                WHERE experiment_key = :key
                GROUP BY variant_name
            ");
            $stmt->execute([':key' => $experimentKey]);
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[(string)$row['variant_name']] = (int)$row['count'];
        }
        return $results;
    }

    public function getRevenue(string $experimentKey, ?string $goal = null): array
    {
        if ($goal !== null) {
            $stmt = $this->pdo->prepare("
                SELECT variant_name, SUM(value) as total
                FROM netfusion_experiment_conversions
                WHERE experiment_key = :key AND goal = :goal
                GROUP BY variant_name
            ");
            $stmt->execute([':key' => $experimentKey, ':goal' => $goal]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT variant_name, SUM(value) as total
                FROM netfusion_experiment_conversions
                WHERE experiment_key = :key
                GROUP BY variant_name
            ");
            $stmt->execute([':key' => $experimentKey]);
        }

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[(string)$row['variant_name']] = (float)($row['total'] ?? 0.0);
        }
        return $results;
    }

    protected function hydrateExperiment(array $row): Experiment
    {
        $variantsData = !empty($row['variants_json']) ? json_decode($row['variants_json'], true) : [];
        $variants = [];
        foreach ($variantsData as $v) {
            $variants[] = Variant::fromArray($v);
        }

        $segment = !empty($row['segment_json']) ? Segment::fromArray(json_decode($row['segment_json'], true)) : null;

        return new Experiment(
            key: (string)$row['key'],
            name: (string)($row['name'] ?? ''),
            status: (string)($row['status'] ?? Experiment::STATUS_RUNNING),
            variants: $variants,
            segment: $segment,
            goals: !empty($row['goals_json']) ? (array)json_decode($row['goals_json'], true) : [],
            trafficAllocation: (int)($row['traffic_allocation'] ?? 100),
            winnerVariant: $row['winner_variant'] !== null ? (string)$row['winner_variant'] : null,
            fallbackVariant: $row['fallback_variant'] !== null ? (string)$row['fallback_variant'] : null,
            metadata: !empty($row['metadata_json']) ? (array)json_decode($row['metadata_json'], true) : []
        );
    }
}
