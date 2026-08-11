<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('identity_card')->unique();
            $table->string('name');

            // Reference workload the campus estimates for this teacher, as a
            // fraction of a full-time equivalent (1.00 = full time). decimal,
            // not float: this number is compared against the sum of the
            // group workloads in RE-02/RE-04, and binary floating point
            // would make 0.25 + 0.25 + 0.5 land just off 1.0 and flip a
            // "jornada en conflicto" verdict on rounding noise alone.
            $table->decimal('reference_workload', 4, 2)->default(1.00);

            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
