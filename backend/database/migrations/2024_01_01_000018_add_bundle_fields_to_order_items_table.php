<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('item_type', ['product', 'bundle'])->default('product')->after('product_id')->comment('订单项类型');
            $table->unsignedBigInteger('bundle_id')->nullable()->after('item_type')->comment('关联套餐ID');
            $table->json('bundle_detail')->nullable()->after('bundle_id')->comment('套餐子项明细快照');

            $table->index('item_type');
            $table->index('bundle_id');
            $table->foreign('bundle_id')->references('id')->on('bundles')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['bundle_id']);
            $table->dropIndex(['item_type']);
            $table->dropIndex(['bundle_id']);
            $table->dropColumn(['item_type', 'bundle_id', 'bundle_detail']);
        });
    }
};
