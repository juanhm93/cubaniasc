<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('period')->nullable()->after('student_id')->index();
        });

        $this->backfillPeriodFromPaymentDate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['period']);
            $table->dropColumn('period');
        });
    }

    /**
     * Existing payments had no period: until now the month of the payment date
     * was used as the covered month, so keep that meaning. Done in PHP so the
     * same code works on SQLite (local) and MySQL (production).
     */
    private function backfillPeriodFromPaymentDate(): void
    {
        $appTimezone = (string) config('app.timezone');
        $academyTimezone = (string) config('cubania.timezone', $appTimezone);

        DB::table('payments')
            ->whereNull('period')
            ->select(['id', 'paid_at', 'created_at'])
            ->chunkById(500, function ($payments) use ($appTimezone, $academyTimezone): void {
                foreach ($payments as $payment) {
                    $moment = $payment->paid_at ?? $payment->created_at;

                    if ($moment === null) {
                        continue;
                    }

                    $period = CarbonImmutable::parse($moment, $appTimezone)
                        ->setTimezone($academyTimezone)
                        ->startOfMonth()
                        ->toDateString();

                    DB::table('payments')->where('id', $payment->id)->update(['period' => $period]);
                }
            });
    }
};
