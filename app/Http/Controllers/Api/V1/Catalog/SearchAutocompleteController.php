<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contracts\ProductSearch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAutocompleteController extends Controller
{
    public function __construct(protected ProductSearch $search) {}

    /**
     * Typeahead suggestions for the search box (FR-SRCH-005).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        return response()->json([
            'data' => collect($this->search->autocomplete(
                trim((string) $validated['q']),
                (int) ($validated['limit'] ?? 10),
            ))->values()->all(),
        ]);
    }
}
