<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\InvoiceReturn;
use App\Models\Pharmacist;
use Illuminate\Http\Request;

class ViewerInvoiceReturnController extends Controller
{
    /**
     * عرض قائمة مرتجعات المبيعات للمشاهد (Read-Only)
     */
    public function index(Request $request)
    {
        $query = InvoiceReturn::with([
            'invoice.pharmacist.center',
            'invoice.representative',
            'items.drug',
        ]);

        if ($request->filled('start_date')) {
            $query->whereDate('return_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('return_date', '<=', $request->end_date);
        }
        if ($request->filled('return_number')) {
            $query->where('return_number', 'like', '%'.$request->return_number.'%');
        }
        if ($request->filled('invoice_serial')) {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('serial_number', 'like', '%'.$request->invoice_serial.'%');
            });
        }
        if ($request->filled('pharmacist_id')) {
            $query->whereHas('invoice', function ($q) use ($request) {
                $q->where('pharmacist_id', $request->pharmacist_id);
            });
        }

        // إحصائيات عامة للمرتجعات
        $statsQuery = clone $query;
        $stats = [
            'total_returns_count' => $statsQuery->count(),
            'total_net_returned' => $statsQuery->sum('total_return_amount'),
            'total_refunded' => $statsQuery->sum('refund_amount'),
        ];

        if ($request->has('export') && $request->export == 'excel') {
            return $this->exportReturns($query->latest('return_date')->get());
        }

        $returns = $query->latest('return_date')->latest('id')->paginate(20)->withQueryString();
        $pharmacists = Pharmacist::select('id', 'name')->orderBy('name')->get();

        return view('viewer.invoices.returns.index', compact('returns', 'pharmacists', 'stats'));
    }

    /**
     * عرض تفاصيل إيصال المرتجع (معاينة وطباعة فقط)
     */
    public function show(InvoiceReturn $return)
    {
        $return->load([
            'invoice.pharmacist.center',
            'invoice.representative',
            'invoice.medicalRepresentative',
            'invoice.doctors',
            'items.drug',
            'creator',
        ]);

        return view('viewer.invoices.returns.show', compact('return'));
    }

    /**
     * تصدير سجل المرتجعات إلى Excel (CSV مع BOM)
     */
    private function exportReturns($returns)
    {
        $filename = 'sales_returns_'.date('Y-m-d').'.csv';

        $callback = function () use ($returns) {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF)); // لدعم اللغة العربية في Excel

            fputcsv($file, [
                'رقم المرتجع',
                'تاريخ المرتجع',
                'رقم الفاتورة الأصلية',
                'الصيدلية',
                'المركز',
                'المندوب',
                'إجمالي الجمهور المرتجع',
                'إجمالي الخصم المرتجع',
                'صافي قيمة المرتجع',
                'المبلغ المسترد نقداً',
                'المسجل بواسطة',
                'ملاحظات',
            ]);

            foreach ($returns as $ret) {
                fputcsv($file, [
                    $ret->return_number,
                    $ret->return_date->format('Y-m-d'),
                    $ret->invoice->serial_number ?? $ret->invoice_id,
                    $ret->invoice->pharmacist->name ?? '-',
                    $ret->invoice->pharmacist->center->name ?? '-',
                    $ret->invoice->representative->name ?? '-',
                    $ret->total_return_public_price,
                    $ret->total_return_discount,
                    $ret->total_return_amount,
                    $ret->refund_amount,
                    $ret->creator->name ?? 'النظام',
                    $ret->notes ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
