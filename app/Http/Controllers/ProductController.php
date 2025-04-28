<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    
    /**
     * Display a list of all products.
     * GET /api/products
     */
    public function index()
    {
        $products = Product::all()->map(function ($product) {
            // Make sure the image exists before generating the URL
            if ($product->image) {
                $product->image_url = asset('storage/products/' . $product->image);
            } else {
                // If no image is available, set a default image
                $product->image_url = asset('storage/products/placeholder.jpg'); 
            }
            return $product;
        });

        return response()->json($products);
    }

    /**
     * Create a new product.
     * POST /api/products
     */
    public function store(Request $request)
    
    {
    
        try {
            // Data validation
            $validatedData = $request->validate([
                'sku' => 'required|string|max:100|unique:products,sku',
                'name' => 'required|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'description' => 'nullable|string',
                'category_id' => 'required|exists:categories,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'stock_quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
    
                // 🔵 Nouveaux champs
                'sub_category' => 'nullable|string|max:255',
                'sizes' => 'nullable|array',
                'sizes.*' => 'string|max:10', // Chaque taille doit être une petite chaîne
                'bestseller' => 'nullable|boolean',
                'date' => 'nullable|date',
            ]);

    
            // Image upload handling
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
                $validatedData['image'] = basename($imagePath);  // Save only the filename
            }
    
            // Transformer 'sizes' en JSON si fourni
            if (isset($validatedData['sizes'])) {
                $validatedData['sizes'] = json_encode($validatedData['sizes']);
            }
    
            // Create the product
            $product = Product::create($validatedData);
    
            return response()->json($product, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
    

    /**
     * Update product details.
     * PUT /api/products/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);

            // Data validation
            $validatedData = $request->validate([
                'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $id,
                'name' => 'sometimes|required|string|max:255',
                'image' => 'nullable|mimes:jpeg,png,jpg,gif|max:2048888',  // Change 'required' to 'nullable'
                'description' => 'nullable|string',
                'category_id' => 'sometimes|required|exists:categories,id',
                'supplier_id' => 'sometimes|required|exists:suppliers,id',
                'stock_quantity' => 'sometimes|required|integer|min:0',
                'price' => 'sometimes|required|numeric|min:0',
            ]);

            // Image upload handling
            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($product->image) {
                    Storage::disk('public')->delete('products/' . $product->image);
                }

                $imagePath = $request->file('image')->store('products', 'public');
                $validatedData['image'] = basename($imagePath); // Save only the filename
            }

            // Update the product
            $product->update($validatedData);

            return response()->json($product, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a specific product by ID.
     * DELETE /api/products/{id}
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Delete associated image if it exists
        if ($product->image && Storage::disk('public')->exists('products/' . $product->image)) {
            Storage::disk('public')->delete('products/' . $product->image);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product successfully deleted'
        ], Response::HTTP_OK);
    }

    /**
     * Display list of products with low stock.
     * GET /api/products/low-stock
     */
    public function lowStock()
    {
        $threshold = env('LOW_STOCK_THRESHOLD', 500); // Low stock threshold configurable via .env
        $products = Product::where('stock_quantity', '<', $threshold)->get();

        return response()->json([
            'message' => 'List of products with low stock',
            'products' => $products
        ], Response::HTTP_OK);
    }
}
