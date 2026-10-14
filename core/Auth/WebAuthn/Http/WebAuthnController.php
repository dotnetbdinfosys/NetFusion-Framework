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

namespace NetFusion\Core\Auth\WebAuthn\Http;

use Throwable;
use NetFusion\Core\Auth\WebAuthn\WebAuthnManager;
use NetFusion\Core\Http\Request;
use NetFusion\Core\Http\Response;

class WebAuthnController
{
    public function __construct(
        protected ?WebAuthnManager $webauthn = null
    ) {
        $this->webauthn = $webauthn ?? (function_exists('app') && app()->has('webauthn') ? app('webauthn') : new WebAuthnManager());
    }

    public function registerOptions(Request $request): Response
    {
        $userId = $request->input('user_id') ?? $request->getAttribute('user_id', 1);
        $username = $request->input('username', 'user@example.com');
        $displayName = $request->input('display_name', $username);

        $options = $this->webauthn->generateRegisterOptions(
            userId: $userId,
            username: $username,
            displayName: $displayName
        );

        return Response::json([
            'success' => true,
            'options' => $options->toArray(),
        ]);
    }

    public function registerVerify(Request $request): Response
    {
        $body = $request->all();
        $challenge = $request->input('challenge') ?? '';
        $name = $request->input('name', 'My Passkey');
        $userId = $request->input('user_id') ?? 1;

        try {
            $credential = $this->webauthn->verifyRegistration(
                response: $body,
                expectedChallenge: $challenge,
                metadata: ['user_id' => $userId, 'name' => $name]
            );

            return Response::json([
                'success' => true,
                'message' => 'Passkey registered successfully.',
                'credential' => $credential->toArray(),
            ], 201);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function loginOptions(Request $request): Response
    {
        $userId = $request->input('user_id');

        $options = $this->webauthn->generateLoginOptions(
            userId: $userId
        );

        return Response::json([
            'success' => true,
            'options' => $options->toArray(),
        ]);
    }

    public function loginVerify(Request $request): Response
    {
        $body = $request->all();
        $challenge = $request->input('challenge') ?? '';

        try {
            $credential = $this->webauthn->verifyAuthentication(
                response: $body,
                expectedChallenge: $challenge
            );

            return Response::json([
                'success' => true,
                'message' => 'Passkey authentication successful.',
                'user_id' => $credential->userId,
                'credential_id' => $credential->id,
            ]);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
