<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_event_receipts', function (Blueprint $table) {
            $table->id();

              $table->string('provider_name', 50);
              $table->string('provider_event_id', 191);
              $table->string('event_type', 100)->nullable();
              $table->string('room_name', 191)->nullable();

              $table->unsignedBigInteger('session_id')->nullable();

              $table->json('payload')->nullable();
              $table->text('processing_error')->nullable();

              $table->timestamp('received_at')->useCurrent();
              $table->timestamp('processed_at')->nullable();

            $table->unique(['provider_name', 'provider_event_id'],
'webhook_event_receipts_provider_event_unique');
            $table->index(['provider_name', 'room_name'],
'webhook_event_receipts_provider_room_index');
            $table->index(['provider_name', 'received_at'],
'webhook_event_receipts_provider_received_index');
        });

     }

     public function down(): void
     {
         Schema::dropIfExists('webhook_event_receipts');
     }
};
