<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            // Papel que a pessoa recebe ao aceitar. Se o papel for apagado, ela entra como membro.
            $table->foreignId('role_id')->nullable()->after('invited_by')->constrained('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
