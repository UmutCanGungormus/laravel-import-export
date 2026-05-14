<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('import-export.tables.mapping_templates', 'import_mapping_templates');
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

            $blueprint->string('tenant_id')->nullable();
            $blueprint->string('importable_type');
            $blueprint->string('template_name');
            $blueprint->text('description')->nullable();
            $blueprint->boolean('is_default')->default(false);
            $blueprint->boolean('is_company_wide')->default(false);
            $blueprint->json('template_data');
            $blueprint->unsignedInteger('usage_count')->default(0);
            $blueprint->timestamp('last_used_at')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['user_id', 'importable_type'], 'imt_user_type_idx');
            $blueprint->index(['tenant_id', 'importable_type', 'is_company_wide'], 'imt_tenant_type_wide_idx');
            $blueprint->unique(['user_id', 'importable_type', 'template_name'], 'imt_user_type_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('import-export.tables.mapping_templates', 'import_mapping_templates'));
    }
};
