<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Document;
use App\Enums\DocumentStatus;
use App\DTO\InvoiceDetails;
use Illuminate\Support\Facades\Log;


class AiDocumentProcessor
{
  
  public function process(Document $document)
  {
  
    $documentPath = $document->file_name;


    $extractedText = $this->extractTextFromDocument($documentPath);


    $invoiceDetails = $this->retrieveInvoiceDetails($extractedText);

    if(!$invoiceDetails) {
        $document->update(['status' => DocumentStatus::FAILED->value]);
        throw new \Exception("Failed to retrieve invoice details");
    }

    $invoice_number = $invoiceDetails->invoice_number;
    if (!$invoice_number) {
        $document->update(['status' => DocumentStatus::FAILED->value]);
        throw new \Exception("Invoice number not found in AI response");
        
    }
    $vendor = $invoiceDetails->vendor;
    $total_amount = $invoiceDetails->total_amount;

    // locate a purchase order that matches the derived vendor, and total_amount
    $purchaseOrder = PurchaseOrder::where('vendor', $vendor)
        ->where('amount', $total_amount)
        ->first();

    Log::info("Matching Purchase Order: ", [$purchaseOrder, $vendor, $total_amount, $documentPath, $document->id]);


    if (!$purchaseOrder) {
        $document->update(['status' => DocumentStatus::FAILED->value]);
        throw new \Exception("Purchase order not found");
    }

    $document->update([
        'invoice_number' => $invoice_number,
        'vendor' => $vendor,
        'total_amount' => $total_amount,
        'po_number' => $purchaseOrder->po_number,
        'status' => DocumentStatus::PROCESSED->value,
    ]);

    return $document->refresh();
  }


    // TODO: Should have try catches and should log errors, like cases of encrypted document should show the error
    private function extractTextFromDocument($documentPath)
    {
        
        // Just a dummy matching text, in actual implementation will have library to extract text
        // return "Invoice Number: INV-5587, Total: 584.00, Vendor: Vendor 404"; //non present purchase order
        return "Invoice Number: INV-5587, Total: 584.00, Vendor: Vendor 5"; //present purchase order
    }

    // TODO: Implement an exponential backoff strategy for retries
    // The ai model is expected to take in a prompt here and return information that matches the schema presented here
    private function retrieveInvoiceDetails($text): ?InvoiceDetails
    {
        $prompt = "
            Extract the invoice details from the following text: $text 

            The invoice details should be returned in the json format specified below:
                {
                    'invoice_number': string,
                    'total_amount': decimal,
                    'vendor': string
                }
        "; // just an example prompt that will be potentially passed to the ai model

        // Just Simulating AI extracting key details using regex ....
        preg_match('/Invoice Number:\s*([^\s,]+)/i', $text, $invoiceMatch);
        preg_match('/Total:\s*([\d\.]+)/i', $text, $totalMatch);
        preg_match('/Vendor:\s*(.+)$/i', $text, $vendorMatch);

        // convert to json just to mimick the expected response from AiModel
        $json = json_encode([
            "invoice_number" => $invoiceMatch[1] ?? null,
            "total_amount"   => $totalMatch[1] ?? null,
            "vendor"         => $vendorMatch[1] ?? null
        ]);

        return InvoiceDetails::fromJson(json_decode($json, true));
    }
}





