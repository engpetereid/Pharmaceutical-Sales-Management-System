<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralExpense;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GeneralExpenseController extends Controller
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

        return view('admin.general_expenses.index', compact('expenses', 'totalExpenses'));
    }

    public function create()
    {
        return view('admin.general_expenses.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'expense_date' => 'required|date',
        ]);

        GeneralExpense::create([
            'amount' => $request->amount,
            'description' => $request->description,
            'expense_date' => $request->expense_date,
        ]);

        return redirect()->route('admin.general-expenses.index')->with(['success' => 'تم إضافة المصروف العام بنجاح']);
    }

    public function edit($id)
    {
        $expense = GeneralExpense::findOrFail($id);
        return view('admin.general_expenses.edit', compact('expense'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'description'  => 'required|string|max:255',
            'expense_date' => 'required|date',
        ]);

        $expense = GeneralExpense::findOrFail($id);
        $expense->update([
            'amount'       => $request->amount,
            'description'  => $request->description,
            'expense_date' => $request->expense_date,
        ]);

        return redirect()->route('admin.general-expenses.index')->with(['success' => 'تم تعديل المصروف العام بنجاح']);
    }

    public function destroy($id)
    {
        $expense = GeneralExpense::findOrFail($id);
        $expense->delete();

        return redirect()->route('admin.general-expenses.index')->with(['success' => 'تم حذف المصروف بنجاح']);
    }
}
