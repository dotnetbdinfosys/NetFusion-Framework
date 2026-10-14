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

namespace NetFusion\Core\Console\Commands;

use NetFusion\Core\Console\Command;
use NetFusion\Core\Vector\VectorEngineManager;

class VectorCollectionsCommand extends Command
{
    protected string $name = 'vector:collections';
    protected string $signature = 'vector:collections {--create= : Create new collection} {--dim=64 : Dimension for new collection} {--metric=cosine : Metric (cosine, euclidean, dot_product)}';
    protected string $description = 'List or manage vector collections and HNSW graph indices';

    public function handle(): int
    {
        /** @var VectorEngineManager $manager */
        $manager = function_exists('vector') ? vector() : (function_exists('app') && app()->has('vector') ? app('vector') : new VectorEngineManager());

        $createName = $this->option('create');
        if (!empty($createName)) {
            $dim = (int)$this->option('dim', 64);
            $metric = \NetFusion\Core\Vector\Math\DistanceMetric::tryFrom((string)$this->option('metric', 'cosine')) ?? \NetFusion\Core\Vector\Math\DistanceMetric::COSINE;
            $manager->collection((string)$createName, $dim, $metric);
            $this->info("Successfully created vector collection '{$createName}' (Dim: {$dim}, Metric: {$metric->value}).");
            return 0;
        }

        $collections = $manager->listCollections();

        $this->info("==========================================================================================");
        $this->info("                 NetFusion Enterprise Vector Collections & HNSW Indices");
        $this->info("==========================================================================================");

        if (empty($collections)) {
            $this->line("No vector collections currently initialized.");
            return 0;
        }

        $this->table(
            ['Collection Name', 'Dimension', 'Metric', 'Documents / Chunks'],
            array_map(function ($name) use ($manager) {
                $col = $manager->collection($name);
                return [
                    $name,
                    (string)$col->getDimension(),
                    $col->getMetric()->value,
                    (string)$col->count(),
                ];
            }, $collections)
        );

        return 0;
    }
}
