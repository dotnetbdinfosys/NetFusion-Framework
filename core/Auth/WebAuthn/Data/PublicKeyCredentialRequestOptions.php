<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Auth\WebAuthn\Data;

use JsonSerializable;

class PublicKeyCredentialRequestOptions implements JsonSerializable
{
    /**
     * @param string $challenge Base64url encoded challenge
     * @param int $timeout In milliseconds
     * @param string|null $rpId Relying Party Identifier
     * @param array<array{type: string, id: string, transports?: string[]}> $allowCredentials
     * @param string $userVerification 'required'|'preferred'|'discouraged'
     * @param array $extensions
     */
    public function __construct(
        public readonly string $challenge,
        public readonly int $timeout = 60000,
        public readonly ?string $rpId = null,
        public readonly array $allowCredentials = [],
        public readonly string $userVerification = 'preferred',
        public readonly array $extensions = []
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'challenge' => $this->challenge,
            'timeout' => $this->timeout,
            'userVerification' => $this->userVerification,
            'allowCredentials' => $this->allowCredentials,
        ];

        if ($this->rpId !== null) {
            $data['rpId'] = $this->rpId;
        }

        if (!empty($this->extensions)) {
            $data['extensions'] = $this->extensions;
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
