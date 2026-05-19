<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvoicePayment;
use App\Models\ZoneExpense;
use App\Models\GeneralExpense;
use App\Models\Zone;
use Carbon\Carbon;

class TreasuryController extends Controller
{
    public function index(Request $request)
    {
        // 1. تجهيز الاستعلامات الأساسية
        $paymentsQuery = InvoicePayment::with(['invoice.pharmacist.center.zones']);
        $zoneExpensesQuery = ZoneExpense::with('zone');
        $generalExpensesQuery = GeneralExpense::query();

        // 2. تطبيق الفلاتر

        // فلتر التاريخ
        if ($request->filled('start_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $paymentsQuery->where('payment_date', '>=', $startDate);
            $zoneExpensesQuery->where('expense_date', '>=', $startDate);
            $generalExpensesQuery->where('expense_date', '>=', $startDate);
        }

        if ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $paymentsQuery->where('payment_date', '<=', $endDate);
            $zoneExpensesQuery->where('expense_date', '<=', $endDate);
            $generalExpensesQuery->where('expense_date', '<=', $endDate);
        }

        // فلتر المناطق (متعدد) - لا يطبق على المصروفات العامة
        if ($request->filled('zone_id')) {
            // تحويل القيمة القادمة إلى مصفوفة لتفادي أي أخطاء
            $zoneIds = (array) $request->zone_id;

            // فلترة التحصيلات عبر العلاقات المتداخلة باستخدام whereIn
            $paymentsQuery->whereHas('invoice.pharmacist.center.zones', function($q) use ($zoneIds) {
                $q->whereIn('zones.id', $zoneIds);
            });

            // فلترة مصروفات المنطقة باستخدام whereIn
            $zoneExpensesQuery->whereIn('zone_id', $zoneIds);
        }

        // 3. جلب البيانات وحساب الإجماليات
        $payments = $paymentsQuery->orderBy('payment_date', 'desc')->get();
        $totalIncome = $payments->sum('amount');

        $zoneExpenses = $zoneExpensesQuery->orderBy('expense_date', 'desc')->get();
        $totalZoneExpenses = $zoneExpenses->sum('amount');

        $generalExpenses = $generalExpensesQuery->orderBy('expense_date', 'desc')->get();
        $totalGeneralExpenses = $generalExpenses->sum('amount');

        // إجمالي المصروفات الكلية
        $totalExpenses = $totalZoneExpenses + $totalGeneralExpenses;

        // الصافي الموجود في الخزنة
        $netBalance = $totalIncome - $totalExpenses;

        $zones = Zone::select('id', 'name')->get();

        // 4. تصدير إكسيل (CSV)
        if ($request->has('export') && $request->export == 'excel') {
            return $this->exportExcel(
                $payments, $zoneExpenses, $generalExpenses,
                $totalIncome, $totalZoneExpenses, $totalGeneralExpenses, $totalExpenses, $netBalance,
                $request->start_date, $request->end_date
            );
        }

        return view('admin.treasury.index', compact(
            'payments', 'totalIncome',
            'zoneExpenses', 'totalZoneExpenses',
            'generalExpenses', 'totalGeneralExpenses',
            'totalExpenses', 'netBalance', 'zones'
        ));
    }

    private function exportExcel($payments, $zoneExpenses, $generalExpenses, $totalIncome, $totalZoneExpenses, $totalGeneralExpenses, $totalExpenses, $netBalance, $startDate, $endDate)
    {
        $filename = "treasury_report_" . date('Y-m-d') . ".csv";
        $callback = function () use ($payments, $zoneExpenses, $generalExpenses, $totalIncome, $totalZoneExpenses, $totalGeneralExpenses, $totalExpenses, $netBalance, $startDate, $endDate) {
            $file = fopen('php://output', 'w');
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // لدعم اللغة العربية

            // الترويسة
            $period = ($startDate || $endDate) ? "الفترة: من $startDate إلى $endDate" : "حتى الآن (كل البيانات)";
            fputcsv($file, ['تقرير الخزينة (حركة الأموال)', $period]);
            fputcsv($file, []);

            // ملخص
            fputcsv($file, ['الملخص المالي']);
            fputcsv($file, ['إجمالي الدخل (التحصيلات)', $totalIncome]);
            fputcsv($file, ['إجمالي مصروفات المناطق', $totalZoneExpenses]);
            fputcsv($file, ['إجمالي المصروفات العامة', $totalGeneralExpenses]);
            fputcsv($file, ['إجمالي المصروفات الكلية', $totalExpenses]);
            fputcsv($file, ['صافي الخزنة (الرصيد)', $netBalance]);
            fputcsv($file, []);

            // 1. الدخل
            fputcsv($file, ['--- تفاصيل الدخل (التحصيلات) ---']);
            fputcsv($file, ['التاريخ', 'رقم الفاتورة', 'الصيدلية', 'المبلغ']);
            foreach ($payments as $payment) {
                fputcsv($file, [
                    $payment->payment_date->format('Y-m-d'),
                    $payment->invoice->serial_number ?? $payment->invoice_id,
                    $payment->invoice->pharmacist->name ?? '-',
                    $payment->amount
                ]);
            }
            fputcsv($file, []);

            // 2. مصروفات المناطق
            fputcsv($file, ['--- تفاصيل مصروفات وعمولات المناطق ---']);
            fputcsv($file, ['التاريخ', 'المنطقة', 'البيان', 'المبلغ']);
            foreach ($zoneExpenses as $expense) {
                fputcsv($file, [
                    $expense->expense_date,
                    $expense->zone->name ?? '-',
                    $expense->description,
                    $expense->amount
                ]);
            }
            fputcsv($file, []);

            // 3. المصروفات العامة
            fputcsv($file, ['--- تفاصيل المصروفات العامة ---']);
            fputcsv($file, ['التاريخ', 'البيان', 'المبلغ']);
            foreach ($generalExpenses as $gExpense) {
                fputcsv($file, [
                    $gExpense->expense_date->format('Y-m-d'),
                    $gExpense->description,
                    $gExpense->amount
                ]);
            }

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
}
