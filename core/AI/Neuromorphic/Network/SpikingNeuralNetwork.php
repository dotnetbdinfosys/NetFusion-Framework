<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Network/SpikingNeuralNetwork.php
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

namespace NetFusion\Core\AI\Neuromorphic\Network;

use NetFusion\Core\AI\Neuromorphic\Enums\NeuronModel;
use NetFusion\Core\AI\Neuromorphic\Enums\EncodingScheme;
use NetFusion\Core\AI\Neuromorphic\Encoding\SpikeEncoder;
use NetFusion\Core\AI\Neuromorphic\Encoding\SpikeDecoder;

/**
 * Multi-Layer Spiking Neural Network (SNN) Architecture.
 *
 * Implements asynchronous temporal spike propagation, synaptic weighting,
 * and surrogate gradient / delta rate training.
 */
class SpikingNeuralNetwork
{
    /** @var SpikingLayer[] */
    private array $layers = [];
    private readonly SpikeEncoder $encoder;
    private readonly SpikeDecoder $decoder;

    public function __construct(
        private readonly string $name = 'default_snn',
        private readonly int $inputDimension = 4
    ) {
        $this->encoder = new SpikeEncoder();
        $this->decoder = new SpikeDecoder();
    }

    /**
     * Append a feed-forward spiking layer.
     */
    public function addLayer(string $name, int $size, NeuronModel $model = NeuronModel::LIF): self
    {
        $prevSize = empty($this->layers) ? $this->inputDimension : end($this->layers)->getSize();
        $this->layers[] = new SpikingLayer($name, $size, $model, inputSize: $prevSize);
        return $this;
    }

    /**
     * Execute temporal forward pass over discrete timesteps with a spike matrix.
     *
     * @param array<int, array<int, int>> $inputSpikes [timestep => [channel => 0|1]]
     * @param float $dt Timestep delta in ms
     * @return array{
     *   output_spikes: array<int, array<int, int>>,
     *   total_synaptic_operations: int,
     *   layer_spikes: array<string, array<int, array<int, int>>>
     * }
     */
    public function forward(array $inputSpikes, float $dt = 1.0): array
    {
        $this->reset();

        $timesteps = count($inputSpikes);
        $layerSpikes = [];
        foreach ($this->layers as $layer) {
            $layerSpikes[$layer->getName()] = [];
        }

        $totalSops = 0; // Synaptic Operations
        $lastLayerName = !empty($this->layers) ? end($this->layers)->getName() : null;

        for ($t = 0; $t < $timesteps; $t++) {
            $currentSimTime = $t * $dt;
            $currentInputs = $inputSpikes[$t] ?? [];

            foreach ($this->layers as $layer) {
                // Compute synaptic injection current from incoming spike vector
                $incomingCurrents = $layer->computeIncomingCurrent($currentInputs);

                // Advance neurons
                $spikes = $layer->step($incomingCurrents, $dt, $currentSimTime);
                $layerSpikes[$layer->getName()][$t] = $spikes;

                // Count active synaptic operations
                foreach ($currentInputs as $spk) {
                    if ($spk === 1) {
                        $totalSops += $layer->getSize();
                    }
                }

                // Output of this layer becomes input to subsequent layer
                $currentInputs = $spikes;
            }
        }

        $outputSpikes = $lastLayerName !== null ? $layerSpikes[$lastLayerName] : [];

        return [
            'output_spikes'             => $outputSpikes,
            'total_synaptic_operations' => $totalSops,
            'layer_spikes'              => $layerSpikes,
        ];
    }

    /**
     * End-to-end continuous inference: encode input, run forward pass, decode classification.
     *
     * @param float[] $continuousFeatures
     * @param int $timesteps
     * @param EncodingScheme $scheme
     * @return array{
     *   predicted_class: int,
     *   confidence: float,
     *   total_spikes: int,
     *   firing_rates: float[],
     *   synaptic_operations: int
     * }
     */
    public function predict(
        array $continuousFeatures,
        int $timesteps = 30,
        EncodingScheme $scheme = EncodingScheme::RATE
    ): array {
        $encoded = $this->encoder->encode($continuousFeatures, $scheme, $timesteps);
        $forwardResult = $this->forward($encoded);

        $decodedClass = $this->decoder->decodeClassification($forwardResult['output_spikes']);
        $rates = $this->decoder->decodeRate($forwardResult['output_spikes']);

        return [
            'predicted_class'     => $decodedClass['predicted_class'],
            'confidence'          => $decodedClass['confidence'],
            'total_spikes'        => $decodedClass['total_spikes'],
            'firing_rates'        => $rates,
            'synaptic_operations' => $forwardResult['total_synaptic_operations'],
        ];
    }

    /**
     * Train SNN on a single sample using surrogate rate-delta learning.
     *
     * @param float[] $features
     * @param int $targetClass
     * @param float $learningRate
     * @param int $timesteps
     * @return array{loss: float, predicted: int, target: int}
     */
    public function trainSample(
        array $features,
        int $targetClass,
        float $learningRate = 0.05,
        int $timesteps = 30
    ): array {
        $encoded = $this->encoder->encodeRate($features, $timesteps, deterministic: true);
        $forward = $this->forward($encoded);

        $outputSpikes = $forward['output_spikes'];
        $decoded = $this->decoder->decodeClassification($outputSpikes);
        $outputRates = $this->decoder->decodeRate($outputSpikes);

        $numClasses = !empty($this->layers) ? end($this->layers)->getSize() : 1;
        $targetRates = array_fill(0, $numClasses, 0.0);
        $targetRates[$targetClass] = 1.0;

        // Compute Mean Squared Error Loss on firing rates
        $loss = 0.0;
        $error = [];
        for ($c = 0; $c < $numClasses; $c++) {
            $diff = ($outputRates[$c] ?? 0.0) - $targetRates[$c];
            $loss += 0.5 * ($diff ** 2);
            $error[$c] = -$diff; // gradient direction towards target
        }

        // Apply weight updates to the output layer
        if (!empty($this->layers)) {
            $outputLayer = end($this->layers);
            $weights = $outputLayer->getWeights();

            // Find presynaptic layer
            $prevLayer = count($this->layers) > 1 ? $this->layers[count($this->layers) - 2] : null;
            $prevSpikeCounts = $prevLayer !== null ? $prevLayer->getSpikeCounts() : [];

            foreach ($weights as $inIdx => $outWeights) {
                $preActivity = ($prevSpikeCounts[$inIdx] ?? 0) / max(1, $timesteps);
                if ($prevLayer === null) {
                    $preActivity = max(0.1, (float)($features[$inIdx] ?? 0.5));
                }

                foreach ($outWeights as $outIdx => $w) {
                    $delta = $learningRate * ($error[$outIdx] ?? 0.0) * $preActivity;
                    $weights[$inIdx][$outIdx] = max(0.01, min(2.0, $w + $delta));
                }
            }

            $outputLayer->setWeights($weights);
        }

        return [
            'loss'      => round($loss, 5),
            'predicted' => $decoded['predicted_class'],
            'target'    => $targetClass,
        ];
    }

    /**
     * Reset membrane states across all layers.
     */
    public function reset(): void
    {
        foreach ($this->layers as $layer) {
            $layer->reset();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getInputDimension(): int
    {
        return $this->inputDimension;
    }

    /**
     * @return SpikingLayer[]
     */
    public function getLayers(): array
    {
        return $this->layers;
    }
}
