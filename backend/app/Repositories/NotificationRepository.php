<?php

namespace App\Repositories;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository
{
    public function __construct(
        private Notification $model,
        private NotificationTemplate $templateModel
    ) {
    }

    public function paginate(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->where('user_id', $userId);

        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query->where('is_read', $filters['is_read']);
        }

        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    public function create(array $data): Notification
    {
        return $this->model->create($data);
    }

    public function markAsRead(Notification $notification): Notification
    {
        $notification->markAsRead();
        return $notification->fresh();
    }

    public function markAllAsRead(int $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    public function find(int $id): ?Notification
    {
        return $this->model->find($id);
    }

    public function findByUser(int $id, int $userId): ?Notification
    {
        return $this->model->where('id', $id)->where('user_id', $userId)->first();
    }

    public function paginateTemplates(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->templateModel->newQuery();

        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function getAllTemplates(): Collection
    {
        return $this->templateModel->where('is_active', true)->get();
    }

    public function findTemplate(int $id): ?NotificationTemplate
    {
        return $this->templateModel->find($id);
    }

    public function findTemplateByCode(string $code): ?NotificationTemplate
    {
        return $this->templateModel->where('code', $code)->first();
    }

    public function createTemplate(array $data): NotificationTemplate
    {
        return $this->templateModel->create($data);
    }

    public function updateTemplate(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $template->update($data);
        return $template->fresh();
    }

    public function deleteTemplate(NotificationTemplate $template): void
    {
        $template->delete();
    }

    public function toggleTemplate(NotificationTemplate $template): NotificationTemplate
    {
        $template->update(['is_active' => !$template->is_active]);
        return $template->fresh();
    }
}
