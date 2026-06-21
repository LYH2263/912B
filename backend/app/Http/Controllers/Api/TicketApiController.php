<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use App\Repositories\TicketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketApiController extends Controller
{
    public function __construct(
        private TicketService $service,
        private TicketRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'category', 'priority', 'assigned_to', 'keyword']);
        $perPage = $request->get('per_page', 15);

        $tickets = $this->repository->paginate($filters, $perPage);

        return response()->json([
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'last_page' => $tickets->lastPage(),
            ],
            'counts' => $this->repository->getCounts(),
        ]);
    }

    public function kanban(): JsonResponse
    {
        $ticketsByStatus = $this->repository->getAllByStatuses();
        $counts = $this->repository->getCounts();

        return response()->json([
            'data' => $ticketsByStatus,
            'counts' => $counts,
        ]);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $ticket = $this->repository->find($ticket->id);
        return response()->json(['data' => $ticket]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'category' => 'required|in:logistics,quality,refund',
            'priority' => 'nullable|in:high,medium,low',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'comment' => 'nullable|string',
        ]);

        try {
            $ticket = $this->service->create($validated);
            return response()->json(['data' => $ticket], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string',
            'category' => 'sometimes|in:logistics,quality,refund',
            'priority' => 'sometimes|in:high,medium,low',
            'status' => 'sometimes|in:pending,processing,resolved,closed',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $ticket = $this->service->update($ticket, $validated);
            return response()->json(['data' => $ticket]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,resolved,closed',
            'comment' => 'nullable|string',
        ]);

        try {
            $ticket = $this->service->updateStatus($ticket, $validated['status'], $validated['comment'] ?? null);
            return response()->json(['data' => $ticket]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        try {
            $ticket = $this->service->assign($ticket, $validated['assigned_to'] ?? null);
            return response()->json(['data' => $ticket]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'is_internal' => 'nullable|boolean',
        ]);

        try {
            $comment = $this->service->addComment($ticket, $validated);
            $comment->load('user');
            return response()->json(['data' => $comment], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        try {
            $this->service->delete($ticket);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function assignees(): JsonResponse
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        return response()->json(['data' => $users]);
    }

    public function counts(): JsonResponse
    {
        return response()->json(['data' => $this->repository->getCounts()]);
    }
}
