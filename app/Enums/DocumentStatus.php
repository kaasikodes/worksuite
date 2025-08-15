<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case ABANDONED = 'abandoned';
}
