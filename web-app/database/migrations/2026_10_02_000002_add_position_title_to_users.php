<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('position_title', 120)->nullable();
        });

        DB::table('users')->select(['id', 'role'])->orderBy('id')->chunk(100, function ($users): void {
            foreach ($users as $user) {
                $title = match ($user->role) {
                    'agency_owner' => 'Direção da agência',
                    'marketing_manager' => 'Gerência de marketing',
                    'professional' => 'Profissional',
                    'client' => 'Cliente',
                    default => 'Membro da agência',
                };

                DB::table('users')->where('id', $user->id)->update(['position_title' => $title]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('position_title');
        });
    }
};
