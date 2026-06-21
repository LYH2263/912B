<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Repositories\NotificationRepository;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationApiController extends Controller
{
    public function __construct(
        private NotificationRepository $repository,
        private NotificationService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $filters = $request->only(['is_read', 'type']);
        $perPage = $request->get('per_page', 15);

        $notifications = $this->repository->paginate($userId, $filters, $perPage);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $userId = Auth::id();
        $count = $this->service->getUnreadCount($userId);

        return response()->json([
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    public function show(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        if (!$notification->is_read) {
            $notification = $this->service->markAsRead($notification);
        }

        return response()->json(['data' => $notification]);
    }

    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            return response()->json(['message' => '无权访问'], 403);
        }

        $notification = $this->service->markAsRead($notification);

        return response()->json(['data' => $notification]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $userId = Auth::id();
        $count = $this->service->markAllAsRead($userId);

        return response()->json([
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'is_active']);
        $perPage = $request->get('per_page', 15);

        $templates = $this->repository->paginateTemplates($filters, $perPage);

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
                'last_page' => $templates->lastPage(),
            ],
        ]);
    }

    public function allTemplates(): JsonResponse
    {
        $templates = $this->repository->getAllTemplates();

        return response()->json(['data' => $templates]);
    }

    public function showTemplate(NotificationTemplate $template): JsonResponse
    {
        return response()->json(['data' => $template]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:notification_templates,code',
            'name' => 'required|string|max:100',
            'type' => 'required|in:order_shipped,stock_warning',
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->repository->createTemplate($validated);

        return response()->json(['data' => $template], 201);
    }

    public function updateTemplate(Request $request, NotificationTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:order_shipped,stock_warning',
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
            'variables' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->repository->updateTemplate($template, $validated);

        return response()->json(['data' => $template]);
    }

    public function destroyTemplate(NotificationTemplate $template): JsonResponse
    {
        $this->repository->deleteTemplate($template);

        return response()->json(null, 204);
    }

    public function toggleTemplate(NotificationTemplate $template): JsonResponse
    {
        $template = $this->repository->toggleTemplate($template);

        return response()->json(['data' => $template]);
    }
}
