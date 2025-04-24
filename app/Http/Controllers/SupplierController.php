<?php
namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function index()
    {
        return response()->json(Supplier::all(), Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
           'contact_information' => 'nullable|string|max:255',
        ]);

        $supplier = Supplier::create($request->all());

        return response()->json($supplier, Response::HTTP_CREATED);
    }

    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);
        return response()->json($supplier, Response::HTTP_OK);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_information' => 'nullable|string|max:255',
        ]);

        $supplier->update($request->all());

        return response()->json($supplier, Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
