<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->text('access_token')->nullable()->after('external_id');
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
            $table->string('driver')->default('sandbox')->after('platform');
            $table->json('metadata')->nullable()->after('sync_history');
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'status']);
            $table->dropColumn(['access_token', 'refresh_token', 'token_expires_at', 'driver', 'metadata']);
        });
    }
};
