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

namespace NetFusion\Core\ZKP\Prover;

use NetFusion\Core\ZKP\Math\BigInt;
use NetFusion\Core\ZKP\Math\FiniteField;

/**
 * Non-Interactive Schnorr Zero-Knowledge Proof of Knowledge of Discrete Logarithm
 * using Fiat-Shamir transformation:
 * Proves knowledge of secret x such that y = g^x mod p without revealing x.
 */
class SchnorrZkp
{
    private FiniteField $field;
    private BigInt $generator;

    public function __construct(?FiniteField $field = null, int|string|BigInt $generator = 2)
    {
        $this->field     = $field ?? FiniteField::bn254();
        $this->generator = $generator instanceof BigInt ? $generator : new BigInt($generator);
    }

    /**
     * Compute public commitment y = g^x mod p
     */
    public function computePublicCommitment(int|string|BigInt $secretX): string
    {
        $x = $secretX instanceof BigInt ? $secretX : new BigInt($secretX);
        $y = $this->generator->modPow($x, $this->field->getModulus());
        return $y->toString();
    }

    /**
     * Prove knowledge of secret x:
     * 1. Sample random nonce v
     * 2. Compute commitment t = g^v mod p
     * 3. Compute challenge c = H(g, y, t) via Fiat-Shamir
     * 4. Compute response r = v - c * x mod (p - 1)
     *
     * @return array{commitment_t: string, response_r: string, public_y: string}
     */
    public function prove(int|string|BigInt $secretX): array
    {
        $p = $this->field->getModulus();
        $order = $p->sub(1);
        $x = (new BigInt($secretX))->mod($order);

        // 1. Random v
        $v = (new BigInt(gmp_import(random_bytes(32))))->mod($order);

        // 2. t = g^v mod p
        $t = $this->generator->modPow($v, $p);

        // Public key y = g^x mod p
        $y = $this->generator->modPow($x, $p);

        // 3. Challenge c = Hash(g || y || t) mod order
        $hashStr = $this->generator->toString() . ':' . $y->toString() . ':' . $t->toString();
        $c = (new BigInt(gmp_import(hash('sha256', $hashStr, true))))->mod($order);

        // 4. r = (v - c * x) mod order
        $cx = $c->mul($x)->mod($order);
        $r = $v->sub($cx)->mod($order);

        return [
            'commitment_t' => $t->toString(),
            'response_r'   => $r->toString(),
            'public_y'     => $y->toString(),
        ];
    }

    /**
     * Verify Schnorr proof:
     * 1. Recompute challenge c = H(g, y, t)
     * 2. Check: g^r * y^c mod p == t
     */
    public function verify(string $publicY, string $commitmentT, string $responseR): bool
    {
        $p = $this->field->getModulus();
        $order = $p->sub(1);

        $y = new BigInt($publicY);
        $t = new BigInt($commitmentT);
        $r = (new BigInt($responseR))->mod($order);

        // Recompute c = Hash(g || y || t)
        $hashStr = $this->generator->toString() . ':' . $y->toString() . ':' . $t->toString();
        $c = (new BigInt(gmp_import(hash('sha256', $hashStr, true))))->mod($order);

        // g^r mod p
        $gr = $this->generator->modPow($r, $p);
        // y^c mod p
        $yc = $y->modPow($c, $p);

        // check: (g^r * y^c) mod p == t
        $reconstructedT = $gr->mul($yc)->mod($p);

        return $reconstructedT->equals($t);
    }
}
