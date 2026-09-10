<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuppressionList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Entries that apply to one account: its own, plus the platform-wide ones
     * (`user_id` null) an operator added.
     */
    public function scopeAppliesTo(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        });
    }

    /**
     * Lowercased addresses suppressed for this account, as an O(1) lookup set.
     * Only the `email` column is pulled, so the footprint stays small even on a
     * large list.
     *
     * @return array<string, int>
     */
    public static function lookupFor(int $userId): array
    {
        return static::query()
            ->appliesTo($userId)
            ->pluck('email')
            ->flip()
            ->all();
    }
}
