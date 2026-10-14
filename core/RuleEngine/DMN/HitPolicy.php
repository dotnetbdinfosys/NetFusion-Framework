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

namespace NetFusion\Core\RuleEngine\DMN;

final class HitPolicy
{
    // Single Hit Policies
    public const UNIQUE = 'UNIQUE';
    public const FIRST = 'FIRST';
    public const PRIORITY = 'PRIORITY';
    public const ANY = 'ANY';

    // Multiple Hit Policies
    public const RULE_ORDER = 'RULE_ORDER';
    public const OUTPUT_ORDER = 'OUTPUT_ORDER';
    public const COLLECT_LIST = 'COLLECT_LIST';
    public const COLLECT_SUM = 'COLLECT_SUM';
    public const COLLECT_COUNT = 'COLLECT_COUNT';
    public const COLLECT_MIN = 'COLLECT_MIN';
    public const COLLECT_MAX = 'COLLECT_MAX';

    /**
     * Check if policy allows multiple matching rules to be aggregated.
     */
    public static function isMultiple(string $hitPolicy): bool
    {
        $policy = strtoupper($hitPolicy);
        return in_array($policy, [
            self::RULE_ORDER,
            self::OUTPUT_ORDER,
            self::COLLECT_LIST,
            self::COLLECT_SUM,
            self::COLLECT_COUNT,
            self::COLLECT_MIN,
            self::COLLECT_MAX,
        ], true);
    }
}
