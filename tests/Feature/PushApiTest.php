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
     * @return array{subscription: array{endpoint: string, keys: array{p256dh: string, auth: string}}}
     */
    private function subscription(string $endpoint): array
    {
        return [
            'subscription' => [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => str_repeat('a', 80),
                    'auth' => str_repeat('b', 22),
                ],
            ],
        ];
    }
}
