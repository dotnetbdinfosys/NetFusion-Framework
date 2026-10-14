<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Workflow/ApprovalPipeline.php
| Module    : Workflow
| Namespace : NetFusion\Core\Workflow
| Purpose   : Multi-stage enterprise approval pipeline engine.
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

namespace NetFusion\Core\Workflow;

use NetFusion\Core\Database\DatabaseManager;
use RuntimeException;

class ApprovalPipeline
{
    protected string $name;
    protected array $stages = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * পাইপলাইনে একটি নতুন অনুমোদন স্টেজ যোগ করুন।
     */
    public function addStage(string $name, ?int $approverId = null, ?string $role = null): static
    {
        $this->stages[] = [
            'name'        => $name,
            'approver_id' => $approverId,
            'role'        => $role,
            'index'       => count($this->stages),
        ];
        return $this;
    }

    public function getStages(): array
    {
        return $this->stages;
    }

    /**
     * সাবজেক্টকে অনুমোদন পাইপলাইনে সাবমিট করুন (১ম স্টেজে রিকোয়েস্ট তৈরি)।
     */
    public function submit(object $subject): bool
    {
        if (empty($this->stages)) {
            throw new RuntimeException("Approval pipeline [{$this->name}] has no stages configured.");
        }

        $entityType = (new \ReflectionClass($subject))->getShortName();
        $entityId = method_exists($subject, 'getAttribute') ? (int)$subject->getAttribute('id') : (int)($subject->id ?? 0);

        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);

        // Delete any existing pending requests for this entity and pipeline
        $db->connection()->statement(
            "DELETE FROM approval_requests WHERE entity_type = ? AND entity_id = ? AND pipeline_name = ?",
            [$entityType, $entityId, $this->name]
        );

        $firstStage = $this->stages[0];
        $now = date('Y-m-d H:i:s');

        $db->connection()->statement(
            "INSERT INTO approval_requests (entity_type, entity_id, pipeline_name, stage_index, stage_name, approver_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)",
            [$entityType, $entityId, $this->name, $firstStage['index'], $firstStage['name'], $firstStage['approver_id'], $now]
        );

        if (function_exists('event')) {
            event('approval.submitted', ['pipeline' => $this->name, 'subject' => $subject, 'stage' => $firstStage]);
        }

        return true;
    }

    /**
     * বর্তমান স্টেজ অনুমোদন করুন।
     */
    public function approve(object $subject, int $approverId, ?string $comment = null): bool
    {
        $current = $this->getCurrentRequest($subject);
        if ($current === null || $current['status'] !== 'pending') {
            throw new RuntimeException("No pending approval request found for this entity.");
        }

        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);
        $now = date('Y-m-d H:i:s');
        $currentIndex = (int)$current['stage_index'];

        // Mark current stage as approved
        $db->connection()->statement(
            "UPDATE approval_requests SET status = 'approved', approver_id = ?, comments = ?, acted_at = ? WHERE id = ?",
            [$approverId, $comment, $now, $current['id']]
        );

        $nextIndex = $currentIndex + 1;

        // Check if there is a next stage
        if (isset($this->stages[$nextIndex])) {
            $nextStage = $this->stages[$nextIndex];
            $db->connection()->statement(
                "INSERT INTO approval_requests (entity_type, entity_id, pipeline_name, stage_index, stage_name, approver_id, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)",
                [$current['entity_type'], $current['entity_id'], $this->name, $nextStage['index'], $nextStage['name'], $nextStage['approver_id'], $now]
            );

            if (function_exists('event')) {
                event('approval.stage_passed', ['pipeline' => $this->name, 'subject' => $subject, 'stage' => $current['stage_name'], 'next_stage' => $nextStage['name']]);
            }
        } else {
            // Pipeline completely approved
            if (function_exists('event')) {
                event('approval.completed', ['pipeline' => $this->name, 'subject' => $subject]);
            }
        }

        return true;
    }

    /**
     * বর্তমান স্টেজ প্রত্যাখ্যান (Reject) করুন।
     */
    public function reject(object $subject, int $approverId, ?string $comment = null): bool
    {
        $current = $this->getCurrentRequest($subject);
        if ($current === null || $current['status'] !== 'pending') {
            throw new RuntimeException("No pending approval request found for this entity.");
        }

        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);
        $now = date('Y-m-d H:i:s');

        $db->connection()->statement(
            "UPDATE approval_requests SET status = 'rejected', approver_id = ?, comments = ?, acted_at = ? WHERE id = ?",
            [$approverId, $comment, $now, $current['id']]
        );

        if (function_exists('event')) {
            event('approval.rejected', ['pipeline' => $this->name, 'subject' => $subject, 'stage' => $current['stage_name'], 'comment' => $comment]);
        }

        return true;
    }

    /**
     * সাবজেক্টের বর্তমান অনুমোদনের স্ট্যাটাস পান ('pending', 'approved', 'rejected', 'not_submitted')।
     */
    public function getStatus(object $subject): string
    {
        $entityType = (new \ReflectionClass($subject))->getShortName();
        $entityId = method_exists($subject, 'getAttribute') ? (int)$subject->getAttribute('id') : (int)($subject->id ?? 0);

        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);

        $row = $db->connection()->selectOne(
            "SELECT status, stage_index FROM approval_requests WHERE entity_type = ? AND entity_id = ? AND pipeline_name = ? ORDER BY id DESC LIMIT 1",
            [$entityType, $entityId, $this->name]
        );

        if ($row === null) {
            return 'not_submitted';
        }

        if ($row['status'] === 'rejected') {
            return 'rejected';
        }

        if ($row['status'] === 'pending') {
            return 'pending';
        }

        // Check if all stages passed
        if ((int)$row['stage_index'] === count($this->stages) - 1 && $row['status'] === 'approved') {
            return 'approved';
        }

        return 'pending';
    }

    public function getCurrentRequest(object $subject): ?array
    {
        $entityType = (new \ReflectionClass($subject))->getShortName();
        $entityId = method_exists($subject, 'getAttribute') ? (int)$subject->getAttribute('id') : (int)($subject->id ?? 0);

        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);

        return $db->connection()->selectOne(
            "SELECT * FROM approval_requests WHERE entity_type = ? AND entity_id = ? AND pipeline_name = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$entityType, $entityId, $this->name]
        );
    }

    /**
     * কোনো ইউজারের পেন্ডিং অ্যাপ্রুভাল রিকোয়েস্টসমূহ পান।
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getPendingRequestsForUser(?int $userId = null): array
    {
        /** @var DatabaseManager $db */
        $db = app(DatabaseManager::class);

        if ($userId !== null) {
            return $db->connection()->select(
                "SELECT * FROM approval_requests WHERE status = 'pending' AND (approver_id = ? OR approver_id IS NULL) ORDER BY id DESC",
                [$userId]
            );
        }

        return $db->connection()->select(
            "SELECT * FROM approval_requests WHERE status = 'pending' ORDER BY id DESC"
        );
    }
}