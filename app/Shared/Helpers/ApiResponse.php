<?php

namespace App\Shared\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  array  $meta
     * @param  int  $status
     * @return JsonResponse
     */
    public static function success(mixed $data = null, string $message = 'Success', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'meta' => $meta,
        ], $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param  string  $message
     * @param  array  $errors
     * @param  int  $status
     * @return JsonResponse
     */
    public static function error(string $message = 'Error', array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return a paginated success JSON response.
     *
     * @param  LengthAwarePaginator  $paginator
     * @param  string  $message
     * @param  array  $meta
     * @return JsonResponse
     */
    public static function paginated(LengthAwarePaginator $paginator, string $message = 'Success', array $meta = []): JsonResponse
    {
        $customMeta = array_merge([
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ], $meta);

        return response()->json([
            'data' => $paginator->items(),
            'message' => $message,
            'meta' => $customMeta,
        ]);
    }
}
