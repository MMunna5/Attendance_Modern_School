<?php

namespace App\Http\Controllers;

use App\Models\AbsenceSms;
use App\Models\TeacherAbsenceSms;
use App\Models\EmergencySmsLog;
use App\Jobs\SendAbsenceSms;
use App\Jobs\SendTeacherAbsenceSms;
use Illuminate\Http\Request;

class SmsStatusController extends Controller
{
    public function index(Request $request)
    {
        // Retry SMS action
        if ($request->isMethod('post')) {
            $action = $request->input('action');
            $kind = $request->input('kind');
            $id = $request->input('id');

            if ($action === 'retry_all') {
                if ($kind === 'student') {
                    AbsenceSms::where('status', AbsenceSms::STATUS_FAILED)
                        ->chunkById(100, function ($messages) {
                            foreach ($messages as $msg) {
                                $msg->update([
                                    'status' => AbsenceSms::STATUS_PENDING,
                                    'next_attempt_at' => null,
                                ]);
                                SendAbsenceSms::dispatch($msg->id);
                            }
                        });
                } elseif ($kind === 'teacher') {
                    TeacherAbsenceSms::where('status', TeacherAbsenceSms::STATUS_FAILED)
                        ->chunkById(100, function ($messages) {
                            foreach ($messages as $msg) {
                                $msg->update([
                                    'status' => TeacherAbsenceSms::STATUS_PENDING,
                                    'next_attempt_at' => null,
                                ]);
                                SendTeacherAbsenceSms::dispatch($msg->id);
                            }
                        });
                }
            } elseif ($action === 'retry') {
                if ($kind === 'student') {
                    $msg = AbsenceSms::find($id);

                    if ($msg) {
                        $msg->update([
                            'status' => AbsenceSms::STATUS_PENDING,
                            'next_attempt_at' => null,
                        ]);

                        SendAbsenceSms::dispatch($msg->id);
                    }
                } else {
                    $msg = TeacherAbsenceSms::find($id);

                    if ($msg) {
                        $msg->update([
                            'status' => TeacherAbsenceSms::STATUS_PENDING,
                            'next_attempt_at' => null,
                        ]);

                        SendTeacherAbsenceSms::dispatch($msg->id);
                    }
                }
            }

            return redirect()->route('sms-status.index');
        }

        // Bill only messages confirmed as sent, using the actual send timestamp.
        [$year, $month, $monthStr] = $this->monthBounds($request->query('month'));

        $studentSent = AbsenceSms::whereYear('sent_at', $year)
            ->whereMonth('sent_at', $month)
            ->where('status', AbsenceSms::STATUS_SENT);
        $teacherSent = TeacherAbsenceSms::whereYear('sent_at', $year)
            ->whereMonth('sent_at', $month)
            ->where('status', TeacherAbsenceSms::STATUS_SENT);
        $emergencySent = EmergencySmsLog::whereYear('sent_at', $year)
            ->whereMonth('sent_at', $month)
            ->whereNotNull('sent_at');

        $counts = [
            'student' => [
                'segments' => (clone $studentSent)->sum('sms_segments'),
                'sent' => (clone $studentSent)->count(),
                'failed' => AbsenceSms::whereYear('date', $year)->whereMonth('date', $month)
                    ->where('status', AbsenceSms::STATUS_FAILED)->count(),
                'pending' => AbsenceSms::whereYear('date', $year)->whereMonth('date', $month)
                    ->where('status', AbsenceSms::STATUS_PENDING)->count(),
            ],
            'teacher' => [
                'segments' => (clone $teacherSent)->sum('sms_segments'),
                'sent' => (clone $teacherSent)->count(),
                'failed' => TeacherAbsenceSms::whereYear('date', $year)->whereMonth('date', $month)
                    ->where('status', TeacherAbsenceSms::STATUS_FAILED)->count(),
                'pending' => TeacherAbsenceSms::whereYear('date', $year)->whereMonth('date', $month)
                    ->where('status', TeacherAbsenceSms::STATUS_PENDING)->count(),
            ],
            'emergency' => [
                'segments' => (clone $emergencySent)->sum('sms_segments'),
                'sent' => (clone $emergencySent)->count(),
            ],
        ];

        $totalSegments = $counts['student']['segments']
            + $counts['teacher']['segments']
            + $counts['emergency']['segments'];
        $totalMessages = $counts['student']['sent']
            + $counts['teacher']['sent']
            + $counts['emergency']['sent'];

        return view('sms.status', [
            'studentFailed' => AbsenceSms::with('student')
                ->where('status', AbsenceSms::STATUS_FAILED)
                ->orderByDesc('created_at')->limit(200)->get(),
            'studentPending' => AbsenceSms::with('student')
                ->where('status', AbsenceSms::STATUS_PENDING)
                ->orderBy('created_at')->limit(200)->get(),
            'teacherFailed' => TeacherAbsenceSms::with('teacher')
                ->where('status', TeacherAbsenceSms::STATUS_FAILED)
                ->orderByDesc('created_at')->limit(200)->get(),
            'teacherPending' => TeacherAbsenceSms::with('teacher')
                ->where('status', TeacherAbsenceSms::STATUS_PENDING)
                ->orderBy('created_at')->limit(200)->get(),
            'monthStr' => $monthStr,
            'counts' => $counts,
            'totalSegments' => $totalSegments,
            'totalMessages' => $totalMessages,
        ]);
    }

    /**
     * Get the selected year and month.
     */
    private function monthBounds(?string $monthStr): array
    {
        if (
            $monthStr &&
            preg_match('/^(\d{4})-(\d{1,2})$/', $monthStr, $m) &&
            (int) $m[2] >= 1 &&
            (int) $m[2] <= 12
        ) {
            return [
                (int) $m[1],
                (int) $m[2],
                sprintf('%04d-%02d', (int) $m[1], (int) $m[2]),
            ];
        }

        $today = now('Asia/Dhaka');

        return [
            $today->year,
            $today->month,
            $today->format('Y-m'),
        ];
    }
}