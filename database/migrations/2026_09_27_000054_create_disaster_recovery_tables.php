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
 * Migration: create_disaster_recovery_tables
 *
 * Tables:
 *   - dr_cluster_nodes   : Region node topology and heartbeat states
 *   - dr_mutation_journal: Append-only mutation delta records
 *   - dr_drill_reports   : DR drill and failover execution reports
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('dr_cluster_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_id', 120)->unique();
            $table->string('region', 64)->index();
            $table->string('role', 32)->default('STANDBY');
            $table->string('status', 32)->default('ONLINE');
            $table->string('endpoint', 255);
            $table->decimal('last_heartbeat', 16, 6)->default(0);
            $table->decimal('replication_lag_sec', 10, 4)->default(0);
            $table->integer('fencing_epoch')->default(1);
            $table->timestamps();
        });

        $this->schema->create('dr_mutation_journal', function (Blueprint $table) {
            $table->id();
            $table->string('tx_id', 64)->unique();
            $table->string('table_name', 120)->index();
            $table->string('operation', 16)->index();
            $table->text('primary_key');
            $table->text('before_state')->nullable();
            $table->text('after_state')->nullable();
            $table->decimal('timestamp_micro', 16, 6)->index();
            $table->string('region', 64)->default('us-east-1');
            $table->string('previous_checksum', 64)->nullable();
            $table->string('checksum', 64)->index();
            $table->timestamps();
        });

        $this->schema->create('dr_drill_reports', function (Blueprint $table) {
            $table->id();
            $table->string('drill_id', 120)->unique();
            $table->string('type', 32)->default('DRILL');
            $table->string('target_region', 64)->index();
            $table->string('status', 32)->default('SUCCESS');
            $table->decimal('rto_seconds', 10, 4)->default(0);
            $table->decimal('rpo_seconds', 10, 4)->default(0);
            $table->boolean('is_sla_compliant')->default(true);
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('dr_drill_reports');
        $this->schema->dropIfExists('dr_mutation_journal');
        $this->schema->dropIfExists('dr_cluster_nodes');
    }
};
