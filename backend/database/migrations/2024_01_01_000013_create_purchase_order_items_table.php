<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->comment('采购单ID');
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->string('product_name')->comment('商品名称快照');
            $table->string('product_sku')->comment('商品SKU快照');
            $table->decimal('purchase_price', 12, 2)->comment('采购单价');
            $table->integer('quantity')->comment('采购数量');
            $table->integer('received_quantity')->default(0)->comment('已入库数量');
            $table->decimal('subtotal', 12, 2)->comment('小计金额');
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
