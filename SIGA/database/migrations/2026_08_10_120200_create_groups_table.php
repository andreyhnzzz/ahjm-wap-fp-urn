<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('course_code');
            $table->string('term');

            // Both relations are nullable *by requirement*, not by accident:
            // INFRA-01 demands the ability to save a group with no teacher
            // and/or no classroom on purpose, because those two states are
            // exactly what the risk board (RE-04) has to detect as High risk.
            //
            // nullOnDelete (not cascade): deleting a teacher must orphan the
            // group, never delete academic history. The group then surfaces
            // on the risk board as "sin docente", which is the truthful
            // reading of what just happened.
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();

            $table->unsignedInteger('estimated_enrollment')->default(0);

            // Workload this specific group represents for its teacher. The
            // per-teacher, per-term sum of this column is what RE-02 reports
            // and what RE-04 compares against the conflict ceiling. Same
            // decimal-over-float reasoning as teachers.reference_workload.
            $table->decimal('assigned_workload', 4, 2)->default(0);

            // Persisted as the backing values of the Modality / GroupStatus
            // domain enums (in_person|virtual|blended, open|closed|cancelled).
            // Stored as strings rather than a DB enum so adding a modality is
            // a domain change, not a migration on a live table.
            $table->string('modality');
            $table->string('status');

            $table->timestamps();

            // The risk board and both reports always slice by term first;
            // every other access path filters within it.
            $table->index('term');
            $table->index(['term', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
