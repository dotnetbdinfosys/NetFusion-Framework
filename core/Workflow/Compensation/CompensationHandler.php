<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
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

declare(strict_types=1);

namespace NetFusion\Core\Workflow\Compensation;

/**
 * Class CompensationHandler
 *
 * Wraps a compensation callable for a completed activity.
 * The handler is invoked in reverse order when compensation is triggered.
 */
class CompensationHandler
{
    private string $id;

    public function __construct(
        private readonly string   $activityId,
        private readonly \Closure $handler,
        private readonly array    $activityOutput = []
    ) {
        $this->id = uniqid('comp_', true);
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getId(): string          { return $this->id; }
    public function getActivityId(): string  { return $this->activityId; }
    public function getOutput(): array       { return $this->activityOutput; }

    // ------------------------------------------------------------------
    // Execution
    // ------------------------------------------------------------------

    /**
     * Execute the compensation logic.
     *
     * @param  array $context Original process context
     * @return array Compensation result
     */
    public function compensate(array $context = []): array
    {
        $result = ($this->handler)($this->activityOutput, $context);
        return is_array($result) ? $result : ['compensated' => true, 'activity_id' => $this->activityId];
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'activity_id' => $this->activityId,
            'has_output'  => !empty($this->activityOutput),
        ];
    }
}
