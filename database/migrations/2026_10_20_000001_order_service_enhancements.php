<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('type', 20)->default('standard')->after('order_number')->index();      // standard | preorder
            $t->string('payment_method', 20)->default('cod')->after('payment_status');         // cod | online
            $t->string('customer_name', 150)->nullable();
            $t->string('customer_email', 190)->nullable();
            $t->string('customer_phone', 40)->nullable();
            $t->timestampTz('hold_expires_at')->nullable()->index();   // pending order is cancelled after this
            $t->timestampTz('shipped_at')->nullable();
            $t->timestampTz('delivered_at')->nullable();
            $t->unsignedBigInteger('inventory_reservation_id')->nullable();
            $t->boolean('needs_attention')->default(false)->index();   // Inventory refused something: a human must look
            $t->text('attention_note')->nullable();
        });

        Schema::table('order_items', function (Blueprint $t) {
            $t->boolean('is_preorder')->default(false);
        });

        Schema::table('preorders', function (Blueprint $t) {
            $t->unsignedBigInteger('reservation_id')->nullable();
            $t->timestampTz('allocated_at')->nullable();
            $t->unique(['order_id', 'variant_id']);
        });

        Schema::table('return_requests', function (Blueprint $t) {
            $t->string('type', 30)->default('customer')->after('reference');   // customer | delivery_failed
            $t->timestampTz('received_at')->nullable();
            $t->timestampTz('rejected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', fn (Blueprint $t) => $t->dropColumn(['type', 'received_at', 'rejected_at']));

        Schema::table('preorders', function (Blueprint $t) {
            $t->dropUnique(['order_id', 'variant_id']);
            $t->dropColumn(['reservation_id', 'allocated_at']);
        });

        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('is_preorder'));

        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn([
            'type', 'payment_method', 'customer_name', 'customer_email', 'customer_phone', 'hold_expires_at',
            'shipped_at', 'delivered_at', 'inventory_reservation_id', 'needs_attention', 'attention_note',
        ]));
    }
};
