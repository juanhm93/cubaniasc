<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Log of the monthly payment reminders. The unique key is what guarantees a
     * student never gets the same reminder twice, however many requests try to
     * send it.
     */
    public function up(): void
    {
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->string('kind', 16);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'period', 'kind']);
            $table->index(['period', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
