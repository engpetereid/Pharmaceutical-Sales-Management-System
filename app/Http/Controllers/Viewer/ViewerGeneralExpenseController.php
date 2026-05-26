<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\GeneralExpense;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ViewerGeneralExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = GeneralExpense::query();

        // فلترة بالتواريخ
        if ($request->filled('start_date')) {
            $query->whereDate('expense_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('expense_date', '<=', $request->end_date);
        }

        // فلترة بالنص (بيان المصروف)
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $totalExpenses = (clone $query)->sum('amount');
        $expenses = $query->orderBy('expense_date', 'desc')->paginate(15)->withQueryString();

        return view('viewer.general_expenses.index', compact('expenses', 'totalExpenses'));
    }


}
