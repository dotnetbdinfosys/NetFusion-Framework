<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : core/Auth/Tokens/NewAccessToken.php
| Module    : Auth\Tokens
| Namespace : NetFusion\Core\Auth\Tokens
| Purpose   : Value object holding newly issued PersonalAccessToken and plain text token.
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

namespace NetFusion\Core\Auth\Tokens;

use JsonSerializable;

/**
 * Class NewAccessToken
 *
 * Value object returned upon token creation containing the plain-text secret token
 * alongside the persisted PersonalAccessToken model.
 *
 * @package NetFusion\Core\Auth\Tokens
 */
class NewAccessToken implements JsonSerializable
{
    /**
     * @param PersonalAccessToken $accessToken
     * @param string              $plainTextToken
     */
    public function __construct(
        public readonly PersonalAccessToken $accessToken,
        public readonly string $plainTextToken
    ) {
    }

    /**
     * Convert to array.
     *
     * @return array{accessToken: PersonalAccessToken, plainTextToken: string}
     */
    public function toArray(): array
    {
        return [
            'accessToken'    => $this->accessToken,
            'plainTextToken' => $this->plainTextToken,
        ];
    }

    /**
     * JSON serialization support.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
