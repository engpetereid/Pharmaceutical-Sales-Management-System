<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class me extends Controller
{
    public function index(Request $request)
    {
        return view('me');
    }
}
