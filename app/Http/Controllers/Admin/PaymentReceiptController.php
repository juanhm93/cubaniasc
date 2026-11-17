<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a payment receipt to staff with the payments ability. Receipts live on
 * the private disk and are never reachable through a public URL.
 */
class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): StreamedResponse
    {
        $path = $payment->receipt_path;

        abort_if($path === null || $path === '', 404);

        // Receipts uploaded before they became private may still be on the public disk.
        foreach ([Payment::RECEIPT_DISK, Payment::LEGACY_RECEIPT_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, headers: [
                    'Cache-Control' => 'private, no-store',
                ]);
            }
        }

        abort(404);
    }
}
