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

namespace NetFusion\Core\WAF\Contracts;

use NetFusion\Core\WAF\Detectors\AnomalyScore;
use NetFusion\Core\WAF\IP\ThreatContext;

interface ThreatDetectorInterface
{
    /**
     * Get unique name/identifier for the detector.
     */
    public function getName(): string;

    /**
     * Inspect a single field value or full context and record anomaly points if a threat is matched.
     *
     * @param string $field Target field name (e.g. 'uri', 'query.search', 'body.comment')
     * @param mixed $value Field value
     * @param ThreatContext $context Full request inspection context
     * @param AnomalyScore $anomalyScore Anomaly score container
     */
    public function inspect(string $field, mixed $value, ThreatContext $context, AnomalyScore $anomalyScore): void;
}
