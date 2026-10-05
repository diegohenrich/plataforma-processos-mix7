<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->string('intake_source', 120)->nullable()->after('brief');
            $table->foreignId('brief_author_id')->nullable()->after('intake_source')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('brief_author_id');
            $table->dropColumn('intake_source');
        });
    }
};
