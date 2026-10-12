<?php

namespace App\Console\Commands;

use App\Push\PushAppRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

#[Signature('push-apps:create {name} {--origin=* : Site origin, such as https://example.com}')]
#[Description('Register an app and print its token')]
class CreatePushApp extends Command
{
    public function handle(PushAppRegistry $registry): int
    {
        /** @var list<string> $origins */
        $origins = array_values(array_filter($this->option('origin')));

        if ($origins === []) {
            $this->error('Give at least one --origin, such as --origin=https://example.com');

            return self::FAILURE;
        }

        try {
            ['app' => $app, 'token' => $token] = $registry->create($this->argument('name'), $origins);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (UniqueConstraintViolationException) {
            $this->error('An app with that name is already registered.');

            return self::FAILURE;
        }

        $this->info('Registered '.$app->name.'.');
        $this->line('App id: '.$app->id);
        $this->line('Origins: '.implode(', ', $app->origins));
        $this->newLine();
        $this->line('Token: '.$token);
        $this->newLine();
        $this->comment('Put this token in that app. It is not shown again.');

        return self::SUCCESS;
    }
}
