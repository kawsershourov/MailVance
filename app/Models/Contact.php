<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_list_id',
        'email',
        'first_name',
        'last_name',
        'company',
        'custom_fields',
        'status',
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function campaignLogs(): HasMany
    {
        return $this->hasMany(CampaignLog::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->email;
    }
}
