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

namespace NetFusion\Core\ZKP;

use NetFusion\Core\ZKP\Circuit\R1csCircuit;
use NetFusion\Core\ZKP\Circuit\CircuitCompiler;
use NetFusion\Core\ZKP\Prover\Groth16Engine;
use NetFusion\Core\ZKP\Prover\SchnorrZkp;
use NetFusion\Core\ZKP\Prover\Proof;
use NetFusion\Core\ZKP\Prover\ProvingKey;
use NetFusion\Core\ZKP\Prover\VerifyingKey;
use NetFusion\Core\ZKP\Credentials\VerifiableCredential;
use NetFusion\Core\ZKP\Credentials\SelectiveDisclosure;
use NetFusion\Core\ZKP\Math\FiniteField;

/**
 * Master Central Facade for Enterprise Zero-Knowledge Proof (ZKP) Engine & Verifiable Credential Privacy Fabric.
 */
class ZkpManager
{
    private array $config;
    private FiniteField $field;
    private SchnorrZkp $schnorr;
    /** @var array<string, array{pk: ProvingKey, vk: VerifyingKey, circuit: R1csCircuit}> */
    private array $compiledCircuits = [];

    public function __construct(array $config = [])
    {
        $this->config  = array_merge([
            'default_field'   => 'bn254',
            'security_level'  => 128,
            'trusted_setup'   => 'groth16',
        ], $config);

        $this->field   = FiniteField::bn254();
        $this->schnorr = new SchnorrZkp($this->field);
    }

    public function getField(): FiniteField
    {
        return $this->field;
    }

    public function schnorr(): SchnorrZkp
    {
        return $this->schnorr;
    }

    /**
     * Create an empty arithmetic R1CS circuit.
     */
    public function circuit(string $name): R1csCircuit
    {
        return new R1csCircuit($name, $this->field);
    }

    /**
     * Get a pre-compiled circuit gadget by name (e.g., 'cubic', 'factor', 'range8').
     */
    public function gadget(string $type, string $name = 'gadget'): R1csCircuit
    {
        return match ($type) {
            'cubic'   => CircuitCompiler::cubicPolynomial($name, $this->field),
            'factor'  => CircuitCompiler::factorMultiplier($name, $this->field),
            'range8'  => CircuitCompiler::rangeProof8Bit($name, $this->field),
            default   => new R1csCircuit($name, $this->field),
        };
    }

    /**
     * Perform setup and register circuit keys.
     *
     * @return array{pk: ProvingKey, vk: VerifyingKey}
     */
    public function setup(R1csCircuit $circuit): array
    {
        $keys = Groth16Engine::setup($circuit);
        $this->compiledCircuits[$circuit->getName()] = [
            'pk'      => $keys['pk'],
            'vk'      => $keys['vk'],
            'circuit' => $circuit,
        ];
        return $keys;
    }

    /**
     * Prove circuit statement from witness.
     */
    public function prove(R1csCircuit|string $circuit, array $witness, ?ProvingKey $pk = null): Proof
    {
        $circuitInstance = $circuit instanceof R1csCircuit ? $circuit : ($this->compiledCircuits[$circuit]['circuit'] ?? null);
        if (!$circuitInstance) {
            $circuitInstance = $this->gadget($circuit, $circuit);
        }

        $provingKey = $pk ?? ($this->compiledCircuits[$circuitInstance->getName()]['pk'] ?? null);
        if (!$provingKey) {
            $keys = $this->setup($circuitInstance);
            $provingKey = $keys['pk'];
        }

        return Groth16Engine::prove($circuitInstance, $provingKey, $witness);
    }

    /**
     * Verify zero-knowledge proof.
     */
    public function verify(VerifyingKey|string $vk, Proof $proof): bool
    {
        $verifyingKey = $vk instanceof VerifyingKey ? $vk : ($this->compiledCircuits[$vk]['vk'] ?? null);
        if (!$verifyingKey) {
            return false;
        }

        return Groth16Engine::verify($verifyingKey, $proof);
    }

    /**
     * Issue a privacy-preserving verifiable credential.
     */
    public function issueCredential(string $id, string $issuer, string $subject, array $claims): VerifiableCredential
    {
        return new VerifiableCredential($id, $issuer, $subject, $claims);
    }

    /**
     * Create selective disclosure presentation.
     */
    public function disclose(VerifiableCredential $vc, array $attributes): array
    {
        return SelectiveDisclosure::createPresentation($vc, $attributes);
    }

    /**
     * Verify selective disclosure presentation.
     */
    public function verifyDisclosure(array $presentation): bool
    {
        return SelectiveDisclosure::verifyPresentation($presentation);
    }
}
