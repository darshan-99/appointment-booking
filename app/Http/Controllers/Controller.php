<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Return a standardised success response.
     *
     * @param  mixed       $data
     * @param  string      $message
     * @param  int         $status   HTTP status code (default 200)
     */
    protected function successResponse(mixed $data, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ], $status);
    }

    /**
     * Return a standardised error response.
     *
     * @param  string      $message
     * @param  mixed       $errors   Optional structured validation / domain errors
     * @param  int         $status   HTTP status code (default 422)
     */
    protected function errorResponse(string $message, mixed $errors = null, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $status);
    }
}
