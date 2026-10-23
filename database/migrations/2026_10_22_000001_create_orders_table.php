<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // MarketHub order id (ord_...), used as the primary key.
            $table->string('id')->primary();
            $table->string('number');
            $table->string('status');
            $table->string('currency', 3);

            $table->string('customer_email');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();

            $table->decimal('delivery_price', 12, 2);
            $table->decimal('discount', 12, 2);
            $table->decimal('total', 12, 2);

            $table->string('shipment_carrier')->nullable();
            $table->string('shipment_tracking_number')->nullable();
            $table->text('shipment_tracking_url')->nullable();

            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            // Marketplace timestamps (stored as UTC instants), not local row timestamps.
            $table->timestampTz('created_at')->index();
            $table->timestampTz('updated_at');
        });

        DB::statement('CREATE INDEX orders_status_created_at_index ON orders (status, created_at DESC)');

        // Case-insensitive email filter.
        DB::statement('CREATE INDEX orders_customer_email_lower_index ON orders (LOWER(customer_email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
