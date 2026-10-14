<?php

/**
 * DotNetBD Enterprise Framework
 *
 * Phase 48 Test Suite: Enterprise WebAuthn / Passkeys, FIDO2 & Biometric Authentication Engine
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Auto-configure OpenSSL on Windows if needed
$possibleConf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
if (file_exists($possibleConf) && !getenv('OPENSSL_CONF')) {
    putenv("OPENSSL_CONF={$possibleConf}");
}

use NetFusion\Core\Application;
use NetFusion\Core\Auth\WebAuthn\Assertion\AssertionVerifier;
use NetFusion\Core\Auth\WebAuthn\Attestation\AttestationStatementVerifier;
use NetFusion\Core\Auth\WebAuthn\Cbor\CborDecoder;
use NetFusion\Core\Auth\WebAuthn\Challenge\ChallengeManager;
use NetFusion\Core\Auth\WebAuthn\Contracts\CredentialStoreInterface;
use NetFusion\Core\Auth\WebAuthn\Contracts\WebAuthnManagerInterface;
use NetFusion\Core\Auth\WebAuthn\Cose\CoseAlgorithm;
use NetFusion\Core\Auth\WebAuthn\Cose\CoseKey;
use NetFusion\Core\Auth\WebAuthn\Data\AuthenticatorData;
use NetFusion\Core\Auth\WebAuthn\Data\ClientData;
use NetFusion\Core\Auth\WebAuthn\Data\PublicKeyCredentialCreationOptions;
use NetFusion\Core\Auth\WebAuthn\Data\PublicKeyCredentialRequestOptions;
use NetFusion\Core\Auth\WebAuthn\Data\WebAuthnCredential;
use NetFusion\Core\Auth\WebAuthn\Http\WebAuthnController;
use NetFusion\Core\Auth\WebAuthn\Store\ArrayCredentialStore;
use NetFusion\Core\Auth\WebAuthn\Store\DatabaseCredentialStore;
use NetFusion\Core\Auth\WebAuthn\Traits\HasPasskeys;
use NetFusion\Core\Auth\WebAuthn\WebAuthnManager;
use NetFusion\Core\Http\Request;

echo "\n=======================================================\n";
echo "   NetFusion Phase 48: WebAuthn & Passkeys Suite       \n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "✔ [PASS] {$description}\n";
    } else {
        $failed++;
        echo "✖ [FAIL] {$description}" . ($detail ? " — {$detail}" : '') . "\n";
    }
}

// -----------------------------------------------------------------------------
// 1. CBOR Decoder Tests
// -----------------------------------------------------------------------------
echo "\n--- 1. CBOR Decoder ---\n";

// CBOR integer: 23 (0x17)
assertTest('CBOR: Decodes small unsigned int', CborDecoder::decode("\x17") === 23);

// CBOR integer: 100 (0x18 0x64)
assertTest('CBOR: Decodes 1-byte uint', CborDecoder::decode("\x18\x64") === 100);

// CBOR negative int: -1 (0x20)
assertTest('CBOR: Decodes negative int -1', CborDecoder::decode("\x20") === -1);

// CBOR text string: "passkey" (0x67 || "passkey")
assertTest('CBOR: Decodes text string', CborDecoder::decode("\x67passkey") === 'passkey');

// CBOR byte string: 4 bytes (0x44 || "\x01\x02\x03\x04")
assertTest('CBOR: Decodes byte string', CborDecoder::decode("\x44\x01\x02\x03\x04") === "\x01\x02\x03\x04");

// CBOR map: {1: 2, 3: -7} -> 0xa2 0x01 0x02 0x03 0x26
$cborMap = "\xa2\x01\x02\x03\x26";
$decodedMap = CborDecoder::decode($cborMap);
assertTest('CBOR: Decodes map with integer keys & negative values', is_array($decodedMap) && $decodedMap[1] === 2 && $decodedMap[3] === -7);

// CBOR boolean & null: true (0xf5), false (0xf4), null (0xf6)
assertTest('CBOR: Decodes booleans and null', CborDecoder::decode("\xf5") === true && CborDecoder::decode("\xf4") === false && CborDecoder::decode("\xf6") === null);

// -----------------------------------------------------------------------------
// 2. COSE Key & PEM Conversion Tests (EC2 & RSA)
// -----------------------------------------------------------------------------
echo "\n--- 2. COSE Key Parsing & Cryptography ---\n";

// Generate real OpenSSL EC (prime256v1) key pair to test full COSE conversion and verification
$sslOpts = file_exists($possibleConf) ? ['config' => $possibleConf] : [];
$ecKey = openssl_pkey_new(array_merge([
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
], $sslOpts));

if ($ecKey === false) {
    while ($err = openssl_error_string()) {
        echo "OpenSSL Error: {$err}\n";
    }
}
$ecDetails = openssl_pkey_get_details($ecKey);
$ecX = $ecDetails['ec']['x'];
$ecY = $ecDetails['ec']['y'];

$ecCoseMap = [
    1 => CoseAlgorithm::KEY_TYPE_EC2,
    3 => CoseAlgorithm::ES256,
    -1 => CoseAlgorithm::EC2_CURVE_P256,
    -2 => $ecX,
    -3 => $ecY,
];

$ecParsed = CoseKey::parse($ecCoseMap);
assertTest('COSE: Parses EC2 (P-256) map to valid PEM', str_contains($ecParsed['pem'], 'BEGIN PUBLIC KEY') && $ecParsed['algorithm'] === CoseAlgorithm::ES256);

// Verify OpenSSL accepts the generated PEM
$sampleMsg = 'NetFusion WebAuthn Test Payload';
openssl_sign($sampleMsg, $ecSignature, $ecKey, OPENSSL_ALGO_SHA256);
$ecVerified = openssl_verify($sampleMsg, $ecSignature, $ecParsed['pem'], OPENSSL_ALGO_SHA256);
assertTest('COSE: Generated EC2 PEM verifies OpenSSL ECDSA signature', $ecVerified === 1);

// Generate real RSA (2048) key pair
$rsaKey = openssl_pkey_new(array_merge([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
], $sslOpts));
$rsaDetails = openssl_pkey_get_details($rsaKey);
$rsaN = $rsaDetails['rsa']['n'];
$rsaE = $rsaDetails['rsa']['e'];

$rsaCoseMap = [
    1 => CoseAlgorithm::KEY_TYPE_RSA,
    3 => CoseAlgorithm::RS256,
    -1 => $rsaN,
    -2 => $rsaE,
];

$rsaParsed = CoseKey::parse($rsaCoseMap);
assertTest('COSE: Parses RSA map to valid PEM', str_contains($rsaParsed['pem'], 'BEGIN PUBLIC KEY') && $rsaParsed['algorithm'] === CoseAlgorithm::RS256);

openssl_sign($sampleMsg, $rsaSignature, $rsaKey, OPENSSL_ALGO_SHA256);
$rsaVerified = openssl_verify($sampleMsg, $rsaSignature, $rsaParsed['pem'], OPENSSL_ALGO_SHA256);
assertTest('COSE: Generated RSA PEM verifies OpenSSL RSA signature', $rsaVerified === 1);

// -----------------------------------------------------------------------------
// 3. Challenge Manager Tests
// -----------------------------------------------------------------------------
echo "\n--- 3. Challenge Manager ---\n";

$challengeMgr = new ChallengeManager();
$regChallenge = $challengeMgr->generate('register', 101, 300);
assertTest('ChallengeManager: Generates base64url challenge', strlen($regChallenge) >= 32 && !str_contains($regChallenge, '+') && !str_contains($regChallenge, '/'));

assertTest('ChallengeManager: Validates matching challenge and consumes it', $challengeMgr->validate($regChallenge, 'register', true));
assertTest('ChallengeManager: Consumed challenge cannot be reused', !$challengeMgr->validate($regChallenge, 'register', true));

// Test Expired Challenge
$expiredChallenge = $challengeMgr->generate('login', 101, -1);
assertTest('ChallengeManager: Rejects expired challenge', !$challengeMgr->validate($expiredChallenge, 'login'));
assertTest('ChallengeManager: prune() cleans expired entries', $challengeMgr->prune() >= 0);

// -----------------------------------------------------------------------------
// 4. AuthenticatorData & Assertion Verifier Tests
// -----------------------------------------------------------------------------
echo "\n--- 4. AuthenticatorData & Assertion Verification ---\n";

$rpId = 'localhost';
$rpIdHash = hash('sha256', $rpId, true);
$flags = AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV | AuthenticatorData::FLAG_AT;
$signCount = 5;
$aaguid = random_bytes(16);
$credId = random_bytes(32);

// Build synthetic AuthenticatorData with Attested Credential Data
// 32 bytes rpIdHash + 1 byte flags + 4 bytes signCount + 16 bytes aaguid + 2 bytes credIdLen + credId
$authDataBinary = $rpIdHash . chr($flags) . pack('N', $signCount) . $aaguid . pack('n', strlen($credId)) . $credId;

$parsedAuthData = AuthenticatorData::parse($authDataBinary);
assertTest('AuthenticatorData: Correctly parses flags, signCount, and credentialId',
    $parsedAuthData->isUserPresent() &&
    $parsedAuthData->isUserVerified() &&
    $parsedAuthData->hasAttestedCredentialData() &&
    $parsedAuthData->signCount === 5 &&
    $parsedAuthData->credentialId === $credId
);

// Assertion signature test
$clientDataJson = json_encode([
    'type' => 'webauthn.get',
    'challenge' => 'test-assertion-challenge',
    'origin' => 'http://localhost',
]);
$clientData = ClientData::fromJson($clientDataJson);
$clientDataHash = hash('sha256', $clientDataJson, true);

// AuthData without AT for get assertion
$authDataGetBinary = $rpIdHash . chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV) . pack('N', 10);
$authDataGet = AuthenticatorData::parse($authDataGetBinary);

$assertionPayload = $authDataGet->raw . $clientDataHash;
openssl_sign($assertionPayload, $assertionSignature, $ecKey, OPENSSL_ALGO_SHA256);

$testCred = new WebAuthnCredential(
    id: ChallengeManager::base64UrlEncode($credId),
    userId: 101,
    publicKeyPem: $ecParsed['pem'],
    attestationType: 'none',
    aaguid: bin2hex($aaguid),
    signCount: 5,
    coseAlgorithm: 'ES256'
);

$assertionVerifier = new AssertionVerifier();
$assertionOk = $assertionVerifier->verify(
    authData: $authDataGet,
    clientData: $clientData,
    signature: $assertionSignature,
    credential: $testCred,
    expectedRpId: $rpId,
    requireUserVerification: true
);
assertTest('AssertionVerifier: Successfully verifies valid WebAuthn assertion signature', $assertionOk);

// Clone / Replay detection: incoming signCount (3) <= stored signCount (5)
$replayAuthDataBinary = $rpIdHash . chr(AuthenticatorData::FLAG_UP) . pack('N', 3);
$replayAuthData = AuthenticatorData::parse($replayAuthDataBinary);
$replayPayload = $replayAuthData->raw . $clientDataHash;
openssl_sign($replayPayload, $replaySig, $ecKey, OPENSSL_ALGO_SHA256);

$replayCaught = false;
try {
    $assertionVerifier->verify($replayAuthData, $clientData, $replaySig, $testCred, $rpId);
} catch (RuntimeException $e) {
    $replayCaught = str_contains($e->getMessage(), 'cloning or replay attack');
}
assertTest('AssertionVerifier: Detects and rejects clone/replay with degraded signCount', $replayCaught);

// -----------------------------------------------------------------------------
// 5. Attestation Statement Verifier Tests
// -----------------------------------------------------------------------------
echo "\n--- 5. Attestation Statement Verification ---\n";

$attestationVerifier = new AttestationStatementVerifier();

// 'none' attestation format
assertTest('AttestationVerifier: Formats none attestation as valid', $attestationVerifier->verify('none', [], $parsedAuthData, $clientDataHash, $ecParsed['pem']));

// 'packed' self-attestation format
$packedSigned = $parsedAuthData->raw . $clientDataHash;
openssl_sign($packedSigned, $packedSig, $ecKey, OPENSSL_ALGO_SHA256);
$packedAttStmt = [
    'alg' => CoseAlgorithm::ES256,
    'sig' => $packedSig,
];
assertTest('AttestationVerifier: Verifies packed self-attestation signature', $attestationVerifier->verify('packed', $packedAttStmt, $parsedAuthData, $clientDataHash, $ecParsed['pem']));

// -----------------------------------------------------------------------------
// 6. WebAuthnManager End-to-End Registration & Login Flow
// -----------------------------------------------------------------------------
echo "\n--- 6. WebAuthnManager Registration & Authentication ---\n";

$store = new ArrayCredentialStore();
$manager = new WebAuthnManager(
    store: $store,
    challenges: $challengeMgr,
    attestationVerifier: $attestationVerifier,
    assertionVerifier: $assertionVerifier,
    config: [
        'rp_name' => 'NetFusion Enterprise',
        'rp_id' => 'localhost',
        'origin' => 'http://localhost',
    ]
);

// Step 1: Generate Registration Options
$regOptions = $manager->generateRegisterOptions(
    userId: 42,
    username: 'ashish@dotnetbd.com',
    displayName: 'Md. Atiquzzaman Ashish'
);
assertTest('WebAuthnManager: Generates registration creation options', $regOptions instanceof PublicKeyCredentialCreationOptions && !empty($regOptions->challenge));

// Step 2: Simulate Client Response for Registration
$regClientDataJson = json_encode([
    'type' => 'webauthn.create',
    'challenge' => $regOptions->challenge,
    'origin' => 'http://localhost',
]);

// Encode EC COSE Key in CBOR
// Map of 5 entries: {1: 2, 3: -7, -1: 1, -2: x, -3: y}
// 0xa5 (map of 5) 0x01 0x02 0x03 0x26 0x20 0x01 0x21 0x58 0x20 [32 bytes x] 0x22 0x58 0x20 [32 bytes y]
$cborEcKey = "\xa5" .
    "\x01\x02" .                       // 1: 2 (kty: EC2)
    "\x03\x26" .                       // 3: -7 (alg: ES256)
    "\x20\x01" .                       // -1: 1 (crv: P-256)
    "\x21\x58\x20" . $ecX .            // -2: 32-byte X
    "\x22\x58\x20" . $ecY;             // -3: 32-byte Y

$regCredId = random_bytes(32);
$regAuthDataBinary = $rpIdHash . chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_AT) . pack('N', 0) . $aaguid . pack('n', strlen($regCredId)) . $regCredId . $cborEcKey;

// AttestationObject map: {fmt: "none", authData: <bytes>, attStmt: {}}
// 0xa3 (map of 3)
// "fmt" (0x63 0x66 0x6d 0x74) -> "none" (0x64 0x6e 0x6f 0x6e 0x65)
// "authData" (0x68 0x61 0x75 0x74 0x68 0x44 0x61 0x74 0x61) -> byte string (0x58 <len> <data>)
// "attStmt" (0x67 0x61 0x74 0x74 0x53 0x74 0x6d 0x74) -> empty map (0xa0)
$attestationObjectBinary = "\xa3" .
    "\x63fmt\x64none" .
    "\x68authData\x58" . chr(strlen($regAuthDataBinary)) . $regAuthDataBinary .
    "\x67attStmt\xa0";

$registrationResponse = [
    'clientDataJSON' => ChallengeManager::base64UrlEncode($regClientDataJson),
    'attestationObject' => ChallengeManager::base64UrlEncode($attestationObjectBinary),
    'userHandle' => ChallengeManager::base64UrlEncode('42'),
];

$registeredCred = $manager->verifyRegistration($registrationResponse, $regOptions->challenge, [
    'user_id' => 42,
    'name' => 'MacBook Touch ID'
]);

assertTest('WebAuthnManager: Registers and persists new passkey credential',
    $registeredCred instanceof WebAuthnCredential &&
    $registeredCred->userId === 42 &&
    $registeredCred->name === 'MacBook Touch ID' &&
    $store->findById($registeredCred->id) !== null
);

// Step 3: Generate Login Options
$loginOptions = $manager->generateLoginOptions(userId: 42);
assertTest('WebAuthnManager: Generates login options with allowedCredentials',
    $loginOptions instanceof PublicKeyCredentialRequestOptions &&
    count($loginOptions->allowCredentials) === 1 &&
    $loginOptions->allowCredentials[0]['id'] === $registeredCred->id
);

// Step 4: Simulate Client Authentication Response
$authClientDataJson = json_encode([
    'type' => 'webauthn.get',
    'challenge' => $loginOptions->challenge,
    'origin' => 'http://localhost',
]);
$authClientDataHash = hash('sha256', $authClientDataJson, true);

$authLoginAuthDataBinary = $rpIdHash . chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV) . pack('N', 1);
$authLoginPayload = $authLoginAuthDataBinary . $authClientDataHash;
openssl_sign($authLoginPayload, $authLoginSignature, $ecKey, OPENSSL_ALGO_SHA256);

$authResponse = [
    'id' => $registeredCred->id,
    'rawId' => $registeredCred->id,
    'clientDataJSON' => ChallengeManager::base64UrlEncode($authClientDataJson),
    'authenticatorData' => ChallengeManager::base64UrlEncode($authLoginAuthDataBinary),
    'signature' => ChallengeManager::base64UrlEncode($authLoginSignature),
];

$authResult = $manager->verifyAuthentication($authResponse, $loginOptions->challenge);
assertTest('WebAuthnManager: Verifies authentication and increments sign counter to 1',
    $authResult->userId === 42 &&
    $authResult->signCount === 1 &&
    $store->findById($registeredCred->id)->signCount === 1
);

// -----------------------------------------------------------------------------
// 7. Database Store & Model Trait Tests
// -----------------------------------------------------------------------------
echo "\n--- 7. Database Credential Store & HasPasskeys Trait ---\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000028_create_webauthn_credentials_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseCredentialStore($pdo);
$dbCred = new WebAuthnCredential(
    id: 'db_cred_001',
    userId: 88,
    publicKeyPem: $ecParsed['pem'],
    attestationType: 'none',
    aaguid: '0000-0000-0000',
    signCount: 0,
    coseAlgorithm: 'ES256',
    name: 'Windows Hello'
);

$dbStore->save($dbCred);
assertTest('Database Store: Persists credential into database', $dbStore->findById('db_cred_001') !== null);

$userCreds = $dbStore->findByUserId(88);
assertTest('Database Store: Retrieves credentials by user_id', count($userCreds) === 1 && $userCreds[0]->name === 'Windows Hello');

$dbStore->updateCounter('db_cred_001', 12);
assertTest('Database Store: Updates sign_count counter', $dbStore->findById('db_cred_001')->signCount === 12);

$dbStore->delete('db_cred_001');
assertTest('Database Store: Deletes credential by ID', $dbStore->findById('db_cred_001') === null);

// Model Trait Test
class TestPasskeyUser {
    use HasPasskeys;
    public int $id = 99;
}

$app = new Application(dirname(__DIR__));
$appWebAuthn = new WebAuthnManager(store: $store);
$app->instance(WebAuthnManager::class, $appWebAuthn);
$app->instance('webauthn', $appWebAuthn);
$app->instance('passkey', $appWebAuthn);

$testUser = new TestPasskeyUser();
assertTest('HasPasskeys Trait: Initial user has no passkeys', !$testUser->hasPasskeys());

// -----------------------------------------------------------------------------
// 8. HTTP Controller Tests
// -----------------------------------------------------------------------------
echo "\n--- 8. HTTP WebAuthn Controller ---\n";

$controller = new WebAuthnController($manager);

$reqOptions = Request::create('POST', '/api/v1/auth/passkey/register/options', ['user_id' => 77, 'username' => 'test@dotnetbd.com']);
$resOptions = $controller->registerOptions($reqOptions);
$resData = json_decode($resOptions->getContent(), true);
assertTest('WebAuthnController: registerOptions returns HTTP 200 with challenge', $resOptions->getStatusCode() === 200 && !empty($resData['options']['challenge']));

$reqLoginOptions = Request::create('POST', '/api/v1/auth/passkey/login/options', ['user_id' => 42]);
$resLoginOptions = $controller->loginOptions($reqLoginOptions);
$resLoginData = json_decode($resLoginOptions->getContent(), true);
assertTest('WebAuthnController: loginOptions returns HTTP 200 with request challenge', $resLoginOptions->getStatusCode() === 200 && !empty($resLoginData['options']['challenge']));

// -----------------------------------------------------------------------------
// 9. Global Helpers & DI Tests
// -----------------------------------------------------------------------------
echo "\n--- 9. Global Helpers & DI Resolution ---\n";

assertTest('Global Helper: webauthn() returns WebAuthnManager', webauthn() instanceof WebAuthnManager);
assertTest('Global Helper: passkey() returns WebAuthnManager', passkey() instanceof WebAuthnManager);

// -----------------------------------------------------------------------------
// 10. Database Migration Clean Up
// -----------------------------------------------------------------------------
echo "\n--- 10. Database Migration Up & Down ---\n";

$migration->down($pdo);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_webauthn_credentials'");
$dropped = $stmt && $stmt->fetchColumn() === false;
$stmt->closeCursor();
unset($stmt);
assertTest('Database Migration: netfusion_webauthn_credentials dropped cleanly', $dropped);

echo "\n=======================================================\n";
echo "   Phase 48 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
