<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiDocumentProcessingTest extends TestCase
{
    use RefreshDatabase;

    
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

    
    public function user_extracts_invoice_information_from_document_within_sychronous_time_limit(): void
    {
        Storage::fake('local');

        // Ensure matching PO exists so processing succeeds
        $po = PurchaseOrder::factory()->create([
            'vendor' => 'Vendor 5',
            'amount' => '584.00',
            'po_number' => 'PO-12345'
        ]);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/mock-ai-extract', [
            'document' => $pdf,
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'processed']);

        $this->assertDatabaseHas('documents', [
            'vendor' => 'Vendor 5',
            'po_number' => 'PO-12345',
            'status' => 'processed',
        ]);
    }

    
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

    
    public function document_status_is_updated_to_processed_after_the_corresponding_purchase_order_is_matched(): void
    {
        $po = PurchaseOrder::factory()->create([
            'vendor' => 'Vendor 5',
            'amount' => '584.00',
            'po_number' => 'PO-12345'
        ]);

        $doc = Document::factory()->create([
            'file_name' => 'documents/test.pdf',
            'status' => 'pending'
        ]);

        $processor = app(\App\Services\AiDocumentProcessor::class);
        $processor->process($doc);

        $this->assertDatabaseHas('documents', [
            'id' => $doc->id,
            'status' => 'processed',
            'po_number' => 'PO-12345'
        ]);
    }

    
    public function document_status_is_updated_to_failed_after_the_corresponding_purchase_order_is_unmatched(): void
    {
        $doc = Document::factory()->create([
            'file_name' => 'documents/test.pdf',
            'status' => 'pending'
        ]);

        $processor = app(\App\Services\AiDocumentProcessor::class);

        $this->expectException(\Exception::class);
        $processor->process($doc);

        $this->assertDatabaseHas('documents', [
            'id' => $doc->id,
            'status' => 'failed'
        ]);
    }

    
    public function document_details_are_updated_when_purchase_order_is_matched(): void
    {
        $po = PurchaseOrder::factory()->create([
            'vendor' => 'Vendor 5',
            'amount' => '584.00',
            'po_number' => 'PO-12345'
        ]);

        $doc = Document::factory()->create([
            'file_name' => 'documents/test.pdf',
            'status' => 'pending'
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

    
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
