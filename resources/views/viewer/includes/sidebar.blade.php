<div class="main-menu menu-fixed menu-light menu-accordion menu-shadow sidebar" data-scroll-to-active="true">
    <div class="main-menu-content">
        <ul class="navigation navigation-main" id="main-menu-navigation" data-menu="menu-navigation">

            <li class="navigation-header">
                <span data-i18n="nav.category.dashboard">لوحات القيادة</span>
                <i class="la la-ellipsis-h ft-minus" data-toggle="tooltip" data-placement="right"
                   data-original-title="Dashboards"></i>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.dashboard') ? 'active' : '' }}">
                <a href="{{ route('viewer.dashboard') }}">
                    <i class="la la-home"></i>
                    <span class="menu-title">الرئيسية (عام)</span>
                </a>
            </li>

            <li class="nav-item {{ request()->is('viewer/dashboard/line/1') ? 'active' : '' }}">
                <a href="{{ route('viewer.dashboard.line', 1) }}">
                    <i class="la la-bar-chart" style="color: #1E9FF2;"></i>
                    <span class="menu-title">إحصائيات Line 1</span>
                </a>
            </li>

            <li class="nav-item {{ request()->is('viewer/dashboard/line/2') ? 'active' : '' }}">
                <a href="{{ route('viewer.dashboard.line', 2) }}">
                    <i class="la la-bar-chart" style="color: #FF9149;"></i>
                    <span class="menu-title">إحصائيات Line 2</span>
                </a>
            </li>

            {{-- العمليات والمخزون  --}}
            <li class="navigation-header">
                <span data-i18n="nav.category.operations">العمليات والمخزون</span>
                <i class="la la-ellipsis-h ft-minus"></i>
            </li>

            {{-- الفواتير --}}
            <li class="nav-item {{ request()->routeIs('viewer.invoices.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.invoices.index') }}">
                    <i class="la la-file-text"></i>
                    <span class="menu-title">فواتير المبيعات</span>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.deals.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.deals.index') }}">
                    <i class="la la-google"></i>
                    <span class="menu-title">اتفاقات الأطباء</span>
                </a>
            </li>

            {{-- التقارير --}}
            <li class="navigation-header">
                <span data-i18n="nav.category.reports">التقارير والتحليلات</span>
                <i class="la la-ellipsis-h ft-minus"></i>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.reports.zone_risk.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.reports.zone_risk.index') }}">
                    <i class="la la-pie-chart"></i>
                    <span class="menu-title">تقرير نسبة الجهاز</span>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.monthly_financials') ? 'active' : '' }}">
                <a href="{{ route('viewer.monthly_financials') }}">
                    <i class="la la-pie-chart"></i>
                    <span class="menu-title">الملخص المالى الشهري</span>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.treasury.index') ? 'active' : '' }}">
                <a href="{{ route('viewer.treasury.index') }}">
                    <i class="la la-pie-chart"></i>
                    <span class="menu-title">الخزنة</span>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.reports.doctors_balance') ? 'active' : '' }}">
                <a href="{{ route('viewer.reports.doctors_balance') }}">
                    <i class="la la-balance-scale"></i>
                    <span class="menu-title">كشف حساب الأطباء</span>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.zones.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.zones.index') }}">
                    <i class="la la-map-marker"></i>
                    <span class="menu-title">المناطق والمناديب</span>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.general-expenses.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.general-expenses.index') }}">
                    <i class="la la-money"></i>
                    <span class="menu-title">المصاريف</span>
                </a>
            </li>

            <li class="nav-item has-sub {{ request()->is('viewer/reports/*') && !request()->routeIs('viewer.reports.zone_risk.*') && !request()->routeIs('viewer.reports.doctors_balance') ? 'open' : '' }}">
                <a href="#"><i class="la la-line-chart"></i><span class="menu-title">تقارير الأداء</span></a>
                <ul class="menu-content">
                    <li class="{{ request()->routeIs('viewer.reports.index') ? 'active' : '' }}">
                        <a class="menu-item" href="{{ route('viewer.reports.index') }}">أداء الصيدليات</a>
                    </li>
                    <li class="{{ request()->routeIs('viewer.reports.doctors.index') ? 'active' : '' }}">
                        <a class="menu-item" href="{{ route('viewer.reports.doctors.index') }}">أداء الأطباء</a>
                    </li>
                    <li class="{{ request()->routeIs('viewer.reports.representatives.index') ? 'active' : '' }}">
                        <a class="menu-item" href="{{ route('viewer.reports.representatives.index') }}">أداء المناديب</a>
                    </li>
                </ul>
            </li>

            {{-- ================= البيانات الأساسية ================= --}}
            <li class="navigation-header">
                <span data-i18n="nav.category.settings">البيانات الأساسية</span>
                <i class="la la-ellipsis-h ft-minus"></i>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.doctors.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.doctors.index') }}"><i class="la la-user-md"></i><span class="menu-title">الأطباء</span></a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.pharmacists.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.pharmacists.index') }}"><i class="la la-hospital-o"></i><span class="menu-title">الصيدليات</span></a>
            </li>

            <li class="nav-item {{ request()->routeIs('viewer.representatives.*') ? 'active' : '' }}">
                <a href="{{ route('viewer.representatives.index') }}"><i class="la la-briefcase"></i><span class="menu-title">المناديب</span></a>
            </li>

            <li class="nav-item has-sub {{ request()->routeIs('viewer.drugs.*') || request()->routeIs('viewer.provinces.*') || request()->routeIs('viewer.centers.*') || request()->routeIs('viewer.warehouses.*') ? 'open' : '' }}">
                <a href="#"><i class="la la-cogs"></i><span class="menu-title">إعدادات النظام</span></a>
                <ul class="menu-content">
                    <li class="{{ request()->routeIs('viewer.warehouses.*') ? 'active' : '' }}">
                        <a class="menu-item" href="{{ route('viewer.warehouses.index') }}">جرد المخازن</a>
                    </li>
                    <li class="{{ request()->routeIs('viewer.drugs.*') ? 'active' : '' }}">
                        <a class="menu-item" href="{{ route('viewer.drugs.index') }}">الأدوية</a>
                    </li>
                    <li class="{{ request()->routeIs('viewer.provinces.*') ? 'active' : '' }}">
                        <a class="menu-item" href="{{ route('viewer.provinces.index') }}">المحافظات</a>
                    </li>
                    <li class="{{ request()->routeIs('viewer.centers.*') ? 'active' : '' }}">
                        <a class="menu-item" href="{{ route('viewer.centers.index') }}">المراكز</a>
                    </li>
                </ul>
            </li>

        </ul>
    </div>
</div>
