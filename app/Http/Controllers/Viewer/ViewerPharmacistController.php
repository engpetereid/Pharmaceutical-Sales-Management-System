<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Pharmacist;
use Illuminate\Http\Request;

class ViewerPharmacistController extends Controller
{
    public function index(Request $request)
    {
        $query = Pharmacist::with('center');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        $pharmacists = $query->latest()->paginate(10);

        $pharmacists->appends($request->all());

        return view('viewer.pharmacists.index', compact('pharmacists'));
    }



    public function show(Pharmacist $pharmacist) {
        $pharmacist->load('center.province');

        $invoices = $pharmacist->invoices()
            ->with(['doctors', 'representative', 'details'])
            ->latest()
            ->paginate(10);

        $totalSales = $pharmacist->invoices()->sum('final_total');
        $totalPaid = $pharmacist->invoices()->sum('paid_amount');
        $totalDue = $pharmacist->invoices()->sum('remaining_amount');

        return view('viewer.pharmacists.show', compact('pharmacist', 'invoices', 'totalSales', 'totalPaid', 'totalDue'));
    }

}
