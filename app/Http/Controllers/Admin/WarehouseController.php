<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\Warehouse;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    public function index()
    {
        $warehouses = Warehouse::with(['parent', 'zones'])
            ->withCount('drugs')
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(10);

        return view('admin.warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        $mainWarehouses = Warehouse::where('type', 'main')->get();

        return view('admin.warehouses.create')->with('mainWarehouses', $mainWarehouses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:warehouses,name',
            'type' => 'required|in:main,sub',
            'parent_id' => 'nullable|required_if:type,sub|exists:warehouses,id',
        ], [
            'parent_id.required_if' => 'يجب اختيار المخزن الرئيسي التابع له.',
        ]);

        try {
            Warehouse::create($request->all());

            return redirect()->route('admin.warehouses.index')->with(['success' => 'تم إضافة المخزن بنجاح']);
        } catch (\Exception $e) {
            return redirect()->back()->with(['error' => 'حدث خطأ: '.$e->getMessage()])->withInput();
        }
    }

    public function show(Warehouse $warehouse)
    {
        $inventory = $warehouse->drugs()
            ->select('drugs.id', 'drugs.name', 'drugs.price', 'drugs.line')
            ->paginate(15);
        $warehouse->load('distributionAreas');

        return view('admin.warehouses.show', compact('warehouse', 'inventory'));
    }

    public function edit(Warehouse $warehouse)
    {
        $mainWarehouses = Warehouse::where('type', 'main')->where('id', '!=', $warehouse->id)->get();

        return view('admin.warehouses.edit', compact('warehouse', 'mainWarehouses'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:warehouses,name,'.$warehouse->id,
            'type' => 'required|in:main,sub',
            'parent_id' => 'nullable|required_if:type,sub|exists:warehouses,id',
        ]);

        try {
            $warehouse->update($request->all());

            return redirect()->route('admin.warehouses.index')->with(['success' => 'تم تحديث بيانات المخزن بنجاح']);
        } catch (\Exception $e) {
            return redirect()->back()->with(['error' => 'حدث خطأ: '.$e->getMessage()])->withInput();
        }
    }

    public function destroy(Warehouse $warehouse)
    {
        try {
            if ($warehouse->drugs()->count() > 0) {
                return redirect()->back()->with(['error' => 'لا يمكن حذف المخزن لأنه يحتوي على أرصدة أدوية.']);
            }
            if ($warehouse->zones()->count() > 0) {
                return redirect()->back()->with(['error' => 'لا يمكن حذف المخزن لأنه مسؤول عن توزيع مناطق معينة.']);
            }

            $warehouse->delete();

            return redirect()->route('admin.warehouses.index')->with(['success' => 'تم حذف المخزن بنجاح']);
        } catch (\Exception $e) {
            return redirect()->back()->with(['error' => $e->getMessage()]);
        }
    }

    public function addStock(Warehouse $warehouse)
    {
        $warehouse->load('parent');

        if ($warehouse->type == 'sub' && $warehouse->parent) {
            $drugs = $warehouse->parent->drugs()
                ->select('drugs.id', 'drugs.name', 'drugs.line')
                ->get()
                ->map(function ($drug) {
                    $drug->max_quantity = $drug->pivot->quantity;

                    return $drug;
                });
        } else {
            $drugs = \App\Models\Drug::select('id', 'name', 'line')->get()
                ->map(function ($drug) {
                    $drug->max_quantity = null;

                    return $drug;
                });
        }

        return view('admin.warehouses.stock.add', compact('warehouse', 'drugs'));
    }

    public function storeStock(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.drug_id' => 'required|exists:drugs,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $logItems = [];
            $drugIds = collect($request->items)->pluck('drug_id')->unique();
            $drugs = Drug::whereIn('id', $drugIds)->pluck('name', 'id');

            foreach ($request->items as $item) {
                $quantityToAdd = (int) $item['quantity'];
                $drugId = $item['drug_id'];
                $drugName = $drugs[$drugId] ?? "دواء #{$drugId}";

                $parentStockBefore = null;
                $parentStockAfter = null;

                if ($warehouse->type == 'sub' && $warehouse->parent) {
                    $parentStock = $warehouse->parent->drugs()->where('drug_id', $drugId)->first();

                    if (! $parentStock || $parentStock->pivot->quantity < $quantityToAdd) {
                        throw ValidationException::withMessages([
                            'items' => "الكمية المطلوبة للدواء (ID: $drugId) غير متوفرة في المخزن الرئيسي.",
                        ]);
                    }

                    $parentStockBefore = $parentStock->pivot->quantity;
                    $parentStockAfter = $parentStockBefore - $quantityToAdd;

                    $warehouse->parent->drugs()->updateExistingPivot($drugId, [
                        'quantity' => $parentStockAfter,
                    ]);
                }

                // استعلام واحد بدلاً من exists() + find()
                $existingStock = $warehouse->drugs()->where('drug_id', $drugId)->first();
                $stockBefore = $existingStock ? $existingStock->pivot->quantity : 0;
                $stockAfter = $stockBefore + $quantityToAdd;

                if ($existingStock) {
                    $warehouse->drugs()->updateExistingPivot($drugId, [
                        'quantity' => $stockAfter,
                    ]);
                } else {
                    $warehouse->drugs()->attach($drugId, [
                        'quantity' => $quantityToAdd,
                    ]);
                }

                $logItems[] = [
                    'drug_id' => $drugId,
                    'drug_name' => $drugName,
                    'quantity_added' => $quantityToAdd,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'parent_stock_before' => $parentStockBefore,
                    'parent_stock_after' => $parentStockAfter,
                ];
            }

            // تسجيل التدقيق للمخزن
            $isSub = ($warehouse->type == 'sub' && $warehouse->parent);
            $actionDesc = $isSub
                ? "تم تحويل ونقل رصيد من المخزن الرئيسي ({$warehouse->parent->name}) إلى المخزن الفرعي ({$warehouse->name})"
                : "تم توريد رصيد جديد إلى المخزن ({$warehouse->name})";

            $itemsSummary = collect($logItems)->map(fn ($i) => "{$i['drug_name']} (+{$i['quantity_added']})")->join('، ');

            AuditService::log(
                action: 'stock_added',
                module: 'Warehouse',
                entity: $warehouse,
                description: "{$actionDesc}: {$itemsSummary}",
                oldValues: [
                    'warehouse' => $warehouse->name,
                    'items' => collect($logItems)->map(fn ($i) => ['drug' => $i['drug_name'], 'stock' => $i['stock_before']])->toArray(),
                ],
                newValues: [
                    'warehouse' => $warehouse->name,
                    'operation' => $isSub ? 'transfer_from_parent' : 'stock_inflow',
                    'parent_warehouse' => $isSub ? $warehouse->parent->name : null,
                    'items' => collect($logItems)->map(fn ($i) => ['drug' => $i['drug_name'], 'added' => $i['quantity_added'], 'new_stock' => $i['stock_after']])->toArray(),
                ]
            );

            DB::commit();

            $message = ($warehouse->type == 'sub') ? 'تم نقل الكميات من المخزن الرئيسي بنجاح' : 'تم توريد الكميات للمخزن بنجاح';

            return redirect()->route('admin.warehouses.show', $warehouse->id)->with(['success' => $message]);

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function returnStock($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        // فقط الأدوية التي لها رصيد في المخزن
        $drugsWithStock = $warehouse->drugs()->wherePivot('quantity', '>', 0)->get();

        return view('admin.warehouses.stock.return', [
            'warehouse' => $warehouse,
            'drugs' => $drugsWithStock,
        ]);
    }

    public function processReturnStock(Request $request, $id)
    {
        $request->validate([
            'drug_id' => 'required|exists:drugs,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // المخزن مع العلاقة "parent" لمعرفة المخزن الرئيسي
        $warehouse = Warehouse::with('parent')->findOrFail($id);

        // التحقق من وجود الدواء في المخزن الحالي
        $stockRecord = $warehouse->drugs()->where('drug_id', $request->drug_id)->first();

        if (! $stockRecord) {
            return redirect()->back()->with(['error' => 'هذا الصنف غير موجود في المخزن.']);
        }

        $currentQty = $stockRecord->pivot->quantity;

        // التحقق من توفر الكمية
        if ($request->quantity > $currentQty) {
            return redirect()->back()
                ->withInput()
                ->with(['error' => "الكمية المراد إرجاعها ({$request->quantity}) أكبر من الرصيد المتوفر ({$currentQty})."]);
        }

        try {
            DB::beginTransaction();

            $drug = Drug::find($request->drug_id);
            $drugName = $drug ? $drug->name : "دواء #{$request->drug_id}";

            //  خصم الكمية من المخزن الحالي ( فرعي أو رئيسي)
            $newQty = $currentQty - $request->quantity;
            $warehouse->drugs()->updateExistingPivot($request->drug_id, ['quantity' => $newQty]);

            $parentName = null;
            $parentQtyBefore = null;
            $parentQtyAfter = null;

            // إذا كان مخزن فرعي وليه مخزن رئيسي نعيد الكمية للمخزن الرئيسي
            if ($warehouse->type == 'sub' && $warehouse->parent) {
                $parentWarehouse = $warehouse->parent;
                $parentName = $parentWarehouse->name;
                $parentStock = $parentWarehouse->drugs()->where('drug_id', $request->drug_id)->first();

                if ($parentStock) {
                    // تحديث رصيد الرئيسي
                    $parentQtyBefore = $parentStock->pivot->quantity;
                    $parentQtyAfter = $parentQtyBefore + $request->quantity;
                    $parentWarehouse->drugs()->updateExistingPivot($request->drug_id, ['quantity' => $parentQtyAfter]);
                } else {
                    $parentQtyBefore = 0;
                    $parentQtyAfter = $request->quantity;
                    $parentWarehouse->drugs()->attach($request->drug_id, ['quantity' => $request->quantity]);
                }
            }

            // وصف وتفاصيل التدقيق
            $isSub = ($warehouse->type == 'sub' && $warehouse->parent);
            $desc = $isSub
                ? "تم إرجاع رصيد من المخزن الفرعي ({$warehouse->name}) إلى المخزن الرئيسي ({$parentName}): {$drugName} (كمية {$request->quantity})"
                : "تم صرف/إرجاع رصيد من المخزن ({$warehouse->name}): {$drugName} (كمية {$request->quantity})";

            if ($request->filled('reason')) {
                $desc .= " - السبب: {$request->reason}";
            }

            AuditService::log(
                action: 'stock_returned',
                module: 'Warehouse',
                entity: $warehouse,
                description: $desc,
                oldValues: [
                    'warehouse' => $warehouse->name,
                    'drug' => $drugName,
                    'stock_before' => $currentQty,
                    'parent_stock_before' => $parentQtyBefore,
                ],
                newValues: [
                    'warehouse' => $warehouse->name,
                    'drug' => $drugName,
                    'returned_quantity' => $request->quantity,
                    'stock_after' => $newQty,
                    'parent_warehouse' => $parentName,
                    'parent_stock_after' => $parentQtyAfter,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                ]
            );

            DB::commit();

            $msg = ($warehouse->type == 'sub')
                ? 'تم تسجيل المرتجع وإعادة الكمية للمخزن الرئيسي بنجاح.'
                : 'تم خصم الكمية من المخزن الرئيسي بنجاح.';

            return redirect()->route('admin.warehouses.show', $warehouse->id)->with(['success' => $msg]);

        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with(['error' => 'حدث خطأ أثناء العملية: '.$e->getMessage()]);
        }
    }
}
