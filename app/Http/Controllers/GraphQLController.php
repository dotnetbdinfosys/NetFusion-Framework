<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Http/Controllers/GraphQLController.php
| Module    : GraphQL
| Namespace : NetFusion\App\Http\Controllers
| Purpose   : HTTP Controller for GraphQL endpoints & GraphiQL IDE playground.
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

use NetFusion\Core\GraphQL\GraphQLManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class GraphQLController
{
    public function __construct(
        protected ?GraphQLManager $graphql = null
    ) {
        $this->graphql = $graphql ?? (function_exists('app') && app()->has(GraphQLManager::class) ? app(GraphQLManager::class) : null);
    }

    /**
     * Handle incoming GraphQL HTTP Request.
     */
    public function handle(Request $request): Response
    {
        $manager = $this->graphql ?? app(GraphQLManager::class);

        // Render GraphiQL Playground on browser GET request
        $accept = $request->header('accept', '');
        $playgroundEnabled = (bool) config('graphql.playground', true);

        if ($request->method() === 'GET' && str_contains($accept, 'text/html') && $playgroundEnabled) {
            $html = $manager->renderPlayground($request->path());
            return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        // Extract Query Payload
        $query = '';
        $variables = [];
        $operationName = null;

        if ($request->method() === 'POST') {
            $contentType = $request->header('content-type', '');
            if (str_contains($contentType, 'application/json')) {
                $body = json_decode($request->rawBody(), true);
                if (is_array($body)) {
                    $query = (string) ($body['query'] ?? '');
                    $variables = isset($body['variables']) && is_array($body['variables']) ? $body['variables'] : [];
                    $operationName = isset($body['operationName']) ? (string) $body['operationName'] : null;
                }
            } else {
                $query = (string) $request->input('query', '');
                $rawVars = $request->input('variables');
                if (is_string($rawVars)) {
                    $variables = json_decode($rawVars, true) ?? [];
                } elseif (is_array($rawVars)) {
                    $variables = $rawVars;
                }
                $operationName = $request->input('operationName');
            }
        } else {
            // GET request with query param
            $query = (string) $request->query('query', '');
            $rawVars = $request->query('variables');
            if (is_string($rawVars)) {
                $variables = json_decode($rawVars, true) ?? [];
            }
            $operationName = $request->query('operationName');
        }

        if (empty(trim($query))) {
            return Response::json([
                'errors' => [['message' => 'Must provide query string in request.']],
            ], 400);
        }

        $result = $manager->execute(
            query: $query,
            variables: $variables,
            context: $request,
            operationName: $operationName
        );

        $status = ($result->hasErrors() && $result->getData() === null) ? 400 : 200;
        return Response::json($result->toArray(), $status);
    }
}
