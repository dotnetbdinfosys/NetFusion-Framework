<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Privacy/Differential/DifferentialPrivacyManager.php
 | Author    : Md. Atiquzzaman Ashish
 | Company   : DotNetBD
 | Website   : www.dotnetbd.com
 | Copyright : © DotNetBD. All rights reserved.
 | License   : MIT
 | Version   : 1.0.0
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace NetFusion\Core\Privacy\Differential;

use NetFusion\Core\Privacy\Differential\Accounting\MomentsAccountant;
use NetFusion\Core\Privacy\Differential\Accounting\PrivacyAccountant;
use NetFusion\Core\Privacy\Differential\Accounting\PrivacyBudget;
use NetFusion\Core\Privacy\Differential\Aggregation\PrivateAggregator;
use NetFusion\Core\Privacy\Differential\Federated\FederatedAggregator;
use NetFusion\Core\Privacy\Differential\Mechanisms\ExponentialMechanism;
use NetFusion\Core\Privacy\Differential\Mechanisms\GaussianMechanism;
use NetFusion\Core\Privacy\Differential\Mechanisms\LaplaceMechanism;
use NetFusion\Core\Privacy\Differential\Mechanisms\RandomizedResponse;

/**
 * Enterprise Differential Privacy Master Facade.
 */
class DifferentialPrivacyManager
{
    private static ?self $instance = null;

    private PrivacyAccountant $accountant;
    private MomentsAccountant $momentsAccountant;
    private PrivateAggregator $aggregator;
    private FederatedAggregator $federatedAggregator;
    private LaplaceMechanism $laplace;
    private GaussianMechanism $gaussian;
    private ExponentialMechanism $exponential;
    private RandomizedResponse $randomizedResponse;
    private array $config;

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_epsilon'   => 1.0,
            'default_delta'     => 1e-5,
            'default_mechanism' => 'laplace',
        ], $config);

        $this->accountant          = new PrivacyAccountant();
        $this->momentsAccountant   = new MomentsAccountant();
        $this->laplace             = new LaplaceMechanism();
        $this->gaussian            = new GaussianMechanism();
        $this->exponential        = new ExponentialMechanism();
        $this->randomizedResponse  = new RandomizedResponse();
        $this->aggregator          = new PrivateAggregator($this->laplace);
        $this->federatedAggregator = new FederatedAggregator($this->gaussian);
    }

    public function getAccountant(): PrivacyAccountant
    {
        return $this->accountant;
    }

    public function getMomentsAccountant(): MomentsAccountant
    {
        return $this->momentsAccountant;
    }

    public function getAggregator(): PrivateAggregator
    {
        return $this->aggregator;
    }

    public function getFederatedAggregator(): FederatedAggregator
    {
        return $this->federatedAggregator;
    }

    public function laplace(): LaplaceMechanism
    {
        return $this->laplace;
    }

    public function gaussian(): GaussianMechanism
    {
        return $this->gaussian;
    }

    public function exponential(): ExponentialMechanism
    {
        return $this->exponential;
    }

    public function randomizedResponse(): RandomizedResponse
    {
        return $this->randomizedResponse;
    }

    /**
     * Get or create a privacy budget tracker for an identifier.
     */
    public function budget(string $identifier, ?float $totalEpsilon = null, ?float $totalDelta = null): PrivacyBudget
    {
        $eps = $totalEpsilon ?? (float)$this->config['default_epsilon'];
        $del = $totalDelta ?? (float)$this->config['default_delta'];
        return $this->accountant->getOrCreateBudget($identifier, $eps, $del);
    }

    /**
     * Add Laplace noise to a value.
     */
    public function addLaplaceNoise(float|int|array $value, float $epsilon, float $sensitivity): float|int|array
    {
        return $this->laplace->addNoise($value, $epsilon, $sensitivity);
    }

    /**
     * Add Gaussian noise to a value.
     */
    public function addGaussianNoise(float|int|array $value, float $epsilon, float $sensitivity, float $delta = 1e-5): float|int|array
    {
        return $this->gaussian->addNoise($value, $epsilon, $sensitivity, $delta);
    }

    /**
     * Compute differentially private COUNT query.
     */
    public function count(int|float $trueCount, ?float $epsilon = null, ?string $budgetId = null): float
    {
        $eps = $epsilon ?? (float)$this->config['default_epsilon'];
        $b = $budgetId !== null ? $this->budget($budgetId) : null;
        return $this->aggregator->privateCount($trueCount, $eps, $b);
    }

    /**
     * Compute differentially private SUM query.
     */
    public function sum(array $values, float $lower, float $upper, ?float $epsilon = null, ?string $budgetId = null): float
    {
        $eps = $epsilon ?? (float)$this->config['default_epsilon'];
        $b = $budgetId !== null ? $this->budget($budgetId) : null;
        return $this->aggregator->privateSum($values, $lower, $upper, $eps, $b);
    }

    /**
     * Compute differentially private MEAN query.
     */
    public function mean(array $values, float $lower, float $upper, ?float $epsilon = null, ?string $budgetId = null): float
    {
        $eps = $epsilon ?? (float)$this->config['default_epsilon'];
        $b = $budgetId !== null ? $this->budget($budgetId) : null;
        return $this->aggregator->privateMean($values, $lower, $upper, $eps, $b);
    }
}
