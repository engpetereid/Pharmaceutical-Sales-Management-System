<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewerCenterController extends Controller
{

    public function index()
    {
        $centers = Center::with('province')->paginate(10);
        return view('viewer.centers.index', compact('centers'));
    }



    /**
     * Display the specified resource.
     */
    public function show(Center $center)
    {
        $center->load(['pharmacists', 'doctors']);
        return view('viewer.centers.show', compact('center'));
    }


}
