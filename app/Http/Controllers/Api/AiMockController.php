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
use App\Enums\DocumentStatus;




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
        
        $validator = Validator::make($request->all(), [
            'documents'   => 'required|array|max:10',
            'documents.*' => 'file|mimes:pdf|max:10240', 
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
        //Send documents to queue for background processing
        foreach ($documentFiles as $documentFile) {
            $documentFilePath = $this->generateDocumentFileNameFromUploadedFile($documentFile);

            $fileName = $documentFile->storeAs('documents', $documentFilePath);

           
            $document = Document::create([
                'file_name' => $fileName,
                'status' => DocumentStatus::PENDING->value

            ]);

            $createdDocuments[] = $document;

            // Dispatch to queue for background processing
            ProcessDocument::dispatch($document)->onQueue('documents');
        }

        return $this->successResponse(
            'Documents are being processed in the background.',
            $createdDocuments
        );
    }


    public function extract(Request $request)
    {
       
        $validator = Validator::make($request->all(), [
            'document' => [
                'required',
                'file',
                'mimes:pdf',
                'max:10240',
                function ($attribute, $value, $fail) use ($request) {
                   
                    if (is_array($request->file($attribute))) {
                        $fail('Only one file is allowed.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation Error',
                $validator->errors(),
                422
            );
        }
      

        $documentFile = $request->file('document');
        
        $documentFilePath = $this->generateDocumentFileNameFromUploadedFile($documentFile);

        //let file name be the full path on server, and cast the actual name to formatted file name in model
        $file_name = $documentFile->storeAs('documents', $documentFilePath);

        
        $document = Document::create([
            'file_name' => $file_name,
            'status' => DocumentStatus::PENDING->value
        ]);
      

      


        // First try to process file synchronously and if the time limit allowed is exceeded then throw an exception, for time being, regardless of the error that occurs in synchronous processing the document will be pushed to queue for document processing
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
            Log::info('Time taken to process document synchronously', ['elapsed_time'=>$elapsed] );

            return $this->successResponse('Document processed successfully', $result);
        } catch (\Throwable $e) {
            // Then process in background if error is encountered
            // So at the moment once we have an error we just push to queue for further processing but given further requirements we might need to enhance this, because the current assumption is that if that whether the error is due to elapsed time or some other issue, just process - but some other issue could be - `was able to process but unable to locate a purchase order` in such a case the user might be better off being notified in response that purchase order does not exist for this invoice, however the business could also have purchase order later updated and in the event of a retry it will actually go through
            // Regardless, the logs should provide enough context for debugging, and to aid in identifying the root cause of the issue when the logs are further processed with an observability stack in place probably grafana, prometheues, loki, ....
            Log::info('Error encountered while processing document synchronously', ['error_message'=>$e?->getMessage(), 'error' => $e]);

            // Fallback to async processing via background job
            ProcessDocument::dispatch($document)->onQueue('documents');

            return $this->successResponse(
                'Document is being processed in the background',
                $document->refresh() //just to enable user see that other fields are still not available
            );
        }


     
    }
}

