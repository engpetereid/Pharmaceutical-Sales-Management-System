<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Representative;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewerRepresentativeController extends Controller
{
    public function index(Request $request)
    {
        $query = Representative::query();

        // منطق البحث
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        $representatives = $query->latest()->paginate(10);

        // الحفاظ على كلمة البحث عند الانتقال للصفحة الى بعدها
        $representatives->appends($request->all());

        return view('viewer.representatives.index', compact('representatives'));
    }


    public function show(Representative $representative)
    {
        // تحميل المناطق التي يديرها كمندوب بيع أو دعاية
        $representative->load(['salesZones.province', 'medicalZones.province']);

        return view('viewer.representatives.show', compact('representative'));
    }


}
