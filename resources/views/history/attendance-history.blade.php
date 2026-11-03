@extends('layouts.app')
@section('title', 'Attendance History - Attendance Register')
@section('header_title', 'Attendance History')

@section('content')

<div class="flex gap-2 mb-5">
    <a href="?class={{ $classFilter }}&roll={{ $rollFilter }}&view=day"
        class="text-xs font-bold px-4 py-2 rounded-lg {{ $viewMode === 'day' ? 'bg-forest text-white' : 'bg-white border border-gray-200 text-ink-soft' }}">
        By Date
    </a>
    <a href="?class={{ $classFilter }}&view=month"
        class="text-xs font-bold px-4 py-2 rounded-lg {{ $viewMode === 'month' ? 'bg-forest text-white' : 'bg-white border border-gray-200 text-ink-soft' }}">
        Full Month
    </a>
</div>

<form method="get" class="bg-white border border-gray-200 rounded-xl p-4 mb-5 shadow-sm flex flex-wrap gap-3 items-end">
    <input type="hidden" name="view" value="{{ $viewMode }}">
    <div class="flex-1 min-w-[140px]">
        <label class="block text-xs font-semibold text-ink-soft mb-1.5">Class</label>
        <select name="class" class="history-class-select w-full border rounded-lg px-3 py-2.5 text-sm focus:outline-none">
            <option value="">Select a class</option>
            @if($viewMode === 'day')
            <option value="__all__" @if($allClassesSelected) selected @endif>All Classes</option>
            @endif
            @foreach($allClasses as $c)
            <option value="{{ $c['name'] }}" @if($c['is_selected']) selected @endif>Class {{ $c['name'] }}</option>
            @endforeach
        </select>
    </div>

    @if($viewMode === 'month')
    <div class="flex-1 min-w-[160px]">
        <label class="block text-xs font-semibold text-ink-soft mb-1.5">Month</label>
        <input type="month" name="month" value="{{ $monthStr }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-forest">
    </div>
    @else
    <div class="flex-1 min-w-[120px]">
        <label class="block text-xs font-semibold text-ink-soft mb-1.5">Roll</label>
        <input type="text" name="roll" value="{{ $rollFilter }}" placeholder="e.g. 12"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-forest">
    </div>
    <div class="flex-1 min-w-[160px]">
        <label class="block text-xs font-semibold text-ink-soft mb-1.5">Date</label>
        <input type="date" name="date" value="{{ $dateFilter }}"
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-forest">
    </div>
    @endif

    <button type="submit" class="bg-forest text-white text-sm font-bold px-5 py-2.5 rounded-lg hover:bg-forest-deep transition">
        View
    </button>

    @if($viewMode === 'day' && $classFilter && !$allClassesSelected)
    <a href="{{ route('history.export-attendance', ['class' => $classFilter, 'date' => $dateFilter]) }}"
        class="bg-gold text-white text-sm font-bold px-5 py-2.5 rounded-lg hover:opacity-90 transition">
        Download Excel
    </a>
    @elseif($viewMode === 'month' && $classFilter && !$allClassesSelected)
    <a href="{{ route('history.export-attendance-month', ['class' => $classFilter, 'month' => $monthStr]) }}"
        class="bg-gold text-white text-sm font-bold px-5 py-2.5 rounded-lg hover:opacity-90 transition">
        Download Excel (Month)
    </a>
    @endif
</form>

@if($viewMode === 'month')

    @if($classFilter && !$allClassesSelected)
        @if(count($records))
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
            @foreach($records as $r)
            <a href="{{ route('student-history-detail', $r['student']) }}?month={{ $monthStr }}"
                class="grid grid-cols-1 sm:grid-cols-12 gap-2 px-4 py-3 items-center {{ $loop->first ? '' : 'border-t border-gray-100' }} text-sm hover:bg-forest/5 transition">
                <div class="sm:col-span-5 font-semibold text-forest-deep break-words">
                    {{ $r['student']->name }}
                    <div class="text-[11px] font-normal text-ink-soft">Roll {{ $r['student']->roll_no }}</div>
                </div>
                <div class="sm:col-span-2 text-present font-bold">{{ $r['present'] }} Present</div>
                <div class="sm:col-span-2 text-absent font-bold">{{ $r['absent'] }} Absent</div>
                <div class="sm:col-span-2 text-ink-soft">{{ $r['marked_days'] }} day(s) marked</div>
                <div class="sm:col-span-1 text-right text-forest text-xs font-bold">Details →</div>
            </a>
            @endforeach
        </div>
        @else
        <div class="text-center text-ink-soft py-16 bg-white border border-gray-200 rounded-2xl">
            No students in this class.
        </div>
        @endif
    @else
    <div class="text-center text-ink-soft py-16 bg-white border border-gray-200 rounded-2xl">
        Please select one specific class to see its monthly summary.
    </div>
    @endif

@else

    @if($allClassesSelected || $classFilter || $rollFilter)
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-white border border-gray-200 rounded-2xl px-4 py-3 text-center shadow-sm">
            <div class="text-[11px] uppercase tracking-widest text-ink-soft">Present</div>
            <div class="text-xl font-bold text-present">{{ $presentCount }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-2xl px-4 py-3 text-center shadow-sm">
            <div class="text-[11px] uppercase tracking-widest text-ink-soft">Absent</div>
            <div class="text-xl font-bold text-absent">{{ $absentCount }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-2xl px-4 py-3 text-center shadow-sm">
            <div class="text-[11px] uppercase tracking-widest text-ink-soft">Class</div>
            <div class="text-xl font-bold text-forest-deep">
                @if($allClassesSelected)All Classes @else {{ $classFilter ?: 'All' }} @endif
            </div>
        </div>
    </div>

    @if(count($records))
    <div class="space-y-2.5">
        @foreach($records as $r)
        <div class="bg-white border border-gray-200 rounded-xl p-3.5 flex items-center gap-3 shadow-sm">
            <a href="{{ route('student-history-detail', $r['student']) }}" class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-10 h-10 rounded-full border-2 border-forest flex items-center justify-center text-sm font-bold text-forest flex-shrink-0 bg-forest/5">
                    {{ $r['student']->roll_no }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-forest-deep break-words">{{ $r['student']->name }}</div>
                    <div class="text-xs text-ink-soft">Class {{ $r['student']->class_name }}{{ $r['student']->section }}</div>
                </div>
            </a>
            <div class="flex-shrink-0 flex items-center gap-2">
                @if($r['status'] === 'present')
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-present">Present</span>
                <form method="post" action="{{ route('history.correct-attendance', $r['student']) }}" class="inline">
                    @csrf
                    <input type="hidden" name="date" value="{{ $dateFilter }}">
                    <input type="hidden" name="class" value="{{ $classFilter }}">
                    <input type="hidden" name="roll" value="{{ $rollFilter }}">
                    <input type="hidden" name="status" value="absent">
                    <label class="flex items-center gap-1 text-[10px] text-ink-soft mb-1 cursor-pointer">
                        <input type="checkbox" name="send_sms" value="yes" class="w-3 h-3 accent-absent">
                        Send SMS
                    </label>
                    <button type="submit" class="flex items-center gap-1 bg-red-50 text-absent border border-red-200 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-red-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Mark Absent
                    </button>
                </form>
                @elseif($r['status'] === 'absent')
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-absent">Absent</span>
                <form method="post" action="{{ route('history.correct-attendance', $r['student']) }}" class="inline">
                    @csrf
                    <input type="hidden" name="date" value="{{ $dateFilter }}">
                    <input type="hidden" name="class" value="{{ $classFilter }}">
                    <input type="hidden" name="roll" value="{{ $rollFilter }}">
                    <input type="hidden" name="status" value="present">
                    <button type="submit" class="flex items-center gap-1 bg-green-50 text-present border border-green-200 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-green-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Mark Present
                    </button>
                </form>
                @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-ink-soft">Not Marked</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center text-ink-soft py-16 bg-white border border-gray-200 rounded-2xl">
        No attendance records found for this class/roll and date.
    </div>
    @endif

    @else
    <div class="text-center text-ink-soft py-16 bg-white border border-gray-200 rounded-2xl">
        Please select a class and/or enter a roll number to view attendance history.
    </div>
    @endif

@endif


<style>
.history-class-select{
    min-height:44px!important;
    border:1px solid #d7e2da!important;
    border-radius:10px!important;
    background-color:#f8fbf9!important;
    color:#40594a!important;
    font-family:'Hind Siliguri',sans-serif!important;
    font-size:15px!important;
    font-weight:500!important;
    box-shadow:0 1px 3px rgba(30,60,40,.04);
}
.history-class-select:focus{
    border-color:#a9c4b1!important;
    background-color:#fff!important;
    box-shadow:0 0 0 3px rgba(169,196,177,.18)!important;
}
.history-class-select option{
    background:#f8fbf9!important;
    color:#40594a!important;
    font-family:'Hind Siliguri',sans-serif!important;
    font-size:15px!important;
}
</style>
@endsection
