<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use App\Models\PushApp;
use App\Push\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Fakes\FakePushSender;
use Tests\TestCase;

class SendAlertsTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = new FakePushSender;
        $this->app->instance(PushSender::class, $this->sender);
        Carbon::setTestNow(Carbon::parse('2026-10-12T16:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_sends_a_due_notification_once(): void
    {
        $device = $this->device();
        Alert::schedule($device, 'expiry', $this->alert('2026-10-12T16:00:00Z'));
        Alert::schedule($device, 'later', $this->alert('2026-10-12T17:00:00Z'));

        $this->artisan('alerts:send')->assertSuccessful();
        $this->artisan('alerts:send')->assertSuccessful();

        $this->assertSame([[$device->id, 'expiry']], $this->sender->sent);
        $this->assertSame('sent', Alert::query()->where('key', 'expiry')->firstOrFail()->status());
        $this->assertSame('pending', Alert::query()->where('key', 'later')->firstOrFail()->status());
    }

    public function test_it_skips_a_notification_that_is_too_late(): void
    {
        $device = $this->device();
        Alert::schedule($device, 'expiry', $this->alert('2026-10-12T15:29:00Z'));

        $this->artisan('alerts:send')->assertSuccessful();

        $this->assertSame([], $this->sender->sent);
        $this->assertSame('skipped', Alert::query()->firstOrFail()->status());
    }

    public function test_it_retries_a_failure_until_the_notification_goes_out(): void
    {
        $this->sender->failsRemaining = 1;
        $device = $this->device();
        Alert::schedule($device, 'expiry', $this->alert('2026-10-12T16:00:00Z'));

        $this->artisan('alerts:send')->assertSuccessful();
        $this->assertSame('pending', Alert::query()->firstOrFail()->status());
        $this->assertSame('temporary', Alert::query()->firstOrFail()->last_error);

        $this->artisan('alerts:send')->assertSuccessful();
        $this->assertSame('sent', Alert::query()->firstOrFail()->status());
        $this->assertNull(Alert::query()->firstOrFail()->last_error);
    }

    public function test_it_removes_a_phone_whose_subscription_is_gone(): void
    {
        $this->sender->outcome = 'gone';
        $device = $this->device();
        Alert::schedule($device, 'expiry', $this->alert('2026-10-12T16:00:00Z'));
        Alert::schedule($device, 'later', $this->alert('2026-10-12T16:00:00Z', 'Later'));

        $this->artisan('alerts:send')->assertSuccessful();

        $this->assertSame(0, Device::query()->count());
        $this->assertSame(0, Alert::query()->count());
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_a_changed_notification_can_be_sent_again(): void
    {
        $device = $this->device();
        $alert = Alert::schedule($device, 'expiry', $this->alert('2026-10-12T16:00:00Z'));
        $alert->forceFill(['sent_at' => now()])->save();

        Alert::schedule($device, 'expiry', $this->alert('2026-10-12T18:00:00Z'));
        $this->assertSame('pending', Alert::query()->firstOrFail()->status());

        $stored = Alert::query()->firstOrFail();
        $stored->forceFill(['sent_at' => now()])->save();
        Alert::schedule($device, 'expiry', $this->alert('2026-10-12T18:00:00Z'));
        $this->assertSame('sent', Alert::query()->firstOrFail()->status());
    }

    private function device(): Device
    {
        $app = new PushApp([
            'name' => 'Dosage Helper '.fake()->unique()->word(),
            'origins' => ['https://dosage.example'],
        ]);
        $app->token_hash = hash('sha256', 'token');
        $app->save();

        $device = new Device([
            'push_app_id' => $app->id,
            'endpoint' => 'https://push.example/'.fake()->uuid(),
            'public_key' => str_repeat('a', 80),
            'auth_token' => str_repeat('b', 22),
            'content_encoding' => 'aes128gcm',
        ]);
        $device->save();

        return $device;
    }

    /**
     * @return array{deliver_at: string, title: string, body: string, url: string, grace_minutes: int}
     */
    private function alert(string $deliverAt, string $title = 'Dosage Helper'): array
    {
        return [
            'deliver_at' => $deliverAt,
            'title' => $title,
            'body' => 'Your bolus could be wearing off.',
            'url' => 'https://dosage.example/',
            'grace_minutes' => 30,
        ];
    }
}
