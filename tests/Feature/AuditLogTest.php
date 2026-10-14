<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Drug;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'role' => 'admin',
        ]);
    }

    public function test_creating_updating_and_deleting_model_automatically_logs_audit_events()
    {
        $this->actingAs($this->admin);

        // 1. إنشاء دواء جديد
        $drug = Drug::create([
            'name' => 'بنادول إكسترا',
            'price' => 120.00,
            'line' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'module' => 'Drug',
            'entity_id' => $drug->id,
            'user_id' => $this->admin->id,
        ]);

        $createLog = AuditLog::where('module', 'Drug')->where('entity_id', $drug->id)->where('action', 'created')->first();
        $this->assertNotNull($createLog->new_values);
        $this->assertEquals('بنادول إكسترا', $createLog->new_values['name']);

        // 2. تعديل السعر
        $drug->update([
            'price' => 150.00,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'module' => 'Drug',
            'entity_id' => $drug->id,
        ]);

        $updateLog = AuditLog::where('module', 'Drug')->where('entity_id', $drug->id)->where('action', 'updated')->first();
        $this->assertNotNull($updateLog->changes);
        $this->assertArrayHasKey('price', $updateLog->changes);
        $this->assertEquals(120.00, $updateLog->changes['price']['old']);
        $this->assertEquals(150.00, $updateLog->changes['price']['new']);

        // 3. حذف الدواء
        $drug->delete();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'module' => 'Drug',
            'entity_id' => $drug->id,
        ]);

        $deleteLog = AuditLog::where('module', 'Drug')->where('entity_id', $drug->id)->where('action', 'deleted')->first();
        $this->assertNotNull($deleteLog->old_values);
    }

    public function test_user_authentication_events_are_recorded_in_audit_log()
    {
        // 1. تسجيل دخول ناجح
        $response = $this->post('/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login_success',
            'module' => 'Auth',
            'user_id' => $this->admin->id,
        ]);

        // 2. تسجيل خروج
        $this->post('/admin/logout');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'logout',
            'module' => 'Auth',
            'user_id' => $this->admin->id,
        ]);

        // 3. محاولة دخول فاشلة
        $this->post('/login', [
            'email' => 'wrong@test.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login_failed',
            'module' => 'Auth',
            'outcome' => 'failed',
        ]);
    }

    public function test_admin_can_view_audit_logs_index_page_and_filter()
    {
        AuditService::log(
            action: 'updated',
            module: 'Invoice',
            description: 'تعديل الفاتورة رقم 500',
            userId: $this->admin->id,
            userName: $this->admin->name,
            userRole: $this->admin->role
        );

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index'));

        $response->assertOk();
        $response->assertSee('سجل العمليات والرقابة');
        $response->assertSee('تعديل الفاتورة رقم 500');
    }

    public function test_admin_can_fetch_audit_log_details_via_json()
    {
        $log = AuditService::log(
            action: 'created',
            module: 'Pharmacist',
            description: 'إضافة صيدلية الأمل',
            newValues: ['name' => 'صيدلية الأمل', 'phone' => '01000000000'],
            userId: $this->admin->id,
            userName: $this->admin->name,
            userRole: $this->admin->role
        );

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.show', $log->id));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $log->id,
            'description' => 'إضافة صيدلية الأمل',
        ]);
    }

    public function test_admin_can_export_audit_logs_to_csv()
    {
        AuditService::log(
            action: 'created',
            module: 'Drug',
            description: 'إضافة صنف دواء',
            userId: $this->admin->id
        );

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', ['export' => 'excel']));

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-disposition'), 'attachment; filename=audit_log_'));
    }

    public function test_warehouse_stock_addition_is_recorded_in_audit_log()
    {
        $warehouse = Warehouse::create([
            'name' => 'المخزن الرئيسي للاختبار',
            'type' => 'main',
        ]);

        $drug = Drug::create([
            'name' => 'كونجستال',
            'price' => 50.00,
            'line' => 1,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.warehouses.stock.store', $warehouse->id), [
            'items' => [
                [
                    'drug_id' => $drug->id,
                    'quantity' => 100,
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_added',
            'module' => 'Warehouse',
            'entity_id' => $warehouse->id,
            'user_id' => $this->admin->id,
        ]);

        $log = AuditLog::where('action', 'stock_added')->where('entity_id', $warehouse->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('كونجستال', $log->description);
        $this->assertEquals('Warehouse', $log->module);
    }

    public function test_warehouse_stock_return_is_recorded_in_audit_log()
    {
        $warehouse = Warehouse::create([
            'name' => 'مخزن الجيزة',
            'type' => 'main',
        ]);

        $drug = Drug::create([
            'name' => 'أوجمنتين',
            'price' => 120.00,
            'line' => 1,
        ]);

        $warehouse->drugs()->attach($drug->id, ['quantity' => 50]);

        $response = $this->actingAs($this->admin)->post(route('admin.warehouses.stock.return.process', $warehouse->id), [
            'drug_id' => $drug->id,
            'quantity' => 15,
            'reason' => 'صنف تالف أثناء النقل',
            'notes' => 'ملاحظات المعاينة',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_returned',
            'module' => 'Warehouse',
            'entity_id' => $warehouse->id,
            'user_id' => $this->admin->id,
        ]);

        $log = AuditLog::where('action', 'stock_returned')->where('entity_id', $warehouse->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('أوجمنتين', $log->description);
        $this->assertStringContainsString('صنف تالف أثناء النقل', $log->description);
        $this->assertEquals(35, $warehouse->drugs()->where('drug_id', $drug->id)->first()->pivot->quantity);
    }
}
