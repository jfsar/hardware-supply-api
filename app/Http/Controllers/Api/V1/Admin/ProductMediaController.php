<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Catalog\DeleteProductDocument;
use App\Actions\Catalog\DeleteProductImage;
use App\Actions\Catalog\StoreProductDocument;
use App\Actions\Catalog\StoreProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\UploadProductDocumentRequest;
use App\Http\Requests\Catalog\UploadProductImageRequest;
use App\Http\Resources\Catalog\ProductDocumentResource;
use App\Http\Resources\Catalog\ProductImageResource;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ProductMediaController extends Controller
{
    /**
     * Upload a product image (products.update).
     */
    public function storeImage(UploadProductImageRequest $request, Product $product, StoreProductImage $store): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var UploadedFile $file */
        $file = $request->file('image');

        $image = $store($user, $product, $file, $request->safe()->except(['image']));

        return response()->json([
            'data' => new ProductImageResource($image),
        ], 201);
    }

    /**
     * Remove a product image (products.update).
     */
    public function destroyImage(Request $request, Product $product, ProductImage $image, DeleteProductImage $delete): JsonResponse
    {
        abort_unless((int) $image->product_id === (int) $product->id, 404);

        /** @var User $user */
        $user = $request->user();

        $delete($user, $image);

        return response()->json([
            'data' => ['message' => __('Image removed.')],
        ]);
    }

    /**
     * Upload a product document/manual (products.update).
     */
    public function storeDocument(UploadProductDocumentRequest $request, Product $product, StoreProductDocument $store): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var UploadedFile $file */
        $file = $request->file('document');

        $document = $store($user, $product, $file, [
            'title' => (string) $request->validated('title'),
            'sort_order' => $request->validated('sort_order'),
            'product_variant_id' => $request->validated('product_variant_id'),
        ]);

        return response()->json([
            'data' => new ProductDocumentResource($document),
        ], 201);
    }

    /**
     * Remove a product document (products.update).
     */
    public function destroyDocument(Request $request, Product $product, ProductDocument $document, DeleteProductDocument $delete): JsonResponse
    {
        abort_unless((int) $document->product_id === (int) $product->id, 404);

        /** @var User $user */
        $user = $request->user();

        $delete($user, $document);

        return response()->json([
            'data' => ['message' => __('Document removed.')],
        ]);
    }
}
