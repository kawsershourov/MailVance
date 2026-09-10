<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'smtp_config_id',
        'contact_list_id',
        'email_template_id',
        'name',
        'subject',
        'sender_name',
        'sender_email',
        'reply_to',
        'status',
        'delay_seconds',
        'jitter_enabled',
        'batch_size',
        'batch_delay_seconds',
        'scheduled_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'opened_count',
        'clicked_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'jitter_enabled' => 'boolean',
        'delay_seconds' => 'integer',
        'batch_size' => 'integer',
        'batch_delay_seconds' => 'integer',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function smtpConfig(): BelongsTo
    {
        return $this->belongsTo(SmtpConfig::class);
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CampaignLog::class);
    }

    public function getOpenRateAttribute(): float
    {
        if ($this->sent_count <= 0) {
            return 0.0;
        }

        return round(($this->opened_count / $this->sent_count) * 100, 1);
    }

    public function getClickRateAttribute(): float
    {
        if ($this->sent_count <= 0) {
            return 0.0;
        }

        return round(($this->clicked_count / $this->sent_count) * 100, 1);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_recipients <= 0) {
            return 0;
        }

        return min(100, (int) round((($this->sent_count + $this->failed_count) / $this->total_recipients) * 100));
    }
}
