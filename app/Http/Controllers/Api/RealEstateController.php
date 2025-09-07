<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RealEstate;
use Illuminate\Http\JsonResponse;

class RealEstateController extends Controller
{

    public function index()
    {
          $rows = RealEstate::query()
            ->selectRaw('id, name, real_state_type as type, city, country')
            ->orderByDesc('id')
            ->get();

        return response()->json($rows, 200);
    }


    public function store(Request $request)
        {

    }


    public function show(RealEstate $property): JsonResponse
    {
        return response()->json($property, 200);
    }


    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }
}
