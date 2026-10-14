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

namespace NetFusion\Core\Collaboration\Http;

use NetFusion\Core\Collaboration\CollabManager;
use NetFusion\Core\Collaboration\Ot\Selection;
use NetFusion\Core\Collaboration\Ot\TextOperation;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class CollabDocumentController
{
    protected CollabManager $collab;

    public function __construct(?CollabManager $collab = null)
    {
        $this->collab = $collab ?? (function_exists('app') && app()->has('collab') ? app('collab') : new CollabManager());
    }

    /**
     * GET /api/v1/collab/documents/{id}
     */
    public function getDocument(Request $request): Response
    {
        $docId = (string)($request->route('id') ?? $request->query('id') ?? $request->input('id') ?? '');
        if (empty($docId)) {
            return Response::json(['error' => 'Document ID is required.'], 400);
        }

        $session = $this->collab->session($docId);
        return Response::json([
            'success' => true,
            'document' => $session->toArray(),
        ]);
    }

    /**
     * POST /api/v1/collab/documents
     */
    public function createDocument(Request $request): Response
    {
        $id = (string)($request->input('id') ?: bin2hex(random_bytes(8)));
        $title = (string)($request->input('title') ?: 'Untitled Document');
        $initialContent = (string)($request->input('content') ?: '');
        $authorId = $request->input('author_id');

        $doc = $this->collab->createDocument($id, $title, $initialContent, $authorId ? (string)$authorId : null);

        return Response::json([
            'success' => true,
            'document' => $doc->toArray(),
        ], 201);
    }

    /**
     * POST /api/v1/collab/documents/{id}/operations
     */
    public function submitOperation(Request $request): Response
    {
        $docId = (string)($request->route('id') ?? $request->query('id') ?? $request->input('id') ?? $request->input('document_id') ?? '');
        $clientRevision = (int)$request->input('revision', 0);
        $userId = (string)($request->input('user_id') ?: 'anonymous');
        $opsData = (array)$request->input('operation', []);

        if (empty($docId) || empty($opsData)) {
            return Response::json(['error' => 'Document ID and operation payload are required.'], 400);
        }

        $operation = TextOperation::fromArray($opsData);
        $session = $this->collab->session($docId);

        try {
            $result = $session->applyClientOperation($clientRevision, $operation, $userId);

            return Response::json([
                'success' => true,
                'revision' => $result['revision'],
                'operation' => $result['operation']->toArray(),
                'document_content' => $session->getContent(),
            ]);
        } catch (\Throwable $e) {
            return Response::json([
                'error' => 'Transformation error: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/collab/documents/{id}/cursor
     */
    public function updateCursor(Request $request): Response
    {
        $docId = (string)($request->route('id') ?? $request->query('id') ?? $request->input('id') ?? $request->input('document_id') ?? '');
        $userId = (string)($request->input('user_id') ?: 'anonymous');
        $username = (string)($request->input('username') ?: 'Anonymous');
        $color = (string)($request->input('color') ?: '#3b82f6');
        $anchor = (int)$request->input('anchor', 0);
        $head = (int)$request->input('head', $anchor);

        $session = $this->collab->session($docId);
        $cursor = $session->updateCursor($userId, $username, $color, new Selection($anchor, $head));

        return Response::json([
            'success' => true,
            'cursor' => $cursor->toArray(),
        ]);
    }
}
