<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('university_id')->nullable()->unique();
            $table->string('role')->default('student');
            $table->boolean('is_active')->default(true);
            $table->json('profile')->nullable();
            $table->string('portfolio_slug')->nullable()->unique();
            $table->boolean('portfolio_public')->default(false);
            $table->json('visible_sections')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('resume_name')->nullable();
            $table->unsignedInteger('portfolio_views')->default(0);
            $table->string('avatar_path')->nullable();
        });
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('semester');
            $table->text('description')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
        Schema::create('course_user', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'user_id']);
        });
        Schema::create('academic_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('type')->default('project');
            $table->string('technology')->nullable();
            $table->string('domain')->nullable();
            $table->string('team_members')->nullable();
            $table->string('source_url')->nullable();
            $table->string('visibility')->default('course');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
            $table->index(['visibility', 'type', 'created_at']);
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('title');
            $table->string('course_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('academic_items');
        Schema::dropIfExists('course_user');
        Schema::dropIfExists('courses');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['university_id', 'role', 'is_active', 'profile', 'portfolio_slug', 'portfolio_public', 'visible_sections', 'resume_path', 'resume_name', 'portfolio_views', 'avatar_path']);
        });
    }
};
