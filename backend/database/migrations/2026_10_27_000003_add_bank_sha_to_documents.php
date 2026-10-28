<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('bank_sha256', 64)->nullable()->after('file_hash_sha256');
            $table->boolean('bank_sha_verified')->default(false)->after('bank_sha256');
            $table->string('bank_sha_match_status', 30)->default('not_provided')->after('bank_sha_verified');
            $table->index('bank_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['bank_sha256', 'bank_sha_verified', 'bank_sha_match_status']);
        });
    }
};
