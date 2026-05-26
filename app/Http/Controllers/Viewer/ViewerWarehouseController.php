<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Drug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewerWarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::with(['parent', 'zones'])
            ->withCount('drugs')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(10);

        return view('viewer.warehouses.index', compact('warehouses'));
    }

    public function show(Warehouse $warehouse)
    {
        $inventory = $warehouse->drugs()
            ->select('drugs.id', 'drugs.name', 'drugs.price', 'drugs.line')
            ->paginate(15);
        $warehouse->load('distributionAreas');

        return view('viewer.warehouses.show', compact('warehouse', 'inventory'));
    }




}
