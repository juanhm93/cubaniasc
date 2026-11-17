<?php

namespace Tests\Feature\Admin;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Payment receipts hold personal and financial data: they must be stored privately,
 * served only to staff with the payments ability, and never accept executable files.
 */
class PaymentReceiptSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const POLYGLOT_GIF_PHP = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;<?php system(\$_GET['c']); ?>";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_malicious_receipts_are_rejected(): void
    {
        $admin = $this->createUserWithRole('admin_staff');
        $enrollment = Enrollment::factory()->create();

        $maliciousFiles = [
            'php script' => $this->realUpload('shell.php', '<?php system($_GET["c"]); ?>'),
            'svg with script' => $this->realUpload(
                'proof.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>',
            ),
            'html renamed to jpg' => $this->realUpload('proof.jpg', '<html><script>alert(1)</script></html>'),
            'gif/php polyglot named .php' => $this->realUpload('shell.php', self::POLYGLOT_GIF_PHP),
            'image with double extension' => $this->realUpload('proof.gif.php', self::POLYGLOT_GIF_PHP),
        ];

        foreach ($maliciousFiles as $label => $file) {
            $this->actingAs($admin)
                ->post(route('admin.payments.store'), $this->paymentPayload($enrollment, ['receipt' => $file]))
                ->assertSessionHasErrors('receipt', "{$label} should be rejected");
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_gif_php_polyglot_with_an_image_name_is_stored_privately_as_an_image(): void
    {
        $admin = $this->createUserWithRole('admin_staff');
        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.payments.store'), $this->paymentPayload($enrollment, [
                'receipt' => $this->realUpload('proof.gif', self::POLYGLOT_GIF_PHP),
            ]))
            ->assertSessionHasNoErrors();

        $path = Payment::query()->sole()->receipt_path;

        $this->assertStringStartsWith(Payment::RECEIPT_DIRECTORY.'/', $path);
        $this->assertStringEndsWith('.gif', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_image_and_pdf_receipts_are_stored_on_the_private_disk(): void
    {
        $admin = $this->createUserWithRole('admin_staff');

        foreach ([UploadedFile::fake()->image('proof.png'), UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf')] as $file) {
            $enrollment = Enrollment::factory()->create();

            $this->actingAs($admin)
                ->post(route('admin.payments.store'), $this->paymentPayload($enrollment, ['receipt' => $file]))
                ->assertSessionHasNoErrors();

            $payment = Payment::query()->latest('id')->first();

            Storage::disk('local')->assertExists($payment->receipt_path);
            Storage::disk('public')->assertMissing($payment->receipt_path);
            $this->assertSame(route('admin.payments.receipt', $payment), $payment->receipt_url);
        }
    }

    public function test_cash_payments_do_not_keep_the_uploaded_file(): void
    {
        $admin = $this->createUserWithRole('admin_staff');
        $enrollment = Enrollment::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.payments.store'), $this->paymentPayload($enrollment, [
                'method' => 'efectivo',
                'receipt' => UploadedFile::fake()->image('proof.png'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Payment::query()->sole()->receipt_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_receipt_is_served_only_to_staff_with_the_payments_ability(): void
    {
        $payment = $this->paymentWithReceiptOn('local');

        $this->get(route('admin.payments.receipt', $payment))->assertRedirect(route('login'));

        foreach (['teacher', 'staff'] as $slug) {
            $this->actingAs($this->createUserWithRole($slug))
                ->get(route('admin.payments.receipt', $payment))
                ->assertForbidden();
        }

        foreach (['admin_staff', 'admin'] as $slug) {
            $this->actingAs($this->createUserWithRole($slug))
                ->get(route('admin.payments.receipt', $payment))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_pending_users_cannot_open_receipts(): void
    {
        $payment = $this->paymentWithReceiptOn('local');
        $pending = $this->createUserWithRole('admin_staff', ['status' => 'pending']);

        $this->actingAs($pending)
            ->get(route('admin.payments.receipt', $payment))
            ->assertRedirect();
    }

    public function test_receipts_still_on_the_public_disk_are_served(): void
    {
        $payment = $this->paymentWithReceiptOn('public');

        $this->actingAs($this->createUserWithRole('admin_staff'))
            ->get(route('admin.payments.receipt', $payment))
            ->assertOk();
    }

    public function test_missing_receipt_returns_not_found(): void
    {
        $admin = $this->createUserWithRole('admin_staff');
        $withoutReceipt = Payment::factory()->create(['receipt_path' => null]);
        $withMissingFile = Payment::factory()->create(['receipt_path' => 'payments/receipts/missing.png']);

        $this->actingAs($admin)->get(route('admin.payments.receipt', $withoutReceipt))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.payments.receipt', $withMissingFile))->assertNotFound();
    }

    public function test_migration_moves_existing_receipts_to_the_private_disk(): void
    {
        $payment = $this->paymentWithReceiptOn('public');
        $migration = require database_path('migrations/2026_11_17_045832_move_payment_receipts_to_private_disk.php');

        $migration->up();

        Storage::disk('local')->assertExists($payment->receipt_path);
        Storage::disk('public')->assertMissing($payment->receipt_path);

        $migration->up();

        Storage::disk('local')->assertExists($payment->receipt_path);
    }

    /**
     * A real temporary file, so the MIME type is detected from its content
     * (fake uploads guess it from the name).
     */
    private function realUpload(string $originalName, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt');
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, null, null, true);
    }

    private function paymentWithReceiptOn(string $disk): Payment
    {
        $path = UploadedFile::fake()->image('proof.png')->store(Payment::RECEIPT_DIRECTORY, $disk);

        return Payment::factory()->create(['receipt_path' => $path, 'method' => 'transferencia']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function paymentPayload(Enrollment $enrollment, array $overrides = []): array
    {
        return [
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => '25.00',
            'method' => 'transferencia',
            'return_month' => now()->format('Y-m'),
            ...$overrides,
        ];
    }
}
