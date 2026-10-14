<?php

declare(strict_types=1);

/**
 * NetFusion Framework - Enterprise Brain-Computer Interface (BCI) & Neural Signal Decoding
 *
 * @author  Md. Atiquzzaman Ashish <atiquzzaman.ashish@dotnetbd.com>
 * @company DotNetBD
 * @link    https://www.dotnetbd.com
 * @license MIT
 */

namespace NetFusion\Core\Neural\Bci\Feedback;

use NetFusion\Core\Neural\Bci\Dsp\FftEngine;
use NetFusion\Core\Neural\Bci\Enums\BrainWaveBand;
use NetFusion\Core\Neural\Bci\Enums\MentalState;

class NeuroFeedbackController
{
    protected float $samplingRate;
    protected float $relaxationThreshold;
    protected float $focusThreshold;
    protected float $hysteresis;

    protected ?MentalState $currentState = null;
    /** @var array<int, array{timestamp: float, state: string, metric: float, feedbackLevel: float}> */
    protected array $eventHistory = [];

    public function __construct(
        float $samplingRate = 250.0,
        float $relaxationThreshold = 1.5,
        float $focusThreshold = 1.2,
        float $hysteresis = 0.1
    ) {
        $this->samplingRate = $samplingRate;
        $this->relaxationThreshold = $relaxationThreshold;
        $this->focusThreshold = $focusThreshold;
        $this->hysteresis = $hysteresis;
    }

    /**
     * Process an incoming EEG time-window and evaluate neuro-feedback control loop
     *
     * @param float[] $signal Time-domain signal from key electrode (e.g. Cz or Pz)
     * @return array{
     *     state: MentalState,
     *     relaxationIndex: float,
     *     focusIndex: float,
     *     feedbackLevel: float,
     *     bandPowers: array<string, float>,
     *     recommendation: string
     * }
     */
    public function evaluate(array $signal): array
    {
        $powers = FftEngine::extractBandPowers($signal, $this->samplingRate);
        $abs = $powers['absolute'];

        $theta = max(1e-10, $abs[BrainWaveBand::THETA->value]);
        $alpha = max(1e-10, $abs[BrainWaveBand::ALPHA->value]);
        $beta  = max(1e-10, $abs[BrainWaveBand::BETA->value]);

        // Relaxation index: Alpha / Theta ratio
        $relaxationIndex = round($alpha / $theta, 4);

        // Focus / Engagement index: Beta / (Theta + Alpha)
        $focusIndex = round($beta / ($theta + $alpha), 4);

        // State detection with hysteresis
        $newState = $this->determineState($relaxationIndex, $focusIndex);
        $this->currentState = $newState;

        // Feedback level (0.0 to 1.0)
        $feedbackLevel = $this->calculateFeedbackLevel($newState, $relaxationIndex, $focusIndex);

        $recommendation = match ($newState) {
            MentalState::DEEP_RELAXATION => 'Maintain slow diaphragmatic breathing; meditative alpha rhythm stabilized.',
            MentalState::CALM_ALERT => 'Optimal flow state detected; equilibrium between sensorimotor calm and focus.',
            MentalState::HIGH_FOCUS => 'High cognitive processing load; active beta synchronization.',
            MentalState::FATIGUED => 'Theta dominance detected; suggest cognitive rest interval or relaxation prompt.',
            MentalState::MOTOR_INTENT => 'Sensorimotor desynchronization event active.',
        };

        $evalResult = [
            'state' => $newState,
            'relaxationIndex' => $relaxationIndex,
            'focusIndex' => $focusIndex,
            'feedbackLevel' => $feedbackLevel,
            'bandPowers' => $powers['relative'],
            'recommendation' => $recommendation,
        ];

        $this->eventHistory[] = [
            'timestamp' => microtime(true),
            'state' => $newState->value,
            'metric' => ($newState === MentalState::DEEP_RELAXATION) ? $relaxationIndex : $focusIndex,
            'feedbackLevel' => $feedbackLevel,
        ];

        return $evalResult;
    }

    protected function determineState(float $relaxationIndex, float $focusIndex): MentalState
    {
        $relUpper = $this->relaxationThreshold;
        $relLower = $this->relaxationThreshold - $this->hysteresis;
        $focUpper = $this->focusThreshold;
        $focLower = $this->focusThreshold - $this->hysteresis;

        // Check if Focus dominates
        if ($focusIndex >= $focUpper && $focusIndex > $relaxationIndex) {
            return MentalState::HIGH_FOCUS;
        }

        // Check if Relaxation dominates
        if ($relaxationIndex >= $relUpper && $relaxationIndex > $focusIndex) {
            return MentalState::DEEP_RELAXATION;
        }

        // Apply hysteresis to current state
        if ($this->currentState === MentalState::HIGH_FOCUS && $focusIndex >= $focLower) {
            return MentalState::HIGH_FOCUS;
        }

        if ($this->currentState === MentalState::DEEP_RELAXATION && $relaxationIndex >= $relLower) {
            return MentalState::DEEP_RELAXATION;
        }

        if ($relaxationIndex < 0.6 && $focusIndex < 0.4) {
            return MentalState::FATIGUED;
        }

        return MentalState::CALM_ALERT;
    }

    protected function calculateFeedbackLevel(MentalState $state, float $relaxationIndex, float $focusIndex): float
    {
        return match ($state) {
            MentalState::DEEP_RELAXATION => min(1.0, max(0.0, $relaxationIndex / 3.0)),
            MentalState::HIGH_FOCUS      => min(1.0, max(0.0, $focusIndex / 2.0)),
            MentalState::CALM_ALERT      => 0.5,
            MentalState::FATIGUED        => 0.1,
            MentalState::MOTOR_INTENT    => 0.8,
        };
    }

    public function getCurrentState(): ?MentalState
    {
        return $this->currentState;
    }

    public function getEventHistory(): array
    {
        return $this->eventHistory;
    }
}
