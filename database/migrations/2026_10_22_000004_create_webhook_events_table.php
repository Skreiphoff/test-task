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
            $table->jsonb('payload');
            // pending while processing, then applied | stale | ignored
            $table->string('outcome');
            $table->timestampTz('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
