<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all()->map(function ($product) {
            $images = json_decode($product->images, true) ?? [];
    
            if (count($images) > 0) {
                $product->image_urls = array_map(function ($image) {
                    return asset('storage/products/' . $image);
                }, $images);
            } else {
                $product->image_urls = [asset('storage/products/placeholder.jpg')];
            }
    
            return $product;
        });
    
        return response()->json($products);
    }
    

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'sku' => 'required|string|max:100|unique:products,sku',
                'name' => 'required|string|max:255',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
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

            if ($request->hasFile('images')) {
                $imagePaths = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $imagePaths[] = basename($path);
                }
                $validatedData['images'] = json_encode($imagePaths);
            }

            if (isset($validatedData['sizes'])) {
                $validatedData['sizes'] = json_encode($validatedData['sizes']);
            }

            $product = Product::create($validatedData);

            // Ajoute les URLs des images
            $product->image_urls = collect(json_decode($product->images))->map(function ($filename) {
                return asset('storage/products/' . $filename);
            });

            return response()->json($product, Response::HTTP_CREATED);
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
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
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

            if ($request->boolean('replace_images')) {
                if ($product->images) {
                    foreach (json_decode($product->images, true) as $img) {
                        Storage::disk('public')->delete('products/' . $img);
                    }
                }
                $product->images = null;
            }

            if ($request->hasFile('images')) {
                $newImages = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $newImages[] = basename($path);
                }

                if (!$request->boolean('replace_images') && $product->images) {
                    $existing = json_decode($product->images, true);
                    $newImages = array_merge($existing, $newImages);
                }

                $validatedData['images'] = json_encode($newImages);
            }

            $product->update($validatedData);

            // Ajoute les URLs des images
            $product->image_urls = collect(json_decode($product->images))->map(function ($filename) {
                return asset('storage/products/' . $filename);
            });

            return response()->json($product, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->images) {
            foreach (json_decode($product->images, true) as $img) {
                Storage::disk('public')->delete('products/' . $img);
            }
        }

        $product->delete();

        return response()->json([
            'message' => 'Product successfully deleted'
        ], Response::HTTP_OK);
    }

    public function lowStock()
    {
        $threshold = env('LOW_STOCK_THRESHOLD', 500);
        $products = Product::where('stock_quantity', '<', $threshold)->get();

        return response()->json([
            'message' => 'List of products with low stock',
            'products' => $products
        ], Response::HTTP_OK);
    }
}
