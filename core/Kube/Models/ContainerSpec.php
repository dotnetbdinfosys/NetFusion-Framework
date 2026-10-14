<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Models;

/**
 * Specification of a single container inside a Pod.
 */
class ContainerSpec
{
    /**
     * @param string $name Container name
     * @param string $image Container image (e.g. 'nginx:1.25', 'redis:alpine')
     * @param string[] $command Command array
     * @param string[] $args Arguments array
     * @param array<string, string> $env Environment key-values
     * @param int[] $ports Exposed container ports
     * @param ResourceRequirements|null $resources Resource requests and limits
     * @param ProbeConfig|null $livenessProbe
     * @param ProbeConfig|null $readinessProbe
     * @param ProbeConfig|null $startupProbe
     * @param VolumeMount[] $volumeMounts
     */
    public function __construct(
        protected string $name,
        protected string $image,
        protected array $command = [],
        protected array $args = [],
        protected array $env = [],
        protected array $ports = [],
        protected ?ResourceRequirements $resources = null,
        protected ?ProbeConfig $livenessProbe = null,
        protected ?ProbeConfig $readinessProbe = null,
        protected ?ProbeConfig $startupProbe = null,
        protected array $volumeMounts = []
    ) {
        $this->resources ??= new ResourceRequirements(100, 128); // default 100m CPU, 128Mi RAM
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @return string[]
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    /**
     * @return string[]
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @return array<string, string>
     */
    public function getEnv(): array
    {
        return $this->env;
    }

    /**
     * @return int[]
     */
    public function getPorts(): array
    {
        return $this->ports;
    }

    public function getResources(): ResourceRequirements
    {
        return $this->resources ?? new ResourceRequirements(0, 0);
    }

    public function getLivenessProbe(): ?ProbeConfig
    {
        return $this->livenessProbe;
    }

    public function getReadinessProbe(): ?ProbeConfig
    {
        return $this->readinessProbe;
    }

    public function getStartupProbe(): ?ProbeConfig
    {
        return $this->startupProbe;
    }

    /**
     * @return VolumeMount[]
     */
    public function getVolumeMounts(): array
    {
        return $this->volumeMounts;
    }
}
