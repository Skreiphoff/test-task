<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            // The idempotency key.
            $table->string('id')->primary();
            $table->string('type');
            // The order is created while the event is processed, so this is not a foreign key.
            $table->string('order_id')->nullable()->index();
            $table->jsonb('payload');
            // Inbox state: pending until a worker applies the event, then applied | stale | ignored
            $table->string('outcome')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('received_at');
            $table->index(['outcome', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
