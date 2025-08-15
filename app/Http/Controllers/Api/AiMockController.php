<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Document;
use App\Traits\ApiResponseTrait;
use App\Services\AiDocumentProcessor;
use App\Jobs\ProcessDocument;
use Illuminate\Support\Facades\Log;



class AiMockController extends Controller
{
    use ApiResponseTrait;

    private $TIME_LIMIT_FOR_SYNC_PROCESSING = 2; // seconds

    public function __construct(AiDocumentProcessor $aiDocumentProcessor)
    {
        $this->aiDocumentProcessor = $aiDocumentProcessor;
    }

    // TODO: $documentFile is not type hinted
   private function generateDocumentFileNameFromUploadedFile($documentFile): string
   {
        $originalName = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension    = $documentFile->getClientOriginalExtension();

        return $originalName . '_' . random_int(1000, 9999) . '.' . $extension;
   }


    public function extractBulk(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'documents'   => 'required|array|max:10',
            'documents.*' => 'file|mimes:pdf|max:10240', // 10MB
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation Error',
                $validator->errors(),
                422
            );
        }

        $documentFiles = $request->file('documents');
        $createdDocuments = [];

        foreach ($documentFiles as $documentFile) {
            // Generate unique file name
            $documentFilePath = $this->generateDocumentFileNameFromUploadedFile($documentFile);

            // Store file in storage/app/documents
            $fileName = $documentFile->storeAs('documents', $documentFilePath);

            // Create record in DB and return model instance
            $document = Document::create([
                'file_name' => $fileName,
            ]);

            $createdDocuments[] = $document;

            // Dispatch job to queue
            ProcessDocument::dispatch($document)->onQueue('documents');
        }

        return $this->successResponse(
            'Documents are being processed in the background.',
            $createdDocuments
        );
    }


    public function extract(Request $request)
    {
        // TODO: Ensure use a standard response trait to handle responses to ensure a consistent format
        $validator = Validator::make($request->all(), [
            'document' => 'required|file|mimes:pdf|max:10240', // 10MB
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation Error',
                $validator->errors(),
                422
            );
        }
      

        $documentFile = $request->file('document');
        // first store file on the server and get the path and store as file_name
        $documentFilePath = $this->generateDocumentFileNameFromUploadedFile($documentFile);

        $file_name = $documentFile->storeAs('documents', $documentFilePath);

        // but we need to store the document in the db
        $document = Document::create([
            'file_name' => $file_name,
        ]);
        // store the file in the storage

        // first try to process the file within a certain time frame, if it does work process file and return it else send a response indicating that the document is being processed, so just ProcessDocument will be sync, if time elapses then it will be processed in the background

        // as the extraction of text from the document is an asynchronous process,
        // we will dispatch a job to handle it
        // ProcessDocument::dispatch($document);


        
        $startTime = microtime(true);

        try {

            Log::info('Attempting to process document', ['document_id' => $document->id]);
            // Try to process synchronously
            $result = $this->aiDocumentProcessor->process($document);

            Log::info('Processing result', ['result' => $result]);
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $this->TIME_LIMIT_FOR_SYNC_PROCESSING) {
                Log::info('Time limit elapsed for asynchronous processing',['elapsed_time'=>$elapsed]);
                throw new \RuntimeException('Processing exceeded time limit');
            }
            Log::info('Time taken to process document asynchronously',['elapsed_time'=>$elapsed]);

            return $this->successResponse('Document processed successfully', $result);
        } catch (\Throwable $e) {
            // So at the moment once we have an error we just push to queue for further processing but given further requirements we might need to enhance this, because the current assumption is that if that whether the error is due to elapsed time or some other issue, just process - but some other issue could be was able to process but unable to locate a purchase order in such a case the user might be better off being notified in response that purcahse does not exist for this invoice, however the business could also have purchase order later updated and in the event of a retry it will actually go through
            // Regardless, the logs should provide enough context for debugging, and to aid in identifying the root cause of the issue when the logs are further processed with an observability stack in place probably grafana, prometheues, loki, ....
            Log::info('What is the error encountered', ['error_message'=>$e?->getMessage(), 'error' => $e]);
            // Fallback to async queue processing
            ProcessDocument::dispatch($document)->onQueue('documents');

            return $this->successResponse(
                'Document is being processed in the background',
                $document->fresh()
            );
        }


     
    }
}

