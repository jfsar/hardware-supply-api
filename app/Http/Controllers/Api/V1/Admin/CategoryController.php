<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Catalog\CreateCategory;
use App\Actions\Catalog\DeleteCategory;
use App\Actions\Catalog\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\Catalog\CategoryResource;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Paginated staff category index (categories.manage).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->with('parent')
            ->when($request->filled('parent_id'), fn (Builder $query) => $query->where('parent_id', (int) $request->input('parent_id')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(min((int) $request->input('per_page', 50), 100));

        return CategoryResource::collection($categories);
    }

    /**
     * Store a new category (categories.manage).
     */
    public function store(StoreCategoryRequest $request, CreateCategory $create): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => new CategoryResource($create($user, $request->validated())),
        ], 201);
    }

    /**
     * Show a single category, children included.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['children' => fn (Builder $query) => $query->orderBy('sort_order')->orderBy('name')]);

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Apply a partial update (categories.manage).
     */
    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $update): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => new CategoryResource($update($user, $category, $request->validated())),
        ]);
    }

    /**
     * Soft-delete the category when unused (categories.manage).
     */
    public function destroy(Request $request, Category $category, DeleteCategory $delete): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $delete($user, $category);

        return response()->json([
            'data' => ['message' => __('Category removed.')],
        ]);
    }
}
