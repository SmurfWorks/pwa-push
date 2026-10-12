<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;

#[Signature('push:install')]
#[Description('Write a VAPID key pair and an admin token into .env when they are missing')]
class InstallPush extends Command
{
    public function handle(): int
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            $this->error('No .env file. Copy .env.example to .env first.');

            return self::FAILURE;
        }

        $created = false;

        if ($this->readEnv('VAPID_PUBLIC_KEY') === '' || $this->readEnv('VAPID_PRIVATE_KEY') === '') {
            $keys = VAPID::createVapidKeys();
            $this->putEnv('VAPID_PUBLIC_KEY', $keys['publicKey']);
            $this->putEnv('VAPID_PRIVATE_KEY', $keys['privateKey']);
            $created = true;
            $this->info('VAPID public key: '.$keys['publicKey']);
        } else {
            $this->info('VAPID public key: '.$this->readEnv('VAPID_PUBLIC_KEY'));
        }

        if ($this->readEnv('VAPID_SUBJECT') === '') {
            $this->putEnv('VAPID_SUBJECT', 'mailto:pwa-push@localhost');
            $this->comment('VAPID_SUBJECT is a placeholder. Set it to a mailto: address you control.');
        }

        if ($this->readEnv('PUSH_ADMIN_TOKEN') === '') {
            $token = Str::random(48);
            $this->putEnv('PUSH_ADMIN_TOKEN', $token);
            $created = true;
            $this->newLine();
            $this->info('Admin token: '.$token);
            $this->comment('Use this token to register apps. It is not shown again.');
        } elseif (! $created) {
            $this->info('Push is already installed.');
        }

        $this->newLine();
        $this->line('Register an app:');
        $this->line('  php artisan push-apps:create "Dosage Helper" --origin=https://example.com --origin=http://localhost:5173');

        return self::SUCCESS;
    }

    private function readEnv(string $key): string
    {
        $contents = file_get_contents(base_path('.env'));

        if ($contents === false || ! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return '';
        }

        return trim($matches[1], " \t\"'");
    }

    private function putEnv(string $key, string $value): void
    {
        $path = base_path('.env');
        $contents = file_get_contents($path);
        $line = $key.'='.$value;

        if ($contents === false) {
            return;
        }

        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $contents)) {
            $contents = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($path, $contents);
    }
}
