<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework
 |--------------------------------------------------------------------------
 |
 | File      : core/Security/Quantum/QuantumKeyManager.php
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

namespace NetFusion\Core\Security\Quantum;

use NetFusion\Core\Security\Quantum\Channel\QkdChannel;
use NetFusion\Core\Security\Quantum\Exceptions\EavesdroppingDetectedException;
use NetFusion\Core\Security\Quantum\Exceptions\QuantumException;
use NetFusion\Core\Security\Quantum\Processing\KeySifter;
use NetFusion\Core\Security\Quantum\Processing\PrivacyAmplifier;
use NetFusion\Core\Security\Quantum\Processing\QberAnalyzer;
use NetFusion\Core\Security\Quantum\Protocols\BB84Protocol;
use NetFusion\Core\Security\Quantum\Protocols\E91Protocol;

/**
 * Master Enterprise Quantum Key Distribution & Key Mesh Orchestrator.
 */
class QuantumKeyManager
{
    private static ?self $instance = null;

    /** @var array<string, array<string>> peer_id => [key_hex_1, key_hex_2, ...] */
    private array $keyPools = [];

    private BB84Protocol $bb84;
    private E91Protocol $e91;
    private KeySifter $sifter;
    private QberAnalyzer $qberAnalyzer;
    private PrivacyAmplifier $amplifier;
    private QkdChannel $channel;

    public function __construct(array $config = [])
    {
        $qberThreshold = (float)($config['qber_threshold'] ?? 0.11);

        $this->bb84         = new BB84Protocol();
        $this->e91          = new E91Protocol();
        $this->sifter       = new KeySifter();
        $this->qberAnalyzer = new QberAnalyzer($qberThreshold);
        $this->amplifier    = new PrivacyAmplifier();
        $this->channel      = new QkdChannel();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getChannel(): QkdChannel
    {
        return $this->channel;
    }

    public function getQberAnalyzer(): QberAnalyzer
    {
        return $this->qberAnalyzer;
    }

    /**
     * Execute BB84 QKD Exchange with a remote peer.
     *
     * @param string $peerId Remote QKD node identifier
     * @param int $photons Number of quantum states to exchange
     * @param float $eavesdropRate Simulated presence of Eve
     * @param float $noiseRate Quantum channel background noise
     * @return array{
     *   peer_id: string,
     *   key_hex: string,
     *   qber: float,
     *   sifting_efficiency: float,
     *   photons_sent: int,
     *   sifted_bits: int,
     *   distilled_bytes: int
     * }
     * @throws EavesdroppingDetectedException
     */
    public function exchangeBb84(
        string $peerId,
        int $photons = 256,
        float $eavesdropRate = 0.0,
        float $noiseRate = 0.0
    ): array {
        // 1. Quantum Transmission
        $raw = $this->bb84->transmit($photons, $eavesdropRate, $noiseRate);

        // 2. Classical Sifting
        $sifted = $this->sifter->sift(
            $raw['alice_bits'],
            $raw['alice_bases'],
            $raw['bob_bits'],
            $raw['bob_bases']
        );

        // 3. Error Rate Estimation & Eavesdropper Detection
        $qberResult = $this->qberAnalyzer->analyze(
            $sifted['sifted_alice'],
            $sifted['sifted_bob'],
            sampleFraction: 0.20
        );

        // 4. Privacy Amplification (distill into 256-bit symmetric key)
        $distilledKeyBinary = $this->amplifier->amplify($qberResult['remaining_alice'], 32, "qkd:{$peerId}");
        $keyHex = bin2hex($distilledKeyBinary);

        // Store into peer key pool
        $this->keyPools[$peerId][] = $keyHex;

        return [
            'peer_id'            => $peerId,
            'key_hex'            => $keyHex,
            'qber'               => $qberResult['qber'],
            'sifting_efficiency' => $sifted['sifting_efficiency'],
            'photons_sent'       => $photons,
            'sifted_bits'        => count($sifted['sifted_alice']),
            'distilled_bytes'    => strlen($distilledKeyBinary),
        ];
    }

    /**
     * Execute E91 Entanglement-based QKD Exchange.
     *
     * @param string $peerId
     * @param int $pairs
     * @param float $eavesdropRate
     * @return array{
     *   peer_id: string,
     *   key_hex: string,
     *   bell_parameter_s: float,
     *   bell_violated: bool
     * }
     * @throws EavesdroppingDetectedException
     */
    public function exchangeE91(
        string $peerId,
        int $pairs = 256,
        float $eavesdropRate = 0.0
    ): array {
        $raw = $this->e91->distribute($pairs, $eavesdropRate);

        if (!$raw['bell_violated']) {
            throw new EavesdroppingDetectedException(
                "Bell inequality test failed (S = {$raw['bell_parameter_s']} <= 2.0). Quantum entanglement broken by eavesdropping."
            );
        }

        // Sift matching basis measurements
        $sifted = $this->sifter->sift(
            $raw['alice_bits'],
            $raw['alice_bases'],
            $raw['bob_bits'],
            $raw['bob_bases']
        );

        $distilledKeyBinary = $this->amplifier->amplify($sifted['sifted_alice'], 32, "e91:{$peerId}");
        $keyHex = bin2hex($distilledKeyBinary);

        $this->keyPools[$peerId][] = $keyHex;

        return [
            'peer_id'          => $peerId,
            'key_hex'          => $keyHex,
            'bell_parameter_s' => $raw['bell_parameter_s'],
            'bell_violated'    => $raw['bell_violated'],
        ];
    }

    /**
     * Consume a distilled key from the pool and encrypt plaintext using OTP.
     */
    public function encryptMessage(string $peerId, string $message): array
    {
        if (empty($this->keyPools[$peerId])) {
            // Auto-negotiate fresh key if pool is empty
            $this->exchangeBb84($peerId, 256);
        }

        $keyHex = array_shift($this->keyPools[$peerId]);
        $keyBinary = hex2bin($keyHex);

        $otp = $this->channel->encryptOtp($message, $keyBinary);
        $otp['peer_id'] = $peerId;
        return $otp;
    }

    /**
     * Decrypt an OTP message using the specified key.
     */
    public function decryptMessage(string $ciphertextHex, string $keyUsedHex): string
    {
        return $this->channel->decryptOtp($ciphertextHex, $keyUsedHex);
    }

    /**
     * @return string[]
     */
    public function getKeyPool(string $peerId): array
    {
        return $this->keyPools[$peerId] ?? [];
    }

    public function getKeyPoolCount(string $peerId): int
    {
        return count($this->keyPools[$peerId] ?? []);
    }
}
