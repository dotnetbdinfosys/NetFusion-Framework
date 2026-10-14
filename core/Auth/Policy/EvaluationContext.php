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

namespace NetFusion\Core\Auth\Policy;

use JsonSerializable;

class EvaluationContext implements JsonSerializable
{
    /**
     * @param array<string, mixed>|object $subject (User, Agent, API Key attributes)
     * @param string $action (e.g., 'read', 'update', 'delete', 'publish', 'refund')
     * @param array<string, mixed>|object|string $resource (Model instance, resource type/attributes)
     * @param array<string, mixed> $environment (IP, timestamp, working hours, geo location)
     */
    public function __construct(
        public mixed $subject,
        public string $action,
        public mixed $resource,
        public array $environment = []
    ) {
    }

    public static function create(
        mixed $subject,
        string $action,
        mixed $resource,
        array $environment = []
    ): self {
        return new self($subject, $action, $resource, $environment);
    }

    /**
     * Retrieve an attribute dynamically from dot notation (e.g. 'subject.department', 'resource.owner_id', 'env.ip').
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $root = array_shift($segments);

        $target = match (strtolower($root)) {
            'subject', 'user', 'principal' => $this->subject,
            'action' => $this->action,
            'resource', 'object' => $this->resource,
            'environment', 'env' => $this->environment,
            default => null,
        };

        if (empty($segments)) {
            return $target ?? $default;
        }

        if ($target === null) {
            return $default;
        }

        return $this->extractNested($target, $segments, $default);
    }

    protected function extractNested(mixed $target, array $segments, mixed $default): mixed
    {
        $current = $target;

        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    return $default;
                }
                $current = $current[$segment];
            } elseif (is_object($current)) {
                if (isset($current->{$segment})) {
                    $current = $current->{$segment};
                } elseif (method_exists($current, $segment)) {
                    $current = $current->{$segment}();
                } elseif (method_exists($current, 'get' . ucfirst($segment))) {
                    $current = $current->{'get' . ucfirst($segment)}();
                } else {
                    return $default;
                }
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'action' => $this->action,
            'resource' => $this->resource,
            'environment' => $this->environment,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
