<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_sms', function (Blueprint $table) {
            $table->unsignedInteger('sms_segments')->default(1)->after('attempts');
        });

        Schema::table('teacher_absence_sms', function (Blueprint $table) {
            $table->unsignedInteger('sms_segments')->default(1)->after('attempts');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_absence_sms', function (Blueprint $table) {
            $table->dropColumn('sms_segments');
        });

        Schema::table('absence_sms', function (Blueprint $table) {
            $table->dropColumn('sms_segments');
        });
    }
};
