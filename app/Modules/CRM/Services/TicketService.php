<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework
|--------------------------------------------------------------------------
|
| File      : app/Modules/CRM/Services/TicketService.php
| Module    : CRM
| Namespace : NetFusion\Modules\CRM\Services
| Purpose   : Customer Support Helpdesk service handling ticket lifecycles and SLA deadlines.
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

namespace NetFusion\Modules\CRM\Services;

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Modules\CRM\Models\Ticket;
use NetFusion\Modules\CRM\Models\TicketReply;
use RuntimeException;

class TicketService
{
    protected array $slaHours = [
        'urgent' => 4,
        'high'   => 12,
        'medium' => 24,
        'low'    => 48,
    ];

    public function __construct(
        protected Application $app
    ) {}

    protected function getDb(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection();
    }

    public function createTicket(array $data): Ticket
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');
        $priority = strtolower($data['priority'] ?? 'medium');
        $hours = $this->slaHours[$priority] ?? 24;
        $slaDueAt = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $ticketNum = $data['ticket_number'] ?? ('TCK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))));

        $id = (int)$db->insert("
            INSERT INTO crm_tickets (tenant_id, ticket_number, customer_name, customer_email, subject, description, priority, status, assigned_to, sla_due_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?)
        ", [
            $data['tenant_id'] ?? null,
            $ticketNum,
            $data['customer_name'],
            $data['customer_email'],
            $data['subject'],
            $data['description'],
            $priority,
            $data['assigned_to'] ?? null,
            $slaDueAt,
            $now,
            $now,
        ]);

        // Webhook dispatch
        if (function_exists('webhook')) {
            try {
                webhook()->dispatch('ticket.created', [
                    'ticket_id'      => $id,
                    'ticket_number'  => $ticketNum,
                    'customer_email' => $data['customer_email'],
                    'priority'       => $priority,
                    'sla_due_at'     => $slaDueAt,
                ]);
            } catch (\Throwable) {
                // Ignore webhook failure
            }
        }

        $row = Ticket::where('id', '=', $id)->first();
        return Ticket::newFromBuilder($row);
    }

    public function addReply(
        int $ticketId,
        string $message,
        string $senderType = 'agent',
        ?int $userId = null
    ): TicketReply {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');

        $ticket = Ticket::where('id', '=', $ticketId)->first();
        if (!$ticket) {
            throw new RuntimeException("Ticket #{$ticketId} not found.");
        }

        $id = (int)$db->insert("
            INSERT INTO crm_ticket_replies (ticket_id, user_id, sender_type, message, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [
            $ticketId,
            $userId,
            $senderType,
            $message,
            $now,
            $now,
        ]);

        // If ticket was closed or resolved and customer replies, reopen it
        if ($senderType === 'customer' && in_array($ticket['status'], ['resolved', 'closed'], true)) {
            $db->statement("
                UPDATE crm_tickets
                SET status = 'open', updated_at = ?
                WHERE id = ?
            ", [$now, $ticketId]);
        } elseif ($senderType === 'agent' && $ticket['status'] === 'open') {
            $db->statement("
                UPDATE crm_tickets
                SET status = 'in_progress', updated_at = ?
                WHERE id = ?
            ", [$now, $ticketId]);
        }

        $row = TicketReply::where('id', '=', $id)->first();
        return TicketReply::newFromBuilder($row);
    }

    public function resolveTicket(int $ticketId): Ticket
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');

        $db->statement("
            UPDATE crm_tickets
            SET status = 'resolved', resolved_at = ?, updated_at = ?
            WHERE id = ?
        ", [$now, $now, $ticketId]);

        $row = Ticket::where('id', '=', $ticketId)->first();
        if (!$row) {
            throw new RuntimeException("Ticket #{$ticketId} not found.");
        }

        if (function_exists('webhook')) {
            try {
                webhook()->dispatch('ticket.resolved', [
                    'ticket_id'     => $ticketId,
                    'ticket_number' => $row['ticket_number'],
                    'resolved_at'   => $now,
                ]);
            } catch (\Throwable) {
                // Ignore webhook failure
            }
        }

        return Ticket::newFromBuilder($row);
    }

    public function closeTicket(int $ticketId): Ticket
    {
        $db = $this->getDb();
        $now = date('Y-m-d H:i:s');

        $db->statement("
            UPDATE crm_tickets
            SET status = 'closed', updated_at = ?
            WHERE id = ?
        ", [$now, $ticketId]);

        $row = Ticket::where('id', '=', $ticketId)->first();
        if (!$row) {
            throw new RuntimeException("Ticket #{$ticketId} not found.");
        }

        return Ticket::newFromBuilder($row);
    }

    public function getTicket(int $id): ?Ticket
    {
        $row = Ticket::where('id', '=', $id)->first();
        return $row ? Ticket::newFromBuilder($row) : null;
    }

    public function listTickets(int $limit = 50, int $offset = 0): array
    {
        $rows = Ticket::query()->limit($limit)->offset($offset)->get();
        return array_map(fn($r) => Ticket::newFromBuilder($r), $rows);
    }
}
