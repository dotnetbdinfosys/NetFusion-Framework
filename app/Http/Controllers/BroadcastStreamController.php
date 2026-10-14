<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Controllers/BroadcastStreamController.php
| Module    : Broadcasting
| Namespace : NetFusion\App\Http\Controllers
| Purpose   : Server-Sent Events (SSE) streaming and channel authorization controller.
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

use NetFusion\Core\Broadcasting\BroadcastManager;
use NetFusion\Core\Broadcasting\Drivers\SseBroadcastDriver;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

/**
 * Class BroadcastStreamController
 *
 * Exposes live SSE event streaming and private channel authentication endpoints.
 *
 * @package NetFusion\App\Http\Controllers
 */
class BroadcastStreamController
{
    public function __construct(
        protected BroadcastManager $broadcaster
    ) {}

    /**
     * Authorize access to private/presence broadcast channels.
     *
     * Route: POST /api/v1/broadcasting/auth
     */
    public function auth(Request $request): Response
    {
        $channelName = (string) ($request->input('channel_name') ?? $request->input('channel', ''));

        if ($channelName === '') {
            return Response::json([
                'auth'    => false,
                'message' => 'Missing channel_name parameter.',
            ], 422);
        }

        $isAuthorized = $this->broadcaster->authenticate($request, $channelName);

        if ($isAuthorized === false) {
            return Response::json([
                'auth'    => false,
                'message' => 'Unauthorized channel access.',
            ], 403);
        }

        $data = [
            'auth'    => true,
            'channel' => $channelName,
        ];

        if (is_array($isAuthorized)) {
            $data['channel_data'] = $isAuthorized;
        }

        return Response::json($data);
    }

    /**
     * Stream real-time events to connected clients via Server-Sent Events (SSE).
     *
     * Route: GET /api/v1/broadcasting/stream
     */
    public function stream(Request $request): Response
    {
        // 1. Resolve requested channels
        $rawChannels = $request->query('channels') ?? $request->query('channel', 'public');
        $channelList = is_array($rawChannels) 
            ? $rawChannels 
            : array_map('trim', explode(',', (string) $rawChannels));

        // 2. Filter authorized channels
        $authorizedChannels = [];
        foreach ($channelList as $ch) {
            if ($this->broadcaster->authenticate($request, $ch) !== false) {
                $authorizedChannels[] = $ch;
            }
        }

        if (empty($authorizedChannels)) {
            return Response::json([
                'error'   => 'unauthorized',
                'message' => 'No authorized channels found for this connection.',
            ], 403);
        }

        /** @var SseBroadcastDriver $driver */
        $driver = $this->broadcaster->driver('sse');
        if (!($driver instanceof SseBroadcastDriver)) {
            $driver = new SseBroadcastDriver();
        }

        $lastEventId = $request->header('Last-Event-Id') 
            ?? $request->query('last_event_id', 0);

        // In test mode or non-streaming request, return buffered events immediately
        if ($request->query('once') || $request->header('X-Test-Mode')) {
            $events = $driver->getEventsSince($authorizedChannels, $lastEventId);
            $output = $driver->formatHeartbeat();

            foreach ($events as $evt) {
                $output .= $driver->formatSseMessage(
                    $evt['event'],
                    $evt['payload'],
                    $evt['id'],
                    (int) config('broadcasting.connections.sse.reconnect', 3000)
                );
            }

            return new Response(
                content   : $output,
                statusCode: 200,
                headers   : [
                    'Content-Type'      => 'text/event-stream; charset=utf-8',
                    'Cache-Control'     => 'no-cache, no-store, must-revalidate',
                    'Connection'        => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ]
            );
        }

        // Live stream mode
        $response = new Response(
            content   : '',
            statusCode: 200,
            headers   : [
                'Content-Type'      => 'text/event-stream; charset=utf-8',
                'Cache-Control'     => 'no-cache, no-store, must-revalidate',
                'Connection'        => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]
        );

        $events = $driver->getEventsSince($authorizedChannels, $lastEventId);
        $output = $driver->formatHeartbeat();

        foreach ($events as $evt) {
            $output .= $driver->formatSseMessage(
                $evt['event'],
                $evt['payload'],
                $evt['id'],
                (int) config('broadcasting.connections.sse.reconnect', 3000)
            );
        }

        $response->setContent($output);
        return $response;
    }
}
