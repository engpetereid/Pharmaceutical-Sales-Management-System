<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إضافة عمود returned_quantity في تفاصيل الفاتورة لتتبع الكمية المرتجعة لكل صنف
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->integer('returned_quantity')->default(0)->after('quantity');
        });

        // 2. جدول المرتجعات الرئيسي (ترويسة إيصال المرتجع)
        Schema::create('invoice_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->date('return_date');
            $table->decimal('total_return_public_price', 10, 2)->default(0);
            $table->decimal('total_return_discount', 10, 2)->default(0);
            $table->decimal('total_return_amount', 10, 2)->default(0); // صافي قيمة المرتجع
            $table->decimal('refund_amount', 10, 2)->default(0); // المبلغ المسترد نقداً إن وجد
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'return_date']);
        });

        // 3. جدول بنود المرتجع (الأصناف المرتجعة)
        Schema::create('invoice_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_return_id')->constrained('invoice_returns')->cascadeOnDelete();
            $table->foreignId('invoice_detail_id')->constrained('invoice_details')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->integer('quantity'); // الكمية المرتجعة
            $table->decimal('unit_price', 10, 2);
            $table->decimal('pharmacist_discount_percentage', 5, 2)->default(0);
            $table->decimal('subtotal_public', 10, 2); // quantity * unit_price
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('subtotal_net', 10, 2); // subtotal_public - discount_amount
            $table->timestamps();

            $table->index(['invoice_return_id', 'drug_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_return_items');
        Schema::dropIfExists('invoice_returns');

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });
    }
};
