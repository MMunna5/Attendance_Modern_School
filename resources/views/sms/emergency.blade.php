@extends('layouts.app')
@section('title', 'Emergency SMS - Attendance Register')
@section('header_title', 'Emergency SMS / Notice')
@section('header_links')
<a href="{{ route('dashboard') }}" class="nav-btn">← Dashboard</a>
@endsection

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl p-4 mb-5">
        <h2 class="font-bold">Send an urgent notice to parents</h2>
        <p class="text-sm mt-1">Choose all students, one class, or multiple specific students. The configured school name is added automatically to the SMS.</p>
    </div>

    @if(session('sms_summary'))
        <div class="rounded-xl border border-green-200 bg-green-50 text-green-800 px-4 py-3 mb-5 text-sm">{{ session('sms_summary') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 mb-5 text-sm">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" id="emergency-sms-form" class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">
        @csrf
        <div>
            <label for="target_type" class="block text-sm font-semibold text-ink mb-2">Send to</label>
            <select id="target_type" name="target_type" required class="w-full rounded-xl border border-gray-300 p-3 bg-white">
                <option value="all" @selected(old('target_type', 'all') === 'all')>All students / all classes</option>
                <option value="class" @selected(old('target_type') === 'class')>A specific class</option>
                <option value="student" @selected(old('target_type') === 'student')>Specific student(s)</option>
            </select>
        </div>

        <div id="class-field" class="{{ old('target_type', 'all') !== 'class' ? 'hidden' : '' }}">
            <label for="class_name" class="block text-sm font-semibold text-ink mb-2">Class</label>
            <select id="class_name" name="class_name" class="w-full rounded-xl border border-gray-300 p-3 bg-white">
                <option value="">Choose class</option>
                @foreach($classes as $className)
                    <option value="{{ $className }}" @selected(old('class_name') === $className)>Class {{ $className }}</option>
                @endforeach
            </select>
        </div>

        <div id="student-field" class="{{ old('target_type') !== 'student' ? 'hidden' : '' }} space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-ink mb-1">Find a student</h3>
                <p class="text-xs text-ink-soft">Search by class, roll number, and/or name. Select multiple students, changing the roll filter between selections if needed.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label for="search-class" class="block text-sm font-semibold text-ink mb-2">Class</label>
                    <select id="search-class" class="w-full rounded-xl border border-gray-300 p-3 bg-white">
                        <option value="">All classes</option>
                        @foreach($classes as $className)
                            <option value="{{ mb_strtolower($className) }}">Class {{ $className }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="search-roll" class="block text-sm font-semibold text-ink mb-2">Roll number</label>
                    <input id="search-roll" type="search" inputmode="numeric" autocomplete="off" class="w-full rounded-xl border border-gray-300 p-3" placeholder="e.g. 12">
                </div>
                <div>
                    <label for="search-name" class="block text-sm font-semibold text-ink mb-2">Student name</label>
                    <input id="search-name" type="search" autocomplete="off" class="w-full rounded-xl border border-gray-300 p-3" placeholder="Enter name">
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <p id="student-search-count" class="text-sm text-ink-soft" aria-live="polite">Enter search details to find a student.</p>
                <button type="button" id="clear-student-search" class="text-sm font-semibold underline text-ink">Clear filters</button>
            </div>

            <div id="student-search-results" class="space-y-2 max-h-80 overflow-y-auto" role="group" aria-label="Matching students">
                <p class="text-sm text-ink-soft rounded-xl border border-dashed border-gray-300 p-4">Choose a class, enter a roll number or name to see matching students here.</p>
            </div>

            <div id="selected-student-inputs">
                @foreach((array) old('student_ids', []) as $studentId)
                    <input type="hidden" name="student_ids[]" value="{{ $studentId }}">
                @endforeach
            </div>
            <div id="selected-student-summary" class="hidden rounded-xl border border-forest bg-green-50 p-3 text-sm text-green-900" aria-live="polite">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <h4 class="font-semibold">Selected students</h4>
                    <button type="button" id="clear-selected-students" class="text-sm font-semibold underline text-red-700">Clear all selections</button>
                </div>
                <ul id="selected-student-list" class="space-y-2"></ul>
            </div>
        </div>

        <div>
            <label for="message" class="block text-sm font-semibold text-ink mb-2">Notice message</label>
            <textarea id="message" data-school-name="{{ config('school.full_name', 'School') }}" name="message" rows="5" maxlength="1000" required class="w-full rounded-xl border border-gray-300 p-3 focus:border-forest focus:ring-1 focus:ring-forest" placeholder="Write the notice you want to send to parents...">{{ old('message', '') }}</textarea>
            <p class="text-xs text-ink-soft mt-1">Maximum 1000 characters. SMS charges depend on your SMS provider and message length.</p>
            <p id="sms-segment-preview" class="text-sm font-semibold text-forest mt-2" aria-live="polite"></p>
        </div>

        <button id="send-emergency-sms" type="submit" class="w-full sm:w-auto rounded-xl bg-forest text-white font-semibold px-6 py-3 hover:opacity-90">
            Send Emergency SMS
        </button>
    </form>
</div>

<script>
(function () {

    const smsMessageField = document.getElementById('message');
    const smsPreview = document.getElementById('sms-segment-preview');
    const gsmBasicChars = new Set(Array.from("@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\u001bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà"));
    const gsmExtendedChars = new Set(Array.from("^{}\\[~]|€\f"));
    function countSmsSegments(text) {
        let septets = 0;
        let gsm7 = true;
        for (const char of text) {
            if (gsmBasicChars.has(char)) septets += 1;
            else if (gsmExtendedChars.has(char)) septets += 2;
            else { gsm7 = false; break; }
        }
        if (gsm7) return {encoding: 'GSM-7', units: septets, segments: septets <= 160 ? 1 : Math.ceil(septets / 153)};
        const units = Array.from(text).reduce((n, char) => n + (char.codePointAt(0) > 0xFFFF ? 2 : 1), 0);
        return {encoding: 'Unicode', units, segments: units <= 70 ? 1 : Math.ceil(units / 67)};
    }
    function refreshSmsPreview() {
        if (!smsMessageField || !smsPreview) return;
        const schoolName = smsMessageField.dataset.schoolName || 'School';
        const fullMessage = smsMessageField.value + '\n' + schoolName;
        const count = countSmsSegments(fullMessage);
        smsPreview.textContent = 'Estimated SMS segments per recipient: ' + count.segments + ' (' + count.encoding + ', ' + count.units + ' units, including school name). Provider billing may vary.';
    }
    if (smsMessageField) {
        smsMessageField.addEventListener('input', refreshSmsPreview);
        refreshSmsPreview();
    }
    const targetType = document.getElementById('target_type');
    const classField = document.getElementById('class-field');
    const studentField = document.getElementById('student-field');
    const searchClass = document.getElementById('search-class');
    const searchRoll = document.getElementById('search-roll');
    const searchName = document.getElementById('search-name');
    const results = document.getElementById('student-search-results');
    const countLabel = document.getElementById('student-search-count');
    const selectedInputs = document.getElementById('selected-student-inputs');
    const selectedSummary = document.getElementById('selected-student-summary');
    const selectedStudentList = document.getElementById('selected-student-list');
    const clearSelectedButton = document.getElementById('clear-selected-students');
    const clearButton = document.getElementById('clear-student-search');
    const students = @json($studentsData);
    const selectedStudentIds = new Set(@json(array_map('strval', (array) old('student_ids', []))));

    function updateTargetFields() {
        classField.classList.toggle('hidden', targetType.value !== 'class');
        studentField.classList.toggle('hidden', targetType.value !== 'student');
    }

    function normalized(value) {
        return String(value || '').trim().toLocaleLowerCase();
    }

    function updateSelectedStudents() {
        selectedInputs.replaceChildren();
        selectedStudentIds.forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'student_ids[]';
            input.value = id;
            selectedInputs.appendChild(input);
        });

        const chosen = students.filter(function (student) { return selectedStudentIds.has(student.id); });
        selectedStudentList.replaceChildren();
        if (!chosen.length) {
            selectedSummary.classList.add('hidden');
            return;
        }

        chosen.forEach(function (student) {
            const item = document.createElement('li');
            item.className = 'flex items-center justify-between gap-3 rounded-lg border border-green-200 bg-white px-3 py-2';
            const label = document.createElement('span');
            label.className = 'min-w-0 font-medium';
            label.textContent = student.name + ' · Roll ' + student.roll;

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'shrink-0 rounded-lg border border-red-200 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50';
            removeButton.textContent = 'Remove';
            removeButton.setAttribute('aria-label', 'Remove ' + student.name + ' from selected students');
            removeButton.addEventListener('click', function () {
                selectedStudentIds.delete(student.id);
                updateSelectedStudents();
                renderResults();
            });

            item.appendChild(label);
            item.appendChild(removeButton);
            selectedStudentList.appendChild(item);
        });
        selectedSummary.classList.remove('hidden');
    }

    function toggleStudent(student) {
        if (selectedStudentIds.has(student.id)) selectedStudentIds.delete(student.id);
        else selectedStudentIds.add(student.id);
        updateSelectedStudents();
        renderResults();
    }

    function renderResults() {
        const classQuery = normalized(searchClass.value);
        const rollQuery = normalized(searchRoll.value);
        const nameQuery = normalized(searchName.value);
        const hasSearch = Boolean(classQuery || rollQuery || nameQuery);
        const matches = students.filter(function (student) {
            return (!classQuery || normalized(student.className) === classQuery) &&
                (!rollQuery || normalized(student.roll).includes(rollQuery)) &&
                (!nameQuery || normalized(student.name).includes(nameQuery));
        });

        results.replaceChildren();
        if (!hasSearch) {
            countLabel.textContent = 'Enter one or more search details';
            const prompt = document.createElement('p');
            prompt.className = 'text-sm text-ink-soft rounded-xl border border-dashed border-gray-300 p-4';
            prompt.textContent = 'Choose a class, enter a roll number, or type a student name. You can combine all three filters.';
            results.appendChild(prompt);
            return;
        }

        countLabel.textContent = matches.length + ' matching student(s)';
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-ink-soft rounded-xl border border-dashed border-gray-300 p-4';
            empty.textContent = 'No students match these filters. Try changing the class, roll number, or name.';
            results.appendChild(empty);
            return;
        }

        matches.slice(0, 100).forEach(function (student) {
            const card = document.createElement('button');
            card.type = 'button';
            card.setAttribute('role', 'checkbox');
            card.setAttribute('aria-checked', selectedStudentIds.has(student.id) ? 'true' : 'false');
            card.className = 'w-full text-left rounded-xl border border-gray-200 p-3 hover:border-forest hover:bg-gray-50 transition-colors';
            if (selectedStudentIds.has(student.id)) card.classList.add('border-forest', 'bg-green-50', 'ring-1', 'ring-forest');

            const title = document.createElement('span');
            title.className = 'block font-semibold text-ink';
            title.textContent = (selectedStudentIds.has(student.id) ? '✓ Selected · ' : '+ Select · ') + student.name + ' · Roll ' + student.roll;
            card.appendChild(title);
            card.addEventListener('click', function () { toggleStudent(student); });
            results.appendChild(card);
        });

        if (matches.length > 100) {
            const note = document.createElement('p');
            note.className = 'text-xs text-ink-soft p-2';
            note.textContent = 'Showing first 100 results. Add more filters to narrow the list.';
            results.appendChild(note);
        }
    }

    targetType.addEventListener('change', updateTargetFields);
    [searchClass, searchRoll, searchName].forEach(function (input) {
        input.addEventListener(input.tagName === 'SELECT' ? 'change' : 'input', renderResults);
    });

    clearButton.addEventListener('click', function () {
        searchClass.value = '';
        searchRoll.value = '';
        searchName.value = '';
        renderResults();
    });

    clearSelectedButton.addEventListener('click', function () {
        selectedStudentIds.clear();
        updateSelectedStudents();
        renderResults();
    });

    document.getElementById('emergency-sms-form').addEventListener('submit', function (event) {
        if (targetType.value === 'student' && selectedStudentIds.size === 0) {
            event.preventDefault();
            alert('Please search for and select at least one student before sending the SMS.');
            return;
        }
        const button = document.getElementById('send-emergency-sms');
        if (button && !button.disabled) {
            if (confirm('Send this SMS notice to the selected recipients now?')) {
                button.disabled = true;
                button.textContent = 'Sending SMS...';
                button.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                event.preventDefault();
            }
        }
    });

    updateTargetFields();
    updateSelectedStudents();
    renderResults();
})();
</script>
@endsection
