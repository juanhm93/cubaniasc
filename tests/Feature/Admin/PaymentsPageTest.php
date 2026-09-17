<?php

namespace Tests\Feature\Admin;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_view_payments_page(): void
    {
        $role = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        Enrollment::factory()->create();

        $this->actingAs($admin);

        $this->get(route('admin.payments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/payments')
                ->has('calendarMonths')
                ->has('calendarYears')
                ->has('preRegistrations')
                ->has('selectedMonth')
                ->has('rows'));
    }

    public function test_non_admin_cannot_view_payments_page(): void
    {
        $role = Role::factory()->create(['slug' => 'teacher', 'name' => 'Teacher']);
        $user = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        $this->get(route('admin.payments.index'))->assertForbidden();
    }

    public function test_admin_can_store_cash_payment_without_reference_or_file(): void
    {
        Storage::fake('public');

        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin);

        $this->post(route('admin.payments.store'), [
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => '75.50',
            'method' => 'efectivo',
            'return_month' => now()->format('Y-m'),
        ])->assertRedirect(route('admin.payments.index', ['month' => now()->format('Y-m')]));

        $this->assertDatabaseHas('payments', [
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => '75.50',
            'method' => 'efectivo',
        ]);
    }

    public function test_transferencia_requires_reference_or_receipt(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin);

        $this->post(route('admin.payments.store'), [
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => '10.00',
            'method' => 'transferencia',
            'reference' => '',
            'return_month' => now()->format('Y-m'),
        ])->assertSessionHasErrors('reference');
    }

    public function test_transferencia_accepts_reference(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin);

        $this->post(route('admin.payments.store'), [
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => '10.00',
            'method' => 'transferencia',
            'reference' => 'REF-12345',
            'return_month' => now()->format('Y-m'),
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'reference' => 'REF-12345',
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
        ]);
    }

    public function test_admin_can_view_student_detail(): void
    {
        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin);

        $this->get(route('admin.students.show', ['student' => $enrollment->student_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/students/show')
                ->has('student')
                ->where('canUpdateEmail', true));
    }

    public function test_store_saves_receipt_file(): void
    {
        Storage::fake('public');

        $role = Role::factory()->create(['slug' => 'admin']);
        $admin = User::factory()->create([
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $enrollment = Enrollment::factory()->create();

        $file = UploadedFile::fake()->image('proof.png', 200, 200);

        $this->actingAs($admin);

        $this->post(route('admin.payments.store'), [
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => '25.00',
            'method' => 'transferencia',
            'receipt' => $file,
            'return_month' => now()->format('Y-m'),
        ])->assertRedirect();

        $payment = Payment::query()->latest('id')->first();
        $this->assertNotNull($payment?->receipt_path);
        Storage::disk('public')->assertExists($payment->receipt_path);
    }
}
