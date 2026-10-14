<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Controllers/IncomingWebhookController.php
| Module    : Webhooks
| Namespace : App\Http\Controllers
| Purpose   : Universal HTTP controller for incoming webhook gateway ingestion.
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

namespace NetFusion\App\Http\Controllers;

use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;
use NetFusion\Core\Webhooks\Incoming\WebhookGateway;
use Throwable;

/**
 * Class IncomingWebhookController
 *
 * Handles HTTP requests for incoming webhooks across all providers.
 *
 * @package NetFusion\App\Http\Controllers
 */
class IncomingWebhookController
{
    /**
     * @param WebhookGateway $gateway
     */
    public function __construct(
        protected WebhookGateway $gateway
    ) {}

    /**
     * Ingest and process an incoming webhook request.
     *
     * Route: POST /api/v1/webhooks/incoming/{gateway}
     */
    public function handle(Request $request): Response
    {
        $gatewayName = $request->route('gateway') ?? 'custom';
        $payload = $request->getContent();
        $headers = $request->headers();

        try {
            $result = $this->gateway->ingest(
                gateway: $gatewayName,
                payload: $payload,
                headers: $headers
            );

            if ($result['status'] === 'invalid_signature') {
                return Response::json([
                    'success' => false,
                    'status'  => 'invalid_signature',
                    'message' => $result['message'],
                ], 401);
            }

            if ($result['duplicate']) {
                return Response::json([
                    'success'   => true,
                    'duplicate' => true,
                    'id'        => $result['id'],
                    'message'   => $result['message'],
                ], 200);
            }

            if (!$result['success']) {
                return Response::json([
                    'success' => false,
                    'status'  => $result['status'],
                    'message' => $result['message'],
                ], 422);
            }

            return Response::json([
                'success'    => true,
                'id'         => $result['id'],
                'gateway'    => $result['gateway'],
                'event_type' => $result['event_type'],
                'status'     => $result['status'],
                'message'    => $result['message'],
            ], 200);

        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Internal server error while processing webhook: ' . $e->getMessage(),
            ], 500);
        }
    }
}
