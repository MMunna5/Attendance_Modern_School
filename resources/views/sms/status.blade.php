@extends('layouts.app')
@section('title', 'SMS Status - Attendance Register')
@section('header_title', 'SMS Delivery Status')

@section('header_links')
<a href="{{ route('dashboard') }}" class="nav-btn">Dashboard</a>
<form method="post" action="{{ route('logout') }}" class="inline">
    @csrf
    <button type="submit" class="nav-btn nav-btn-danger">Log Out</button>
</form>
@endsection

@section('content')

<form method="get" class="bg-white border border-gray-200 rounded-xl p-4 mb-5 shadow-sm flex flex-wrap gap-3 items-end">
    <div>
        <label for="sms-month" class="block text-xs font-semibold text-ink-soft mb-1.5">Month</label>
        <input id="sms-month" type="month" name="month" value="{{ $monthStr }}" class="border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
    </div>
    <button type="submit" class="bg-forest text-white text-sm font-bold px-5 py-2.5 rounded-lg hover:bg-forest-deep transition">View Month</button>
</form>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    @foreach([['Student', $counts['student']], ['Teacher', $counts['teacher']]] as [$label, $c])
    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
        <div class="font-display text-[11px] tracking-widest uppercase text-ink-soft mb-3">{{ $label }} SMS · {{ $monthStr }}</div>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div><div class="text-lg font-bold text-present">{{ $c['sent'] }}</div><div class="text-[10px] text-ink-soft">Sent</div></div>
            <div><div class="text-lg font-bold text-absent">{{ $c['failed'] }}</div><div class="text-[10px] text-ink-soft">Failed</div></div>
            <div><div class="text-lg font-bold text-amber-600">{{ $c['pending'] }}</div><div class="text-[10px] text-ink-soft">Pending</div></div>
        </div>
        <div class="text-xs text-ink-soft mt-3">Estimated billable SMS segments: <strong>{{ $c['segments'] }}</strong></div>
    </div>
    @endforeach
</div>

<div class="text-sm text-ink-soft mb-5">
    Failed messages show the provider error and can be retried. Pending messages are waiting for the queue worker. Showing up to 200 messages in each list.
</div>

@foreach([['Student — Failed', $studentFailed, 'student'], ['Student — Pending', $studentPending, 'student'], ['Teacher — Failed', $teacherFailed, 'teacher'], ['Teacher — Pending', $teacherPending, 'teacher']] as [$label, $list, $kind])
<div class="font-display text-[11px] tracking-widest uppercase text-ink-soft mb-3 mt-6 flex flex-wrap items-center justify-between gap-3">
    <span>{{ $label }} ({{ $list->count() }})</span>
    @if(str_contains($label, 'Failed') && $list->count() > 0)
    <form method="post" action="{{ route('sms-status.index') }}" class="inline">
        @csrf
        <input type="hidden" name="action" value="retry_all">
        <input type="hidden" name="kind" value="{{ $kind }}">
        <button type="submit" onclick="return confirm('Retry all failed {{ $kind }} SMS?')" class="bg-gold text-white text-xs font-bold px-3 py-2 rounded-lg hover:opacity-90 transition">Retry All {{ ucfirst($kind) }} Failed</button>
    </form>
    @endif
</div>
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm mb-5">
    @forelse($list as $msg)
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 px-4 py-3 items-center border-t border-gray-100 text-sm first:border-t-0">
        <div class="sm:col-span-3 font-semibold text-forest-deep break-words">
            {{ $kind === 'student' ? ($msg->student->name ?? 'Deleted student') : ($msg->teacher->name ?? 'Deleted teacher') }}
            @if($kind === 'student' && $msg->student)
            <div class="text-[11px] font-normal text-ink-soft">Roll {{ $msg->student->roll_no }} · Class {{ $msg->student->class_name }}{{ $msg->student->section }}</div>
            @endif
        </div>
        <div class="sm:col-span-2 text-ink-soft break-words">
            @if($kind === 'student')
                @if($msg->student && $msg->student->parent_mobile){{ $msg->student->parent_mobile }}@else<span class="text-absent">No phone</span>@endif
            @else
                @if($msg->teacher && $msg->teacher->mobile){{ $msg->teacher->mobile }}@else<span class="text-absent">No phone</span>@endif
            @endif
        </div>
        <div class="sm:col-span-2 text-ink-soft">{{ $msg->date->format('d M Y') }}</div>
        <div class="sm:col-span-3 text-[11px] {{ str_contains($label, 'Failed') ? 'text-absent' : 'text-ink-soft' }} break-words">
            @if(str_contains($label, 'Failed')){{ $msg->last_error ?: '—' }} (attempt {{ $msg->attempts }})@else Queued, waiting for worker · Attempts: {{ $msg->attempts }}@endif
        </div>
        <div class="sm:col-span-2 text-right">
            @if(str_contains($label, 'Failed'))
            <form method="post" action="{{ route('sms-status.index') }}" class="inline">
                @csrf
                <input type="hidden" name="action" value="retry">
                <input type="hidden" name="kind" value="{{ $kind }}">
                <input type="hidden" name="id" value="{{ $msg->id }}">
                <button type="submit" class="bg-gold text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:opacity-90 transition">Retry</button>
            </form>
            @endif
        </div>
    </div>
    @empty
    <div class="text-center text-ink-soft py-8 text-sm">
        @if($kind === 'student' && str_contains($label, 'Failed')) No failed student SMS. 🎉
        @elseif($kind === 'teacher' && str_contains($label, 'Failed')) No failed teacher SMS. 🎉
        @else Queue is empty. @endif
    </div>
    @endforelse
</div>
@endforeach
@endsection
