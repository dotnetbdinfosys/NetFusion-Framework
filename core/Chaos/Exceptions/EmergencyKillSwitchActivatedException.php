<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Exceptions;

class EmergencyKillSwitchActivatedException extends ChaosException
{
    protected array $activationDetails;

    public function __construct(string $message = "Emergency Kill Switch is ACTIVE. All chaos experiments halted.", array $details = [], ?\Throwable $previous = null)
    {
        $this->activationDetails = $details;
        parent::__construct($message, 503, $previous);
    }

    public function getActivationDetails(): array
    {
        return $this->activationDetails;
    }
}
