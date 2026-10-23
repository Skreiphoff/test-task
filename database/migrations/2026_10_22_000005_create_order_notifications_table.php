<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transactional outbox: a row is written in the same transaction as the order change.
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('kind');
            // pending | sent | skipped
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            // Lease of the worker that is sending right now; an expired lease means the worker died.
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('processed_at')->nullable();
            // At most one email of each kind per order.
            $table->unique(['order_id', 'kind']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notifications');
    }
};
