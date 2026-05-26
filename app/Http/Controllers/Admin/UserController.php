<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * عرض قائمة المستخدمين
     */
    public function index()
    {
        $users = User::latest()->paginate(15);
        return view('admin.users.index', compact('users'));
    }

    /**
     * عرض صفحة إنشاء مستخدم جديد
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * حفظ المستخدم الجديد
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:admin,viewer,accountant'],
        ], [
            'role.in' => 'الصلاحية المحددة غير صالحة.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('admin.users.index')
            ->with(['success' => 'تم إضافة المستخدم بنجاح.']);
    }

    /**
     * عرض صفحة التعديل
     */
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * تحديث بيانات المستخدم
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:admin,viewer,accountant'],
        ], [
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل لمستخدم آخر.',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ];

        // تحديث كلمة المرور فقط إذا تم إدخال كلمة مرور جديدة
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with(['success' => 'تم تحديث بيانات المستخدم بنجاح.']);
    }

    /**
     * حذف المستخدم
     */
    public function destroy(User $user)
    {
        // منع المستخدم من حذف نفسه
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')
                ->with(['error' => 'لا يمكنك حذف حسابك الشخصي أثناء تسجيل الدخول!']);
        }

        try {
            $user->delete();
            return redirect()->route('admin.users.index')
                ->with(['success' => 'تم حذف المستخدم بنجاح.']);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with(['error' => 'حدث خطأ أثناء الحذف: ' . $e->getMessage()]);
        }
    }
}
