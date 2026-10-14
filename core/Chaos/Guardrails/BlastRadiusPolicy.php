<?php

declare(strict_types=1);

namespace NetFusion\Core\Chaos\Guardrails;

/**
 * Class BlastRadiusPolicy
 *
 * Enforces strict blast-radius constraints: environment gating, route matching,
 * header probe requirements, max affected traffic percentage, and duration caps.
 */
class BlastRadiusPolicy
{
    protected array $allowedEnvironments;
    protected array $allowedRoutes = [];
    protected array $excludedRoutes = [];
    protected ?string $requiredHeaderName = null;
    protected ?string $requiredHeaderValue = null;
    protected float $maxAffectedPercent = 100.0;
    protected int $maxDurationSeconds = 300;
    protected ?string $lastRejectionReason = null;

    public function __construct(
        array $allowedEnvironments = ['development', 'testing', 'staging'],
        array $allowedRoutes = [],
        array $excludedRoutes = ['/health', '/metrics', '/readiness', '/liveness'],
        ?string $requiredHeaderName = null,
        ?string $requiredHeaderValue = null,
        float $maxAffectedPercent = 100.0,
        int $maxDurationSeconds = 300
    ) {
        $this->allowedEnvironments = array_map('strtolower', $allowedEnvironments);
        $this->allowedRoutes = $allowedRoutes;
        $this->excludedRoutes = $excludedRoutes;
        $this->requiredHeaderName = $requiredHeaderName ? strtolower($requiredHeaderName) : null;
        $this->requiredHeaderValue = $requiredHeaderValue;
        $this->maxAffectedPercent = max(0.0, min(100.0, $maxAffectedPercent));
        $this->maxDurationSeconds = max(1, $maxDurationSeconds);
    }

    public function getAllowedEnvironments(): array
    {
        return $this->allowedEnvironments;
    }

    public function setAllowedEnvironments(array $envs): static
    {
        $this->allowedEnvironments = array_map('strtolower', $envs);
        return $this;
    }

    public function getAllowedRoutes(): array
    {
        return $this->allowedRoutes;
    }

    public function setAllowedRoutes(array $routes): static
    {
        $this->allowedRoutes = $routes;
        return $this;
    }

    public function getExcludedRoutes(): array
    {
        return $this->excludedRoutes;
    }

    public function setExcludedRoutes(array $routes): static
    {
        $this->excludedRoutes = $routes;
        return $this;
    }

    public function setRequiredHeader(?string $name, ?string $value = null): static
    {
        $this->requiredHeaderName = $name ? strtolower($name) : null;
        $this->requiredHeaderValue = $value;
        return $this;
    }

    public function getMaxAffectedPercent(): float
    {
        return $this->maxAffectedPercent;
    }

    public function setMaxAffectedPercent(float $percent): static
    {
        $this->maxAffectedPercent = max(0.0, min(100.0, $percent));
        return $this;
    }

    public function getMaxDurationSeconds(): int
    {
        return $this->maxDurationSeconds;
    }

    public function setMaxDurationSeconds(int $seconds): static
    {
        $this->maxDurationSeconds = max(1, $seconds);
        return $this;
    }

    public function getLastRejectionReason(): ?string
    {
        return $this->lastRejectionReason;
    }

    /**
     * Evaluate context against all blast radius rules.
     */
    public function isPermitted(array $context = []): bool
    {
        $this->lastRejectionReason = null;

        // 1. Environment Check
        $currentEnv = strtolower((string) ($context['environment'] ?? 'production'));
        if (!in_array($currentEnv, $this->allowedEnvironments, true)) {
            $this->lastRejectionReason = "Environment '{$currentEnv}' is not in allowed environments: [" . implode(', ', $this->allowedEnvironments) . "]";
            return false;
        }

        // 2. Route Check
        $route = (string) ($context['route'] ?? $context['path'] ?? '');
        if ($route !== '') {
            // Check exclusions first
            foreach ($this->excludedRoutes as $excluded) {
                if ($this->matchPattern($excluded, $route)) {
                    $this->lastRejectionReason = "Route '{$route}' is explicitly excluded by pattern '{$excluded}'";
                    return false;
                }
            }

            // Check inclusions if specified
            if (!empty($this->allowedRoutes)) {
                $matched = false;
                foreach ($this->allowedRoutes as $allowed) {
                    if ($this->matchPattern($allowed, $route)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    $this->lastRejectionReason = "Route '{$route}' does not match any allowed route patterns";
                    return false;
                }
            }
        }

        // 3. Canary Header Requirement
        if ($this->requiredHeaderName !== null) {
            $headers = array_change_key_case((array) ($context['headers'] ?? []), CASE_LOWER);
            if (!isset($headers[$this->requiredHeaderName])) {
                $this->lastRejectionReason = "Missing mandatory probe header: '{$this->requiredHeaderName}'";
                return false;
            }
            if ($this->requiredHeaderValue !== null && (string) $headers[$this->requiredHeaderName] !== $this->requiredHeaderValue) {
                $this->lastRejectionReason = "Probe header '{$this->requiredHeaderName}' value mismatch (expected '{$this->requiredHeaderValue}', got '{$headers[$this->requiredHeaderName]}')";
                return false;
            }
        }

        // 4. Max Affected Traffic Percentage
        if ($this->maxAffectedPercent < 100.0) {
            $roll = mt_rand(1, 10000) / 100.0;
            if ($roll > $this->maxAffectedPercent) {
                $this->lastRejectionReason = "Traffic roll ({$roll}%) exceeded max allowed blast radius ({$this->maxAffectedPercent}%)";
                return false;
            }
        }

        return true;
    }

    protected function matchPattern(string $pattern, string $path): bool
    {
        if ($pattern === $path) {
            return true;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
        return (bool) preg_match($regex, $path);
    }
}
