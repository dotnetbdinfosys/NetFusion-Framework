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
 * Migration: create_notification_tables
 *
 * Tables:
 *   - notification_subscriptions : Topic → token/user subscriptions
 *   - notification_delivery_log  : Per-send delivery result tracking
 *   - notification_inbox         : Database channel in-app inbox
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 120)->index();
            $table->string('recipient', 512);
            $table->string('channel', 32)->default('fcm');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $this->schema->create('notification_delivery_log', function (Blueprint $table) {
            $table->id();
            $table->string('notification_id', 64)->index();
            $table->string('channel', 32);
            $table->string('recipient', 512);
            $table->boolean('success')->default(false);
            $table->string('message_id', 255)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        $this->schema->create('notification_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('notification_id', 64)->index();
            $table->string('recipient', 255)->index();
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->text('data')->nullable();   // JSON
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('notification_inbox');
        $this->schema->dropIfExists('notification_delivery_log');
        $this->schema->dropIfExists('notification_subscriptions');
    }
};
