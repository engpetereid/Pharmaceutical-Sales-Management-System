<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;
use App\Models\Province;
use App\Models\Center;
use App\Models\Pharmacist;
use App\Models\Doctor;
use App\Models\Representative;
use App\Models\Invoice;
use App\Models\DoctorDeal;
use App\Models\InvoicePayment;
use App\Models\GeneralExpense;
use App\Models\ZoneExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $provinces = Province::select('provinces.id', 'provinces.name')
            ->leftJoin('centers', 'centers.province_id', '=', 'provinces.id')
            ->leftJoin('pharmacists', 'pharmacists.center_id', '=', 'centers.id')
            ->leftJoin('invoices', function($join) use ($request) {
                $join->on('invoices.pharmacist_id', '=', 'pharmacists.id');
                if ($request->has('line')) {
                    $join->where('invoices.line', $request->line);
                }
            })
            ->selectRaw('COALESCE(SUM(invoices.final_total), 0) as total_sales')
            ->selectRaw('COUNT(DISTINCT centers.id) as centers_count')
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByDesc('total_sales')
            ->get();

        return view('admin.reports.index', compact('provinces'));
    }

    public function showProvince($id)
    {
        $province = Province::findOrFail($id);

        $centers = Center::where('province_id', $id)
            ->select('centers.id', 'centers.name')
            ->leftJoin('pharmacists', 'pharmacists.center_id', '=', 'centers.id')
            ->leftJoin('invoices', 'invoices.pharmacist_id', '=', 'pharmacists.id')
            ->selectRaw('COALESCE(SUM(invoices.final_total), 0) as total_sales')
            ->selectRaw('COUNT(DISTINCT pharmacists.id) as pharmacists_count')
            ->groupBy('centers.id', 'centers.name')
            ->orderByDesc('total_sales')
            ->get();

        $totalSales = $centers->sum('total_sales');

        return view('admin.reports.province', compact('province', 'centers', 'totalSales'));
    }


    public function showCenter($id)
    {
        $center = Center::with('province')->findOrFail($id);

        $pharmacists = Pharmacist::where('center_id', $id)
            ->select('pharmacists.*')
            ->leftJoin('invoices', 'invoices.pharmacist_id', '=', 'pharmacists.id')
            ->selectRaw('COALESCE(SUM(invoices.final_total), 0) as total_sales')
            ->selectRaw('COUNT(invoices.id) as invoices_count')
            ->groupBy('pharmacists.id')
            ->orderByDesc('total_sales')
            ->get();

        $totalSales = $pharmacists->sum('total_sales');

        return view('admin.reports.center', compact('center', 'pharmacists', 'totalSales'));
    }

    public function showPharmacist($id)
    {
        $pharmacist = Pharmacist::with(['center.province'])->findOrFail($id);

        $invoices = $pharmacist->invoices()
            ->with(['doctors', 'representative'])
            ->latest()
            ->paginate(20);

        $totalSales = $pharmacist->invoices()->sum('final_total');
        $totalPaid = $pharmacist->invoices()->sum('paid_amount');
        $totalDue = $pharmacist->invoices()->sum('remaining_amount');

        return view('admin.reports.pharmacist', compact('pharmacist', 'invoices', 'totalSales', 'totalPaid', 'totalDue'));
    }


    public function doctorsIndex(Request $request)
    {
        $provinces = Province::select('provinces.id', 'provinces.name')
            ->leftJoin('centers', 'centers.province_id', '=', 'provinces.id')
            ->leftJoin('doctors', 'doctors.center_id', '=', 'centers.id')
            // تعديل لربط جدول الفواتير مع جدول الأطباء عبر الجدول الوسيط
            ->leftJoin('doctor_invoice', 'doctor_invoice.doctor_id', '=', 'doctors.id')
            ->leftJoin('invoices', function($join) use ($request) {
                $join->on('invoices.id', '=', 'doctor_invoice.invoice_id');
                if ($request->has('line')) {
                    $join->where('invoices.line', $request->line);
                }
            })
            ->selectRaw('COUNT(DISTINCT doctors.id) as doctors_count')
            ->selectRaw('COALESCE(SUM(invoices.final_total), 0) as total_sales')
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByDesc('total_sales')
            ->get();

        return view('admin.reports.doctors.index', compact('provinces'));
    }

    public function showDoctorProvince($id)
    {
        $province = Province::findOrFail($id);

        $centers = Center::where('province_id', $id)
            ->select('centers.id', 'centers.name')
            ->leftJoin('doctors', 'doctors.center_id', '=', 'centers.id')
            // التعديل هنا للربط عبر الجدول الوسيط
            ->leftJoin('doctor_invoice', 'doctor_invoice.doctor_id', '=', 'doctors.id')
            ->leftJoin('invoices', 'invoices.id', '=', 'doctor_invoice.invoice_id')
            ->selectRaw('COUNT(DISTINCT doctors.id) as doctors_count')
            ->selectRaw('COALESCE(SUM(invoices.final_total), 0) as total_sales')
            ->groupBy('centers.id', 'centers.name')
            ->orderByDesc('total_sales')
            ->get();

        return view('admin.reports.doctors.province', compact('province', 'centers'));
    }

    public function showDoctorCenter($id)
    {
        $center = Center::with('province')->findOrFail($id);

        $doctors = Doctor::where('center_id', $id)
            ->withSum('invoices as total_sales', 'final_total')
            ->with('deals') // جلب الاتفاقات لحساب العمولات
            ->get()
            ->map(function ($doctor) {
                // حساب العمولة المستحقة غير المدفوعة من الاتفاقات بدلاً من الفواتير المحذوفة أعمدتها
                $unpaidCommission = $doctor->deals->where('is_paid', false)->sum(function ($deal) {
                    return max(0, $deal->commission_amount - $deal->paid_amount);
                });

                $doctor->unpaid_commission = $unpaidCommission;
                return $doctor;
            });

        return view('admin.reports.doctors.center', compact('center', 'doctors'));
    }


    public function showDoctor($id)
    {
        $doctor = Doctor::with(['center.province', 'deals'])->findOrFail($id);

        $invoices = $doctor->invoices()->latest()->paginate(20);

        $totalSales = $doctor->invoices()->sum('final_total');

        // الاعتماد على جدول الاتفاقات (Deals) لحساب عمولات الطبيب
        $totalCommissionEarned = $doctor->deals->sum('commission_amount');
        $paidCommission = $doctor->deals->sum('paid_amount');
        $dueCommission = max(0, $totalCommissionEarned - $paidCommission);

        return view('admin.reports.doctors.show', compact(
            'doctor', 'invoices', 'totalSales', 'totalCommissionEarned', 'paidCommission', 'dueCommission'
        ));
    }


    public function payDoctorCommission(Request $request, $id)
    {
        $doctor = Doctor::findOrFail($id);

        // تسوية جميع الاتفاقات غير المدفوعة للطبيب
        $deals = $doctor->deals()->where('is_paid', false)->get();
        $updatedCount = 0;

        foreach ($deals as $deal) {
            $deal->update([
                'is_paid' => true,
                'status' => 1,
                'paid_amount' => $deal->commission_amount
            ]);
            $updatedCount++;
        }

        if ($updatedCount > 0) {
            return redirect()->back()->with(['success' => "تم تسوية المستحقات بنجاح لـ $updatedCount اتفاق مالي للطبيب."]);
        } else {
            return redirect()->back()->with(['error' => 'لا توجد مستحقات أو اتفاقات غير مسددة لتسويتها.']);
        }
    }


    public function representativesIndex(Request $request)
    {
        $query = Representative::query();
        $query->withSum(['invoices as total_sales' => function($q) use ($request) {
            if ($request->has('line') && ($request->line == 1 || $request->line == 2)) {
                $q->where('line', $request->line);
            }
        }], 'final_total');
        $query->withCount(['invoices' => function($q) use ($request) {
            if ($request->has('line') && ($request->line == 1 || $request->line == 2)) {
                $q->where('line', $request->line);
            }
        }]);

        if ($request->has('line') && ($request->line == 1 || $request->line == 2)) {
            $query->whereHas('invoices', function($q) use ($request) {
                $q->where('line', $request->line);
            });
        }

        $representatives = $query->orderByDesc('total_sales')->paginate(10);

        $representatives->appends($request->all());

        return view('admin.reports.representatives.index', compact('representatives'));
    }


    public function showRepresentative($id)
    {
        $representative = Representative::findOrFail($id);

        $invoices = $representative->invoices()
            ->with(['pharmacist.center', 'doctors']) // تعديل من doctor إلى doctors
            ->latest()
            ->paginate(20);

        $totalSales = $representative->invoices()->sum('final_total');
        $totalCollected = $representative->invoices()->sum('paid_amount');

        return view('admin.reports.representatives.show', compact('representative', 'invoices', 'totalSales', 'totalCollected'));
    }
    public function monthlyFinancials(Request $request)
    {
        $selectedMonth = $request->input('month', Carbon::now()->month);
        $selectedYear = $request->input('year', Carbon::now()->year);
        $selectedZone = $request->input('zone');

        $startDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($selectedYear, $selectedMonth, 1)->endOfMonth();

        // 1. حساب إجمالي الدخل
        $paymentsQuery = InvoicePayment::with(['invoice.pharmacist'])
            ->whereBetween('payment_date', [$startDate, $endDate]);

        if ($selectedZone) {
            $paymentsQuery->whereHas('invoice.pharmacist.center.zones', function($q) use ($selectedZone) {
                $q->where('zones.id', $selectedZone);
            });
        }

        $payments = $paymentsQuery->orderBy('payment_date', 'desc')->get();
        $totalIncome = $payments->sum('amount');

        // 2. حساب مصروفات المناطق فقط
        $zoneExpensesQuery = ZoneExpense::with('zone')
            ->whereBetween('expense_date', [$startDate, $endDate]);

        if ($selectedZone) {
            $zoneExpensesQuery->where('zone_id', $selectedZone);
        }

        $zoneExpenses = $zoneExpensesQuery->orderBy('expense_date', 'desc')->get();
        $totalExpenses = $zoneExpenses->sum('amount');

        // 3. صافي الربح
        $netProfit = $totalIncome - $totalExpenses;

        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
            7 => 'يوليو', 8 => 'أغسطس', 9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $years = range(Carbon::now()->year - 2, Carbon::now()->year + 1);
        $zones = Zone::pluck('name', 'id')->toArray();

        // 4. تصدير إكسيل
        if ($request->has('export') && $request->export == 'excel') {
            $filename = "financials_{$selectedYear}_{$selectedMonth}.csv";
            $callback = function () use ($payments, $zoneExpenses, $months, $selectedMonth, $selectedYear, $totalIncome, $totalExpenses, $netProfit) {
                $file = fopen('php://output', 'w');
                fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($file, ['التقرير المالي لشهر', $months[(int)$selectedMonth] . ' ' . $selectedYear]);
                fputcsv($file, ['إجمالي الدخل', $totalIncome]);
                fputcsv($file, ['إجمالي المصروفات', $totalExpenses]);
                fputcsv($file, ['صافي التدفق النقدي', $netProfit]);
                fputcsv($file, []);

                fputcsv($file, ['التاريخ', 'رقم الفاتورة/البيان', 'العميل/المنطقة', 'المبلغ']);
                foreach ($payments as $payment) {
                    fputcsv($file, [$payment->payment_date->format('Y-m-d'), $payment->invoice->serial_number ?? $payment->invoice_id, $payment->invoice->pharmacist->name ?? '-', $payment->amount]);
                }
                foreach ($zoneExpenses as $expense) {
                    fputcsv($file, [$expense->expense_date, $expense->zone->name ?? '-', $expense->description, '-' . $expense->amount]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, [
                "Content-Type" => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
            ]);
        }

        return view('admin.reports.monthly_financials', compact(
            'selectedMonth', 'selectedYear', 'months', 'years', 'zones', 'selectedZone',
            'payments', 'totalIncome', 'zoneExpenses', 'totalExpenses', 'netProfit'
        ));
    }
}
