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

namespace NetFusion\Core\WAF\Detectors;

use NetFusion\Core\WAF\Contracts\ThreatDetectorInterface;
use NetFusion\Core\WAF\IP\ThreatContext;

class ProtocolViolationDetector implements ThreatDetectorInterface
{
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

    public function getName(): string
    {
        return 'ProtocolViolationDetector';
    }

    public function inspect(string $field, mixed $value, ThreatContext $context, AnomalyScore $anomalyScore): void
    {
        // 1. HTTP Method validation
        $method = strtoupper($context->getMethod());
        if (!in_array($method, $this->allowedMethods, true)) {
            $anomalyScore->addMatch(
                ruleId: 'HTTP_INVALID_METHOD',
                detector: $this->getName(),
                field: 'method',
                points: 5.0,
                matchSnippet: $method,
                message: "Protocol Violation: Unsupported or malicious HTTP method [{$method}]"
            );
        }

        // 2. CRLF Injection in headers
        if (str_starts_with($field, 'headers.')) {
            $headerStr = (string)$value;
            if (preg_match('/[\r\n]/', $headerStr)) {
                $anomalyScore->addMatch(
                    ruleId: 'HTTP_HEADER_CRLF',
                    detector: $this->getName(),
                    field: $field,
                    points: 5.0,
                    matchSnippet: urlencode($headerStr),
                    message: 'Protocol Violation: CRLF header injection / HTTP response splitting attempt'
                );
            }
        }

        // 3. HTTP Request Smuggling Check (Conflicting Content-Length and Transfer-Encoding)
        $headers = $context->getHeaders();
        $hasCl = isset($headers['content-length']) || isset($headers['Content-Length']);
        $hasTe = isset($headers['transfer-encoding']) || isset($headers['Transfer-Encoding']);

        if ($hasCl && $hasTe) {
            $anomalyScore->addMatch(
                ruleId: 'HTTP_REQUEST_SMUGGLING',
                detector: $this->getName(),
                field: 'headers',
                points: 5.0,
                matchSnippet: 'CL+TE',
                message: 'Protocol Violation: Potential HTTP Request Smuggling (both Content-Length and Transfer-Encoding present)'
            );
        }
    }
}
