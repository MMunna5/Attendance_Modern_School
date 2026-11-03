@extends('layouts.app')
@section('title', 'Upload Students - Attendance Register')
@section('header_title', 'Upload Students (Excel)')

@section('content')
    <p class="text-sm text-ink-soft">
        Upload one or more <code>.xlsx</code> files. The first row must have these exact columns, in this order:
        <strong>Roll, Name, Class, Section, Session, Phone</strong>.
    </p>
    <form method="post" action="{{ route('students.upload') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <input type="file" name="excel_file[]" accept=".xlsx" multiple required class="block w-full text-sm border border-gray-300 rounded-lg px-3 py-2.5">
        <button type="submit" class="bg-forest text-white font-bold px-5 py-2.5 rounded-lg hover:bg-forest-deep transition">Upload</button>
    </form>

    @if(!empty($fileResults))
    <div class="space-y-3 mt-4">
        @foreach($fileResults as $r)
        <div class="border rounded-lg p-3 text-sm {{ isset($r['error']) ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' }}">
            <div class="font-semibold">{{ $r['filename'] }}</div>
            @if(isset($r['error']))
                <div class="text-absent">{{ $r['error'] }}</div>
            @else
                <div class="text-ink-soft">
                    Class(es): {{ $r['class_label'] }} · Created: {{ $r['created'] }} · Updated: {{ $r['updated'] }} · Skipped: {{ $r['skipped'] }}
                </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
