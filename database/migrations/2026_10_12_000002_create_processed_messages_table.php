<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ids of RabbitMQ events already handled: a repeated delivery is recognised and skipped.
        Schema::create('processed_messages', function (Blueprint $t) {
            $t->string('message_id', 36)->primary();
            $t->string('consumer', 100);
            $t->timestamp('processed_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
    }
};
