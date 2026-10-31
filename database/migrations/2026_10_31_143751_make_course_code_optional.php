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
        Schema::table('courses', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('courses')->whereNull('code')->orderBy('id')->chunkById(200, function ($courses): void {
            foreach ($courses as $course) {
                DB::table('courses')->where('id', $course->id)->update(['code' => 'restored-'.str()->uuid()]);
            }
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('code')->nullable(false)->change();
        });
    }
};
