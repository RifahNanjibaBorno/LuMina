<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->enum('status', ['active', 'inactive'])->default('active');
        });
        Schema::create('course_teacher', function (Blueprint $table): void {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'user_id']);
        });
        DB::table('courses')->where('is_archived', true)->update(['status' => 'inactive']);
        DB::table('courses')->whereNotNull('teacher_id')->orderBy('id')->chunkById(200, function ($courses): void {
            DB::table('course_teacher')->insert($courses->map(fn ($course): array => ['course_id' => $course->id, 'user_id' => $course->teacher_id])->all());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_teacher');
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
