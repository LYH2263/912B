<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_id')->comment('套餐ID');
            $table->unsignedBigInteger('product_id')->comment('商品ID');
            $table->unsignedInteger('quantity')->default(1)->comment('商品数量');
            $table->timestamps();

            $table->index('bundle_id');
            $table->index('product_id');
            $table->foreign('bundle_id')->references('id')->on('bundles')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->unique(['bundle_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
    }
};
