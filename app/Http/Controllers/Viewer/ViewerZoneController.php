<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Province;
use App\Models\Center;
use App\Models\Representative;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewerZoneController extends Controller
{
    public function index()
    {
        $zones = Zone::with(['province', 'salesRepresentative', 'medicalRepresentative', 'centers', 'warehouse'])
            ->orderBy('province_id')
            ->orderBy('line')
            ->paginate(10);

        return view('viewer.zones.index', compact('zones'));
    }

    public function show(Request $request, Zone $zone)
    {
        $zone->load(['province', 'salesRepresentative', 'medicalRepresentative', 'centers', 'warehouse', 'expenses']);

        // التحقق من طلب التصدير
        if ($request->has('export') && $request->export == 'excel') {
            $filename = "zone_expenses_" . $zone->id . "_" . date('Y-m-d') . ".csv";

            $callback = function () use ($zone) {
                $file = fopen('php://output', 'w');
                // دعم اللغة العربية
                fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($file, ['تقرير مصروفات المنطقة']);
                fputcsv($file, ['المنطقة:', $zone->name, 'الخط:', 'Line ' . $zone->line]);
                fputcsv($file, []);

                // ترويسة الجدول
                fputcsv($file, ['التاريخ', 'بيان المصروف (في إيه)', 'المبلغ', 'وقت الإضافة']);

                foreach ($zone->expenses as $expense) {
                    fputcsv($file, [
                        $expense->expense_date,
                        $expense->description,
                        $expense->amount,
                        $expense->created_at->format('Y-m-d H:i')
                    ]);
                }

                fputcsv($file, []);
                fputcsv($file, ['', 'الإجمالي:', $zone->expenses->sum('amount')]);

                fclose($file);
            };

            return response()->stream($callback, 200, [
                "Content-Type" => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            ]);
        }

        return view('viewer.zones.show', compact('zone'));
    }
}
