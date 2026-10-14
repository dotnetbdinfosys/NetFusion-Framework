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

class CallableStreamSink implements SinkInterface
{
    protected string $name;
    /** @var callable */
    protected $handler;

    public function __construct(string $name, callable $handler)
    {
        $this->name = $name;
        $this->handler = $handler;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function emit(mixed $data, array $context = []): void
    {
        call_user_func($this->handler, $data, $context);
    }
}
