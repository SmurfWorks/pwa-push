<?php

namespace App\Models;

use App\Support\DeliveryClock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable(['push_app_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding', 'timezone'])]
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
     * Move waiting clock-time notifications onto this phone's timezone.
     * A clock time that does not exist there keeps its previous send time.
     */
    public function retargetLocalAlerts(): void
    {
        if (! is_string($this->timezone) || $this->timezone === '') {
            return;
        }

        $this->alerts()
            ->whereNull('sent_at')
            ->whereNull('skipped_at')
            ->whereNotNull('local_deliver_at')
            ->each(function (Alert $alert): void {
                try {
                    $instant = DeliveryClock::resolve((string) $alert->local_deliver_at, $this->timezone)['instant'];
                } catch (InvalidArgumentException) {
                    return;
                }

                if ($alert->deliver_at?->utc()->startOfSecond()->equalTo($instant)) {
                    return;
                }

                $alert->deliver_at = $instant;
                $alert->save();
            });
    }

    /**
     * @return array{id: string, timezone: ?string, created_at: string}
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'timezone' => $this->timezone,
            'created_at' => $this->created_at->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
