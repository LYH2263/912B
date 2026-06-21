<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_no')->unique()->comment('采购单号');
            $table->string('supplier_name')->comment('供应商名称');
            $table->string('supplier_contact')->nullable()->comment('供应商联系人');
            $table->string('supplier_phone')->nullable()->comment('供应商联系电话');
            $table->date('expected_arrival_date')->comment('预计到货日期');
            $table->date('actual_arrival_date')->nullable()->comment('实际到货日期');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('采购总金额');
            $table->text('remark')->nullable()->comment('备注');
            $table->string('status')->default('draft')->comment('状态：draft 草稿，pending 待入库，partial 部分入库，completed 已完成');
            $table->unsignedBigInteger('created_by')->comment('创建人ID');
            $table->unsignedBigInteger('stock_in_by')->nullable()->comment('入库人ID');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('stock_in_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
