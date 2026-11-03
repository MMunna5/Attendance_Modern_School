@extends('layouts.app')
@section('title', 'Teacher History - Attendance Register')
@section('header_title', $teacher->name . "'s Attendance")

@section('header_links')
<a href="{{ route('teacher-attendance-history') }}" class="nav-btn">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
    Back
</a>
@endsection

@section('content')
<div class="bg-white border border-gray-200 rounded-xl p-4 mb-5 shadow-sm flex items-center justify-between flex-wrap gap-3">
    <div class="font-bold text-forest-deep">{{ $teacher->name }}</div>
    <div class="flex gap-2">
        <a href="?month={{ $prevMonth }}" class="bg-gray-100 text-ink text-xs font-bold px-3 py-2 rounded-lg hover:bg-gray-200">&larr; {{ $prevMonth }}</a>
        <span class="px-3 py-2 text-xs font-bold text-forest-deep">{{ $monthStr }}</span>
        <a href="?month={{ $nextMonth }}" class="bg-gray-100 text-ink text-xs font-bold px-3 py-2 rounded-lg hover:bg-gray-200">{{ $nextMonth }} &rarr;</a>
    </div>
</div>

<div class="grid grid-cols-3 gap-3 mb-6">
    <div class="bg-white border rounded-xl p-3 text-center"><div class="text-xs text-ink-soft">Present</div><div class="text-xl font-bold text-present">{{ $presentCount }}</div></div>
    <div class="bg-white border rounded-xl p-3 text-center"><div class="text-xs text-ink-soft">Absent</div><div class="text-xl font-bold text-absent">{{ $absentCount }}</div></div>
    <div class="bg-white border rounded-xl p-3 text-center"><div class="text-xs text-ink-soft">Marked</div><div class="text-xl font-bold text-forest-deep">{{ $totalMarked }}</div></div>
</div>

<div class="grid grid-cols-2 gap-4">
    <div>
        <div class="text-xs font-semibold text-ink-soft mb-2 uppercase">Absent Dates</div>
        <div class="flex flex-wrap gap-1.5">
            @forelse($absentDates as $d)<span class="bg-red-50 text-absent text-xs font-semibold px-2 py-1 rounded">{{ $d->format('d M') }}</span>@empty<span class="text-xs text-ink-soft">None</span>@endforelse
        </div>
    </div>
    <div>
        <div class="text-xs font-semibold text-ink-soft mb-2 uppercase">Present Dates</div>
        <div class="flex flex-wrap gap-1.5">
            @forelse($presentDates as $d)<span class="bg-green-50 text-present text-xs font-semibold px-2 py-1 rounded">{{ $d->format('d M') }}</span>@empty<span class="text-xs text-ink-soft">None</span>@endforelse
        </div>
    </div>
</div>
@endsection
