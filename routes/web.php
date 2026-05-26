<?php

use App\Http\Controllers\Admin\CenterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\DrugController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PharmacistController;
use App\Http\Controllers\Admin\ProvinceController;
use App\Http\Controllers\Admin\RepresentativeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\DoctorDealController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\ZoneExpenseController;
use App\Http\Controllers\Admin\ZoneReportController;
use App\Http\Controllers\Admin\ZoneController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\me;

Route::get('/', function () {

    if (!auth()->check()) {
        return redirect()->route('login');
    }

    if (auth()->user()->role == 'admin') {
        return redirect()->route('admin.dashboard');
    }

    if (auth()->user()->role == 'viewer') {
        return redirect()->route('viewer.dashboard');
    }
    abort(403);
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('me',[me::class,'index'])->name('me');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::resource('users', UserController::class);
    Route::resource('drugs', DrugController::class);
    Route::resource('provinces', ProvinceController::class);
    Route::resource('centers', CenterController::class);
    Route::resource('doctors', DoctorController::class);
    Route::resource('pharmacists', PharmacistController::class);
    Route::resource('representatives', RepresentativeController::class);
    Route::resource('zones', ZoneController::class);
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::resource('invoices', InvoiceController::class);
    Route::get('invoices/{invoice}/payments', [\App\Http\Controllers\Admin\InvoiceController::class, 'payments'])->name('invoices.payments');
    Route::post('invoices/{invoice}/payments', [\App\Http\Controllers\Admin\InvoiceController::class, 'storePayment'])->name('invoices.payments.store');
    Route::put('invoices/{invoice}/payments/{payment}', [\App\Http\Controllers\Admin\InvoiceController::class, 'updatePayment'])->name('invoices.payments.update');
    Route::delete('invoices/{invoice}/payments/{payment}', [\App\Http\Controllers\Admin\InvoiceController::class, 'destroyPayment'])->name('invoices.payments.destroy');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'printPdf'])->name('invoices.pdf');
    Route::get('/dashboard/line/{id}', [DashboardController::class, 'lineDashboard'])->name('dashboard.line');

    Route::get('zones/{id}/expenses/create', [ZoneExpenseController::class, 'create'])->name('zones.expenses.create');
    Route::post('zones/{id}/expenses', [ZoneExpenseController::class, 'store'])->name('zones.expenses.store');
    Route::get('expenses/{id}/edit', [ZoneExpenseController::class, 'edit'])->name('zones.expenses.edit');
    Route::put('expenses/{id}', [ZoneExpenseController::class, 'update'])->name('zones.expenses.update');
    Route::delete('expenses/{id}', [ZoneExpenseController::class, 'destroy'])->name('zones.expenses.destroy');
    Route::resource('general-expenses', \App\Http\Controllers\Admin\GeneralExpenseController::class)->names('general-expenses');
    Route::get('/monthly-financials', [\App\Http\Controllers\Admin\ReportController::class, 'monthlyFinancials'])->name('monthly_financials');
    Route::get('/treasury', [\App\Http\Controllers\Admin\TreasuryController::class, 'index'])->name('treasury.index');

    Route::resource('deals', DoctorDealController::class);
    Route::post('deals/{deal}/pay', [DoctorDealController::class, 'markAsPaid'])->name('deals.pay');
    Route::get('deals/{deal}/invoices', [DoctorDealController::class, 'showInvoices'])->name('deals.invoices');
    Route::post('deals/{deal}/toggle-active', [DoctorDealController::class, 'toggleActive'])->name('deals.toggleActive');
    Route::post('deals/{deal}/toggle-archive', [DoctorDealController::class, 'toggleArchive'])->name('deals.toggleArchive');
    Route::get('admin/deals/archived', [DoctorDealController::class, 'archived'])->name('deals.archived');

    Route::resource('warehouses', WarehouseController::class);
    Route::prefix('warehouses/{warehouse}/stock')->name('warehouses.stock.')->group(function () {

        Route::get('add', [WarehouseController::class, 'addStock'])->name('add');
        Route::post('add', [WarehouseController::class, 'storeStock'])->name('store');

        Route::get('return', [WarehouseController::class, 'returnStock'])->name('return');
        Route::post('return', [WarehouseController::class, 'processReturnStock'])->name('return.process');
    });

    Route::prefix('reports')->name('reports.')->group(function () {

        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/province/{id}', [ReportController::class, 'showProvince'])->name('province');
        Route::get('/center/{id}', [ReportController::class, 'showCenter'])->name('center');
        Route::get('/pharmacist/{id}', [ReportController::class, 'showPharmacist'])->name('pharmacist');
        Route::get('/monthly-financials', [\App\Http\Controllers\Admin\ReportController::class, 'monthlyFinancials'])->name('monthly_financials');


        Route::prefix('representatives')->name('representatives.')->group(function () {
            Route::get('/', [ReportController::class, 'representativesIndex'])->name('index');
            Route::get('/{id}', [ReportController::class, 'showRepresentative'])->name('show');
        });
        Route::get('doctors-balance', [App\Http\Controllers\Admin\DoctorBalanceController::class, 'index'])->name('doctors_balance');
        Route::prefix('doctors')->name('doctors.')->group(function () {
            Route::get('/', [ReportController::class, 'doctorsIndex'])->name('index');
            Route::get('/province/{id}', [ReportController::class, 'showDoctorProvince'])->name('province');
            Route::get('/center/{id}', [ReportController::class, 'showDoctorCenter'])->name('center');
            Route::get('/doctor/{id}', [ReportController::class, 'showDoctor'])->name('show');
            Route::post('/doctor/{id}/pay', [ReportController::class, 'payDoctorCommission'])->name('pay');
        });

        Route::prefix('zone-risk')->name('zone_risk.')->group(function () {
            Route::get('/', [ZoneReportController::class, 'index'])->name('index');
            Route::get('/export', [ZoneReportController::class, 'export'])->name('export');
            Route::get('/{id}', [ZoneReportController::class, 'show'])->name('show');
        });

        Route::get('zone-risk-shortcut', [ZoneReportController::class, 'index'])->name('zone_risk');
    });
});






Route::middleware(['auth', 'role:viewer'])->prefix('viewer')->name('viewer.')->group(function () {

    Route::get('/', [\App\Http\Controllers\Viewer\ViewerDashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard/line/{id}', [\App\Http\Controllers\Viewer\ViewerDashboardController::class, 'lineDashboard'])->name('dashboard.line');

    // Read-only resources (only index + show)
    Route::resource('drugs', \App\Http\Controllers\Viewer\ViewerDrugController::class)->only(['index', 'show']);
    Route::resource('provinces', \App\Http\Controllers\Viewer\ViewerProvinceController::class)->only(['index']);
    Route::resource('centers', \App\Http\Controllers\Viewer\ViewerCenterController::class)->only(['index', 'show']);
    Route::resource('doctors', \App\Http\Controllers\Viewer\ViewerDoctorController::class)->only(['index', 'show']);
    Route::resource('pharmacists', \App\Http\Controllers\Viewer\ViewerPharmacistController::class)->only(['index', 'show']);
    Route::resource('representatives', \App\Http\Controllers\Viewer\ViewerRepresentativeController::class)->only(['index', 'show']);
    Route::resource('zones', \App\Http\Controllers\Viewer\ViewerZoneController::class)->only(['index', 'show']);
    Route::resource('warehouses', \App\Http\Controllers\Viewer\ViewerWarehouseController::class)->only(['index', 'show']);
    Route::resource('deals', \App\Http\Controllers\Viewer\ViewerDoctorDealController::class)->only(['index']);
    Route::get('deals/{deal}/invoices', [\App\Http\Controllers\Viewer\ViewerDoctorDealController::class, 'showInvoices'])->name('deals.invoices');
    Route::get('deals/archived', [\App\Http\Controllers\Viewer\ViewerDoctorDealController::class, 'archived'])->name('deals.archived');

    // Invoices (read-only)
    Route::get('invoices/export', [\App\Http\Controllers\Viewer\ViewerInvoiceController::class, 'export'])->name('invoices.export');
    Route::resource('invoices', \App\Http\Controllers\Viewer\ViewerInvoiceController::class)->only(['index', 'show']);
    Route::get('invoices/{invoice}/payments', [\App\Http\Controllers\Viewer\ViewerInvoiceController::class, 'payments'])->name('invoices.payments');
    Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Viewer\ViewerInvoiceController::class, 'printPdf'])->name('invoices.pdf');

    // General expenses (read-only)
    Route::get('general-expenses', [\App\Http\Controllers\Viewer\ViewerGeneralExpenseController::class, 'index'])->name('general-expenses.index');

    // Financial views
    Route::get('/monthly-financials', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'monthlyFinancials'])->name('monthly_financials');
    Route::get('/treasury', [\App\Http\Controllers\Viewer\ViewerTreasuryController::class, 'index'])->name('treasury.index');

    // Reports (read-only)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'index'])->name('index');
        Route::get('/province/{id}', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'showProvince'])->name('province');
        Route::get('/center/{id}', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'showCenter'])->name('center');
        Route::get('/pharmacist/{id}', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'showPharmacist'])->name('pharmacist');
        Route::get('/monthly-financials', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'monthlyFinancials'])->name('monthly_financials');

        Route::prefix('representatives')->name('representatives.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'representativesIndex'])->name('index');
            Route::get('/{id}', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'showRepresentative'])->name('show');
        });

        Route::get('doctors-balance', [\App\Http\Controllers\Viewer\ViewerDoctorBalanceController::class, 'index'])->name('doctors_balance');

        Route::prefix('doctors')->name('doctors.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'doctorsIndex'])->name('index');
            Route::get('/province/{id}', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'showDoctorProvince'])->name('province');
            Route::get('/center/{id}', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'showDoctorCenter'])->name('center');
            Route::get('/doctor/{id}', [\App\Http\Controllers\Viewer\ViewerReportController::class, 'showDoctor'])->name('show');
        });

        Route::prefix('zone-risk')->name('zone_risk.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Viewer\ViewerZoneReportController::class, 'index'])->name('index');
            Route::get('/export', [\App\Http\Controllers\Viewer\ViewerZoneReportController::class, 'export'])->name('export');
            Route::get('/{id}', [\App\Http\Controllers\Viewer\ViewerZoneReportController::class, 'show'])->name('show');
        });

        Route::get('zone-risk-shortcut', [\App\Http\Controllers\Viewer\ViewerZoneReportController::class, 'index'])->name('zone_risk');
    });
});


Route::middleware(['auth', 'role:accountant'])->prefix('accountant')->name('accountant.')->group(function () {


});

require __DIR__ . '/auth.php';
