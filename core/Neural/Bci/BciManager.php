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

namespace NetFusion\Core\Neural\Bci;

use NetFusion\Core\Neural\Bci\Decoder\MotorImageryDecoder;
use NetFusion\Core\Neural\Bci\Dsp\DspFilter;
use NetFusion\Core\Neural\Bci\Dsp\FftEngine;
use NetFusion\Core\Neural\Bci\Enums\BciModality;
use NetFusion\Core\Neural\Bci\Enums\SignalChannel;
use NetFusion\Core\Neural\Bci\Exceptions\BciSignalException;
use NetFusion\Core\Neural\Bci\Exceptions\ElectrodeImpedanceException;
use NetFusion\Core\Neural\Bci\Feedback\NeuroFeedbackController;

class BciManager
{
    protected float $samplingRate;
    protected float $notchFrequency;
    protected float $maxElectrodeImpedanceKohms;

    /** @var array<string, array<string, float[]>> Session multi-channel ring buffers */
    protected array $sessionBuffers = [];
    /** @var array<string, array{modality: BciModality, startedAt: float, channels: string[], status: string}> */
    protected array $activeSessions = [];
    /** @var array<string, MotorImageryDecoder> */
    protected array $decoders = [];
    protected NeuroFeedbackController $neuroFeedback;

    public function __construct(
        float $samplingRate = 250.0,
        float $notchFrequency = 50.0,
        float $maxElectrodeImpedanceKohms = 10.0,
        ?NeuroFeedbackController $neuroFeedback = null
    ) {
        $this->samplingRate = $samplingRate;
        $this->notchFrequency = $notchFrequency;
        $this->maxElectrodeImpedanceKohms = $maxElectrodeImpedanceKohms;
        $this->neuroFeedback = $neuroFeedback ?? new NeuroFeedbackController($samplingRate);
    }

    /**
     * Start a new BCI recording or decoding session
     *
     * @param string $sessionId
     * @param BciModality $modality
     * @param string[] $channels List of electrode channels (e.g. ['C3', 'Cz', 'C4'])
     */
    public function startSession(string $sessionId, BciModality $modality, array $channels): void
    {
        $this->activeSessions[$sessionId] = [
            'modality' => $modality,
            'startedAt' => microtime(true),
            'channels' => $channels,
            'status' => 'ACTIVE',
        ];

        $this->sessionBuffers[$sessionId] = [];
        foreach ($channels as $ch) {
            $this->sessionBuffers[$sessionId][$ch] = [];
        }
    }

    /**
     * Ingest sample batch for a specific channel in an active session
     *
     * @param string $sessionId
     * @param string $channel
     * @param float[] $microvolts
     * @param float $impedanceKohms Optional electrode contact impedance in kΩ
     */
    public function ingestStream(
        string $sessionId,
        string $channel,
        array $microvolts,
        float $impedanceKohms = 5.0
    ): void {
        if (!isset($this->activeSessions[$sessionId])) {
            throw new BciSignalException("BCI session not found or inactive: {$sessionId}");
        }

        if ($impedanceKohms > $this->maxElectrodeImpedanceKohms) {
            throw new ElectrodeImpedanceException("Electrode impedance on channel {$channel} ({$impedanceKohms} kOhm) exceeds safety threshold ({$this->maxElectrodeImpedanceKohms} kOhm).");
        }

        if (!isset($this->sessionBuffers[$sessionId][$channel])) {
            $this->sessionBuffers[$sessionId][$channel] = [];
        }

        foreach ($microvolts as $sample) {
            $this->sessionBuffers[$sessionId][$channel][] = $sample;
        }
    }

    /**
     * Apply full DSP cleaning pipeline: Notch filter + Bandpass + CAR
     *
     * @param array<string, float[]> $channelSignals
     * @param float $lowCut
     * @param float $highCut
     * @return array<string, float[]>
     */
    public function cleanSignals(array $channelSignals, float $lowCut = 1.0, float $highCut = 45.0): array
    {
        $notchCoeff = DspFilter::designNotch($this->notchFrequency, $this->samplingRate);
        $bandpassCoeff = DspFilter::designBandpass($lowCut, $highCut, $this->samplingRate);

        $filtered = [];
        foreach ($channelSignals as $ch => $samples) {
            // 1. Notch filter (50Hz / 60Hz power-line elimination)
            $notched = DspFilter::filtfilt($samples, $notchCoeff);
            // 2. Bandpass filter
            $bandpassed = DspFilter::filtfilt($notched, $bandpassCoeff);
            $filtered[$ch] = $bandpassed;
        }

        // 3. Common Average Reference (CAR)
        return DspFilter::commonAverageReference($filtered);
    }

    /**
     * Register or retrieve Motor Imagery decoder
     */
    public function getMotorImageryDecoder(string $name = 'default'): MotorImageryDecoder
    {
        if (!isset($this->decoders[$name])) {
            $this->decoders[$name] = new MotorImageryDecoder($this->samplingRate);
        }
        return $this->decoders[$name];
    }

    /**
     * Evaluate neuro-feedback on a specified channel stream
     *
     * @param float[] $signal
     * @return array{state: \NetFusion\Core\Neural\Bci\Enums\MentalState, relaxationIndex: float, focusIndex: float, feedbackLevel: float, bandPowers: array<string, float>, recommendation: string}
     */
    public function evaluateFeedback(array $signal): array
    {
        return $this->neuroFeedback->evaluate($signal);
    }

    public function getNeuroFeedbackController(): NeuroFeedbackController
    {
        return $this->neuroFeedback;
    }

    /**
     * Retrieve buffered samples for an active session
     *
     * @return array<string, float[]>
     */
    public function getSessionBuffer(string $sessionId): array
    {
        return $this->sessionBuffers[$sessionId] ?? [];
    }

    public function endSession(string $sessionId): array
    {
        if (!isset($this->activeSessions[$sessionId])) {
            throw new BciSignalException("Session {$sessionId} does not exist.");
        }

        $session = $this->activeSessions[$sessionId];
        $session['status'] = 'COMPLETED';
        $session['endedAt'] = microtime(true);
        $session['totalSamplesPerChannel'] = array_map(fn($buf) => count($buf), $this->sessionBuffers[$sessionId] ?? []);

        unset($this->activeSessions[$sessionId]);
        unset($this->sessionBuffers[$sessionId]);

        return $session;
    }

    public function getSamplingRate(): float
    {
        return $this->samplingRate;
    }
}
