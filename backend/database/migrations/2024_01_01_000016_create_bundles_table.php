<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('套餐名称');
            $table->string('sku', 100)->unique()->comment('套餐SKU');
            $table->text('description')->nullable()->comment('套餐描述');
            $table->string('image', 255)->nullable()->comment('套餐封面图');
            $table->decimal('total_price', 10, 2)->comment('套餐总价');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('状态');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};
