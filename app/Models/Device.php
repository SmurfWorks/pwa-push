<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['push_app_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding'])]
class Device extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<PushApp, $this>
     */
    public function pushApp(): BelongsTo
    {
        return $this->belongsTo(PushApp::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * @return array{id: string, created_at: string}
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
