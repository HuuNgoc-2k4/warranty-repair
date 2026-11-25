<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            // ref do Repair Service sinh ra (T{ticketId}); UNIQUE để gửi lại không giữ kho hai lần
            $table->string('ref', 64)->unique();
            $table->unsignedBigInteger('part_id')->index();
            $table->unsignedInteger('qty');
            $table->string('status', 32)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
