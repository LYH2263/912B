<?php

namespace App\Repositories;

use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TicketRepository
{
    public function create(array $data): Ticket
    {
        return Ticket::create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);
        return $ticket->fresh();
    }

    public function delete(Ticket $ticket): void
    {
        $ticket->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Ticket::with(['creator', 'assignee']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters['keyword']}%")
                    ->orWhere('ticket_no', 'like', "%{$filters['keyword']}%");
            });
        }

        return $query->orderByDesc('priority')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getAllByStatuses(): array
    {
        $statuses = [
            Ticket::STATUS_PENDING,
            Ticket::STATUS_PROCESSING,
            Ticket::STATUS_RESOLVED,
            Ticket::STATUS_CLOSED,
        ];

        $result = [];
        foreach ($statuses as $status) {
            $result[$status] = Ticket::with(['creator', 'assignee'])
                ->where('status', $status)
                ->orderByDesc('priority')
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $result;
    }

    public function find(int $id): ?Ticket
    {
        return Ticket::with(['creator', 'assignee', 'comments.user'])->find($id);
    }

    public function addComment(Ticket $ticket, array $data): \App\Models\TicketComment
    {
        return $ticket->comments()->create($data);
    }

    public function getCounts(): array
    {
        return [
            'pending' => Ticket::where('status', Ticket::STATUS_PENDING)->count(),
            'processing' => Ticket::where('status', Ticket::STATUS_PROCESSING)->count(),
            'resolved' => Ticket::where('status', Ticket::STATUS_RESOLVED)->count(),
            'closed' => Ticket::where('status', Ticket::STATUS_CLOSED)->count(),
            'total' => Ticket::count(),
        ];
    }
}
