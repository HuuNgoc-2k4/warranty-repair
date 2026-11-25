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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('serial', 64)->index();
            $table->string('customer');
            $table->string('phone', 20);
            $table->text('issue');
            $table->boolean('in_warranty');
            $table->string('status', 32)->index();
            // part_id tham chiếu Inventory (khác schema), không đặt khóa ngoại
            $table->unsignedBigInteger('part_id')->index();
            $table->unsignedInteger('qty');
            $table->string('idempotency_key', 64)->unique();
            $table->string('request_hash', 64);
            $table->string('failure_reason', 64)->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
