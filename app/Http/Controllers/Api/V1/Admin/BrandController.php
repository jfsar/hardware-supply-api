<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Catalog\CreateBrand;
use App\Actions\Catalog\DeleteBrand;
use App\Actions\Catalog\UpdateBrand;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Requests\Catalog\UpdateBrandRequest;
use App\Http\Resources\Catalog\BrandResource;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    /**
     * Paginated staff brand index (brands.manage).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $brands = Brand::query()
            ->orderBy('name')
            ->paginate(min((int) $request->input('per_page', 50), 100));

        return BrandResource::collection($brands);
    }

    /**
     * Store a new brand (brands.manage).
     */
    public function store(StoreBrandRequest $request, CreateBrand $create): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => new BrandResource($create($user, $request->validated())),
        ], 201);
    }

    /**
     * Show a single brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'data' => new BrandResource($brand),
        ]);
    }

    /**
     * Apply a partial update (brands.manage).
     */
    public function update(UpdateBrandRequest $request, Brand $brand, UpdateBrand $update): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => new BrandResource($update($user, $brand, $request->validated())),
        ]);
    }

    /**
     * Soft-delete the brand (brands.manage).
     */
    public function destroy(Request $request, Brand $brand, DeleteBrand $delete): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $delete($user, $brand);

        return response()->json([
            'data' => ['message' => __('Brand removed.')],
        ]);
    }
}
