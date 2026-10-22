<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('kind');
            $table->timestampTz('claimed_at');
            // At most one email of each kind per order.
            $table->unique(['order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notifications');
    }
};
