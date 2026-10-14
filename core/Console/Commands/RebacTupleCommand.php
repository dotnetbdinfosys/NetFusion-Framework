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

use NetFusion\Core\Auth\Policy\AccessManager;
use NetFusion\Core\Auth\Policy\Rebac\RebacTuple;
use NetFusion\Core\Console\Command;
use Throwable;

class RebacTupleCommand extends Command
{
    protected string $name = 'rebac:tuple';
    protected string $description = 'Manage Zanzibar ReBAC relationship tuples (add, delete, list, check)';

    public function handle(): int
    {
        $action = (string)$this->argument(0, 'list');

        /** @var AccessManager $manager */
        $manager = $this->app->make(AccessManager::class);

        try {
            return match (strtolower($action)) {
                'add' => $this->handleAdd($manager),
                'delete', 'remove' => $this->handleDelete($manager),
                'check' => $this->handleCheck($manager),
                default => $this->handleList($manager),
            };
        } catch (Throwable $e) {
            $this->error("ReBAC tuple operation failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function handleAdd(AccessManager $manager): int
    {
        $notation = (string)$this->argument(1);
        if (empty($notation)) {
            $this->error("Usage: php netfusion rebac:tuple add \"document:101#viewer@user:42\"");
            return 1;
        }

        $manager->rebac()->write($notation);
        $this->success("Successfully added relationship tuple: {$notation}");
        return 0;
    }

    protected function handleDelete(AccessManager $manager): int
    {
        $notation = (string)$this->argument(1);
        if (empty($notation)) {
            $this->error("Usage: php netfusion rebac:tuple delete \"document:101#viewer@user:42\"");
            return 1;
        }

        $manager->rebac()->delete($notation);
        $this->info("Successfully deleted relationship tuple: {$notation}");
        return 0;
    }

    protected function handleCheck(AccessManager $manager): int
    {
        $arg1 = (string)$this->argument(1);
        $relation = (string)$this->argument(2);
        $arg2 = (string)$this->argument(3);

        if (empty($arg1) || empty($relation) || empty($arg2)) {
            $this->error("Usage: php netfusion rebac:tuple check <entity> <relation> <subject>\nExample: php netfusion rebac:tuple check document:101 viewer user:42");
            return 1;
        }

        $entity = $arg1;
        $subject = $arg2;

        if (str_starts_with($arg1, 'user:') && !str_starts_with($arg2, 'user:')) {
            $entity = $arg2;
            $subject = $arg1;
        }

        $this->info("Checking relation [{$entity}] -> [{$relation}] for [{$subject}]...");
        $hasAccess = $manager->rebac()->check($entity, $relation, $subject);

        if ($hasAccess) {
            $this->success("PERMISSION GRANTED: {$subject} has '{$relation}' relation on {$entity}.");
        } else {
            $this->error("PERMISSION DENIED: {$subject} DOES NOT have '{$relation}' relation on {$entity}.");
        }

        return 0;
    }

    protected function handleList(AccessManager $manager): int
    {
        $entityType = null;
        $entityId = null;

        if ($this->option('entity')) {
            $parts = explode(':', (string)$this->option('entity'), 2);
            $entityType = $parts[0] ?? null;
            $entityId = $parts[1] ?? null;
        }

        $tuples = $manager->rebacStore()->read(entityType: $entityType, entityId: $entityId);

        if (empty($tuples)) {
            $this->info("No relationship tuples found.");
            return 0;
        }

        $this->info("=================== ReBAC Zanzibar Relationship Tuples ===================");
        foreach ($tuples as $t) {
            $this->line("  • " . $t->toString());
        }
        $this->line(str_repeat("-", 80));
        $this->line("Total Tuples: " . count($tuples));

        return 0;
    }
}
