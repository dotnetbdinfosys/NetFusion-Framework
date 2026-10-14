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
 * Migration: create_replication_tables
 *
 * Tables:
 *   - replication_crdt_state    : Serialized CRDT state per key/region
 *   - replication_vector_clocks : VectorClock snapshots per key
 *   - replication_log           : Operation log for audit/replay
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('replication_crdt_state', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->index();
            $table->string('type', 32);          // gcounter|pncounter|orset|lww|mv
            $table->string('region', 64)->index();
            $table->text('state');               // JSON-serialized CRDT state
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        $this->schema->create('replication_vector_clocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('clock');               // JSON: {regionId: counter}
            $table->timestamps();
        });

        $this->schema->create('replication_operation_log', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 64);
            $table->string('key', 120)->index();
            $table->string('region', 64)->index();
            $table->text('data')->nullable();     // JSON payload
            $table->boolean('replicated')->default(false);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('replication_operation_log');
        $this->schema->dropIfExists('replication_vector_clocks');
        $this->schema->dropIfExists('replication_crdt_state');
    }
};
