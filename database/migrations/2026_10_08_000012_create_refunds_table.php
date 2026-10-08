<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('BDT');
            $table->string('method', 40);
            $table->string('status', 40)->default('pending')->index();
            $table->string('provider_reference', 150)->nullable()->index();
            $table->string('reason', 255)->nullable();
            $table->timestampTz('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
