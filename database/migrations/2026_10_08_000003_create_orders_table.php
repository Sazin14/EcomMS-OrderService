<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->string('status', 30)->default('pending')->index();
            $table->string('payment_status', 40)->default('pending')->index();
            $table->string('fulfillment_status', 40)->default('unfulfilled')->index();

            $table->string('currency', 3)->default('BDT');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);

            $table->jsonb('billing_address')->nullable();
            $table->jsonb('shipping_address')->nullable();

            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();

            $table->string('idempotency_key', 150)->unique();

            $table->timestampTz('placed_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
