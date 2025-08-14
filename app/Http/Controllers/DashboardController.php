<?php

namespace App\Http\Controllers;

use App\Models\Document;

class DashboardController extends Controller
{
    public function index()
    {
        $documents = Document::latest()->get();
        return view('dashboard', compact('documents'));
    }
}
