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

namespace NetFusion\Core\CEP\Window;

use NetFusion\Core\CEP\Contracts\EventInterface;
use NetFusion\Core\CEP\Contracts\WindowFunctionInterface;
use NetFusion\Core\CEP\Contracts\WindowInterface;

class WindowAggregate implements WindowFunctionInterface
{
    public const AGG_SUM = 'sum';
    public const AGG_AVG = 'avg';
    public const AGG_MIN = 'min';
    public const AGG_MAX = 'max';
    public const AGG_COUNT = 'count';
    public const AGG_STDDEV = 'stddev';
    public const AGG_PERCENTILE = 'percentile';
    public const AGG_CUSTOM = 'custom';

    protected string $type;
    protected ?string $field;
    protected mixed $extra;
    /** @var callable|null */
    protected $customCallback;

    public function __construct(
        string $type,
        ?string $field = null,
        mixed $extra = null,
        ?callable $customCallback = null
    ) {
        $this->type = $type;
        $this->field = $field;
        $this->extra = $extra;
        $this->customCallback = $customCallback;
    }

    public static function sum(string $field): self
    {
        return new self(self::AGG_SUM, $field);
    }

    public static function avg(string $field): self
    {
        return new self(self::AGG_AVG, $field);
    }

    public static function min(string $field): self
    {
        return new self(self::AGG_MIN, $field);
    }

    public static function max(string $field): self
    {
        return new self(self::AGG_MAX, $field);
    }

    public static function count(): self
    {
        return new self(self::AGG_COUNT);
    }

    public static function stddev(string $field): self
    {
        return new self(self::AGG_STDDEV, $field);
    }

    public static function percentile(string $field, float $p = 95.0): self
    {
        return new self(self::AGG_PERCENTILE, $field, $p);
    }

    public static function custom(callable $callback): self
    {
        return new self(self::AGG_CUSTOM, null, null, $callback);
    }

    public function apply(string $key, WindowInterface $window, array $events): mixed
    {
        if (empty($events)) {
            return [
                'key' => $key,
                'window' => $window->getKey(),
                'start' => $window->getStart(),
                'end' => $window->getEnd(),
                'count' => 0,
                'value' => null,
            ];
        }

        if ($this->customCallback !== null) {
            return call_user_func($this->customCallback, $key, $window, $events);
        }

        $values = [];
        if ($this->field !== null) {
            foreach ($events as $event) {
                if ($event instanceof EventInterface) {
                    $val = $event->get($this->field);
                    if ($val !== null && is_numeric($val)) {
                        $values[] = (float) $val;
                    }
                }
            }
        }

        $resultValue = match ($this->type) {
            self::AGG_COUNT => count($events),
            self::AGG_SUM => array_sum($values),
            self::AGG_AVG => !empty($values) ? array_sum($values) / count($values) : 0.0,
            self::AGG_MIN => !empty($values) ? min($values) : null,
            self::AGG_MAX => !empty($values) ? max($values) : null,
            self::AGG_STDDEV => $this->calculateStdDev($values),
            self::AGG_PERCENTILE => $this->calculatePercentile($values, (float) ($this->extra ?? 95.0)),
            default => count($events),
        };

        return [
            'key' => $key,
            'window' => $window->getKey(),
            'start' => $window->getStart(),
            'end' => $window->getEnd(),
            'count' => count($events),
            'field' => $this->field,
            'agg_type' => $this->type,
            'value' => $resultValue,
            'window_summary' => $window->toString(),
        ];
    }

    protected function calculateStdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $variance = 0.0;
        foreach ($values as $x) {
            $variance += pow($x - $mean, 2);
        }

        return sqrt($variance / ($n - 1));
    }

    protected function calculatePercentile(array $values, float $p): ?float
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $n = count($values);
        $rank = ($p / 100.0) * ($n - 1);
        $lowerIndex = (int) floor($rank);
        $upperIndex = (int) ceil($rank);

        if ($lowerIndex === $upperIndex) {
            return $values[$lowerIndex];
        }

        $fraction = $rank - $lowerIndex;
        return $values[$lowerIndex] + $fraction * ($values[$upperIndex] - $values[$lowerIndex]);
    }
}
