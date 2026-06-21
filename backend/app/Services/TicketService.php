<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Repositories\TicketRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(
        private TicketRepository $repository
    ) {
    }

    public function create(array $data): Ticket
    {
        $data['ticket_no'] = Ticket::generateTicketNo();
        $data['created_by'] = Auth::id();
        $data['status'] = Ticket::STATUS_PENDING;

        return DB::transaction(function () use ($data) {
            $ticket = $this->repository->create($data);

            if (!empty($data['comment'])) {
                $this->repository->addComment($ticket, [
                    'ticket_id' => $ticket->id,
                    'user_id' => Auth::id(),
                    'content' => $data['comment'],
                    'is_internal' => false,
                ]);
            }

            return $ticket->load(['creator', 'assignee']);
        });
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $data) {
            if (isset($data['status']) && $data['status'] !== $ticket->status) {
                $this->validateStatusTransition($ticket, $data['status']);

                if ($data['status'] === Ticket::STATUS_RESOLVED) {
                    $data['resolved_at'] = now();
                } elseif ($data['status'] === Ticket::STATUS_CLOSED) {
                    $data['closed_at'] = now();
                }
            }

            return $this->repository->update($ticket, $data);
        });
    }

    public function updateStatus(Ticket $ticket, string $status, ?string $comment = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $status, $comment) {
            $this->validateStatusTransition($ticket, $status);

            $data = ['status' => $status];

            if ($status === Ticket::STATUS_RESOLVED && !$ticket->resolved_at) {
                $data['resolved_at'] = now();
            } elseif ($status === Ticket::STATUS_CLOSED && !$ticket->closed_at) {
                $data['closed_at'] = now();
            }

            $updatedTicket = $this->repository->update($ticket, $data);

            if ($comment) {
                $this->repository->addComment($updatedTicket, [
                    'ticket_id' => $updatedTicket->id,
                    'user_id' => Auth::id(),
                    'content' => '[状态变更: ' . Ticket::STATUS_LABELS[$status] . '] ' . $comment,
                    'is_internal' => false,
                ]);
            }

            return $updatedTicket;
        });
    }

    public function assign(Ticket $ticket, ?int $assigneeId): Ticket
    {
        return DB::transaction(function () use ($ticket, $assigneeId) {
            $updatedTicket = $this->repository->update($ticket, [
                'assigned_to' => $assigneeId,
            ]);

            $assigneeName = $assigneeId ? (\App\Models\User::find($assigneeId)?->name ?? '未知用户') : '未指派';
            $this->repository->addComment($updatedTicket, [
                'ticket_id' => $updatedTicket->id,
                'user_id' => Auth::id(),
                'content' => '[指派处理人] 已指派给: ' . $assigneeName,
                'is_internal' => true,
            ]);

            return $updatedTicket;
        });
    }

    public function addComment(Ticket $ticket, array $data): TicketComment
    {
        return $this->repository->addComment($ticket, [
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'content' => $data['content'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);
    }

    public function delete(Ticket $ticket): void
    {
        $this->repository->delete($ticket);
    }

    private function validateStatusTransition(Ticket $ticket, string $newStatus): void
    {
        $allowedTransitions = [
            Ticket::STATUS_PENDING => [Ticket::STATUS_PROCESSING, Ticket::STATUS_CLOSED],
            Ticket::STATUS_PROCESSING => [Ticket::STATUS_PENDING, Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED],
            Ticket::STATUS_RESOLVED => [Ticket::STATUS_PROCESSING, Ticket::STATUS_CLOSED],
            Ticket::STATUS_CLOSED => [],
        ];

        $allowed = $allowedTransitions[$ticket->status] ?? [];
        if (!in_array($newStatus, $allowed)) {
            throw new \Exception(
                "状态变更不允许: 从 [{$ticket->status_label}] 到 [" . (Ticket::STATUS_LABELS[$newStatus] ?? $newStatus) . "]"
            );
        }
    }
}
