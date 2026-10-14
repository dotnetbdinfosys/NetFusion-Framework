<?php

/**
 * NetFusion Phase 52 Test Suite:
 * Enterprise Zero-Trust Secret Management, Envelope Encryption & Dynamic Key Rotation Vault
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Security\Vault\Contracts\VaultStoreInterface;
use NetFusion\Core\Security\Vault\DynamicSecretGenerator;
use NetFusion\Core\Security\Vault\Encryption\EnvelopeEncrypter;
use NetFusion\Core\Security\Vault\Encryption\EncryptedPayload;
use NetFusion\Core\Security\Vault\Encryption\LocalKeyProvider;
use NetFusion\Core\Security\Vault\Secret;
use NetFusion\Core\Security\Vault\SecretLease;
use NetFusion\Core\Security\Vault\Store\ArrayVaultStore;
use NetFusion\Core\Security\Vault\Store\DatabaseVaultStore;
use NetFusion\Core\Security\Vault\VaultManager;

echo "\n=======================================================\n";
echo "   NetFusion Phase 52: Zero-Trust Vault & Encryption   \n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "✔ [PASS] {$desc}\n";
        $passed++;
    } else {
        echo "✖ [FAIL] {$desc}\n";
        $failed++;
    }
}

// -----------------------------------------------------------------------------
// 1. KeyProvider & Master Key Management
// -----------------------------------------------------------------------------
echo "--- 1. Master Key (KEK) Provider & Rotation ---\n";

$keyProvider = new LocalKeyProvider([1 => 'test-master-key-32-bytes-long!12']);
assertTest('LocalKeyProvider: Initializes with active KEK version 1', $keyProvider->getActiveVersion() === 1);
assertTest('LocalKeyProvider: Returns valid 32-byte binary key', strlen($keyProvider->getMasterKey(1)) === 32);

$newKekVer = $keyProvider->rotateMasterKey();
assertTest('LocalKeyProvider: Rotates Master Key and increments version to 2',
    $newKekVer === 2 &&
    $keyProvider->getActiveVersion() === 2 &&
    $keyProvider->hasVersion(1) &&
    $keyProvider->hasVersion(2)
);

// -----------------------------------------------------------------------------
// 2. Two-Tier Envelope Encryption & Rewrapping
// -----------------------------------------------------------------------------
echo "\n--- 2. Envelope Encryption (AES-256-GCM + DEK/KEK) ---\n";

$encrypter = new EnvelopeEncrypter($keyProvider);
$plaintext = "SUPER_SECRET_STRIPE_API_KEY_sk_live_99887766";

$encrypted = $encrypter->encrypt($plaintext, 1);
assertTest('EnvelopeEncrypter: Produces valid EncryptedPayload with KEK version 1',
    $encrypted instanceof EncryptedPayload &&
    $encrypted->version === 1 &&
    !empty($encrypted->edek) &&
    !empty($encrypted->ciphertext)
);

$decrypted = $encrypter->decrypt($encrypted);
assertTest('EnvelopeEncrypter: Correctly unwraps DEK and decrypts ciphertext with AES-256-GCM',
    $decrypted === $plaintext
);

// Re-wrap under new KEK Version 2
$rewrapped = $encrypter->rewrap($encrypted, 2);
assertTest('EnvelopeEncrypter: rewrap() updates KEK version to 2 without altering ciphertext',
    $rewrapped->version === 2 &&
    $rewrapped->ciphertext === $encrypted->ciphertext &&
    $rewrapped->edek !== $encrypted->edek
);

$decryptedRewrapped = $encrypter->decrypt($rewrapped);
assertTest('EnvelopeEncrypter: Decrypts re-wrapped payload under new KEK version 2',
    $decryptedRewrapped === $plaintext
);

// -----------------------------------------------------------------------------
// 3. Dynamic Secret Generation & Ephemeral Leases
// -----------------------------------------------------------------------------
echo "\n--- 3. Dynamic Secret Generation & Leases ---\n";

$dbCreds = DynamicSecretGenerator::generate('database/production_users', ['roles' => ['readwrite']]);
assertTest('DynamicSecretGenerator: Generates dynamic database credentials with random username/password',
    $dbCreds['type'] === 'database' &&
    str_starts_with($dbCreds['username'], 'v_usr_') &&
    !empty($dbCreds['password'])
);

$encCreds = $encrypter->encrypt(json_encode($dbCreds), 2);
$lease = new SecretLease('lease_101', 'database/production_users', $encCreds, microtime(true) + 3600, 3600);
assertTest('SecretLease: Tracks remaining TTL and expiration',
    !$lease->isExpired() &&
    $lease->getRemainingSeconds() > 3500 &&
    $lease->renewCount === 0
);

$lease->renew(7200);
assertTest('SecretLease: Renews TTL and increments renew count',
    $lease->ttl === 7200 &&
    $lease->renewCount === 1
);

$lease->revoke();
assertTest('SecretLease: Revocation immediately marks lease as expired', $lease->isExpired());

// -----------------------------------------------------------------------------
// 4. In-Memory Store & Database Vault Store
// -----------------------------------------------------------------------------
echo "\n--- 4. Vault Storage & Database Migration ---\n";

$arrStore = new ArrayVaultStore();
$secret1 = new Secret('api/keys/stripe', 1, $encrypted, ['owner' => 'finance']);
$arrStore->set($secret1);

assertTest('ArrayVaultStore: Stores and retrieves secret by path',
    $arrStore->get('api/keys/stripe') !== null &&
    $arrStore->get('api/keys/stripe')->version === 1
);

// Database Store
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migration = require __DIR__ . '/../database/migrations/2026_09_20_000032_create_vault_secrets_tables.php';
$migration->up($pdo);

$dbStore = new DatabaseVaultStore($pdo);
$dbStore->set($secret1);

assertTest('DatabaseVaultStore: Persists encrypted secret into SQLite',
    $dbStore->get('api/keys/stripe') !== null &&
    $dbStore->get('api/keys/stripe')?->payload->cipher === 'aes-256-gcm'
);

$dbStore->saveLease($lease);
assertTest('DatabaseVaultStore: Saves and loads dynamic lease',
    $dbStore->getLease('lease_101') !== null &&
    $dbStore->getLease('lease_101')?->revoked === true
);

$prunedLeases = $dbStore->pruneExpiredLeases();
assertTest('DatabaseVaultStore: Prunes revoked/expired leases',
    $prunedLeases === 1 &&
    $dbStore->getLease('lease_101') === null
);

// Migration Down
$migration->down($pdo);
$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='netfusion_vault_secrets'");
$dropped = $stmt && $stmt->fetchColumn() === false;
assertTest('Database Migration: Dropped Vault tables cleanly on down()', $dropped);

// -----------------------------------------------------------------------------
// 5. VaultManager Facade & Zero-Downtime Key Rotation
// -----------------------------------------------------------------------------
echo "\n--- 5. VaultManager Facade & Zero-Downtime Key Rotation ---\n";

$vault = new VaultManager(new ArrayVaultStore(), $keyProvider, $encrypter);

// Store secret
$stored = $vault->set('auth/jwt_private_key', '-----BEGIN PRIVATE KEY-----MIIEv...', ['env' => 'production']);
assertTest('VaultManager: set() envelope-encrypts and stores secret',
    $stored instanceof Secret &&
    $stored->path === 'auth/jwt_private_key' &&
    $stored->version === 1
);

// Read plaintext
$retrieved = $vault->get('auth/jwt_private_key');
assertTest('VaultManager: get() decrypts and returns plaintext secret',
    $retrieved === '-----BEGIN PRIVATE KEY-----MIIEv...'
);

// Store structured array secret
$dbConfig = ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'top_secret_pass'];
$vault->set('database/credentials', $dbConfig);
$retrievedDb = $vault->get('database/credentials');
assertTest('VaultManager: Stores and decrypts structured array secrets',
    is_array($retrievedDb) &&
    $retrievedDb['pass'] === 'top_secret_pass'
);

// Issue Dynamic Lease via Vault
$dynamicLease = $vault->lease('database/app_pool', 3600);
$leasedCreds = $vault->getLeaseCredentials($dynamicLease->id);
assertTest('VaultManager: lease() issues dynamic secret and decrypts credentials',
    is_array($leasedCreds) &&
    !empty($leasedCreds['username']) &&
    !empty($leasedCreds['password'])
);

// Key Rotation & Re-encryption
$oldKek = $vault->getKeyProvider()->getActiveVersion();
$newKek = $vault->rotateMasterKey();
$reencryptedCount = $vault->reencryptAll();

assertTest('VaultManager: rotateMasterKey() & reencryptAll() re-wraps all secrets under new KEK',
    $newKek > $oldKek &&
    $reencryptedCount >= 2 &&
    $vault->get('auth/jwt_private_key') === '-----BEGIN PRIVATE KEY-----MIIEv...'
);

// -----------------------------------------------------------------------------
// 6. Global Helpers & DI Resolution
// -----------------------------------------------------------------------------
echo "\n--- 6. Global Helpers & DI Resolution ---\n";

$app = new Application(dirname(__DIR__));
$app->instance(VaultManager::class, $vault);
$app->instance('vault', $vault);

assertTest('Global Helper: vault() returns VaultManager instance', vault() instanceof VaultManager);
assertTest('Global Helper: secret() retrieves decrypted secret by path', secret('auth/jwt_private_key') === '-----BEGIN PRIVATE KEY-----MIIEv...');
assertTest('Global Helper: secret() returns default when path missing', secret('non_existent', 'default_val') === 'default_val');

echo "\n=======================================================\n";
echo "   Phase 52 Tests Completed: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit($failed > 0 ? 1 : 0);
