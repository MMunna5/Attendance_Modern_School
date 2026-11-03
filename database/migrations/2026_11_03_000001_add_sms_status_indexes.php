<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_sms', function (Blueprint $table) {
            $table->index(['status', 'sent_at'], 'abs_sms_status_sent_idx');
            $table->index(['status', 'date'], 'abs_sms_status_date_idx');
            $table->index(['status', 'created_at'], 'abs_sms_status_created_idx');
        });

        Schema::table('teacher_absence_sms', function (Blueprint $table) {
            $table->index(['status', 'sent_at'], 'teacher_sms_status_sent_idx');
            $table->index(['status', 'date'], 'teacher_sms_status_date_idx');
            $table->index(['status', 'created_at'], 'teacher_sms_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_absence_sms', function (Blueprint $table) {
            $table->dropIndex('teacher_sms_status_sent_idx');
            $table->dropIndex('teacher_sms_status_date_idx');
            $table->dropIndex('teacher_sms_status_created_idx');
        });

        Schema::table('absence_sms', function (Blueprint $table) {
            $table->dropIndex('abs_sms_status_sent_idx');
            $table->dropIndex('abs_sms_status_date_idx');
            $table->dropIndex('abs_sms_status_created_idx');
        });
    }
};
