<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher; // hasOne relation, null if not a teacher
        $today = now('Asia/Dhaka')->toDateString(); // see note in AppServiceProvider re: timezone

        $totalStudents = 0;
        $presentCount = 0;
        $absentCount = 0;
        $teacherClasses = [];

        if ($teacher) {
            $teacherClasses = $teacher->class_list;
            $students = Student::whereIn('class_name', $teacherClasses)->withoutGlobalScopes();
            $totalStudents = $students->count();

            $attendanceTotals = Attendance::whereHas('student', function ($q) use ($teacherClasses) {
                $q->whereIn('class_name', $teacherClasses);
            })->where('date', $today)
                ->selectRaw('SUM(CASE WHEN is_present = 1 THEN 1 ELSE 0 END) as present_count, SUM(CASE WHEN is_present = 0 THEN 1 ELSE 0 END) as absent_count')
                ->first();

            $presentCount = (int) ($attendanceTotals->present_count ?? 0);
            $absentCount = (int) ($attendanceTotals->absent_count ?? 0);
        }

        $adminPresent = $adminAbsent = $adminTotal = 0;
        if ($user->is_admin) {
            $adminTotals = Attendance::where('date', $today)
                ->selectRaw('SUM(CASE WHEN is_present = 1 THEN 1 ELSE 0 END) as present_count, SUM(CASE WHEN is_present = 0 THEN 1 ELSE 0 END) as absent_count')
                ->first();

            $adminPresent = (int) ($adminTotals->present_count ?? 0);
            $adminAbsent = (int) ($adminTotals->absent_count ?? 0);
            $adminTotal = Student::withoutGlobalScopes()->count();
        }

        return view('dashboard', compact(
            'teacher', 'teacherClasses', 'totalStudents', 'presentCount', 'absentCount',
            'today', 'adminPresent', 'adminAbsent', 'adminTotal'
        ));
    }
}
