<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AiDocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function handle(AiDocumentProcessor $processor)
    {
        $document = $processor->process($this->document);

        if (!$document) {
            return;
        }

        
    }
}
