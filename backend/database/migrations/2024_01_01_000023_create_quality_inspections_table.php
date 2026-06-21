<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique()->comment('质检批次号');
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->comment('关联采购单ID');
            $table->unsignedInteger('qualified_quantity')->default(0)->comment('合格数量');
            $table->unsignedInteger('unqualified_quantity')->default(0)->comment('不合格数量');
            $table->text('unqualified_reason')->nullable()->comment('不合格原因');
            $table->string('inspector')->comment('质检员');
            $table->date('inspection_date')->comment('质检日期');
            $table->text('remark')->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->comment('创建人ID');
            $table->timestamps();

            $table->index('product_id');
            $table->index('purchase_order_id');
            $table->index('inspection_date');
            $table->index('batch_no');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_inspections');
    }
};
