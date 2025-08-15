<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AiDocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Enums\DocumentStatus;



class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; //try at least 3 times before marking as abandoned, sending to failed jobs
    public $maxExceptions = 3;

    protected $documentId;

    public function __construct(Document $document)
    {
        $this->documentId = $document->id;
    }

    public function backoff()
    {
        return [5, 60, 120];  //exponential backoff of 5s, 60s, 120s
    }

    public function handle(AiDocumentProcessor $processor)
    {
       

        DB::transaction(function () use ($processor) {
            // Lock the document to prevent other worker servers from processing it, in the event of multiple servers been used ....
            $document = Document::where('id', $this->documentId)->lockForUpdate()->first();
            if (!$document) {
                return;
            }
            // ensure that abandoned and processed documents are not further processed in the event of a retry
            if (in_array($document->status, [DocumentStatus::ABANDONED->value, DocumentStatus::PROCESSED->value])) {
                Log::info('Document already processed or abandoned, skipping', [
                    'document_id' => $this->documentId,
                    'status' => $document->status,
                ]);
                return;
            }
            // process the document
            $document = $processor->process($document);



            
        });
    }

    public function failed(\Throwable $exception)
    {
        // Log the failed document processing, including the error message. In another case will probably notify the user who initiated the document processing, might also consider batching jobs to a user so user is notified when a job fails when the batch is done entirely as opposed to spamming the user with individual failure notifications.
        Log::error('Document processing failed', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);

        DB::transaction(function () {
            $document = Document::where('id', $this->documentId)
                ->lockForUpdate()
                ->first();

            $document->update(['status' => DocumentStatus::ABANDONED->value]);
        });
    }
}
