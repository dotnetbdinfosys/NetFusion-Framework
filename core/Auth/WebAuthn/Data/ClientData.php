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

use InvalidArgumentException;

class ClientData
{
    public function __construct(
        public readonly string $type,
        public readonly string $challenge,
        public readonly string $origin,
        public readonly ?bool $crossOrigin = null,
        public readonly ?array $tokenBinding = null,
        public readonly string $rawJson = ''
    ) {
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid clientDataJSON format: not a valid JSON object.');
        }

        if (!isset($data['type'], $data['challenge'], $data['origin'])) {
            throw new InvalidArgumentException('clientDataJSON missing required fields (type, challenge, origin).');
        }

        return new self(
            type: (string)$data['type'],
            challenge: (string)$data['challenge'],
            origin: (string)$data['origin'],
            crossOrigin: isset($data['crossOrigin']) ? (bool)$data['crossOrigin'] : null,
            tokenBinding: isset($data['tokenBinding']) && is_array($data['tokenBinding']) ? $data['tokenBinding'] : null,
            rawJson: $json
        );
    }
}
