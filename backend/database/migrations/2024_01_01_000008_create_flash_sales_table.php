<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->unsignedBigInteger('product_id');
            $table->decimal('flash_price', 10, 2);
            $table->unsignedInteger('activity_stock');
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('per_limit')->default(1);
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->enum('status', ['pending', 'active', 'ended'])->default('pending');
            $table->timestamps();

            $table->index('status');
            $table->index('start_time');
            $table->index('end_time');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sales');
    }
};
