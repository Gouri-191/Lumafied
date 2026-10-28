<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->string('prev_hash', 64)->nullable()->after('status');
            $table->string('chain_hash', 64)->nullable()->after('prev_hash');
            $table->index('chain_hash');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropColumn(['prev_hash', 'chain_hash']);
        });
    }
};
