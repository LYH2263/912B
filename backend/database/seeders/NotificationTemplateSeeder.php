<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'order_shipped',
                'name' => '订单发货通知',
                'type' => 'order_shipped',
                'title' => '订单发货通知：{order_no}',
                'content' => '订单号 {order_no} 已发货，请及时处理。
收货人：{shipping_name}
联系电话：{shipping_phone}
收货地址：{shipping_address}
商品：{items}
订单金额：¥{final_amount}
发货时间：{shipped_at}',
                'variables' => json_encode([
                    'order_no' => '订单号',
                    'order_id' => '订单ID',
                    'final_amount' => '订单金额',
                    'shipping_name' => '收货人',
                    'shipping_phone' => '联系电话',
                    'shipping_address' => '收货地址',
                    'items' => '商品列表',
                    'shipped_at' => '发货时间',
                ]),
                'is_active' => true,
            ],
            [
                'code' => 'stock_warning',
                'name' => '库存预警通知',
                'type' => 'stock_warning',
                'title' => '库存预警：{product_name}',
                'content' => '商品 {product_name}（SKU：{product_sku}）库存不足，请及时补货。
当前库存：{stock_quantity}
预警阈值：{low_stock_threshold}
预警时间：{warning_time}',
                'variables' => json_encode([
                    'product_name' => '商品名称',
                    'product_sku' => '商品SKU',
                    'product_id' => '商品ID',
                    'stock_quantity' => '当前库存',
                    'low_stock_threshold' => '预警阈值',
                    'warning_time' => '预警时间',
                ]),
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['code' => $template['code']],
                $template
            );
        }
    }
}
