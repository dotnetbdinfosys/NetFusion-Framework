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

namespace NetFusion\Core\Workflow\Definition\Nodes;

class UserTask extends AbstractNode
{
    protected ?string $assignee;
    /** @var array<string> */
    protected array $candidateGroups;
    /** @var array<string, mixed> */
    protected array $formSchema;
    protected ?string $dueDate;

    public function __construct(
        string $id,
        string $name,
        ?string $assignee = null,
        array $candidateGroups = [],
        array $formSchema = [],
        ?string $dueDate = null,
        array $metadata = []
    ) {
        parent::__construct($id, $name, 'userTask', $metadata);
        $this->assignee = $assignee;
        $this->candidateGroups = $candidateGroups;
        $this->formSchema = $formSchema;
        $this->dueDate = $dueDate;
    }

    public function getAssignee(): ?string
    {
        return $this->assignee;
    }

    public function getCandidateGroups(): array
    {
        return $this->candidateGroups;
    }

    public function getFormSchema(): array
    {
        return $this->formSchema;
    }

    public function getDueDate(): ?string
    {
        return $this->dueDate;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'assignee' => $this->assignee,
            'candidateGroups' => $this->candidateGroups,
            'formSchema' => $this->formSchema,
            'dueDate' => $this->dueDate,
            'incomingFlows' => $this->incomingFlows,
            'outgoingFlows' => $this->outgoingFlows,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        $node = new self(
            $data['id'],
            $data['name'] ?? 'User Task',
            $data['assignee'] ?? null,
            $data['candidateGroups'] ?? [],
            $data['formSchema'] ?? [],
            $data['dueDate'] ?? null,
            $data['metadata'] ?? []
        );
        $node->incomingFlows = $data['incomingFlows'] ?? [];
        $node->outgoingFlows = $data['outgoingFlows'] ?? [];
        return $node;
    }
}
