<?php

namespace App\Http\Controllers;

use App\Jobs\SendTeacherAbsenceSms;
use App\Models\Teacher;
use App\Models\TeacherAbsenceSms;
use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherAttendanceController extends Controller
{
    public function show(Request $request)
    {
        $today = now('Asia/Dhaka')->toDateString();
        $fullAlready = TeacherAttendance::whereHas('teacher', fn ($q) => $q->where('employment_type', Teacher::EMPLOYMENT_FULL))->where('date', $today)->exists();
        $partAlready = TeacherAttendance::whereHas('teacher', fn ($q) => $q->where('employment_type', Teacher::EMPLOYMENT_PART))->where('date', $today)->exists();

        $teachers = Teacher::orderBy('id')->get();
        $attendanceMap = TeacherAttendance::where('date', $today)->pluck('is_present', 'teacher_id');

        $teacherRows = $teachers->map(fn ($t) => ['teacher' => $t, 'is_present' => $attendanceMap->get($t->id, true)]);
        $fullTimeRows = $teacherRows->filter(fn ($r) => $r['teacher']->employment_type === Teacher::EMPLOYMENT_FULL)->values();
        $partTimeRows = $teacherRows->filter(fn ($r) => $r['teacher']->employment_type === Teacher::EMPLOYMENT_PART)->values();

        return view('teacher-attendance.mark', [
            'today' => $today,
            'fullAlreadyMarked' => $fullAlready,
            'partAlreadyMarked' => $partAlready,
            'fullTimeRows' => $fullTimeRows,
            'partTimeRows' => $partTimeRows,
            'fullPresent' => $fullAlready ? $fullTimeRows->where('is_present', true)->count() : 0,
            'fullAbsent' => $fullAlready ? $fullTimeRows->count() - $fullTimeRows->where('is_present', true)->count() : 0,
            'partPresent' => $partAlready ? $partTimeRows->where('is_present', true)->count() : 0,
            'partAbsent' => $partAlready ? $partTimeRows->count() - $partTimeRows->where('is_present', true)->count() : 0,
            'totalTeachers' => $teachers->count(),
            'saved' => false,
            'savedType' => null,
            'smsQueuedCount' => 0,
        ]);
    }

    public function store(Request $request)
    {
        $today = now('Asia/Dhaka')->toDateString();
        $section = $request->input('section');

        $fullAlready = TeacherAttendance::whereHas('teacher', fn ($q) => $q->where('employment_type', Teacher::EMPLOYMENT_FULL))->where('date', $today)->exists();
        $partAlready = TeacherAttendance::whereHas('teacher', fn ($q) => $q->where('employment_type', Teacher::EMPLOYMENT_PART))->where('date', $today)->exists();

        $employmentType = null;
        if ($section === 'full' && !$fullAlready) {
            $employmentType = Teacher::EMPLOYMENT_FULL;
        } elseif ($section === 'part' && !$partAlready) {
            $employmentType = Teacher::EMPLOYMENT_PART;
        }

        $smsQueuedCount = 0;
        if ($employmentType) {
            DB::transaction(function () use ($request, $employmentType, $today, &$smsQueuedCount) {
                $alreadyMarked = TeacherAttendance::whereHas('teacher', fn ($q) => $q->where('employment_type', $employmentType))
                    ->where('date', $today)->lockForUpdate()->exists();

                if (!$alreadyMarked) {
                    $sectionTeachers = Teacher::where('employment_type', $employmentType)->orderBy('id')->get();
                    $attendanceRows = [];
                    $absentTeachers = [];
                    $timestamp = now();

                    foreach ($sectionTeachers as $teacher) {
                        $status = $request->input("tatt_{$teacher->id}", 'present');
                        $isPresent = $status === 'present';
                        $attendanceRows[] = [
                            'teacher_id' => $teacher->id,
                            'date' => $today,
                            'is_present' => $isPresent,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];

                        if (!$isPresent) {
                            $absentTeachers[] = $teacher;
                        }
                    }

                    if ($attendanceRows) {
                        TeacherAttendance::insertOrIgnore($attendanceRows);
                    }

                    foreach ($absentTeachers as $teacher) {
                        $sms = TeacherAbsenceSms::firstOrCreate(['teacher_id' => $teacher->id, 'date' => $today]);
                        if ($sms->wasRecentlyCreated) {
                            SendTeacherAbsenceSms::dispatch($sms->id);
                            $smsQueuedCount++;
                        }
                    }
                }
            });
        }

        return redirect()->route('teacher-attendance.show')->with('saved', true)->with('sms_queued_count', $smsQueuedCount);
    }
}
