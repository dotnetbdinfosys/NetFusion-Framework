<?php

declare(strict_types=1);

namespace NetFusion\Core\AI\Agent\Memory;

class WorkingMemory
{
    /** @var array<string, mixed> */
    private array $scratchpad = [];

    /** @var array<int, array{task: string, status: string}> */
    private array $plan = [];

    public function set(string $key, mixed $value): void
    {
        $this->scratchpad[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->scratchpad[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->scratchpad);
    }

    public function delete(string $key): bool
    {
        if (array_key_exists($key, $this->scratchpad)) {
            unset($this->scratchpad[$key]);
            return true;
        }
        return false;
    }

    public function all(): array
    {
        return $this->scratchpad;
    }

    public function setPlan(array $tasks): void
    {
        $this->plan = [];
        foreach ($tasks as $task) {
            $this->plan[] = [
                'task' => is_string($task) ? $task : ($task['task'] ?? ''),
                'status' => is_array($task) ? ($task['status'] ?? 'pending') : 'pending',
            ];
        }
    }

    public function updatePlanTask(int $index, string $status): void
    {
        if (isset($this->plan[$index])) {
            $this->plan[$index]['status'] = $status;
        }
    }

    public function getPlan(): array
    {
        return $this->plan;
    }

    public function addChecklistItem(string $task, bool $done = false): self
    {
        $this->plan[] = [
            'task' => $task,
            'status' => $done ? 'completed' : 'pending',
            'done' => $done,
        ];
        return $this;
    }

    public function markChecklistItemDone(int $index): self
    {
        if (isset($this->plan[$index])) {
            $this->plan[$index]['status'] = 'completed';
            $this->plan[$index]['done'] = true;
        }
        return $this;
    }

    public function getChecklist(): array
    {
        return array_map(function ($item) {
            $done = ($item['done'] ?? false) || ($item['status'] === 'completed');
            return [
                'task' => $item['task'],
                'status' => $item['status'],
                'done' => $done,
            ];
        }, $this->plan);
    }

    public function clear(): void
    {
        $this->scratchpad = [];
        $this->plan = [];
    }

    public function toContextPrompt(): string
    {
        $lines = [];
        if (!empty($this->scratchpad)) {
            $lines[] = "[Working Scratchpad Variables]";
            foreach ($this->scratchpad as $k => $v) {
                $lines[] = " - {$k}: " . (is_scalar($v) ? $v : json_encode($v));
            }
        }

        if (!empty($this->plan)) {
            $lines[] = "[Current Execution Checklist]";
            foreach ($this->getChecklist() as $i => $item) {
                $mark = $item['done'] ? '[x]' : '[ ]';
                $lines[] = " {$mark} {$item['task']}";
            }
        }

        return implode("\n", $lines);
    }
}
