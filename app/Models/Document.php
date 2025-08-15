<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;
    protected $fillable = ['file_name', 'invoice_number', 'vendor', 'total_amount', 'po_number', 'status'];


    public function getFormattedFileNameAttribute()
    {
         $name = $this->file_name;

        // 1. Remove 'documents/' prefix
        $name = preg_replace('/^documents\//', '', $name);

        // 2. Remove _<digits> before the file extension
        $name = preg_replace('/_\d+(?=\.[^.]+$)/', '', $name);

        return $name;
    }
}
