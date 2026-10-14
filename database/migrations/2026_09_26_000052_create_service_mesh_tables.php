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
 * Migration: create_service_mesh_tables
 *
 * Tables:
 *   - mesh_workloads    : Registered workloads and SPIFFE IDs
 *   - mesh_certificates : Active/historical X.509 SVIDs
 *   - mesh_audit_log    : Policy authorization access decisions
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('mesh_workloads', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 120)->unique();
            $table->string('spiffe_id', 255)->index();
            $table->string('namespace', 64)->default('default');
            $table->string('trust_domain', 120)->default('cluster.local');
            $table->string('status', 32)->default('HEALTHY');
            $table->text('metadata')->nullable(); // JSON
            $table->timestamps();
        });

        $this->schema->create('mesh_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 120)->index();
            $table->string('spiffe_id', 255)->index();
            $table->string('serial_number', 64);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });

        $this->schema->create('mesh_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('source_spiffe', 255)->index();
            $table->string('target_spiffe', 255)->index();
            $table->string('method', 16);
            $table->string('path', 255);
            $table->boolean('allowed')->default(false);
            $table->string('matched_rule', 120)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('evaluated_at')->nullable();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('mesh_audit_log');
        $this->schema->dropIfExists('mesh_certificates');
        $this->schema->dropIfExists('mesh_workloads');
    }
};
