@extends('layouts.app')
@section('title', 'Dashboard - Attendance Register')
@section('header_title', 'Welcome, ' . ($teacher->name ?? auth()->user()->username))

@section('content')

<style>
/* Slightly larger Quick Access cards for comfortable reading and tapping */
.dashboard-quick-access-grid > a{min-height:124px!important;padding:20px 21px!important}
.dashboard-quick-access-grid > a h2{font-size:15px!important;line-height:1.35!important}
.dashboard-quick-access-grid > a p{margin-top:7px!important;font-size:12.5px!important;line-height:1.4!important}
@media(max-width:767px){
  .dashboard-quick-access-grid > a{min-height:114px!important;padding:18px 19px!important}
}

/* Keep every Quick Access card title at the same enlarged size */
.dashboard-quick-access-grid > a h2{font-size:18px!important;line-height:1.35!important}
@media(max-width:767px){
  .dashboard-quick-access-grid > a h2{font-size:17px!important;line-height:1.35!important}
}

/* 12 soft accent colours: three summary counters + nine Quick Access cards */
.dashboard-summary-grid>div:nth-child(1){background:#eef5ff!important;border-color:#bfd5f4!important}
.dashboard-summary-grid>div:nth-child(2){background:#eaf8f1!important;border-color:#b8e2cb!important}
.dashboard-summary-grid>div:nth-child(3){background:#fff0f0!important;border-color:#f1c2c2!important}
.dashboard-quick-access-grid>a:nth-child(1){background:#edf5ff!important;border-color:#b9d5f5!important}
.dashboard-quick-access-grid>a:nth-child(1) h2{color:#245b9b!important}
.dashboard-quick-access-grid>a:nth-child(2){background:#eaf8f8!important;border-color:#b5e1df!important}
.dashboard-quick-access-grid>a:nth-child(2) h2{color:#176b68!important}
.dashboard-quick-access-grid>a:nth-child(3){background:#f3efff!important;border-color:#d2c5f2!important}
.dashboard-quick-access-grid>a:nth-child(3) h2{color:#5a4398!important}
.dashboard-quick-access-grid>a:nth-child(4){background:#edf8ef!important;border-color:#bfdfc4!important}
.dashboard-quick-access-grid>a:nth-child(4) h2{color:#28643b!important}
.dashboard-quick-access-grid>a:nth-child(5){background:#eaf7f5!important;border-color:#b8ded6!important}
.dashboard-quick-access-grid>a:nth-child(5) h2{color:#21675b!important}
.dashboard-quick-access-grid>a:nth-child(6){background:#fff6e9!important;border-color:#eed8b2!important}
.dashboard-quick-access-grid>a:nth-child(6) h2{color:#80551c!important}
.dashboard-quick-access-grid>a:nth-child(7){background:#fff0f3!important;border-color:#f0c4cf!important}
.dashboard-quick-access-grid>a:nth-child(7) h2{color:#91445a!important}
.dashboard-quick-access-grid>a:nth-child(8){background:#edf7ff!important;border-color:#bfdcf1!important}
.dashboard-quick-access-grid>a:nth-child(8) h2{color:#28628a!important}
.dashboard-quick-access-grid>a:nth-child(9){background:#f2f3f7!important;border-color:#d0d5e0!important}
.dashboard-quick-access-grid>a:nth-child(9) h2{color:#4b5874!important}


/* Deep, distinct Quick Access colours with high-contrast readable text */
.dashboard-quick-access-grid>a:nth-child(1){background:#245a91!important;border-color:#194875!important}
.dashboard-quick-access-grid>a:nth-child(2){background:#176b65!important;border-color:#10544f!important}
.dashboard-quick-access-grid>a:nth-child(3){background:#5b478f!important;border-color:#493773!important}
.dashboard-quick-access-grid>a:nth-child(4){background:#356b43!important;border-color:#285536!important}
.dashboard-quick-access-grid>a:nth-child(5){background:#176b78!important;border-color:#10545f!important}
.dashboard-quick-access-grid>a:nth-child(6){background:#8a5a18!important;border-color:#704710!important}
.dashboard-quick-access-grid>a:nth-child(7){background:#91465b!important;border-color:#743749!important}
.dashboard-quick-access-grid>a:nth-child(8){background:#285d85!important;border-color:#1e4969!important}
.dashboard-quick-access-grid>a:nth-child(9){background:#4b5874!important;border-color:#3b465d!important}
.dashboard-quick-access-grid>a h2{color:#fff!important}
.dashboard-quick-access-grid>a p{color:rgba(255,255,255,.88)!important}
.dashboard-quick-access-grid>a{box-shadow:0 3px 9px rgba(20,35,55,.13)!important}
.dashboard-quick-access-grid>a:hover{filter:brightness(1.08)}


/* Final contrast override: force all Quick Access card text and icons to white */
.dashboard-quick-access-grid>a,
.dashboard-quick-access-grid>a *,
.dashboard-quick-access-grid>a h2,
.dashboard-quick-access-grid>a p,
.dashboard-quick-access-grid>a span{color:#fff!important}
.dashboard-quick-access-grid>a svg{color:#fff!important;stroke:currentColor!important}

</style>


@if($teacher)
<div class="mb-2 font-display text-[11px] tracking-widest uppercase text-ink-soft">
    Today · {{ \Carbon\Carbon::parse($today)->format('l, d F Y') }}
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
    <div class="bg-white border border-gray-200 rounded-2xl px-4 py-4 text-center shadow-sm">
        <div class="text-[11px] uppercase tracking-widest text-ink-soft">Total</div>
        <div class="text-2xl font-bold text-forest-deep mt-1">{{ $totalStudents }}</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-2xl px-4 py-4 text-center shadow-sm">
        <div class="text-[11px] uppercase tracking-widest text-ink-soft">Present</div>
        <div class="text-2xl font-bold text-present mt-1">{{ $presentCount }}</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-2xl px-4 py-4 text-center shadow-sm">
        <div class="text-[11px] uppercase tracking-widest text-ink-soft">Absent</div>
        <div class="text-2xl font-bold text-absent mt-1">{{ $absentCount }}</div>
    </div>
</div>

@if(count($teacherClasses))
<div class="font-display text-[11px] tracking-widest uppercase text-ink-soft mb-3">
    Your Assigned Class{{ count($teacherClasses) > 1 ? 'es' : '' }} — Take Attendance
</div>

<div class="grid gap-4 mb-6">
    @foreach($teacherClasses as $className)
    <a href="{{ route('attendance.show', ['class' => $className]) }}"
        class="block bg-white border-2 border-forest/20 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-forest transition">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-forest text-white flex items-center justify-center text-xl font-bold flex-shrink-0">
                {{ $className }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-display text-[11px] tracking-widest uppercase text-gold mb-0.5">Class</div>
                <h2 class="text-xl font-bold text-forest-deep">Class {{ $className }}</h2>
                <p class="text-sm text-ink-soft mt-1">Mark present / absent · SMS to parents of absentees</p>
            </div>
            <div class="text-forest font-bold text-sm flex-shrink-0 hidden sm:block">Open →</div>
        </div>
    </a>
    @endforeach
</div>
<a href="{{ route('absent-report') }}" class="block bg-white border-2 border-absent/20 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-absent transition mb-4">
    <h2 class="text-lg font-bold text-absent">Absent Yesterday</h2>
    <p class="text-sm text-ink-soft mt-1">Quick list of who was absent on the previous day</p>
</a>

<a href="{{ route('class-history') }}" class="block bg-white border border-gray-200 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-forest transition mb-6">
    <h2 class="text-lg font-bold text-forest-deep">Class Attendance History</h2>
    <p class="text-sm text-ink-soft mt-1">Check by date, full month, or a single student's absent days</p>
</a>
@else
<div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-5 mb-6 text-sm">
    No class has been assigned to you yet. Please contact the admin.
</div>
@endif
@endif

@if(auth()->user()->is_admin)
<div class="font-display text-[11px] tracking-widest uppercase text-ink-soft mt-6 mb-3">Today · All Classes</div>

<div class="dashboard-summary-grid grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
    <div class="bg-white border border-gray-200 rounded-2xl px-4 py-4 text-center shadow-sm">
        <div class="text-[11px] uppercase tracking-widest text-ink-soft">Students</div>
        <div class="text-2xl font-bold text-forest-deep mt-1">{{ $adminTotal }}</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-2xl px-4 py-4 text-center shadow-sm">
        <div class="text-[11px] uppercase tracking-widest text-ink-soft">Present</div>
        <div class="text-2xl font-bold text-present mt-1">{{ $adminPresent }}</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-2xl px-4 py-4 text-center shadow-sm">
        <div class="text-[11px] uppercase tracking-widest text-ink-soft">Absent</div>
        <div class="text-2xl font-bold text-absent mt-1">{{ $adminAbsent }}</div>
    </div>
</div>

<div class="font-display text-[11px] tracking-widest uppercase text-ink-soft mb-3">Quick Access</div>

<div class="dashboard-quick-access-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @php
        $links = [
            ['attendance.show', 'Take Student Attendance', 'Mark attendance for any class'],
            ['students.index', 'Student List', 'View, add, edit or upload student list'],
            ['history.attendance', 'Student Attendance History', 'By date or full month per class'],
            ['teachers.index', 'Teachers', 'Create logins and assign classes'],
            ['teacher-attendance.show', 'Teacher Attendance', "Mark today's attendance for teachers"],
            ['teacher-attendance-history', 'Teacher Attendance History', 'Monthly present/absent summary & Excel download'],
            ['students.upload', 'Upload Excel', 'Bulk import students from Excel file'],
            ['sms-status.index', 'SMS Status', 'See failed/pending absence SMS and retry them'],
            ['emergency-sms.index', 'Bulk SMS', 'Send an urgent notice to all classes or selected students'],
        ];
    @endphp
    @foreach($links as [$route, $title, $desc])
    <a href="{{ route($route) }}" class="block bg-gradient-to-br from-green-50 to-white border border-green-200 rounded-xl p-5 shadow-sm hover:shadow-md hover:border-forest transition min-h-[112px]">
        <h2 class="text-base font-bold text-forest-deep">{{ $title }}</h2>
        <p class="text-[13px] text-ink-soft mt-2 leading-relaxed">{{ $desc }}</p>
    </a>
    @endforeach
</div>
@endif

@if(!$teacher && !auth()->user()->is_admin)
<div class="text-center text-ink-soft py-16 bg-white border border-gray-200 rounded-2xl">
    Your account is not linked to any teacher or admin role.<br>
    Please contact the administrator.
</div>
@endif

@endsection
