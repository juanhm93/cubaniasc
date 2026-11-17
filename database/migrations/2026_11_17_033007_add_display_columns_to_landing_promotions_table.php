<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the display columns to databases that ran the create migration before they existed.
     * On a fresh database the create migration already includes them, so this does nothing.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('landing_promotions', 'display_mode')) {
            Schema::table('landing_promotions', function (Blueprint $table) {
                $table->string('display_mode', 20)->default('both')->after('cta_url');
            });
        }

        if (! Schema::hasColumn('landing_promotions', 'modal_orientation')) {
            Schema::table('landing_promotions', function (Blueprint $table) {
                $table->string('modal_orientation', 20)->default('portrait')->after('display_mode');
            });
        }
    }

    /**
     * The columns belong to the create migration, so rolling this back leaves them in place.
     */
    public function down(): void
    {
        //
    }
};
