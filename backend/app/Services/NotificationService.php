<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\NotificationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        private NotificationRepository $repository
    ) {
    }

    public function renderTemplate(NotificationTemplate $template, array $variables = []): array
    {
        $title = $template->title;
        $content = $template->content;

        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $title = str_replace($placeholder, (string) $value, $title);
            $content = str_replace($placeholder, (string) $value, $content);
        }

        return [
            'title' => $title,
            'content' => $content,
        ];
    }

    public function sendNotification(int $userId, string $type, array $data = [], ?NotificationTemplate $template = null): Notification
    {
        return DB::transaction(function () use ($userId, $type, $data, $template) {
            $title = $data['title'] ?? '系统通知';
            $content = $data['content'] ?? '';
            $templateId = null;

            if ($template) {
                $rendered = $this->renderTemplate($template, $data);
                $title = $rendered['title'];
                $content = $rendered['content'];
                $templateId = $template->id;
            }

            $notification = $this->repository->create([
                'user_id' => $userId,
                'template_id' => $templateId,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'data' => $data,
            ]);

            Log::info('发送站内通知', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
            ]);

            return $notification;
        });
    }

    public function sendOrderShippedNotification(Order $order, ?int $userId = null): ?Notification
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return null;
        }

        $template = $this->repository->findTemplateByCode('order_shipped');

        if ($template && !$template->is_active) {
            $template = null;
        }

        $orderItemsText = $order->orderItems->map(function ($item) {
            return "{$item->product_name} x{$item->quantity}";
        })->implode('、');

        $variables = [
            'order_no' => $order->order_no,
            'order_id' => $order->id,
            'final_amount' => (float) $order->final_amount,
            'shipping_name' => $order->shipping_name ?? '未填写',
            'shipping_phone' => $order->shipping_phone ?? '未填写',
            'shipping_address' => $order->shipping_address ?? '未填写',
            'items' => $orderItemsText,
            'shipped_at' => $order->shipped_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
        ];

        return $this->sendNotification(
            $userId,
            'order_shipped',
            $variables,
            $template
        );
    }

    public function sendStockWarningNotification(Product $product, ?int $userId = null): ?Notification
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return null;
        }

        $template = $this->repository->findTemplateByCode('stock_warning');

        if ($template && !$template->is_active) {
            $template = null;
        }

        $variables = [
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_id' => $product->id,
            'stock_quantity' => $product->stock_quantity,
            'low_stock_threshold' => $product->low_stock_threshold,
            'warning_time' => now()->format('Y-m-d H:i'),
        ];

        return $this->sendNotification(
            $userId,
            'stock_warning',
            $variables,
            $template
        );
    }

    public function checkAndSendStockWarning(Product $product, ?int $userId = null): ?Notification
    {
        if ($product->isLowStock() || $product->isOutOfStock()) {
            return $this->sendStockWarningNotification($product, $userId);
        }
        return null;
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->repository->getUnreadCount($userId);
    }

    public function markAsRead(Notification $notification): Notification
    {
        return $this->repository->markAsRead($notification);
    }

    public function markAllAsRead(int $userId): int
    {
        return $this->repository->markAllAsRead($userId);
    }
}
