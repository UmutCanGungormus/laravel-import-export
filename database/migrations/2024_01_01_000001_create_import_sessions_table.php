<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('import-export.tables.sessions', 'import_sessions');
        $usersTable = config('import-export.tables.users', 'users');
        $useUsersFk = (bool) config('import-export.foreign_keys.users', true);

        Schema::create($table, function (Blueprint $blueprint) use ($usersTable, $useUsersFk) {
            $blueprint->id();

            $blueprint->unsignedBigInteger('user_id')->nullable();
            if ($useUsersFk) {
                $blueprint->foreign('user_id')
                    ->references('id')
                    ->on($usersTable)
                    ->cascadeOnDelete();
            }

            // Tenant identifier (company_id, workspace_id, …). Nullable so
            // single-tenant apps can ignore it.
            $blueprint->string('tenant_id')->nullable();

            $blueprint->string('importable_type');
            $blueprint->string('file_name');
            $blueprint->string('file_path');
            $blueprint->string('file_disk')->default('local');
            $blueprint->string('status')->default('pending');
            $blueprint->unsignedInteger('total_rows')->default(0);
            $blueprint->unsignedInteger('processed_rows')->default(0);
            $blueprint->unsignedInteger('successful_rows')->default(0);
            $blueprint->unsignedInteger('failed_rows')->default(0);
            $blueprint->json('detected_headers')->nullable();
            $blueprint->json('options')->nullable();
            $blueprint->timestamp('started_at')->nullable();
            $blueprint->timestamp('completed_at')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['user_id', 'status']);
            $blueprint->index(['importable_type', 'status']);
            $blueprint->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('import-export.tables.sessions', 'import_sessions'));
    }
};
