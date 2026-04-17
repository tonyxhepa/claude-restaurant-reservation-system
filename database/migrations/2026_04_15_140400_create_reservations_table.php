<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_table_id')->constrained()->cascadeOnDelete();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone')->nullable();
            $table->unsignedTinyInteger('party_size');
            $table->date('reservation_date');
            $table->time('start_time');
            $table->string('status')->default('pending');
            $table->text('special_notes')->nullable();
            $table->string('confirmation_code')->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_date', 'start_time']);
            $table->index(['restaurant_table_id', 'reservation_date', 'start_time'], 'res_table_date_time_idx');
            $table->index('status');
            $table->index('guest_email');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
