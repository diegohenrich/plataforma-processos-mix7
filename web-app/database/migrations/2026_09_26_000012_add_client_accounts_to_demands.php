<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->foreignId('client_user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['client_user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->dropIndex(['client_user_id', 'status', 'updated_at']);
            $table->dropConstrainedForeignId('client_user_id');
        });
    }
};
