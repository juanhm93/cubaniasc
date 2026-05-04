<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Place;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_time_session_attendees_support_students_and_external_people(): void
    {
        $company = Company::factory()->create();
        $place = Place::factory()->create(['company_id' => $company->id]);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $student = Student::factory()->create();

        $sessionId = DB::table('one_time_sessions')->insertGetId([
            'company_id' => $company->id,
            'place_id' => $place->id,
            'user_id' => $owner->id,
            'type' => 'workshop',
            'name' => 'Bachata Intensivo',
            'description' => 'Taller de una sola oportunidad',
            'price' => 30,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('one_time_session_attendees')->insert([
            [
                'one_time_session_id' => $sessionId,
                'student_id' => $student->id,
                'name' => $student->name,
                'phone' => null,
                'school' => null,
                'payment_status' => 'paid',
                'amount_due' => 30,
                'amount_paid' => 30,
                'paid_at' => now(),
                'observations' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'one_time_session_id' => $sessionId,
                'student_id' => null,
                'name' => 'Invitado Externo',
                'phone' => '+584121112233',
                'school' => 'Academia Vecina',
                'payment_status' => 'pending',
                'amount_due' => 30,
                'amount_paid' => null,
                'paid_at' => null,
                'observations' => 'Llega referido por Instagram',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertDatabaseHas('one_time_session_attendees', [
            'one_time_session_id' => $sessionId,
            'student_id' => $student->id,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('one_time_session_attendees', [
            'one_time_session_id' => $sessionId,
            'student_id' => null,
            'name' => 'Invitado Externo',
            'phone' => '+584121112233',
            'school' => 'Academia Vecina',
            'payment_status' => 'pending',
        ]);
    }
}
