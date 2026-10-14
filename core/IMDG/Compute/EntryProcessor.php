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

namespace NetFusion\Core\IMDG\Compute;

use NetFusion\Core\IMDG\Contracts\EntryProcessorInterface;

class EntryProcessor implements EntryProcessorInterface
{
    /** @var callable */
    protected $closure;

    public function __construct(callable $closure)
    {
        $this->closure = $closure;
    }

    public static function create(callable $closure): self
    {
        return new self($closure);
    }

    public function process(string $key, mixed &$value): mixed
    {
        return ($this->closure)($key, $value);
    }
}
