<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/NeuromorphicManager.php
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

namespace NetFusion\Core\AI\Neuromorphic;

use NetFusion\Core\AI\Neuromorphic\Enums\NeuronModel;
use NetFusion\Core\AI\Neuromorphic\Enums\EncodingScheme;
use NetFusion\Core\AI\Neuromorphic\Encoding\SpikeEncoder;
use NetFusion\Core\AI\Neuromorphic\Encoding\SpikeDecoder;
use NetFusion\Core\AI\Neuromorphic\Network\SpikingNeuralNetwork;
use NetFusion\Core\AI\Neuromorphic\Exceptions\NeuromorphicException;

/**
 * Enterprise Neuromorphic Computing & Spiking Neural Network Orchestrator.
 */
class NeuromorphicManager
{
    private static ?self $instance = null;

    /** @var array<string, SpikingNeuralNetwork> */
    private array $networks = [];

    private readonly SpikeEncoder $encoder;
    private readonly SpikeDecoder $decoder;

    public function __construct()
    {
        $this->encoder = new SpikeEncoder();
        $this->decoder = new SpikeDecoder();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Create and register a Spiking Neural Network topology.
     *
     * @param string $name
     * @param int $inputDim
     * @param array<int, array{name: string, size: int, model?: NeuronModel}> $layers
     * @return SpikingNeuralNetwork
     */
    public function createNetwork(string $name, int $inputDim = 4, array $layers = []): SpikingNeuralNetwork
    {
        $network = new SpikingNeuralNetwork($name, $inputDim);
        foreach ($layers as $layerConfig) {
            $model = $layerConfig['model'] ?? NeuronModel::LIF;
            $network->addLayer($layerConfig['name'], $layerConfig['size'], $model);
        }
        $this->networks[$name] = $network;
        return $network;
    }

    public function getNetwork(string $name): ?SpikingNeuralNetwork
    {
        return $this->networks[$name] ?? null;
    }

    /**
     * Encode continuous array into discrete spike train.
     *
     * @param float[] $values
     * @param EncodingScheme $scheme
     * @param int $timesteps
     * @return array<int, array<int, int>>
     */
    public function encode(
        array $values,
        EncodingScheme $scheme = EncodingScheme::RATE,
        int $timesteps = 50
    ): array {
        return $this->encoder->encode($values, $scheme, $timesteps);
    }

    /**
     * Decode spike matrix into classification decision and firing rates.
     *
     * @param array<int, array<int, int>> $spikeMatrix
     * @return array{predicted_class: int, total_spikes: int, confidence: float, firing_rates: float[]}
     */
    public function decode(array $spikeMatrix): array
    {
        $cls = $this->decoder->decodeClassification($spikeMatrix);
        $rates = $this->decoder->decodeRate($spikeMatrix);
        return [
            'predicted_class' => $cls['predicted_class'],
            'total_spikes'    => $cls['total_spikes'],
            'confidence'      => $cls['confidence'],
            'firing_rates'    => $rates,
        ];
    }

    /**
     * Perform SNN inference on a registered network.
     *
     * @param string $networkName
     * @param float[] $features
     * @param int $timesteps
     * @return array{
     *   predicted_class: int,
     *   confidence: float,
     *   total_spikes: int,
     *   firing_rates: float[],
     *   synaptic_operations: int
     * }
     */
    public function infer(string $networkName, array $features, int $timesteps = 30): array
    {
        $network = $this->getNetwork($networkName);
        if ($network === null) {
            // Auto-create standard classifier network if not found
            $network = $this->createNetwork($networkName, count($features), [
                ['name' => 'hidden_lif', 'size' => 8, 'model' => NeuronModel::LIF],
                ['name' => 'output_cls', 'size' => 2, 'model' => NeuronModel::LIF],
            ]);
        }

        return $network->predict($features, $timesteps);
    }

    /**
     * Train a network on a dataset over multiple epochs.
     *
     * @param string $networkName
     * @param array<int, array{input: float[], target: int}> $dataset
     * @param int $epochs
     * @param float $learningRate
     * @param int $timesteps
     * @return array{epochs: int, final_loss: float, accuracy: float}
     */
    public function train(
        string $networkName,
        array $dataset,
        int $epochs = 5,
        float $learningRate = 0.05,
        int $timesteps = 30
    ): array {
        $network = $this->getNetwork($networkName);
        if ($network === null) {
            throw new NeuromorphicException("Network '{$networkName}' not registered for training.");
        }

        $lastLoss = 0.0;
        for ($ep = 0; $ep < $epochs; $ep++) {
            $epochLoss = 0.0;
            foreach ($dataset as $sample) {
                $res = $network->trainSample($sample['input'], (int)$sample['target'], $learningRate, $timesteps);
                $epochLoss += $res['loss'];
            }
            $lastLoss = count($dataset) > 0 ? $epochLoss / count($dataset) : 0.0;
        }

        // Calculate accuracy
        $correct = 0;
        foreach ($dataset as $sample) {
            $pred = $network->predict($sample['input'], $timesteps);
            if ($pred['predicted_class'] === (int)$sample['target']) {
                $correct++;
            }
        }

        $accuracy = count($dataset) > 0 ? round(($correct / count($dataset)) * 100.0, 2) : 0.0;

        return [
            'epochs'     => $epochs,
            'final_loss' => round($lastLoss, 5),
            'accuracy'   => $accuracy,
        ];
    }

    /**
     * Benchmark neuromorphic Synaptic Operations per Second (SOPs).
     *
     * @param int $neurons
     * @param int $timesteps
     * @return array{
     *   neurons: int,
     *   timesteps: int,
     *   total_sops: int,
     *   elapsed_ms: float,
     *   sops_per_second: float,
     *   spike_sparsity: float
     * }
     */
    public function benchmark(int $neurons = 50, int $timesteps = 50): array
    {
        $snn = new SpikingNeuralNetwork('bench_snn', inputDimension: $neurons);
        $snn->addLayer('layer1', $neurons, NeuronModel::LIF);
        $snn->addLayer('layer2', max(2, (int)($neurons / 2)), NeuronModel::LIF);

        // Generate synthetic sparse input spikes
        $inputSpikes = [];
        $totalInputElements = $timesteps * $neurons;
        $activeInputs = 0;
        for ($t = 0; $t < $timesteps; $t++) {
            $inputSpikes[$t] = [];
            for ($n = 0; $n < $neurons; $n++) {
                $spk = (mt_rand(1, 100) <= 10) ? 1 : 0; // 10% active spikes
                $inputSpikes[$t][$n] = $spk;
                if ($spk === 1) {
                    $activeInputs++;
                }
            }
        }

        $start = microtime(true);
        $res = $snn->forward($inputSpikes);
        $elapsed = max(0.0001, microtime(true) - $start);

        $sops = $res['total_synaptic_operations'];
        $sopsRate = round($sops / $elapsed, 2);
        $sparsity = round((1.0 - ($activeInputs / max(1, $totalInputElements))) * 100.0, 2);

        return [
            'neurons'         => $neurons,
            'timesteps'       => $timesteps,
            'total_sops'      => $sops,
            'elapsed_ms'      => round($elapsed * 1000.0, 2),
            'sops_per_second' => $sopsRate,
            'spike_sparsity'  => $sparsity,
        ];
    }

    public function getEncoder(): SpikeEncoder
    {
        return $this->encoder;
    }

    public function getDecoder(): SpikeDecoder
    {
        return $this->decoder;
    }
}
