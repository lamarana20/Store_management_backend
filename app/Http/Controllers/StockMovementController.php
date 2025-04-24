<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\StockMovement;

class StockMovementController extends Controller
{
    public function index()
    {
        $stockMovements = StockMovement::all();
        return response()->json($stockMovements);
    }
    public function adjustStock(Request $request)
{
    $validatedData = $request->validate([
        'product_id' => 'required|integer|exists:products,id',
        'quantity' => 'required|integer',
        'reason' => 'required|string|max:255',
        'movement_type' => 'required|in:in,out',
        'supplier_id' => 'integer|exists:suppliers,id',
    ]);
    $movementType = $validatedData['movement_type'];
    $reason = $validatedData['reason'];

    if ($movementType === 'out') {
        $validatedData['quantity'] = -abs($validatedData['quantity']);
        $product = Product::find($validatedData['product_id']);
        if ($product->stock_quantity < abs($validatedData['quantity'])) {
            return response()->json(['error' => 'Insufficient stock'], 400);
        }
        if ($reason === 'purchase') {
            return response()->json(['error' => 'Cannot perform outbound movement for purchase reason'], 400);
        }
    } else {
        if ($reason === 'sale') {
            return response()->json(['error' => 'Cannot perform inbound movement for sale reason'], 400);
        }
        
        if ($reason === 'purchase') {
            $validatedData['quantity'] = abs($validatedData['quantity']);
        } elseif ($reason === 'adjustment') {
            $validatedData['quantity'] = abs($validatedData['quantity']);
        } else {
            return response()->json(['error' => 'Invalid reason'], 400);
        }
    }    StockMovement::create([
        'product_id' => $validatedData['product_id'],
        'quantity' => $validatedData['quantity'],
        'reason' => $validatedData['reason'],
        'movement_type' => $movementType,
        'supplier_id' => $validatedData['supplier_id'] ,
    ]);

    $product = Product::find($validatedData['product_id']);
    $product->stock_quantity += $validatedData['quantity'];
    $product->save();

    return response()->json(['message' => 'Stock adjusted successfully'], 201);
}

public function recordPurchase(Request $request)
{
    // Validate data
    $validatedData = $request->validate([
        'product_id' => 'required|integer|exists:products,id',
        'supplier_id' => 'required|integer|exists:suppliers,id',
        'quantity' => 'required|integer|min:1',
        'purchase_price' => 'required|numeric|min:0',
    ]);

    // Get the product
    $product = Product::find($validatedData['product_id']);
    
    // Update price if purchase price is different from current price
    if ($validatedData['purchase_price'] !== $product->price) {
        $product->price = $validatedData['purchase_price'];
        $product->save();
    }

    // Record stock movement (Purchase)
    StockMovement::create([
        'product_id' => $validatedData['product_id'],
        'supplier_id' => $validatedData['supplier_id'],
        'quantity' => $validatedData['quantity'],
        'purchase_price' => $validatedData['purchase_price'],
        'movement_type' => 'in',
    ]);

    // Update product stock quantity
    $product->stock_quantity += $validatedData['quantity'];
    $product->save();

    // Return JSON response
    return response()->json(['message' => 'Stock purchase recorded successfully'], 201);
}


public function recordTransfer(Request $request)
{
    $validatedData = $request->validate([
        'product_id' => 'required|integer|exists:products,id',
        'from_location_id' => '|integer|exists:locations,id',
        'to_location_id' => '|integer|exists:locations,id|different:from_location_id',
        'quantity' => 'required|integer|min:1',
        'reason' => 'required|string|max:255',
        'movement_type' => 'out',
    ]);

    StockMovement::create([
        'product_id' => $validatedData['product_id'],
        'from_location_id' => $validatedData['from_location_id'],
        'to_location_id' => $validatedData['to_location_id'],
        'quantity' => $validatedData['quantity'],
        'reason' => $validatedData['reason'],
        'movement_type' => 'out',
    ]);

    $product = Product::find($validatedData['product_id']);
    $product->stock_quantity -= $validatedData['quantity'];
    $product->save();

    return response()->json(['message' => 'Stock transfer recorded successfully'], 201);
}

}
