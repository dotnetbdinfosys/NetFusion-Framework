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

namespace NetFusion\Core\CEP\Stream\Sink;

use NetFusion\Core\CEP\Contracts\SinkInterface;
use NetFusion\Core\CEP\Pattern\MatchedPattern;
use NetFusion\Core\Workflow\WorkflowEngine;

class WorkflowStreamSink implements SinkInterface
{
    protected string $name;
    protected string $workflowDefinitionId;
    protected ?WorkflowEngine $workflowEngine;
    /** @var callable|null */
    protected $payloadMapper;

    public function __construct(
        string $name,
        string $workflowDefinitionId,
        ?WorkflowEngine $workflowEngine = null,
        ?callable $payloadMapper = null
    ) {
        $this->name = $name;
        $this->workflowDefinitionId = $workflowDefinitionId;
        $this->workflowEngine = $workflowEngine;
        $this->payloadMapper = $payloadMapper;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function emit(mixed $data, array $context = []): void
    {
        $payload = [];
        $businessKey = $context['key'] ?? null;

        if ($this->payloadMapper !== null) {
            $payload = call_user_func($this->payloadMapper, $data, $context);
        } elseif ($data instanceof MatchedPattern) {
            $payload = [
                'pattern' => $data->getPatternName(),
                'partition_key' => $data->getPartitionKey(),
                'duration' => $data->getDuration(),
                'matched_data' => $data->toArray(),
            ];
            $businessKey = $data->getPartitionKey();
        } elseif (is_array($data)) {
            $payload = $data;
        } else {
            $payload = ['output' => $data];
        }

        $engine = $this->workflowEngine;
        if ($engine === null && function_exists('workflow_engine')) {
            $engine = workflow_engine();
        }

        if ($engine !== null) {
            $engine->start($this->workflowDefinitionId, $payload, $businessKey);
        } elseif (function_exists('workflow_start')) {
            workflow_start($this->workflowDefinitionId, $payload, $businessKey);
        }
    }
}
