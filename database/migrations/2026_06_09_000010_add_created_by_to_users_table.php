<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `created_by` (usuario que creó al usuario) a la tabla users.
 * FK self-referencial, nullable y nullOnDelete. Idempotente con guard hasColumn.
 * Los usuarios existentes quedan con NULL (no se conoce su creador histórico).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }
};
