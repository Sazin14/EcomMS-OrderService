<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('reference', 60)->unique();
            $table->string('status', 40)->default('requested')->index();
            $table->string('reason', 100)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('staff_note')->nullable();
            $table->string('idempotency_key', 150)->unique();

            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};
