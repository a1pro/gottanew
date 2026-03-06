<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coach\CoachingPackage;

class PackageController extends Controller
{
    public function index()
    {
        return CoachingPackage::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'coach_id' => 'required|exists:coaches,id',
            'name' => 'required|string',
            'description' => 'required|string',
            'duration_minutes' => 'required|integer',
            'price_amount' => 'required|numeric',
            'price_currency' => 'required|string',
            'coin_cost' => 'required|integer',
            'package_type' => 'required|in:basic,premium,vip',
            'features' => 'nullable|array'
        ]);

        $package = CoachingPackage::create($data);

        return response()->json($package, 201);
    }

    public function show($id)
    {
        return CoachingPackage::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $package = CoachingPackage::findOrFail($id);

        $package->update($request->all());

        return response()->json($package);
    }

    public function destroy($id)
    {
        $package = CoachingPackage::findOrFail($id);
        $package->delete();

        return response()->json([
            'message' => 'Package deleted'
        ]);
    }
}