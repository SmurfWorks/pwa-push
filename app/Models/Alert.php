<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['push_app_id', 'device_id', 'key', 'deliver_at', 'grace_minutes', 'title', 'body', 'url', 'icon', 'tag'])]
class Alert extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deliver_at' => 'datetime',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'grace_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PushApp, $this>
     */
    public function pushApp(): BelongsTo
    {
        return $this->belongsTo(PushApp::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Book or replace a notification. A repeat of the same alert stays as it is,
     * including one already sent. A change is pending again.
     *
     * @param  array{deliver_at: mixed, title: string, body: string, url?: ?string, icon?: ?string, tag?: ?string, grace_minutes?: int}  $fields
     */
    public static function schedule(Device $device, string $key, array $fields): self
    {
        $alert = static::query()->firstOrNew([
            'device_id' => $device->id,
            'key' => $key,
        ]);

        $deliverAt = Carbon::parse($fields['deliver_at'])->utc()->startOfSecond();
        $next = [
            'title' => $fields['title'],
            'body' => $fields['body'],
            'url' => $fields['url'] ?? null,
            'icon' => $fields['icon'] ?? null,
            'tag' => $fields['tag'] ?? null,
            'grace_minutes' => (int) ($fields['grace_minutes'] ?? 30),
        ];

        $unchanged = $alert->exists
            && $alert->deliver_at?->utc()->startOfSecond()->equalTo($deliverAt)
            && $alert->title === $next['title']
            && $alert->body === $next['body']
            && $alert->url === $next['url']
            && $alert->icon === $next['icon']
            && $alert->tag === $next['tag']
            && (int) $alert->grace_minutes === $next['grace_minutes'];

        $alert->push_app_id = $device->push_app_id;
        $alert->deliver_at = $deliverAt;
        $alert->title = $next['title'];
        $alert->body = $next['body'];
        $alert->url = $next['url'];
        $alert->icon = $next['icon'];
        $alert->tag = $next['tag'];
        $alert->grace_minutes = $next['grace_minutes'];

        if (! $unchanged) {
            $alert->sent_at = null;
            $alert->skipped_at = null;
            $alert->last_error = null;
        }

        $alert->save();

        return $alert;
    }

    public function status(): string
    {
        if ($this->sent_at !== null) {
            return 'sent';
        }

        if ($this->skipped_at !== null) {
            return 'skipped';
        }

        return 'pending';
    }

    /**
     * @return array{key: string, deliver_at: string, grace_minutes: int, title: string, body: string, url: ?string, icon: ?string, tag: ?string, status: string}
     */
    public function toPublicArray(): array
    {
        return [
            'key' => $this->key,
            'deliver_at' => $this->deliver_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'grace_minutes' => $this->grace_minutes,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
            'tag' => $this->tag,
            'status' => $this->status(),
        ];
    }
}
