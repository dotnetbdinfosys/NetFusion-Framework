<?php

declare(strict_types=1);

namespace NetFusion\Core\Observability\Metrics\Instruments;

/**
 * Interface InstrumentInterface
 *
 * Contract for all OpenTelemetry metric instruments.
 */
interface InstrumentInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getUnit(): string;

    public function getType(): string;

    public function getData(): array;

    public function reset(): void;
}
