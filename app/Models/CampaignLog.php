<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'recipient_email',
        'recipient_name',
        'tracking_token',
        'status',
        'error_message',
        'is_opened',
        'opened_at',
        'is_clicked',
        'clicked_at',
        'sent_at',
    ];

    protected $casts = [
        'is_opened' => 'boolean',
        'is_clicked' => 'boolean',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
