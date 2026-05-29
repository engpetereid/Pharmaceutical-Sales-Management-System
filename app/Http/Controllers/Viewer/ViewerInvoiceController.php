<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePayment;
use App\Models\Pharmacist;
use App\Models\Doctor;
use App\Models\Drug;
use App\Models\Province;
use App\Models\Representative;
use App\Models\Zone;
use App\Models\DoctorDeal;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use ArPHP\I18N\Arabic;

class ViewerInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->getFilteredQuery($request);

        $totalsQuery = clone $query;

        $stats = [
            'total_public_sales' => $totalsQuery->sum('total_amount'),
            'total_net_sales' => $totalsQuery->sum('final_total'),
            'total_collected' => $totalsQuery->sum('paid_amount'),
        ];

        $stats['total_remaining'] = $stats['total_net_sales'] - $stats['total_collected'];

        $invoices = $query->latest('invoice_date')->paginate(20)->withQueryString();

        $centers = Center::all();
        $zones = Zone::where('line', 1)->get();

        $doctors = Doctor::select('id', 'name')->get();
        $representatives = Representative::select('id', 'name')->get();
        $pharmacists = Pharmacist::select('id', 'name')->get();

        return view('viewer.invoices.index', compact('invoices', 'centers', 'stats', 'zones','doctors', 'representatives', 'pharmacists'));
    }

    public function export(Request $request)
    {
        $query = $this->getFilteredQuery($request);

        // إذا كان التصدير تفصيلي، نحتاج لجلب بيانات الأدوية
        if ($request->export_type == 'details') {
            $query->with(['details.drug']);
        }

        $invoices = $query->latest('invoice_date')->get();

        if ($request->export_type == 'details') {
            $filename = "invoices_details_report_" . date('Y-m-d') . ".csv";

            // استخراج جميع الأدوية الفريدة من الفواتير لعمل أعمدة ديناميكية
            $uniqueDrugs = [];
            foreach ($invoices as $invoice) {
                foreach ($invoice->details as $detail) {
                    if ($detail->drug) {
                        $uniqueDrugs[$detail->drug_id] = $detail->drug->name;
                    }
                }
            }
            // ترتيب الأدوية أبجدياً للحفاظ على ترتيب الأعمدة
            asort($uniqueDrugs);

            $callback = function () use ($invoices, $uniqueDrugs) {
                $file = fopen('php://output', 'w');
                fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // لدعم اللغة العربية

                // 1. بناء صف العناوين (الهيدر)
                $headers = ['رقم الفاتورة', 'التاريخ', 'الصيدلية', 'المركز'];

                foreach ($uniqueDrugs as $drugId => $drugName) {
                    $headers[] = $drugName; // عمود للكمية
                    $headers[] = 'خصم ' . $drugName . ' %'; // عمود للخصم
                }

                array_push($headers, 'الإجمالي (الصافي)', 'المدفوع', 'المتبقي', 'حالة الفاتورة');

                fputcsv($file, $headers);

                // 2. إدخال بيانات الفواتير
                foreach ($invoices as $invoice) {
                    $statusText = match ($invoice->status) {
                        1 => 'مدفوع',
                        2 => 'آجل',
                        3 => 'جزئي',
                        default => '-'
                    };

                    // مصفوفة للوصول السريع لتفاصيل الفاتورة حسب الـ drug_id
                    $detailsMap = [];
                    foreach ($invoice->details as $detail) {
                        $detailsMap[$detail->drug_id] = $detail;
                    }

                    // البيانات الأساسية للفاتورة
                    $row = [
                        $invoice->serial_number ?? $invoice->id,
                        $invoice->invoice_date,
                        $invoice->pharmacist->name ?? '-',
                        $invoice->pharmacist->center->name ?? '-',
                    ];

                    // إضافة الكميات والخصومات لكل دواء
                    foreach ($uniqueDrugs as $drugId => $drugName) {
                        if (isset($detailsMap[$drugId])) {
                            $row[] = $detailsMap[$drugId]->quantity; // الكمية
                            $row[] = $detailsMap[$drugId]->pharmacist_discount_percentage . '%'; // الخصم
                        } else {
                            $row[] = '0'; // الكمية صفر إذا لم يكن الدواء موجوداً
                            $row[] = '-'; // لا يوجد خصم
                        }
                    }

                    // إضافة الإجماليات المالية الكلية للفاتورة في النهاية
                    array_push($row,
                        $invoice->final_total,
                        $invoice->paid_amount,
                        $invoice->remaining_amount,
                        $statusText
                    );

                    fputcsv($file, $row);
                }
                fclose($file);
            };
        } else {
            // التصدير العادي (ملخص)
            $filename = "invoices_summary_report_" . date('Y-m-d') . ".csv";

            $callback = function () use ($invoices) {
                $file = fopen('php://output', 'w');
                fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($file, [
                    'رقم الفاتورة', 'التاريخ', 'الصيدلية', 'المركز', 'المنطقة',
                    'الأطباء', 'المندوب', 'الإجمالي (جمهور)', 'الصافي', 'المدفوع', 'المتبقي', 'الحالة'
                ]);

                foreach ($invoices as $invoice) {
                    $statusText = match ($invoice->status) {
                        1 => 'مدفوع',
                        2 => 'آجل',
                        3 => 'جزئي',
                        default => '-'
                    };

                    $doctorsNames = $invoice->doctors->pluck('name')->implode(' - ') ?: '-';
                    $zoneName = $invoice->pharmacist?->center?->zones?->first()?->name ?? '-';

                    fputcsv($file, [
                        $invoice->serial_number ?? $invoice->id,
                        $invoice->invoice_date,
                        $invoice->pharmacist->name ?? '-',
                        $invoice->pharmacist->center->name ?? '-',
                        $zoneName,
                        $doctorsNames,
                        $invoice->representative->name ?? '-',
                        $invoice->total_amount,
                        $invoice->final_total,
                        $invoice->paid_amount,
                        $invoice->remaining_amount,
                        $statusText
                    ]);
                }
                fclose($file);
            };
        }

        return response()->stream($callback, 200, [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['representative', 'medicalRepresentative', 'pharmacist.center', 'doctors', 'details.drug']);
        return view('viewer.invoices.show', compact('invoice'));
    }

    public function printPdf(Invoice $invoice)
    {
        $invoice->load(['representative', 'medicalRepresentative', 'pharmacist.center', 'doctors', 'details.drug']);
        $html = view('admin.invoices.pdf', compact('invoice'))->render();
        $arabic = new Arabic();
        $p = $arabic->arIdentify($html);
        for ($i = count($p) - 1; $i >= 0; $i -= 2) {
            $utf8ar = $arabic->utf8Glyphs(substr($html, $p[$i - 1], $p[$i] - $p[$i - 1]));
            $html = substr_replace($html, $utf8ar, $p[$i - 1], $p[$i] - $p[$i - 1]);
        }
        $pdf = Pdf::loadHTML($html);
        $pdf->setOption(['dpi' => 150, 'defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => true]);
        return $pdf->stream('invoice_' . $invoice->id . '.pdf');
    }

    // ==========================================
    // إدارة سداد الدفعات (Payments Management)
    // ==========================================

    public function payments(Invoice $invoice)
    {
        $invoice->load(['payments' => function($q) {
            $q->orderBy('payment_date', 'desc');
        }, 'pharmacist']);
        return view('viewer.invoices.payments', compact('invoice'));
    }





    // ==========================================
    // دوال المساعدة الداخلية
    // ==========================================

    private function getPharmacistsForForm(): \Illuminate\Support\Collection
    {
        return Pharmacist::with([
            'center',
            'deals' => fn($q) => $q->where('is_archived', false)->where('is_active', true),
            'deals.doctor',
            'deals.drugs',
        ])->get()->map(fn($ph) => [
            'id'        => $ph->id,
            'name'      => $ph->name,
            'center_id' => $ph->center_id,
            'center'    => $ph->center ? [
                'id'          => $ph->center->id,
                'name'        => $ph->center->name,
                'province_id' => $ph->center->province_id,
            ] : null,
            'deals'     => $ph->deals
        ->map(function ($deal) {
            if ($deal->is_archived || !$deal->doctor) return null;

            $isComplete = $deal->target_amount > 0
                && $deal->achieved_amount >= $deal->target_amount;

            return [
                'id'         => $deal->id,
                'drugs'      => $deal->drugs->pluck('id')->toArray(),
                'is_general' => $deal->drugs->isEmpty(),
                'doctor'     => [
                    'id'              => $deal->doctor->id,
                    'name'            => $deal->doctor->name . ($isComplete ? ' (مكتمل)' : ''),
                    'speciality'      => $deal->doctor->speciality,
                    'commission_rate' => $deal->commission_percentage,
                ],
            ];
        })
        ->filter()
        ->values(),
        ]);
    }

    private function getFilteredQuery(Request $request)
    {
        $query = Invoice::with(['pharmacist.center', 'doctors', 'representative']);

        if ($request->filled('start_date')) {
            $query->whereDate('invoice_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('invoice_date', '<=', $request->end_date);
        }
        if ($request->filled('center_id')) {
            $query->whereHas('pharmacist', function ($q) use ($request) {
                $q->where('center_id', $request->center_id);
            });
        }
        if ($request->filled('zone_id')) {
            $query->whereHas('pharmacist.center.zones', function ($q) use ($request) {
                $q->where('zones.id', $request->zone_id);
            });
        }
        if ($request->filled('serial_number')) {
            $query->where('serial_number', $request->serial_number);
        }
        if ($request->filled('line')) {
            $query->where('line', 'like', '%' . $request->line . '%');
        }
        if ($request->filled('status')) {
            $query->whereIn('status', (array) $request->status);
        }
        if ($request->filled('pharmacist_id')) {
            $query->where('pharmacist_id', $request->pharmacist_id);
        }
        if ($request->filled('doctor_id')) {
            $query->whereHas('doctors', function ($q) use ($request) {
                $q->where('doctors.id', $request->doctor_id);
            });
        }
        if ($request->filled('representative_id')) {
            $query->where('representative_id', $request->representative_id);
        }

        return $query;
    }

}
