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

namespace NetFusion\Core\Auth\Policy\Contracts;

use NetFusion\Core\Auth\Policy\Decision;
use NetFusion\Core\Auth\Policy\EvaluationContext;

interface PolicyEngineInterface
{
    /**
     * Evaluate authorization for the given evaluation context.
     *
     * @param EvaluationContext $context
     * @return Decision
     */
    public function evaluate(EvaluationContext $context): Decision;

    /**
     * Check if access is granted.
     *
     * @param mixed $subject
     * @param string $action
     * @param mixed $resource
     * @param array<string, mixed> $environment
     * @return bool
     */
    public function allows(mixed $subject, string $action, mixed $resource, array $environment = []): bool;

    /**
     * Register a Policy into the policy store.
     */
    public function register(\NetFusion\Core\Auth\Policy\Policy $policy): self;

    /**
     * Define a Policy fluently via callback.
     */
    public function define(string $id, callable $callback, int $priority = 0): \NetFusion\Core\Auth\Policy\Policy;

    /**
     * Check if access is denied.
     */
    public function denies(mixed $subject, string $action, mixed $resource, array $environment = []): bool;
}
