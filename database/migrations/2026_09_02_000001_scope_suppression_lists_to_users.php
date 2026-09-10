<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression entries used to be global: one row per address, shared by every
 * account. That made the public unsubscribe endpoint a cross-tenant weapon —
 * suppressing an address for one sender silently removed it from everybody
 * else's campaigns too.
 *
 * Entries are now owned. A null `user_id` is a platform-wide entry (what every
 * pre-existing row becomes), so operator-level blocks keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // The old unique index was on `email` alone, which would stop two
        // accounts from ever suppressing the same address independently.
        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->dropUnique('suppression_lists_email_unique');
        });

        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->unique(['user_id', 'email'], 'suppression_lists_user_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->dropUnique('suppression_lists_user_email_unique');
        });

        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('suppression_lists', function (Blueprint $table) {
            $table->unique('email', 'suppression_lists_email_unique');
        });
    }
};
