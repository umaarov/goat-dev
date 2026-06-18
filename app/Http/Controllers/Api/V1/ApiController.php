<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base controller for every /api/v1 endpoint.
 *
 * Provides a single, consistent success envelope:
 *   { "success": true, "data": ..., "meta": ... }
 *
 * Errors are produced centrally by the global exception handler in
 * bootstrap/app.php as: { "success": false, "error_code": ..., "message": ... }
 */
abstract class ApiController extends Controller
{
    /**
     * Standard success response.
     */
    protected function ok(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = ['success' => true];

        if ($data instanceof JsonResource) {
            // Let the resource render itself but keep our envelope.
            $payload['data'] = $data->resolve();
        } else {
            $payload['data'] = $data;
        }

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * 201 Created.
     */
    protected function created(mixed $data = null, array $meta = []): JsonResponse
    {
        return $this->ok($data, 201, $meta);
    }

    /**
     * A simple message-only response.
     */
    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $status);
    }

    /**
     * An explicit error response (used for domain errors that are not exceptions,
     * e.g. "already voted"). Matches the global handler's error shape.
     */
    protected function error(string $message, int $status = 400, string $errorCode = 'error', array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ], $extra), $status);
    }

    /**
     * Wrap a paginator in our envelope, transforming items through the given
     * resource class and exposing pagination details under "meta".
     *
     * @param  class-string<JsonResource>|null  $resourceClass
     */
    protected function paginated(LengthAwarePaginator $paginator, ?string $resourceClass = null): JsonResponse
    {
        $collection = $paginator->getCollection();

        $data = $resourceClass
            ? $resourceClass::collection($collection)->resolve()
            : $collection;

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Wrap an already-built resource collection (no pagination).
     */
    protected function collection(AnonymousResourceCollection $collection): JsonResponse
    {
        return $this->ok($collection->resolve());
    }
}
