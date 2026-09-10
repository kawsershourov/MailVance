<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            // Design-panel settings. Null means the template is raw hand-written HTML.
            $table->json('design')->nullable()->after('body_text');
            // Stored relative to the local disk; embedded into sent mail as a cid: part.
            $table->string('logo_path')->nullable()->after('design');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn(['design', 'logo_path']);
        });
    }
};
