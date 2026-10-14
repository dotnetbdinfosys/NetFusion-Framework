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

namespace NetFusion\Core\WAF\Exceptions;

class ChallengeRequiredException extends WAFException
{
    /**
     * @param string $challengeType Challenge type (pow, js_cookie, captcha)
     * @param array<string, mixed> $challengeData Payload or params for the client challenge
     */
    public function __construct(
        protected string $challengeType = 'pow',
        protected array $challengeData = []
    ) {
        parent::__construct("WAF Security Challenge Required: [{$challengeType}]");
    }

    public function getChallengeType(): string
    {
        return $this->challengeType;
    }

    public function getChallengeData(): array
    {
        return $this->challengeData;
    }
}
