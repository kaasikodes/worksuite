<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Enums\DocumentStatus;
use Tests\TestCase;


class AiDocumentProcessingFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createUnMatchingPurchaseOrders()
    {
        $orders = [
            ['po_number' => 'PO-1001', 'vendor' => 'Vendor 100', 'amount' => 1200.00],
            ['po_number' => 'PO-1002', 'vendor' => 'Vendor 321', 'amount' => 25000.00],
        ];

        foreach ($orders as $order) {
            PurchaseOrder::factory()->create($order);
        }
    }
    private function createMatchingPurchaseOrders()
    {
        $orders = [
            ['po_number' => 'PO-1001', 'vendor' => 'Vendor 1', 'amount' => 500.00],
            ['po_number' => 'PO-1002', 'vendor' => 'Vendor 3', 'amount' => 250.00],
            ['po_number' => 'PO-12345', 'vendor' => 'Vendor 5', 'amount' => 584.00],
        ];

        foreach ($orders as $order) {
            PurchaseOrder::factory()->create($order);
        }
    }




    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_only_upload_pdf_documents(): void
    {
        Storage::fake('local');
        Queue::fake();

        $response = $this->postJson('/api/mock-ai-extract', [
            'document' => UploadedFile::fake()->create('file.txt', 10, 'text/plain'),
        ]);

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_only_upload_pdf_documents_that_are_less_than_or_equal_to_10mb(): void
    {
        Storage::fake('local');
        Queue::fake();

        // Larger than 10MB
        $largePdf = UploadedFile::fake()->create('invoice.pdf', 11000, 'application/pdf');

        $response = $this->postJson('/api/mock-ai-extract', [
            'document' => $largePdf,
        ]);

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_extracts_invoice_information_from_document_within_sychronous_time_limit(): void
    {
        Storage::fake('local');
        

        // Matching PO so processing succeeds
        $this->createMatchingPurchaseOrders();

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/mock-ai-extract', [
            'document' => $pdf,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => DocumentStatus::PROCESSED->value]);

        $this->assertDatabaseHas('documents', [
            'vendor' => 'Vendor 5',
            'po_number' => 'PO-12345',
            'status' => DocumentStatus::PROCESSED->value,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_has_document_processed_in_background_when_synchronous_time_limit_exceeded(): void
    {
        Storage::fake('local');
        Queue::fake();

        // Temporarily override processor to simulate delay
        $this->mock(\App\Services\AiDocumentProcessor::class, function ($mock) {
            $mock->shouldReceive('process')->andReturnUsing(function ($doc) {
                sleep(3); // exceeds time limit
                return $doc;
            });
        });

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/mock-ai-extract', [
            'document' => $pdf,
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'pending']);

        Queue::assertPushed(ProcessDocument::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_has_documents_processed_in_background_when_multiple_documents_are_uploaded(): void
    {
        Storage::fake('local');
        Queue::fake();

        $pdf1 = UploadedFile::fake()->create('invoice1.pdf', 100, 'application/pdf');
        $pdf2 = UploadedFile::fake()->create('invoice2.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/mock-ai-extract/bulk', [
            'documents' => [$pdf1, $pdf2],
        ]);

        $response->assertStatus(200);
        Queue::assertPushed(ProcessDocument::class, 2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function document_status_is_updated_to_processed_after_the_corresponding_purchase_order_is_matched(): void
    {
        $this->createMatchingPurchaseOrders();

        $doc = Document::factory()->create([
            'file_name' => 'documents/test.pdf',
            'status' => DocumentStatus::PENDING->value
        ]);

        $processor = app(\App\Services\AiDocumentProcessor::class);
        $processor->process($doc);

        $this->assertDatabaseHas('documents', [
            'id' => $doc->id,
            'status' => DocumentStatus::PROCESSED->value,
            'po_number' => 'PO-12345'
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function document_status_is_updated_to_failed_after_the_corresponding_purchase_order_is_unmatched(): void
    {
        $this->createUnMatchingPurchaseOrders();
        $doc = Document::factory()->create([
            'file_name' => 'documents/unmatched.pdf',
            'status'    => DocumentStatus::PENDING->value
        ]);

        $processor = app(\App\Services\AiDocumentProcessor::class);

        $this->expectException(\Exception::class);
        $processor->process($doc);

        $this->assertDatabaseHas('documents', [
            'id' => $doc->id,
            'status' => DocumentStatus::FAILED->value
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function document_details_are_updated_when_purchase_order_is_matched(): void
    {
        $this->createMatchingPurchaseOrders();


        $doc = Document::factory()->create([
            'file_name' => 'documents/test.pdf',
            'status' => DocumentStatus::PENDING->value
        ]);

        $processor = app(\App\Services\AiDocumentProcessor::class);
        $processor->process($doc);

        $this->assertDatabaseHas('documents', [
            'id' => $doc->id,
            'invoice_number' => 'INV-5587',
            'vendor' => 'Vendor 5',
            'total_amount' => '584.00',
            'po_number' => 'PO-12345'
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
