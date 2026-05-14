<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('import-export.tables.column_mappings', 'import_column_mappings');
        $sessionsTable = config('import-export.tables.sessions', 'import_sessions');

        Schema::create($table, function (Blueprint $blueprint) use ($sessionsTable) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('import_session_id');
            $blueprint->foreign('import_session_id')
                ->references('id')
                ->on($sessionsTable)
                ->cascadeOnDelete();

            $blueprint->string('source_column');
            $blueprint->string('target_field')->nullable();
            $blueprint->decimal('confidence_score', 5, 3)->default(0);
            $blueprint->string('match_method')->default('none');
            $blueprint->json('transformation_rules')->nullable();
            $blueprint->boolean('is_required')->default(false);
            $blueprint->boolean('is_confirmed')->default(false);
            $blueprint->timestamps();

            $blueprint->unique(['import_session_id', 'source_column'], 'imcm_session_source_unique');
            $blueprint->index(['import_session_id', 'is_confirmed'], 'imcm_session_confirmed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('import-export.tables.column_mappings', 'import_column_mappings'));
    }
};
