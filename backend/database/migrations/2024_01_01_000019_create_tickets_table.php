<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 32)->unique();
            $table->string('title', 200);
            $table->text('description');
            $table->enum('category', ['logistics', 'quality', 'refund'])->comment('物流问题/质量问题/退款咨询');
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['pending', 'processing', 'resolved', 'closed'])->default('pending');
            $table->unsignedBigInteger('created_by')->comment('录入管理员');
            $table->unsignedBigInteger('assigned_to')->nullable()->comment('指派处理人');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'priority']);
            $table->index(['category', 'status']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
