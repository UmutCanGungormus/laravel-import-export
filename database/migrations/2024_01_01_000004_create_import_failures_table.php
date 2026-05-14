<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('import-export.tables.failures', 'import_failures');
        $sessionsTable = config('import-export.tables.sessions', 'import_sessions');

        Schema::create($table, function (Blueprint $blueprint) use ($sessionsTable) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('import_session_id');
            $blueprint->foreign('import_session_id')
                ->references('id')
                ->on($sessionsTable)
                ->cascadeOnDelete();

            $blueprint->unsignedInteger('row_number');
            $blueprint->json('row_data');
            $blueprint->json('errors')->nullable();
            $blueprint->text('exception_message')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['import_session_id', 'row_number'], 'imf_session_row_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('import-export.tables.failures', 'import_failures'));
    }
};
