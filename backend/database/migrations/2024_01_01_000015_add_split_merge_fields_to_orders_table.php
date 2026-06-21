<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('source_order_id')->nullable()->after('user_id');
            $table->string('split_merge_type', 20)->nullable()->after('source_order_id');

            $table->index('source_order_id');
            $table->foreign('source_order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['source_order_id']);
            $table->dropColumn(['source_order_id', 'split_merge_type']);
        });
    }
};
