<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all()->map(function ($product) {
            $images = json_decode($product->images, true) ?? [];
            
            if (count($images) > 0) {
                $product->image_urls = $images;
            } else {
                $product->image_urls = ['https://images.unsplash.com/photo-1764588037085-a78240016f8b?q=80&w=987&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'];
            }

            return $product;
        });

        return response()->json($products);
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        $images = json_decode($product->images, true) ?? [];
        
        if (count($images) > 0) {
            $product->image_urls = $images;
        } else {
            $product->image_urls = ['https://images.unsplash.com/photo-1764617988939-034265354ad6?q=80&w=987&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'];
        }

        return response()->json($product);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'sku' => 'required|string|max:100|unique:products,sku',
                'name' => 'required|string|max:255',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'stock_quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'sub_category' => 'nullable|string|max:255',
                'sizes' => 'nullable|array',
                'sizes.*' => 'string|max:10',
                'bestseller' => 'nullable|boolean',
                'date' => 'nullable|date',
            ]);

            // Upload images to Cloudinary
            if ($request->hasFile('images')) {
                $imageUrls = [];
                foreach ($request->file('images') as $image) {
                    $result = Cloudinary::upload($image->getRealPath(), [
                        'folder' => 'products',
                        'transformation' => [
                            'quality' => 'auto',
                            'fetch_format' => 'auto',
                        ]
                    ]);
                    $imageUrls[] = $result->getSecurePath();
                }
                $validatedData['images'] = json_encode($imageUrls);
            }

            if (isset($validatedData['sizes'])) {
                $validatedData['sizes'] = json_encode($validatedData['sizes']);
            }

            $product = Product::create($validatedData);
            $product->image_urls = json_decode($product->images) ?? [];

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validatedData = $request->validate([
                'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $id,
                'name' => 'sometimes|required|string|max:255',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'replace_images' => 'nullable|boolean',
                'description' => 'nullable|string',
                'category_id' => 'sometimes|required|exists:categories,id',
                'supplier_id' => 'sometimes|required|exists:suppliers,id',
                'stock_quantity' => 'sometimes|required|integer|min:0',
                'price' => 'sometimes|required|numeric|min:0',
                'sub_category' => 'nullable|string|max:255',
                'sizes' => 'nullable|array',
                'sizes.*' => 'string|max:10',
                'bestseller' => 'nullable|boolean',
                'date' => 'nullable|date',
            ]);

            if (isset($validatedData['sizes'])) {
                $validatedData['sizes'] = json_encode($validatedData['sizes']);
            }

            // Delete old images from Cloudinary if replacing
            if ($request->boolean('replace_images') && $product->images) {
                $oldImages = json_decode($product->images, true) ?? [];
                foreach ($oldImages as $imageUrl) {
                    $this->deleteFromCloudinary($imageUrl);
                }
                $validatedData['images'] = null;
            }

            // Upload new images to Cloudinary
            if ($request->hasFile('images')) {
                $newImages = [];
                foreach ($request->file('images') as $image) {
                    $result = Cloudinary::upload($image->getRealPath(), [
                        'folder' => 'products',
                        'transformation' => [
                            'quality' => 'auto',
                            'fetch_format' => 'auto',
                        ]
                    ]);
                    $newImages[] = $result->getSecurePath();
                }

                // Merge with existing images if not replacing
                if (!$request->boolean('replace_images') && $product->images) {
                    $existing = json_decode($product->images, true) ?? [];
                    $newImages = array_merge($existing, $newImages);
                }

                $validatedData['images'] = json_encode($newImages);
            }

            $product->update($validatedData);
            $product->image_urls = json_decode($product->images) ?? [];

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Delete images from Cloudinary
            if ($product->images) {
                $images = json_decode($product->images, true) ?? [];
                foreach ($images as $imageUrl) {
                    $this->deleteFromCloudinary($imageUrl);
                }
            }

            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function lowStock()
    {
        $threshold = env('LOW_STOCK_THRESHOLD', 10);
        
        $products = Product::where('stock_quantity', '<', $threshold)
            ->get()
            ->map(function ($product) {
                $images = json_decode($product->images, true) ?? [];
                $product->image_urls = count($images) > 0 ? $images : ['https://via.placeholder.com/300x300?text=No+Image'];
                return $product;
            });

        return response()->json([
            'message' => 'Products with low stock',
            'threshold' => $threshold,
            'count' => $products->count(),
            'products' => $products
        ], Response::HTTP_OK);
    }

    /**
     * Delete image from Cloudinary
     */
    private function deleteFromCloudinary($imageUrl)
    {
        if (!$imageUrl) return;

        try {
            // Extract public_id from Cloudinary URL
            // URL format: https://res.cloudinary.com/cloud_name/image/upload/v1234567890/products/filename.jpg
            if (preg_match('/\/v\d+\/(.+)\.\w+$/', $imageUrl, $matches)) {
                $publicId = $matches[1];
                Cloudinary::destroy($publicId);
            }
        } catch (\Exception $e) {
            // Log error but don't stop the process
            \Log::warning('Failed to delete image from Cloudinary: ' . $e->getMessage());
        }
    }
}