<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductController extends Controller
{
    /**
     * Display a listing of products with pagination.
     */
    public function index(Request $request)
    {
        try {
            $query = Product::query();
            
            // Apply filters if provided
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            
            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            // Order by
            $orderBy = $request->get('order_by', 'created_at');
            $orderDirection = $request->get('order_direction', 'desc');
            $query->orderBy($orderBy, $orderDirection);
            
            // Paginate
            $perPage = $request->get('per_page', 20);
            $products = $query->paginate($perPage);
            
            // Transform products with images
            $products->getCollection()->transform(function ($product) {
                return $this->transformProduct($product);
            });
            
            return response()->json([
                'message' => 'Products retrieved successfully',
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified product.
     */
    public function show($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product = $this->transformProduct($product);
            
            return response()->json([
                'message' => 'Product retrieved successfully',
                'product' => $product
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            Log::error('Error fetching product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving product',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        try {
            // Validation basique pour votre structure de table
            $validator = Validator::make($request->all(), [
                'sku' => 'required|string|max:100|unique:products,sku',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'stock_quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0|max:999999.99',
                'images' => 'nullable|array|max:10', // Gardé pour la compatibilité avec le frontend
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validatedData = $validator->validated();
            
            // Gérer les champs JSON si envoyés (pour compatibilité avec le frontend)
            $jsonFields = ['sizes', 'colors', 'tags'];
            foreach ($jsonFields as $field) {
                if ($request->has($field)) {
                    if (is_array($request->$field)) {
                        // Stocker dans la colonne 'image' comme JSON (utilisation temporaire)
                        // Note: Ce n'est pas idéal, mais fonctionne avec votre structure actuelle
                        $additionalData = json_decode($validatedData['description'] ?? '{}', true);
                        $additionalData[$field] = $request->$field;
                        $validatedData['description'] = json_encode($additionalData);
                    }
                }
            }

            // Upload images to Cloudinary - stockage dans la colonne 'image' comme JSON
            if ($request->hasFile('images')) {
                $imageUrls = [];
                foreach ($request->file('images') as $image) {
                    $result = Cloudinary::upload($image->getRealPath(), [
                        'folder' => 'products/' . date('Y/m'),
                        'transformation' => [
                            'quality' => 'auto',
                            'fetch_format' => 'auto',
                            'width' => 1200,
                            'height' => 1200,
                            'crop' => 'limit'
                        ]
                    ]);
                    $imageUrls[] = $result->getSecurePath();
                }
                // Stocker comme JSON dans la colonne 'image'
                $validatedData['image'] = json_encode($imageUrls);
            } else {
                // Image par défaut
                $validatedData['image'] = json_encode([
                    'https://images.unsplash.com/photo-1764588037085-a78240016f8b?q=80&w=987&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
                ]);
            }

            $product = Product::create($validatedData);
            $product = $this->transformProduct($product);

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $id,
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'sometimes|required|exists:categories,id',
                'supplier_id' => 'sometimes|required|exists:suppliers,id',
                'stock_quantity' => 'sometimes|required|integer|min:0',
                'price' => 'sometimes|required|numeric|min:0|max:999999.99',
                'images' => 'nullable|array|max:10',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
                'delete_images' => 'nullable|array',
                'delete_images.*' => 'string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validatedData = $validator->validated();
            
            // Gérer les champs JSON si envoyés
            $jsonFields = ['sizes', 'colors', 'tags', 'bestseller', 'sub_category', 'date'];
            foreach ($jsonFields as $field) {
                if ($request->has($field)) {
                    // Stocker dans la description comme JSON
                    $currentDescription = json_decode($product->description ?? '{}', true);
                    if (is_array($request->$field)) {
                        $currentDescription[$field] = $request->$field;
                    } else {
                        $currentDescription[$field] = $request->$field;
                    }
                    $validatedData['description'] = json_encode($currentDescription);
                }
            }

            // Handle image management
            $currentImages = json_decode($product->image, true) ?? [];
            
            if (!is_array($currentImages)) {
                $currentImages = [$currentImages];
            }
            
            // Handle image deletions
            if (isset($validatedData['delete_images']) && is_array($validatedData['delete_images'])) {
                foreach ($validatedData['delete_images'] as $imageToDelete) {
                    $this->deleteFromCloudinary($imageToDelete);
                    // Remove from current images array
                    $currentImages = array_filter($currentImages, function($image) use ($imageToDelete) {
                        return $image !== $imageToDelete;
                    });
                }
                unset($validatedData['delete_images']);
            }

            // Upload new images to Cloudinary
            $newImages = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $result = Cloudinary::upload($image->getRealPath(), [
                        'folder' => 'products/' . date('Y/m'),
                        'transformation' => [
                            'quality' => 'auto',
                            'fetch_format' => 'auto',
                            'width' => 1200,
                            'height' => 1200,
                            'crop' => 'limit'
                        ]
                    ]);
                    $newImages[] = $result->getSecurePath();
                }
            }

            // Merge existing and new images
            $allImages = array_merge(array_values($currentImages), $newImages);
            if (!empty($allImages)) {
                $validatedData['image'] = json_encode($allImages);
            }

            $product->update($validatedData);
            $product = $this->transformProduct($product->fresh());

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Delete images from Cloudinary
            if ($product->image) {
                $images = json_decode($product->image, true) ?? [];
                if (!is_array($images)) {
                    $images = [$images];
                }
                foreach ($images as $image) {
                    if (filter_var($image, FILTER_VALIDATE_URL)) {
                        $this->deleteFromCloudinary($image);
                    }
                }
            }

            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully'
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get low stock products.
     */
    public function lowStock(Request $request)
    {
        try {
            $threshold = env('LOW_STOCK_THRESHOLD', 10);
            
            $query = Product::where('stock_quantity', '<', $threshold);
            
            // Apply search if provided
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }
            
            $products = $query->orderBy('stock_quantity', 'asc')
                ->paginate($request->get('per_page', 20));
            
            // Transform products
            $products->getCollection()->transform(function ($product) {
                return $this->transformProduct($product);
            });
            
            return response()->json([
                'message' => 'Products with low stock retrieved successfully',
                'threshold' => $threshold,
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Error fetching low stock products: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving low stock products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search products.
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2|max:255',
                'category_id' => 'nullable|exists:categories,id',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $query = Product::query();
            $searchQuery = $validator->validated()['query'];
            
            $query->where(function($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%")
                  ->orWhere('sku', 'like', "%{$searchQuery}%")
                  ->orWhere('description', 'like', "%{$searchQuery}%");
            });
            
            if (isset($validator->validated()['category_id'])) {
                $query->where('category_id', $validator->validated()['category_id']);
            }
            
            $limit = $validator->validated()['limit'] ?? 20;
            $products = $query->limit($limit)->get();
            
            // Transform products
            $products->transform(function ($product) {
                return $this->transformProduct($product);
            });
            
            return response()->json([
                'message' => 'Products found successfully',
                'data' => $products,
                'count' => $products->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error searching products: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error searching products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get product statistics.
     */
    public function statistics()
    {
        try {
            $totalProducts = Product::count();
            $outOfStock = Product::where('stock_quantity', 0)->count();
            $lowStock = Product::where('stock_quantity', '<', env('LOW_STOCK_THRESHOLD', 10))
                ->where('stock_quantity', '>', 0)
                ->count();
            
            // Get total inventory value
            $inventoryValue = Product::sum(\DB::raw('stock_quantity * price'));
            
            return response()->json([
                'message' => 'Product statistics retrieved successfully',
                'statistics' => [
                    'total_products' => $totalProducts,
                    'out_of_stock' => $outOfStock,
                    'low_stock' => $lowStock,
                    'inventory_value' => (float) $inventoryValue,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting product statistics: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error retrieving product statistics',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Transform product with images and additional data from description field.
     */
    private function transformProduct(Product $product)
    {
        // Parse images from the 'image' column
        $images = json_decode($product->image, true) ?? [];
        
        if (empty($images) || !is_array($images)) {
            $images = [$product->image ?: 'https://images.unsplash.com/photo-1764588037085-a78240016f8b?q=80&w=987&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'];
        }
        
        // Parse additional data from description field
        $additionalData = [];
        $description = $product->description;
        
        if ($description) {
            // Try to decode as JSON first
            $decoded = json_decode($description, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $additionalData = $decoded;
                $description = $additionalData['text'] ?? $description;
                unset($additionalData['text']);
            }
        }
        
        // Extract common fields from additional data
        $sizes = $additionalData['sizes'] ?? [];
        $colors = $additionalData['colors'] ?? [];
        $tags = $additionalData['tags'] ?? [];
        $bestseller = $additionalData['bestseller'] ?? false;
        $sub_category = $additionalData['sub_category'] ?? null;
        $date = $additionalData['date'] ?? null;
        
        // Add computed fields
        $product->image_urls = $images;
        $product->primary_image = $images[0] ?? null;
        $product->sizes = $sizes;
        $product->colors = $colors;
        $product->tags = $tags;
        $product->bestseller = (bool) $bestseller;
        $product->sub_category = $sub_category;
        $product->date = $date;
        $product->in_stock = $product->stock_quantity > 0;
        $product->stock_status = $product->stock_quantity == 0 ? 'out_of_stock' : 
                                ($product->stock_quantity < env('LOW_STOCK_THRESHOLD', 10) ? 'low_stock' : 'in_stock');
        
        // Keep original description text
        $product->description = $description;
        
        return $product;
    }

    /**
     * Delete image from Cloudinary.
     */
    private function deleteFromCloudinary($imageUrl)
    {
        if (!$imageUrl) return;

        try {
            // Extract public_id from Cloudinary URL
            if (preg_match('/\/v\d+\/(.+)\.\w+$/', $imageUrl, $matches)) {
                $publicId = $matches[1];
                Cloudinary::destroy($publicId);
                Log::info('Deleted image from Cloudinary: ' . $publicId);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete image from Cloudinary: ' . $e->getMessage());
        }
    }
}