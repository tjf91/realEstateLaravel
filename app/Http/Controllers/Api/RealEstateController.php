<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRealEstateRequest;
use App\Http\Requests\UpdateRealEstateRequest;
use App\Models\RealEstate;
use Illuminate\Http\JsonResponse;

class RealEstateController extends Controller
{
    // GET /api/properties  (list view fields only)
    public function index(): JsonResponse
    {
        $rows = RealEstate::query()
            ->selectRaw('id, name, real_state_type as type, city, country')
            ->orderByDesc('id')
            ->get();

        return response()->json($rows, 200);
    }

    // GET /api/properties/{property}  (full record)
    public function show(RealEstate $property): JsonResponse
    {
        return response()->json($property, 200);
    }

    // POST /api/properties
    public function store(StoreRealEstateRequest $request): JsonResponse
    {
        $estate = RealEstate::create($request->validated());
        return response()->json($estate, 201);
    }

    // PATCH/PUT /api/properties/{property}
    public function update(UpdateRealEstateRequest $request, RealEstate $property): JsonResponse
    {
        $property->update($request->validated());
        $property->refresh(); // return newly updated record
        return response()->json($property, 200);
    }

    // DELETE /api/properties/{property}  (soft delete, return deleted record)
    public function destroy(RealEstate $property): JsonResponse
    {
        $property->delete();
        return response()->json($property, 200);
    }
}
