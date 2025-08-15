<?php

namespace App\DTO;
use Illuminate\Support\Facades\Log;

// primary purpose of Invoice Dto is to handle the validation of json input provided by ai model, and convert to recognized and standard schema
class InvoiceDetails
{
    public string $invoice_number;
    public float $total_amount;
    public string $vendor;

    public function __construct(string $invoice_number, float $total_amount, string $vendor)
    {
        $this->invoice_number = $invoice_number;
        $this->total_amount = $total_amount;
        $this->vendor = $vendor;
    }

    public static function fromJson(array $data): ?self
    {
        $invoice_number = $data['invoice_number'] ?? null;
        $total_amount = isset($data['total_amount']) ? (float) $data['total_amount'] : null;
        $vendor = $data['vendor'] ?? null;

        if (!is_string($invoice_number) || !is_float($total_amount) || !is_string($vendor)) {

            Log::error("Invalid invoice details", [
                'invoice_number' => $invoice_number,
                'total_amount' => $total_amount,
                'vendor' => $vendor,
                'validation_errs'=> [
                    'invoice_number' => is_string($invoice_number),
                    'total_amount' => is_float($total_amount),
                    'vendor' => is_string($vendor)
                ]
            ]);

            return null;
        }

        return new self($invoice_number, $total_amount, $vendor);
    }
}