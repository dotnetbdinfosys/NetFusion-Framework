<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/TestCli/Database/Migrations/2026_09_17_220224_create_orders_table.php
| Module    : Modules\TestCli
| Namespace : None (Migration File)
| Purpose   : Migration script for create_orders_table.
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

use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(Connection $db): void
    {
        // $db->statement("CREATE TABLE example (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(Connection $db): void
    {
        // $db->statement("DROP TABLE IF EXISTS example");
    }
};