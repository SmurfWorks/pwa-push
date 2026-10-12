<?php

namespace Tests\Feature;

use App\Models\PushApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_names_the_service(): void
    {
        $this->getJson('/')->assertOk()->assertJson(['service' => 'pwa-push']);
    }

    public function test_the_public_key_is_readable_without_a_token(): void
    {
        $this->getJson('/api/vapid-public-key')
            ->assertOk()
            ->assertJson(['publicKey' => 'test-public-key']);
    }

    public function test_an_admin_can_register_list_and_remove_apps(): void
    {
        $created = $this->postJson('/api/apps', [
            'name' => 'Dosage Helper',
            'origins' => ['https://dosage.example/', 'http://localhost:5173'],
        ], $this->admin())->assertCreated();

        $created->assertJsonPath('name', 'Dosage Helper');
        $created->assertJsonPath('origins', ['https://dosage.example', 'http://localhost:5173']);
        $token = $created->json('token');
        $this->assertIsString($token);
        $this->assertSame(48, strlen($token));

        $this->getJson('/api/apps', $this->admin())
            ->assertOk()
            ->assertJsonPath('apps.0.name', 'Dosage Helper')
            ->assertJsonMissing(['token' => $token]);

        $this->postJson('/api/apps', [
            'name' => 'Worknote',
            'origins' => ['http://example.com'],
        ], $this->admin())->assertUnprocessable();

        $this->postJson('/api/apps', [
            'name' => 'Dosage Helper',
            'origins' => ['https://dosage.example'],
        ], $this->admin())->assertUnprocessable();

        $this->deleteJson('/api/apps/'.$created->json('id'), [], $this->admin())->assertNoContent();
        $this->assertSame(0, PushApp::query()->count());
    }

    public function test_app_routes_reject_the_admin_token_and_a_missing_token(): void
    {
        $this->postJson('/api/apps', [
            'name' => 'Dosage Helper',
            'origins' => ['https://dosage.example'],
        ])->assertUnauthorized();

        $this->getJson('/api/devices', $this->admin())->assertUnauthorized();
    }

    public function test_a_phone_can_register_and_schedule_a_notification(): void
    {
        $dosage = $this->registerApp('Dosage Helper', ['https://dosage.example', 'http://localhost:5173']);
        $worknote = $this->registerApp('Worknote', ['https://worknote.example']);

        $device = $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1'), [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertCreated();

        $again = $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1'), [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertOk();

        $this->assertSame($device->json('id'), $again->json('id'));

        $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1'), [
            'Authorization' => 'Bearer '.$worknote['token'],
        ])->assertConflict();

        $notification = $this->putJson('/api/devices/'.$device->json('id').'/notifications/expiry', [
            'deliver_at' => '2026-10-12T16:00:00Z',
            'title' => 'Dosage Helper',
            'body' => 'Your bolus could be wearing off.',
            'url' => 'https://dosage.example/',
            'icon' => 'https://dosage.example/icons/icon-192.png',
            'tag' => 'expiry',
            'grace_minutes' => 30,
        ], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertCreated();

        $notification->assertJsonPath('status', 'pending');
        $notification->assertJsonPath('key', 'expiry');

        $this->putJson('/api/devices/'.$device->json('id').'/notifications/expiry', [
            'deliver_at' => '2026-10-12T16:00:00Z',
            'title' => 'Dosage Helper',
            'body' => 'Your bolus could be wearing off.',
            'url' => 'https://dosage.example/',
            'icon' => 'https://dosage.example/icons/icon-192.png',
            'tag' => 'expiry',
        ], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertOk()->assertJsonPath('status', 'pending');

        $this->putJson('/api/devices/'.$device->json('id').'/notifications/expiry', [
            'deliver_at' => '2026-10-12T16:00:00Z',
            'title' => 'Dosage Helper',
            'body' => 'Your bolus could be wearing off.',
            'url' => 'https://other.example/',
            'icon' => 'https://other.example/icons/icon-192.png',
        ], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['url', 'icon']);

        $this->putJson('/api/devices/'.$device->json('id').'/notifications/time-09:00', [
            'deliver_at' => '2026-10-13T16:00:00Z',
            'title' => 'Dosage Helper',
            'body' => 'You have no active target.',
            'url' => 'http://localhost:5173/',
        ], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertCreated();

        $this->getJson('/api/devices/'.$device->json('id').'/notifications', [
            'Authorization' => 'Bearer '.$worknote['token'],
        ])->assertNotFound();

        $this->deleteJson('/api/devices/'.$device->json('id').'/notifications/expiry', [], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertNoContent();

        $this->deleteJson('/api/devices/'.$device->json('id').'/notifications', [], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertOk()->assertJson(['deleted' => 1]);
    }

    public function test_a_clock_time_is_read_in_the_phones_timezone(): void
    {
        $dosage = $this->registerApp('Dosage Helper', ['https://dosage.example']);
        $device = $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1', 'America/Los_Angeles'), [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertCreated();

        $device->assertJsonPath('timezone', 'America/Los_Angeles');

        $this->putJson('/api/devices/'.$device->json('id').'/notifications/morning', [
            'deliver_at' => '2026-10-12T09:00:00',
            'title' => 'Dosage Helper',
            'body' => 'You have no active target.',
            'url' => 'https://dosage.example/',
        ], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertCreated()
            ->assertJsonPath('deliver_at', '2026-10-12T16:00:00Z')
            ->assertJsonPath('local_deliver_at', '2026-10-12T09:00:00')
            ->assertJsonPath('status', 'pending');
    }

    public function test_a_new_timezone_moves_a_waiting_clock_time_and_leaves_an_absolute_time(): void
    {
        $dosage = $this->registerApp('Dosage Helper', ['https://dosage.example']);
        $headers = ['Authorization' => 'Bearer '.$dosage['token']];
        $device = $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1', 'Europe/London'), $headers)
            ->assertCreated();
        $root = '/api/devices/'.$device->json('id').'/notifications';

        $this->putJson($root.'/morning', [
            'deliver_at' => '2026-10-12T09:00:00',
            'title' => 'Dosage Helper',
            'body' => 'You have no active target.',
            'url' => 'https://dosage.example/',
        ], $headers)->assertCreated()->assertJsonPath('deliver_at', '2026-10-12T08:00:00Z');

        $this->putJson($root.'/fixed', [
            'deliver_at' => '2026-10-12T16:00:00Z',
            'title' => 'Dosage Helper',
            'body' => 'Your bolus could be wearing off.',
            'url' => 'https://dosage.example/',
        ], $headers)->assertCreated()->assertJsonPath('deliver_at', '2026-10-12T16:00:00Z');

        $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1', 'America/Los_Angeles'), $headers)
            ->assertOk()
            ->assertJsonPath('id', $device->json('id'))
            ->assertJsonPath('timezone', 'America/Los_Angeles');

        $notifications = $this->getJson($root, $headers)->assertOk()->json('notifications');
        $byKey = [];

        foreach ($notifications as $notification) {
            $byKey[$notification['key']] = $notification;
        }

        $this->assertSame('2026-10-12T16:00:00Z', $byKey['morning']['deliver_at']);
        $this->assertSame('2026-10-12T09:00:00', $byKey['morning']['local_deliver_at']);
        $this->assertSame('2026-10-12T16:00:00Z', $byKey['fixed']['deliver_at']);
        $this->assertNull($byKey['fixed']['local_deliver_at']);
    }

    public function test_registering_again_without_a_timezone_keeps_the_stored_one(): void
    {
        $dosage = $this->registerApp('Dosage Helper', ['https://dosage.example']);
        $headers = ['Authorization' => 'Bearer '.$dosage['token']];

        $device = $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1', 'America/Los_Angeles'), $headers)
            ->assertCreated();

        $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1'), $headers)
            ->assertOk()
            ->assertJsonPath('id', $device->json('id'))
            ->assertJsonPath('timezone', 'America/Los_Angeles');
    }

    public function test_a_clock_time_without_a_timezone_returns_422(): void
    {
        $dosage = $this->registerApp('Dosage Helper', ['https://dosage.example']);
        $device = $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1'), [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertCreated();

        $this->putJson('/api/devices/'.$device->json('id').'/notifications/morning', [
            'deliver_at' => '2026-10-12T09:00:00',
            'title' => 'Dosage Helper',
            'body' => 'You have no active target.',
        ], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'deliver_at' => 'Register a timezone for this phone, or send a time that includes one.',
        ]);

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_an_unknown_timezone_returns_422(): void
    {
        $dosage = $this->registerApp('Dosage Helper', ['https://dosage.example']);

        $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1', 'Pacific Standard Time'), [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'timezone' => 'Use a timezone name such as America/Los_Angeles.',
        ]);

        $this->assertDatabaseCount('devices', 0);
    }

    public function test_a_time_skipped_by_the_clock_change_returns_422(): void
    {
        $dosage = $this->registerApp('Dosage Helper', ['https://dosage.example']);
        $device = $this->postJson('/api/devices', $this->subscription('https://push.example/phone-1', 'America/Los_Angeles'), [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertCreated();

        $this->putJson('/api/devices/'.$device->json('id').'/notifications/morning', [
            'deliver_at' => '2026-03-08T02:30:00',
            'title' => 'Dosage Helper',
            'body' => 'You have no active target.',
        ], [
            'Authorization' => 'Bearer '.$dosage['token'],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'deliver_at' => "That time does not exist in this phone's timezone.",
        ]);

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_artisan_registers_and_rotates_an_app(): void
    {
        $this->artisan('push-apps:create', [
            'name' => 'Worknote',
            '--origin' => ['https://worknote.example', 'http://localhost:5174'],
        ])->assertSuccessful();

        $app = PushApp::query()->where('name', 'Worknote')->firstOrFail();
        $this->assertSame(['https://worknote.example', 'http://localhost:5174'], $app->origins);
        $previous = $app->token_hash;

        $this->artisan('push-apps:list')->expectsOutputToContain('Worknote')->assertSuccessful();

        $this->artisan('push-apps:rotate', ['name' => 'Worknote'])->assertSuccessful();
        $this->assertNotSame($previous, $app->fresh()->token_hash);

        $this->artisan('push-apps:create', [
            'name' => 'Worknote',
            '--origin' => ['https://worknote.example'],
        ])->assertFailed();
    }

    /**
     * @param  list<string>  $origins
     * @return array{id: string, token: string}
     */
    private function registerApp(string $name, array $origins): array
    {
        $response = $this->postJson('/api/apps', [
            'name' => $name,
            'origins' => $origins,
        ], $this->admin())->assertCreated();

        return [
            'id' => $response->json('id'),
            'token' => $response->json('token'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function admin(): array
    {
        return ['Authorization' => 'Bearer test-admin-token'];
    }

    /**
     * @return array{timezone?: string, subscription: array{endpoint: string, keys: array{p256dh: string, auth: string}}}
     */
    private function subscription(string $endpoint, ?string $timezone = null): array
    {
        $payload = [
            'subscription' => [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => str_repeat('a', 80),
                    'auth' => str_repeat('b', 22),
                ],
            ],
        ];

        if ($timezone !== null) {
            $payload['timezone'] = $timezone;
        }

        return $payload;
    }
}
