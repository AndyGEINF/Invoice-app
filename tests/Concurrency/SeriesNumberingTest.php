<?php

declare(strict_types=1);

use App\Domain\Customers\Customer;
use App\Domain\Documents\Invoice;
use App\Domain\Documents\Series;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * SC-003: 50 emisiones simultáneas en la misma serie reciben 50 números
 * distintos y consecutivos, sin huecos.
 *
 * Cada emisión es un proceso PHP independiente con su propia conexión, así que
 * los datos de partida deben estar confirmados: aquí no vale RefreshDatabase
 * (lo envuelve todo en una transacción que los procesos no verían).
 */

uses(DatabaseMigrations::class);

const PARALLEL_ISSUES = 50;
const PROCESS_TIMEOUT_SECONDS = 120;

/**
 * Variables de entorno para que los procesos hijos usen la base de test y no
 * la de desarrollo del .env.
 *
 * @return array<string, string>
 */
function testDatabaseEnv(): array
{
    $connection = config('database.default');
    $db = config("database.connections.{$connection}");

    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => $connection,
        'DB_HOST' => (string) $db['host'],
        'DB_PORT' => (string) $db['port'],
        'DB_DATABASE' => (string) $db['database'],
        'DB_USERNAME' => (string) $db['username'],
        'DB_PASSWORD' => (string) $db['password'],
        'QUEUE_CONNECTION' => 'sync',
    ];
}

it('50 emisiones en paralelo reciben números distintos y consecutivos', function () {
    configuredIssuer();
    $series = Series::factory()->invoices()->create();
    $customer = Customer::factory()->create();

    $php = (new PhpExecutableFinder)->find(false);
    $env = testDatabaseEnv();

    /** @var list<Process> $processes */
    $processes = [];

    for ($i = 0; $i < PARALLEL_ISSUES; $i++) {
        $process = new Process(
            [$php, base_path('artisan'), 'invoice:issue-test-invoice', $series->id, $customer->id],
            base_path(),
            $env,
            null,
            PROCESS_TIMEOUT_SECONDS,
        );
        $process->start();
        $processes[] = $process;
    }

    $outputs = [];

    foreach ($processes as $process) {
        $process->wait();

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput());

        $outputs[] = trim($process->getOutput());
    }

    $numbers = Invoice::query()
        ->where('series_id', $series->id)
        ->whereNotNull('number')
        ->orderBy('number')
        ->pluck('number')
        ->all();

    expect($outputs)->toHaveCount(PARALLEL_ISSUES)
        ->and(array_unique($outputs))->toHaveCount(PARALLEL_ISSUES)
        ->and($numbers)->toBe(range(1, PARALLEL_ISSUES))
        ->and($series->refresh()->next_number)->toBe(PARALLEL_ISSUES + 1);
});
