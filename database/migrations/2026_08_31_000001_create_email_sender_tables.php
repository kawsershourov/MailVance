<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('smtp_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('host');
            $table->integer('port')->default(587);
            $table->string('encryption')->default('tls'); // tls, ssl, starttls, none
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('hourly_limit')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('total_contacts')->default(0);
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('status')->default('active'); // active, unsubscribed, bounced
            $table->timestamps();

            $table->unique(['contact_list_id', 'email']);
        });

        Schema::create('suppression_lists', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique()->index();
            $table->string('reason')->default('unsubscribed'); // unsubscribed, bounced, spam_complaint
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('body_html');
            $table->text('body_text')->nullable();
            $table->integer('spam_score')->default(0);
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smtp_config_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_list_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, processing, paused, completed, cancelled
            $table->integer('delay_seconds')->default(0);
            $table->boolean('jitter_enabled')->default(true);
            $table->integer('batch_size')->default(0);
            $table->integer('batch_delay_seconds')->default(0);
            $table->dateTime('scheduled_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->string('tracking_token')->unique()->index();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('error_message')->nullable();
            $table->boolean('is_opened')->default(false)->index();
            $table->dateTime('opened_at')->nullable();
            $table->boolean('is_clicked')->default(false)->index();
            $table->dateTime('clicked_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_logs');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('suppression_lists');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('smtp_configs');
    }
};
