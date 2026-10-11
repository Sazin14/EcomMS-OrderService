<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('preorders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('variant_id');
            $table->string('sku', 100);
            $table->unsignedInteger('quantity');
            $table->string('status', 40)->default('pending')->index();
            $table->timestampTz('expected_at')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['variant_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preorders');
    }
};
