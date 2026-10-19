<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\SmsService;
use App\Support\ClassChoices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EmergencySmsController extends Controller
{
    public function index(Request $request)
    {
        $classes = ClassChoices::all();
        $students = Student::withoutGlobalScopes()
            ->orderBy('class_name')
            ->orderBy('section')
            ->orderBy('roll_no')
            ->get(['id', 'name', 'class_name', 'section', 'roll_no', 'parent_mobile']);

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'target_type' => ['required', Rule::in(['all', 'class', 'student'])],
                'class_name' => ['nullable', 'string', Rule::in($classes)],
                'student_ids' => ['required_if:target_type,student', 'array'],
                'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
                'message' => ['required', 'string', 'max:1000'],
            ]);

            $targetType = $validated['target_type'];
            $messageText = trim($validated['message']);

            if ($messageText === '') {
                return back()->withErrors(['message' => 'Please write a notice before sending.'])->withInput();
            }

            if ($targetType === 'class') {
                $className = (string) ($validated['class_name'] ?? '');
                if ($className === '' || !in_array($className, $classes, true)) {
                    return back()->withErrors(['class_name' => 'Please choose a valid class.'])->withInput();
                }
                $recipients = Student::withoutGlobalScopes()->where('class_name', $className)->get();
            } elseif ($targetType === 'student') {
                $ids = array_values(array_unique(array_map('intval', $validated['student_ids'] ?? [])));
                if (!$ids) {
                    return back()->withErrors(['student_ids' => 'Please select at least one student.'])->withInput();
                }
                $recipients = Student::withoutGlobalScopes()->whereIn('id', $ids)->get();
                if ($recipients->isEmpty()) {
                    return back()->withErrors(['student_ids' => 'No valid students were selected.'])->withInput();
                }
            } else {
                $recipients = Student::withoutGlobalScopes()->get();
            }

            $sent = 0;
            $failed = 0;
            $missingPhone = 0;
            $billedSegments = 0;
            $fullMessage = SmsService::appendSchoolName($messageText, false);
            $segmentsPerMessage = SmsService::segmentCount($fullMessage);

            foreach ($recipients as $student) {
                if (!trim((string) $student->parent_mobile)) {
                    $missingPhone++;
                    continue;
                }

                try {
                    [$success, $detail] = SmsService::send($student->parent_mobile, $fullMessage);
                    if ($success) {
                        $sent++;
                        $billedSegments += $segmentsPerMessage;
                    } else {
                        $failed++;
                        Log::warning('Emergency SMS failed for student.', [
                            'student_id' => $student->id,
                            'detail' => $detail,
                        ]);
                    }
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::error('Emergency SMS exception.', [
                        'student_id' => $student->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $summary = "Emergency SMS finished: {$sent} sent, {$failed} failed, {$missingPhone} skipped (no parent phone), {$recipients->count()} student(s) targeted. Billable SMS segments (including school name): {$billedSegments}.";
            return redirect()->route('emergency-sms.index')->with('sms_summary', $summary);
        }

        $studentsData = $students->map(fn ($student) => [
            'id' => (string) $student->id,
            'name' => (string) $student->name,
            'className' => (string) $student->class_name,
            'section' => (string) ($student->section ?? ''),
            'roll' => (string) $student->roll_no,
        ])->values()->all();

        return view('sms.emergency', compact('classes', 'studentsData'));
    }
}
