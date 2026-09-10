<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmtpConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_name',
        'from_email',
        'reply_to',
        'is_default',
        'hourly_limit',
    ];

    /**
     * Never serialize the relay password — views json_encode() this model to seed
     * the edit modal, which would otherwise print the credential into page HTML.
     */
    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'port' => 'integer',
        'hourly_limit' => 'integer',
        'password' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
