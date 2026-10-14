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
 * Migration: create_apm_tables
 *
 * Tables:
 *   - apm_traces       : Completed distributed trace spans
 *   - apm_events       : Structured log events
 *   - apm_slow_queries : Slow query records
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('apm_traces', function (Blueprint $table) {
            $table->id();
            $table->string('trace_id', 32)->index();
            $table->string('span_id', 16);
            $table->string('parent_id', 16)->nullable();
            $table->string('service', 64);
            $table->string('operation', 255);
            $table->string('status', 16)->default('ok');  // ok|error|unset
            $table->decimal('duration_ms', 12, 3)->default(0);
            $table->text('tags')->nullable();              // JSON
            $table->text('events')->nullable();            // JSON
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        $this->schema->create('apm_events', function (Blueprint $table) {
            $table->id();
            $table->string('level', 16);
            $table->string('channel', 64);
            $table->text('message');
            $table->text('context')->nullable();  // JSON
            $table->string('trace_id', 32)->nullable()->index();
            $table->timestamp('logged_at')->nullable();
            $table->timestamps();
        });

        $this->schema->create('apm_slow_queries', function (Blueprint $table) {
            $table->id();
            $table->text('sql');
            $table->decimal('execution_ms', 12, 3);
            $table->text('bindings')->nullable();
            $table->string('trace_id', 32)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('apm_slow_queries');
        $this->schema->dropIfExists('apm_events');
        $this->schema->dropIfExists('apm_traces');
    }
};
