<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\ZooKeeper\Session;

/**
 * Registry and lifecycle manager for all client sessions.
 */
class SessionManager
{
    /** @var array<int, Session> [sessionId => Session] */
    protected array $sessions = [];

    public function createSession(int $timeoutMs = 30000): Session
    {
        $session = new Session(0, $timeoutMs);
        $this->sessions[$session->getId()] = $session;
        return $session;
    }

    public function getSession(int $sessionId): ?Session
    {
        return $this->sessions[$sessionId] ?? null;
    }

    public function heartbeat(int $sessionId): bool
    {
        $session = $this->getSession($sessionId);
        if ($session !== null && !$session->isClosed()) {
            $session->touch();
            return true;
        }
        return false;
    }

    public function closeSession(int $sessionId): ?Session
    {
        if (isset($this->sessions[$sessionId])) {
            $session = $this->sessions[$sessionId];
            $session->close();
            unset($this->sessions[$sessionId]);
            return $session;
        }
        return null;
    }

    /**
     * Sweep and return all expired sessions.
     *
     * @return array<Session>
     */
    public function sweepExpired(float $customTime = 0.0): array
    {
        $expired = [];
        foreach ($this->sessions as $id => $session) {
            if ($session->isExpired($customTime)) {
                $session->close();
                $expired[] = $session;
                unset($this->sessions[$id]);
            }
        }
        return $expired;
    }

    /**
     * @return array<int, Session>
     */
    public function getSessions(): array
    {
        return $this->sessions;
    }
}
