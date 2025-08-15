<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Document;

class AiDocumentProcessor
{
  // should return an associative array 
  // [
  //   "invoice_number": "INV-5587",
  //   "total_amount": "584.00",
  //   "vendor": "Vendor 5"
  // ]
  public function process(Document $document)
  {
    // Implement send file to mock AI, match with mock PO
    $documentPath = $document->file_name;

    // Extract text from the document

    // and send it to the mock AI for processing

    // Step 1: Extract text from the document
    $extractedText = $this->extractTextFromDocument($documentPath);

    // Step 2: Send to mock AI for processing (simulate AI response)
    $aiResponse = $this->retrieveInvoiceDetails($extractedText);

    $document = $document->fresh();
    $invoice_number = $aiResponse['invoice_number'] ?? null;
    if (!$invoice_number) {
        $document->update(['status' => 'failed']);
        throw new \Exception("Invoice number not found in AI response");
        
    }
    $vendor = $aiResponse['vendor'] ?? null;
    $total_amount = $aiResponse['total_amount'] ?? null;

    // locate a purchase order that matches the derived vendor, total_amount
    $purchaseOrder = PurchaseOrder::where('vendor', $vendor)
        ->where('amount', $total_amount)
        ->first();


    if (!$purchaseOrder) {
        $document->update(['status' => 'failed']);
        throw new \Exception("Purchase order not found");
    }

    $document->update([
        'invoice_number' => $invoice_number,
        'vendor' => $vendor,
        'total_amount' => $total_amount,
        'po_number' => $purchaseOrder->po_number,
        'status' => 'processed',
    ]);

    return $document->refresh();
  }


  // TODO: Should have try catches and should log errors, like cases of encrycpted document should show the error
   private function extractTextFromDocument($documentPath)
   {
        // Simulate text extraction, in  a real implementation will probably use a library to extract text from the document
        // this can also be a direct call to the ai model, but its cheaper to extract the text and send to the ai model(as opposed to the document been fed to the model directly and having it extract the text and then get the relevant information)
        return "Invoice Number: INV-5587, Total: 584.00, Vendor: Vendor 5";
    }

    // TODO: Implement an exponential backoff strategy for retries
    private function retrieveInvoiceDetails($text)
    {
        // Simulate AI extracting key details using regex
        preg_match('/Invoice Number:\s*([^\s,]+)/i', $text, $invoiceMatch);
        preg_match('/Total:\s*([\d\.]+)/i', $text, $totalMatch);
        preg_match('/Vendor:\s*(.+)$/i', $text, $vendorMatch);

        return [
            "invoice_number" => $invoiceMatch[1] ?? null,
            "total_amount"   => $totalMatch[1] ?? null,
            "vendor"         => $vendorMatch[1] ?? null
        ];
    }
}
