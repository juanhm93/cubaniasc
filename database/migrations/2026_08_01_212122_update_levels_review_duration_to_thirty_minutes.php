<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('levels')->update(['review_duration_seconds' => 1800]);
    }

    public function down(): void
    {
        DB::table('levels')->update(['review_duration_seconds' => 300]);
    }
};
