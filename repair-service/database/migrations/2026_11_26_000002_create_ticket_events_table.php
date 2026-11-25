<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};
