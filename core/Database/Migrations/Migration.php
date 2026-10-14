<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Database/Migrations/Migration.php
| Module    : Core\Database\Migrations
| Namespace : NetFusion\Core\Database\Migrations
| Purpose   : Abstract base migration contract defining up() and down() schema transitions.
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

namespace NetFusion\Core\Database\Migrations;

use NetFusion\Core\Database\Connection;

/**
 * NetFusion Base Database Migration
 *
 * Defines the schema transformations applied during forward migrations (up)
 * and backward rollbacks (down).
 *
 * @package NetFusion\Core\Database\Migrations
 * @version 1.0.0
 */
abstract class Migration
{
    /**
     * Run the migrations (apply schema changes).
     *
     * @param Connection $db
     */
    abstract public function up(Connection $db): void;

    /**
     * Reverse the migrations (rollback schema changes).
     *
     * @param Connection $db
     */
    abstract public function down(Connection $db): void;
}
