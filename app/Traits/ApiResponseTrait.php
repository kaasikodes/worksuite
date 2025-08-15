<?php

namespace App\Traits;

trait ApiResponseTrait
{
    protected function successResponse(string $message, $data = null, int $code = 200)
    {
        return response()->json([
            'message' => $message,
            'data'    => $data
        ], $code);
    }

    protected function errorResponse(string $message, $errors = null, int $code = 400)
    {
        return response()->json([
            'message' => $message,
            'errors'  => $errors
        ], $code);
    }
}
