<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Models/User.php
| Module    : Models
| Namespace : NetFusion\App\Models
| Purpose   : Default User model implementing Personal Access Token capabilities.
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

namespace NetFusion\App\Models;

use NetFusion\Core\Auth\Tokens\HasApiTokens;
use NetFusion\Core\Database\Model;

/**
 * Class User
 *
 * Primary user entity capable of issuing and authenticating with Personal Access Tokens.
 *
 * @package NetFusion\App\Models
 */
class User extends Model
{
    use HasApiTokens;

    protected string $table = 'users';

    protected array $fillable = [
        'name',
        'email',
        'password',
        'created_at',
        'updated_at',
    ];

    protected array $hidden = [
        'password',
    ];
}
