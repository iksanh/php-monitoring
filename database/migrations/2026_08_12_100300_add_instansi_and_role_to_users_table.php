<?php

use App\Enums\Peran;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('instansi_id')->nullable()->after('email')->constrained('instansi')->nullOnDelete();
            $table->enum('role', array_column(Peran::cases(), 'value'))
                ->default(Peran::Viewer->value)
                ->after('instansi_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instansi_id');
            $table->dropColumn('role');
        });
    }
};
