<?php

/*
 |--------------------------------------------------------------------------
 | NetFusion Framework — Migration
 |--------------------------------------------------------------------------
 |
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

use NetFusion\Core\Database\Migration;
use NetFusion\Core\Database\Schema\Blueprint;

/**
 * Migration: create_pqc_vault_tables
 *
 * Tables:
 *   - pqc_vault_keys : Stored post-quantum and hybrid master keys
 *   - pqc_audit_logs : Cryptographic operations audit trail (encrypt, decrypt, re-encrypt)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('pqc_vault_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_id', 120)->unique();
            $table->string('algorithm', 64)->default('HYBRID-X25519-MLKEM768');
            $table->text('public_key');
            $table->text('encrypted_secret_key');
            $table->string('status', 32)->default('ACTIVE');
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        $this->schema->create('pqc_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 32)->index(); // ENCRYPT, DECRYPT, RE_ENCRYPT, SIGN, VERIFY
            $table->string('key_id', 120)->nullable()->index();
            $table->string('algorithm', 64);
            $table->string('status', 32)->default('SUCCESS');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('pqc_audit_logs');
        $this->schema->dropIfExists('pqc_vault_keys');
    }
};
