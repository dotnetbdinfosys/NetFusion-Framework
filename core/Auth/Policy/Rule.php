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

class Rule implements JsonSerializable
{
    /**
     * @param string $id
     * @param Effect $effect
     * @param array<string>|string $actions ('read', 'update', '*' etc.)
     * @param array<string>|string $resources ('Document', 'Invoice', '*' etc.)
     * @param array<string, mixed>|callable|null $conditions
     * @param string|null $description
     */
    public function __construct(
        public string $id,
        public Effect $effect = Effect::ALLOW,
        public array|string $actions = '*',
        public array|string $resources = '*',
        public mixed $conditions = null,
        public ?string $description = null
    ) {
    }

    public static function allow(
        string $id,
        array|string $actions = '*',
        array|string $resources = '*',
        mixed $conditions = null,
        ?string $description = null
    ): self {
        return new self($id, Effect::ALLOW, $actions, $resources, $conditions, $description);
    }

    public static function deny(
        string $id,
        array|string $actions = '*',
        array|string $resources = '*',
        mixed $conditions = null,
        ?string $description = null
    ): self {
        return new self($id, Effect::DENY, $actions, $resources, $conditions, $description);
    }

    public function matchesAction(string $action): bool
    {
        if ($this->actions === '*' || (is_array($this->actions) && in_array('*', $this->actions, true))) {
            return true;
        }

        $acts = is_array($this->actions) ? $this->actions : [$this->actions];
        foreach ($acts as $pattern) {
            if (strcasecmp($pattern, $action) === 0 || fnmatch($pattern, $action)) {
                return true;
            }
        }

        return false;
    }

    public function matchesResource(mixed $resource): bool
    {
        if ($this->resources === '*' || (is_array($this->resources) && in_array('*', $this->resources, true))) {
            return true;
        }

        $candidates = [];

        if (is_string($resource)) {
            $candidates[] = $resource;
            if (str_contains($resource, ':')) {
                [$type] = explode(':', $resource, 2);
                $candidates[] = $type;
            }
        } elseif (is_object($resource)) {
            $shortName = (new \ReflectionClass($resource))->getShortName();
            $candidates[] = $shortName;
            $candidates[] = strtolower($shortName);
            if (isset($resource->id)) {
                $candidates[] = $shortName . ':' . $resource->id;
                $candidates[] = strtolower($shortName) . ':' . $resource->id;
            }
        } elseif (is_array($resource)) {
            if (isset($resource['type'])) {
                $type = (string)$resource['type'];
                $candidates[] = $type;
                if (isset($resource['id'])) {
                    $candidates[] = $type . ':' . $resource['id'];
                }
            }
            if (isset($resource['id'])) {
                $candidates[] = (string)$resource['id'];
            }
        }

        $resList = is_array($this->resources) ? $this->resources : [$this->resources];
        foreach ($resList as $pattern) {
            foreach ($candidates as $candidate) {
                if (strcasecmp($pattern, $candidate) === 0 || fnmatch($pattern, $candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'effect' => $this->effect->value,
            'actions' => $this->actions,
            'resources' => $this->resources,
            'conditions' => is_callable($this->conditions) ? 'custom_callable' : $this->conditions,
            'description' => $this->description,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
