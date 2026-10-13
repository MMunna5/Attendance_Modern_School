<?php

namespace App\Jobs;

use App\Models\TeacherAbsenceSms;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTeacherAbsenceSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 300;

    public function __construct(public int $teacherAbsenceSmsId)
    {
    }

    public function handle(): void
    {
        $message = TeacherAbsenceSms::with('teacher')->find($this->teacherAbsenceSmsId);
        if (!$message) {
            return;
        }

        $teacher = $message->teacher;
        if (!$teacher || !$teacher->mobile) {
            $message->update([
                'status' => TeacherAbsenceSms::STATUS_FAILED,
                'last_error' => 'No teacher phone number.',
                'next_attempt_at' => null,
            ]);
            return;
        }

        $sms = SmsService::buildTeacherAbsentMessage($teacher->name, $message->date->format('d-M-y'));
        $message->update(['sms_segments' => SmsService::segmentCount($sms)]);
        [$success, $response] = SmsService::send($teacher->mobile, $sms);

        if ($success) {
            $message->update([
                'status' => TeacherAbsenceSms::STATUS_SENT,
                'next_attempt_at' => null,
                'last_error' => '',
                'sent_at' => now(),
            ]);
        } else {
            $message->update([
                'status' => TeacherAbsenceSms::STATUS_FAILED,
                'attempts' => $message->attempts + 1,
                'last_error' => substr($response ?: 'SMS provider rejected the request.', 0, 1000),
            ]);
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);
            }
        }
    }
}
