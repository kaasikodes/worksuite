<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Services\AiDocumentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\DocumentStatus;


class AiDocumentProcessorTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_a_document_and_updates_it_with_invoice_and_po_details()
    {
        // Arrange: create matching PurchaseOrder
        $po = PurchaseOrder::factory()->create([
            'vendor' => 'Vendor 5',
            'amount' => '584.00',
            'po_number' => 'PO-1234'
        ]);

        // Create document (simulate uploaded file name)
        $document = Document::factory()->create([
            'file_name' => 'documents/sample.pdf',
            'status' => DocumentStatus::PENDING->value
        ]);

        $processor = new AiDocumentProcessor();

        // Act
        $processedDoc = $processor->process($document);

        // Assert
        $this->assertEquals('INV-5587', $processedDoc->invoice_number);
        $this->assertEquals('Vendor 5', $processedDoc->vendor);
        $this->assertEquals('584.00', $processedDoc->total_amount);
        $this->assertEquals('PO-1234', $processedDoc->po_number);
        $this->assertEquals('processed', $processedDoc->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fails_processing_if_purchase_order_is_not_found()
    {
        // Arrange: No matching PO in DB
        $document = Document::factory()->create([
            'file_name' => 'documents/sample.pdf',
            'status' => DocumentStatus::PENDING->value
        ]);

        $processor = new AiDocumentProcessor();

        // Expect exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Purchase order not found");

        // Act
        $processor->process($document);

        // Assert: status should be updated to failed
        $this->assertEquals('failed', $document->fresh()->status);
    }
}
