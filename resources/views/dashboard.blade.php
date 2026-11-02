@extends('layouts.app')
@section('title', 'Dashboard - Attendance Register')
@section('header_title', 'Welcome, ' . ($teacher->name ?? auth()->user()->username))

@section('content')

<style>
<!-- Compact Quick Access cards to reduce dashboard vertical height -->
@media(min-width:768px){
  .dashboard-quick-access-grid > a{min-height:76px!important;padding:11px 12px!important}
}
.dashboard-quick-access-grid > a h2{font-size:13px!important;line-height:1.25!important}
.dashboard-quick-access-grid > a p{margin-top:5px!important;font-size:11px!important;line-height:1.3!important}
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

<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
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
            ['emergency-sms.index', 'Emergency SMS / Notice', 'Send an urgent notice to all classes or selected students'],
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
