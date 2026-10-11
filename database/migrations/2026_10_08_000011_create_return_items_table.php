<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();

            $table->unsignedBigInteger('variant_id');
            $table->string('sku', 100);
            $table->unsignedInteger('quantity');
            $table->string('reason', 100)->nullable();
            $table->string('condition', 40)->nullable();
            $table->decimal('refund_amount', 14, 2)->default(0);

            $table->timestamps();

            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
