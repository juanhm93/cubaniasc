<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotions staff upload to show on the public landing (modal and section).
     */
    public function up(): void
    {
        Schema::create('landing_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('image_path');
            $table->string('cta_label', 40)->nullable();
            $table->string('cta_url')->nullable();
            $table->string('display_mode', 20)->default('both');
            $table->string('modal_orientation', 20)->default('portrait');
            $table->boolean('is_active')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_promotions');
    }
};
