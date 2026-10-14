<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/AI/Neuromorphic/Network/SpikingLayer.php
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
use NetFusion\Core\AI\Neuromorphic\Neurons\LIFNeuron;
use NetFusion\Core\AI\Neuromorphic\Neurons\IzhikevichNeuron;

/**
 * Homogeneous Layer of Biophysical Spiking Neurons.
 */
class SpikingLayer
{
    /** @var array<int, LIFNeuron|IzhikevichNeuron> */
    private array $neurons = [];

    /** @var array<int, array<int, float>> [prevIndex => [thisIndex => weight]] */
    private array $weights = [];

    public function __construct(
        private readonly string $name,
        private readonly int $size,
        private readonly NeuronModel $model = NeuronModel::LIF,
        private readonly int $inputSize = 0
    ) {
        $this->initializeNeurons();
        $this->initializeWeights();
    }

    private function initializeNeurons(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $id = "{$this->name}_n{$i}";
            if ($this->model === NeuronModel::IZHIKEVICH) {
                $this->neurons[$i] = new IzhikevichNeuron($id);
            } else {
                $this->neurons[$i] = new LIFNeuron($id);
            }
        }
    }

    private function initializeWeights(): void
    {
        if ($this->inputSize <= 0) {
            return;
        }

        // Initialize Gaussian / Xavier-like random weights
        $scale = sqrt(2.0 / ($this->inputSize + $this->size));
        for ($in = 0; $in < $this->inputSize; $in++) {
            $this->weights[$in] = [];
            for ($out = 0; $out < $this->size; $out++) {
                $this->weights[$in][$out] = round((mt_rand(1, 1000) / 1000.0) * $scale, 4);
            }
        }
    }

    /**
     * Advance all neurons in the layer for a single timestep.
     *
     * @param float[] $inputCurrents Array of injected currents per neuron
     * @param float $dt Timestep in ms
     * @param float $currentTime Current simulation time
     * @return array<int, int> Binary spike vector [0 or 1 per neuron]
     */
    public function step(array $inputCurrents, float $dt = 1.0, float $currentTime = 0.0): array
    {
        $spikes = [];
        for ($i = 0; $i < $this->size; $i++) {
            $current = $inputCurrents[$i] ?? 0.0;
            $fired = $this->neurons[$i]->step($current, $dt, $currentTime);
            $spikes[$i] = $fired ? 1 : 0;
        }
        return $spikes;
    }

    /**
     * Compute injected currents to this layer from the previous layer's spike vector.
     *
     * @param array<int, int> $preSpikes Spike vector from preceding layer
     * @return float[] Injected current vector for each neuron in this layer
     */
    public function computeIncomingCurrent(array $preSpikes): array
    {
        $currents = array_fill(0, $this->size, 0.0);
        foreach ($preSpikes as $inIdx => $spike) {
            if ($spike === 1 && isset($this->weights[$inIdx])) {
                foreach ($this->weights[$inIdx] as $outIdx => $weight) {
                    $currents[$outIdx] += $weight;
                }
            }
        }
        return $currents;
    }

    public function reset(): void
    {
        foreach ($this->neurons as $neuron) {
            $neuron->reset();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getModel(): NeuronModel
    {
        return $this->model;
    }

    /**
     * @return array<int, LIFNeuron|IzhikevichNeuron>
     */
    public function getNeurons(): array
    {
        return $this->neurons;
    }

    /**
     * @return array<int, int>
     */
    public function getSpikeCounts(): array
    {
        $counts = [];
        foreach ($this->neurons as $i => $n) {
            $counts[$i] = $n->getSpikeCount();
        }
        return $counts;
    }

    /**
     * @return array<int, array<int, float>>
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    /**
     * @param array<int, array<int, float>> $weights
     */
    public function setWeights(array $weights): void
    {
        $this->weights = $weights;
    }

    public function setWeight(int $inIdx, int $outIdx, float $weight): void
    {
        $this->weights[$inIdx][$outIdx] = $weight;
    }
}
