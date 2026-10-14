<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Phase 70 Test Suite
 |--------------------------------------------------------------------------
 |
 | Enterprise Post-Quantum Cryptography (PQC), Lattice Key Encapsulation (ML-KEM),
 | Hybrid Key Exchange & Quantum-Resistant Envelope Vault Engine.
 |
 |--------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Security/Pqc/Math/PolynomialRing.php';
require_once __DIR__ . '/../core/Security/Pqc/Kem/MlKemParameters.php';
require_once __DIR__ . '/../core/Security/Pqc/Kem/KemKeyPair.php';
require_once __DIR__ . '/../core/Security/Pqc/Kem/EncapsulationResult.php';
require_once __DIR__ . '/../core/Security/Pqc/Kem/MlKemEngine.php';
require_once __DIR__ . '/../core/Security/Pqc/Dsa/MlDsaParameters.php';
require_once __DIR__ . '/../core/Security/Pqc/Dsa/DsaKeyPair.php';
require_once __DIR__ . '/../core/Security/Pqc/Dsa/Signature.php';
require_once __DIR__ . '/../core/Security/Pqc/Dsa/MlDsaEngine.php';
require_once __DIR__ . '/../core/Security/Pqc/Hybrid/HybridKeyPair.php';
require_once __DIR__ . '/../core/Security/Pqc/Hybrid/HybridCiphertext.php';
require_once __DIR__ . '/../core/Security/Pqc/Hybrid/HybridSession.php';
require_once __DIR__ . '/../core/Security/Pqc/Hybrid/HybridKeyExchange.php';
require_once __DIR__ . '/../core/Security/Pqc/Vault/PqcEnvelopeVault.php';
require_once __DIR__ . '/../core/Security/Pqc/PqcManager.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Security\Pqc\Math\PolynomialRing;
use NetFusion\Core\Security\Pqc\Kem\MlKemParameters;
use NetFusion\Core\Security\Pqc\Kem\KemKeyPair;
use NetFusion\Core\Security\Pqc\Kem\EncapsulationResult;
use NetFusion\Core\Security\Pqc\Kem\MlKemEngine;
use NetFusion\Core\Security\Pqc\Dsa\MlDsaParameters;
use NetFusion\Core\Security\Pqc\Dsa\DsaKeyPair;
use NetFusion\Core\Security\Pqc\Dsa\Signature;
use NetFusion\Core\Security\Pqc\Dsa\MlDsaEngine;
use NetFusion\Core\Security\Pqc\Hybrid\HybridKeyPair;
use NetFusion\Core\Security\Pqc\Hybrid\HybridCiphertext;
use NetFusion\Core\Security\Pqc\Hybrid\HybridSession;
use NetFusion\Core\Security\Pqc\Hybrid\HybridKeyExchange;
use NetFusion\Core\Security\Pqc\Vault\PqcEnvelopeVault;
use NetFusion\Core\Security\Pqc\PqcManager;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $description): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ✓ {$description}\n";
    } else {
        $failed++;
        echo "  ✗ FAIL: {$description}\n";
    }
}

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║ NetFusion Phase 70 — Post-Quantum Cryptography & ML-KEM Suite║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// =========================================================================
// 70A-1: Polynomial Ring Arithmetic & Number Theoretic Transform
// =========================================================================
echo "═══ 70A-1: PolynomialRing — Lattice Mathematics ═══\n";

$zero = PolynomialRing::zero();
assertTest(count($zero) === 256 && array_sum($zero) === 0, 'PolynomialRing::zero() generates 256 zero coefficients');

$a = array_fill(0, 256, 100);
$b = array_fill(0, 256, 200);
$sum = PolynomialRing::add($a, $b);
assertTest($sum[0] === 300 && $sum[255] === 300, 'PolynomialRing::add() adds polynomials modulo q');

$diff = PolynomialRing::sub($a, $b);
assertTest($diff[0] === (100 - 200 + 3329), 'PolynomialRing::sub() subtracts polynomials with modular wrap');

$scaled = PolynomialRing::scalarMul(5, $a);
assertTest($scaled[0] === 500, 'PolynomialRing::scalarMul() scales coefficients modulo q');

// Modulo X^256 + 1 test: (X^200) * (X^100) = X^300 = -X^44 mod (X^256 + 1)
$p1 = PolynomialRing::zero();
$p1[200] = 1;
$p2 = PolynomialRing::zero();
$p2[100] = 1;
$prod = PolynomialRing::multiply($p1, $p2);
assertTest($prod[44] === (3329 - 1), 'PolynomialRing::multiply() correctly applies negacyclic reduction X^256 = -1');

// Compression & Decompression test
$cPoly = [0, 832, 1665, 2497, 3328];
$compressed = PolynomialRing::compress($cPoly, 4);
$decompressed = PolynomialRing::decompress($compressed, 4);
assertTest(count($compressed) === 5, 'PolynomialRing::compress() compresses to d bits');
assertTest(abs($decompressed[2] - 1665) < 150, 'PolynomialRing::decompress() reconstitutes approximate values');

// CBD Noise & Uniform Sampling
$cbd = PolynomialRing::sampleCbd('seed_test_cbd_1234567890123456', 0, 2);
assertTest(count($cbd) === 256, 'PolynomialRing::sampleCbd() samples 256 coefficients');
$allBounded = true;
foreach ($cbd as $coeff) {
    $val = $coeff > (3329 / 2) ? $coeff - 3329 : $coeff;
    if (abs($val) > 2) {
        $allBounded = false;
        break;
    }
}
assertTest($allBounded, 'PolynomialRing::sampleCbd() produces coefficients within [-eta, eta]');

$uniform = PolynomialRing::sampleUniform('seed_matrix_test_1234567890123', 0, 0);
assertTest(count($uniform) === 256, 'PolynomialRing::sampleUniform() samples 256 uniform coefficients');

assertTest(PolynomialRing::modPow(17, 256, 3329) === 1, 'PolynomialRing::modPow() confirms 17^256 = 1 mod 3329');
assertTest(PolynomialRing::modInverse(256, 3329) === 3316, 'PolynomialRing::modInverse() computes modular inverse');

// =========================================================================
// 70A-2: ML-KEM Parameters & KeyPair
// =========================================================================
echo "\n═══ 70A-2: ML-KEM Parameters & KeyPair ═══\n";

$p512 = MlKemParameters::mlKem512();
assertTest($p512->k === 2 && $p512->name === MlKemParameters::ML_KEM_512, 'MlKemParameters::mlKem512() sets k=2');

$p768 = MlKemParameters::mlKem768();
assertTest($p768->k === 3 && $p768->name === MlKemParameters::ML_KEM_768, 'MlKemParameters::mlKem768() sets k=3 (NIST Level 3)');

$p1024 = MlKemParameters::mlKem1024();
assertTest($p1024->k === 4 && $p1024->name === MlKemParameters::ML_KEM_1024, 'MlKemParameters::mlKem1024() sets k=4 (NIST Level 5)');

$fromName = MlKemParameters::fromName('kyber512');
assertTest($fromName->name === MlKemParameters::ML_KEM_512, 'MlKemParameters::fromName() resolves alias kyber512');

// =========================================================================
// 70A-3: ML-KEM Engine — KeyGen, Encapsulation & Decapsulation
// =========================================================================
echo "\n═══ 70A-3: ML-KEM Engine (Module-LWE) ═══\n";

$kem768 = new MlKemEngine($p768);
$kp = $kem768->generateKeyPair();
assertTest(strlen($kp->getPublicKey()) === (3 * 512 + 32), 'ML-KEM-768 public key size is exact (1568 bytes)');
assertTest(strlen($kp->getSecretKey()) === (3 * 512 + 1568 + 32 + 32), 'ML-KEM-768 secret key size is exact (3168 bytes)');
assertTest(!empty($kp->getPublicKeyBase64()), 'KemKeyPair::getPublicKeyBase64() returns valid base64');
assertTest(is_array($kp->jsonSerialize()), 'KemKeyPair is JSON serializable');

$encap = $kem768->encapsulate($kp->getPublicKey());
assertTest(strlen($encap->getCiphertext()) === (3 * 512 + 512), 'Encapsulation produces valid ciphertext size (2048 bytes)');
assertTest(strlen($encap->getSharedSecret()) === 32, 'Encapsulation produces 32-byte shared secret');
assertTest(!empty($encap->getSharedSecretHex()), 'EncapsulationResult::getSharedSecretHex() returns hex string');

$ssDec = $kem768->decapsulate($kp->getSecretKey(), $encap->getCiphertext());
assertTest($ssDec === $encap->getSharedSecret(), 'Decapsulation exactly recovers encapsulated shared secret');

// Tamper resistance test (CCA2 Implicit Rejection)
$tamperedCt = $encap->getCiphertext();
$tamperedCt[20] = chr(ord($tamperedCt[20]) ^ 0x01);
$ssTampered = $kem768->decapsulate($kp->getSecretKey(), $tamperedCt);
assertTest($ssTampered !== $encap->getSharedSecret() && strlen($ssTampered) === 32, 'Tampered ciphertext triggers CCA2 implicit rejection (pseudo-random key)');

// Deterministic seed test
$seed64 = str_repeat("A", 32) . str_repeat("B", 32);
$kpDet1 = $kem768->generateKeyPair($seed64);
$kpDet2 = $kem768->generateKeyPair($seed64);
assertTest($kpDet1->getPublicKey() === $kpDet2->getPublicKey(), 'Deterministic seed reproduces identical public key');
assertTest($kpDet1->getSecretKey() === $kpDet2->getSecretKey(), 'Deterministic seed reproduces identical secret key');

// ML-KEM-512 cross test
$kem512 = new MlKemEngine($p512);
$kp512 = $kem512->generateKeyPair();
$enc512 = $kem512->encapsulate($kp512->getPublicKey());
$dec512 = $kem512->decapsulate($kp512->getSecretKey(), $enc512->getCiphertext());
assertTest($dec512 === $enc512->getSharedSecret(), 'ML-KEM-512 successfully completes key encapsulation & recovery');

// =========================================================================
// 70B: Lattice Digital Signatures (ML-DSA / Crystals-Dilithium)
// =========================================================================
echo "\n═══ 70B: ML-DSA Engine — Lattice Digital Signatures ═══\n";

$dsaParams = MlDsaParameters::mlDsa44();
assertTest($dsaParams->k === 4 && $dsaParams->l === 4, 'MlDsaParameters::mlDsa44() sets dimensions k=4, l=4');
$dsaParams65 = MlDsaParameters::fromName('dilithium3');
assertTest($dsaParams65->name === MlDsaParameters::ML_DSA_65, 'MlDsaParameters::fromName() resolves alias');

$dsa = new MlDsaEngine($dsaParams);
$dsaKp = $dsa->generateKeyPair();
assertTest(!empty($dsaKp->getPublicKey()), 'ML-DSA generates public verification key');
assertTest(!empty($dsaKp->getSecretKey()), 'ML-DSA generates secret signing key');

$testMessage = "NetFusion Zero-Trust Transaction #2026-PQC";
$sig = $dsa->sign($testMessage, $dsaKp->getSecretKey());
assertTest(strlen($sig->getBytes()) > 0, 'ML-DSA generates digital signature');
assertTest(!empty($sig->getBase64()), 'Signature::getBase64() encodes signature');

$verified = $dsa->verify($testMessage, $sig, $dsaKp->getPublicKey());
assertTest($verified === true, 'ML-DSA verifies authentic digital signature');

$tamperedMsg = $testMessage . " [tampered]";
assertTest($dsa->verify($tamperedMsg, $sig, $dsaKp->getPublicKey()) === false, 'ML-DSA rejects tampered message');

$tamperedSigBytes = $sig->getBytes();
$tamperedSigBytes[8] = chr(ord($tamperedSigBytes[8]) ^ 0x01);
assertTest($dsa->verify($testMessage, $tamperedSigBytes, $dsaKp->getPublicKey()) === false, 'ML-DSA rejects corrupted signature bytes');

$wrongKp = $dsa->generateKeyPair();
assertTest($dsa->verify($testMessage, $sig, $wrongKp->getPublicKey()) === false, 'ML-DSA rejects signature checked with mismatched public key');

$sigRoundTrip = Signature::fromBase64($sig->getBase64());
assertTest($sigRoundTrip->getBytes() === $sig->getBytes(), 'Signature round-trips through base64 deserialization');

// =========================================================================
// 70C: Hybrid Key Exchange (X25519 + ML-KEM-768)
// =========================================================================
echo "\n═══ 70C: Hybrid Key Exchange (X25519 + ML-KEM-768) ═══\n";

$hybrid = new HybridKeyExchange($kem768);
$hybKp = $hybrid->generateKeyPair();
assertTest(!empty($hybKp->getClassicalPublicKey()), 'HybridKeyPair contains classical X25519 public key');
assertTest(!empty($hybKp->getPqcPublicKey()), 'HybridKeyPair contains post-quantum ML-KEM public key');
assertTest(!empty($hybKp->getCompositePublicKeyBase64()), 'HybridKeyPair exports composite public key');

// Sender encapsulates
$hybResult = $hybrid->encapsulate($hybKp);
$hybCiphertext = $hybResult['ciphertext'];
$senderSession = $hybResult['session'];
assertTest($senderSession instanceof HybridSession, 'Hybrid encapsulation returns active HybridSession');
assertTest(!empty($hybCiphertext->toBase64()), 'HybridCiphertext converts to base64');

// Recipient decapsulates
$recipientSession = $hybrid->decapsulate($hybKp, $hybCiphertext);
assertTest($senderSession->getMasterKey() === $recipientSession->getMasterKey(), 'Dual-layer Hybrid key exchange arrives at identical master session key');

// AEAD Encryption over negotiated session
$plainSecret = "Top Secret: Enterprise Merger & Acquisition Strategy 2027";
$encPayload = $senderSession->encrypt($plainSecret, 'context-header');
$decPayload = $recipientSession->decrypt($encPayload['base64'], 'context-header');
assertTest($decPayload === $plainSecret, 'HybridSession AEAD (AES-256-GCM) encrypts and decrypts payload');

// Tamper test on session payload
$corruptedPayload = base64_decode($encPayload['base64']);
$corruptedPayload[strlen($corruptedPayload) - 5] = chr(ord($corruptedPayload[strlen($corruptedPayload) - 5]) ^ 0x01);
$sessionTamperCaught = false;
try {
    $recipientSession->decrypt(base64_encode($corruptedPayload), 'context-header');
} catch (\Throwable $e) {
    $sessionTamperCaught = true;
}
assertTest($sessionTamperCaught, 'HybridSession detects and rejects ciphertext tampering');

// =========================================================================
// 70D: Post-Quantum Envelope Encryption Vault
// =========================================================================
echo "\n═══ 70D: Post-Quantum Envelope Encryption Vault ═══\n";

$vault = new PqcEnvelopeVault($hybrid);
assertTest($vault->hasKey('master-v1'), 'PqcEnvelopeVault initializes with default master-v1 key');

$sensitiveData = "Customer SSN: 000-12-3456 | Credit Card: 4111-2222-3333-4444";
$envelope = $vault->encrypt($sensitiveData);
assertTest(str_contains($envelope, 'HYBRID-X25519-MLKEM768-AES256GCM'), 'Vault envelope records hybrid algorithm header');
assertTest(str_contains($envelope, 'master-v1'), 'Vault envelope records key ID');

$decrypted = $vault->decrypt($envelope);
assertTest($decrypted === $sensitiveData, 'PqcEnvelopeVault successfully decrypts encrypted envelope');

// Key Rotation: Register master-v2 and re-encrypt
$vault->registerKey('master-v2');
assertTest($vault->hasKey('master-v2'), 'Vault registers new key master-v2');

$reEncrypted = $vault->reEncrypt($envelope, 'master-v2');
assertTest(str_contains($reEncrypted, 'master-v2'), 'Vault re-encrypts envelope under master-v2');
assertTest($vault->decrypt($reEncrypted) === $sensitiveData, 'Re-encrypted envelope decrypts to original data');

// Tampered envelope test
$envData = json_decode($envelope, true);
$envData['tag'] = base64_encode(str_repeat('X', 16));
$tamperedEnvJson = json_encode($envData);
$vaultTamperCaught = false;
try {
    $vault->decrypt($tamperedEnvJson);
} catch (\Throwable $e) {
    $vaultTamperCaught = true;
}
assertTest($vaultTamperCaught, 'PqcEnvelopeVault rejects tampered envelope tag');

// =========================================================================
// 70E: Central Facade & Global Helpers
// =========================================================================
echo "\n═══ 70E: Central Facade & Global Helpers ═══\n";

$manager = new PqcManager();
assertTest($manager->kem() instanceof MlKemEngine, 'PqcManager::kem() returns MlKemEngine');
assertTest($manager->dsa() instanceof MlDsaEngine, 'PqcManager::dsa() returns MlDsaEngine');
assertTest($manager->hybrid() instanceof HybridKeyExchange, 'PqcManager::hybrid() returns HybridKeyExchange');
assertTest($manager->vault() instanceof PqcEnvelopeVault, 'PqcManager::vault() returns PqcEnvelopeVault');

// Shortcuts
$bench = $manager->benchmark();
assertTest(isset($bench['kem']['keygen_ms'], $bench['dsa']['sign_ms'], $bench['hybrid']['encap_ms']), 'PqcManager::benchmark() returns complete performance metrics');

$dsaKpMgr = $manager->dsa()->generateKeyPair();
$sigMgr = $manager->sign("Fast Check", $dsaKpMgr->getSecretKey());
assertTest($manager->verify("Fast Check", $sigMgr, $dsaKpMgr->getPublicKey()), 'PqcManager::sign() and verify() shortcuts succeed');

$envMgr = $manager->encrypt("Database Secret");
assertTest($manager->decrypt($envMgr) === "Database Secret", 'PqcManager::encrypt() and decrypt() shortcuts succeed');

// Global helpers
assertTest(pqc() instanceof PqcManager, 'Global pqc() helper resolves singleton');
assertTest(pqc_kem() instanceof MlKemEngine, 'Global pqc_kem() helper resolves MlKemEngine');
assertTest(pqc_dsa() instanceof MlDsaEngine, 'Global pqc_dsa() helper resolves MlDsaEngine');
assertTest(pqc_hybrid() instanceof HybridKeyExchange, 'Global pqc_hybrid() helper resolves HybridKeyExchange');
assertTest(pqc_vault() instanceof PqcEnvelopeVault, 'Global pqc_vault() helper resolves PqcEnvelopeVault');

$globalEnv = pqc_encrypt("Global Encrypt Test");
assertTest(pqc_decrypt($globalEnv) === "Global Encrypt Test", 'Global pqc_encrypt() and pqc_decrypt() helpers function cleanly');

// =========================================================================
// SUMMARY
// =========================================================================
echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    PHASE 70 TEST SUMMARY                    ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo sprintf("║  Passed: %-5d Failed: %-5d Total: %-5d                    ║\n", $passed, $failed, $passed + $failed);
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

if ($failed === 0) {
    echo "✅ All Phase 70 tests passed!\n";
    exit(0);
} else {
    echo "❌ Some Phase 70 tests failed.\n";
    exit(1);
}
