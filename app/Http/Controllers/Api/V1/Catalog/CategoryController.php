<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\CategoryResource;
use App\Models\Category;
use App\Support\CategoryTreeCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Public category browsing: flat list or nested tree (?view=tree).
     */
    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        if ((string) $request->query('view') === 'tree') {
            return response()->json([
                'data' => CategoryTreeCache::tree(),
            ]);
        }

        $categories = Category::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Show one active category by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with(['children' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->firstOrFail();

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }
}
