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
 * Migration: create_accounting_tables
 *
 * Creates tables for the Double-Entry Accounting Engine:
 *   - accounting_accounts    : Chart of Accounts
 *   - accounting_journals    : Journal Entries (header)
 *   - accounting_journal_lines: Journal Lines (debit/credit details)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Chart of Accounts
        $this->schema->create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('type', 16);          // asset|liability|equity|income|expense
            $table->string('normal_balance', 8);  // debit|credit
            $table->string('parent_code', 20)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Journal Entry headers
        $this->schema->create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->string('entry_id', 64)->unique();
            $table->date('entry_date');
            $table->string('reference', 64)->nullable();
            $table->string('description', 255);
            $table->string('currency', 3)->default('USD');
            $table->boolean('posted')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        // Journal Entry lines (individual debits/credits)
        $this->schema->create('accounting_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->string('entry_id', 64)->index();
            $table->string('account_code', 20)->index();
            $table->string('side', 8);           // debit|credit
            $table->decimal('amount', 18, 6);
            $table->string('currency', 3)->default('USD');
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Audit log for closing entries
        $this->schema->create('accounting_closing_log', function (Blueprint $table) {
            $table->id();
            $table->string('closing_date', 10);
            $table->string('period_from', 10)->nullable();
            $table->string('period_to', 10)->nullable();
            $table->decimal('net_income', 18, 6)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('income_summary_code', 20)->default('3003');
            $table->string('retained_earnings_code', 20)->default('3002');
            $table->integer('entries_posted')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('accounting_closing_log');
        $this->schema->dropIfExists('accounting_journal_lines');
        $this->schema->dropIfExists('accounting_journals');
        $this->schema->dropIfExists('accounting_accounts');
    }
};
