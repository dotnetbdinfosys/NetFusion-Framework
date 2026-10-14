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

namespace NetFusion\Core\Formal\Smt;

/**
 * Satisfying model / variable valuation assignment result.
 */
class Model
{
    /** @var array<string, mixed> */
    private array $assignments;

    public function __construct(array $assignments = [])
    {
        $this->assignments = $assignments;
    }

    public function get(string $varName): mixed
    {
        return $this->assignments[$varName] ?? null;
    }

    public function has(string $varName): bool
    {
        return array_key_exists($varName, $this->assignments);
    }

    public function toArray(): array
    {
        return $this->assignments;
    }

    public function toSmtLib(): string
    {
        $lines = ['(model'];
        foreach ($this->assignments as $var => $val) {
            $valStr = is_bool($val) ? ($val ? 'true' : 'false') : (string)$val;
            $lines[] = "  (define-fun {$var} () Int {$valStr})";
        }
        $lines[] = ')';
        return implode("\n", $lines);
    }

    public function __toString(): string
    {
        return json_encode($this->assignments, JSON_PRETTY_PRINT);
    }
}
