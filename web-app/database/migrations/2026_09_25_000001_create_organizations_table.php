<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('role', 32)->default('professional')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->index(['organization_id', 'role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'role', 'is_active']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['role', 'is_active']);
        });

        Schema::dropIfExists('organizations');
    }
};
