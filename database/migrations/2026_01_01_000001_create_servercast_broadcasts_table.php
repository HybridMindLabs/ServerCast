<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servercast_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('message', 500);

            // 'all' at send time, or 'selected' for a specific set. Kept for
            // display; the actual targets are the deliveries below.
            $table->string('target_type', 16)->default('selected');

            // One row of the map per server the message went to:
            // [ { "server_id": 1, "command_id": 42 }, ... ]
            // command_id lets the panel resolve live delivery status from the
            // core bridge queue without duplicating it here.
            $table->json('deliveries');

            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servercast_broadcasts');
    }
};
