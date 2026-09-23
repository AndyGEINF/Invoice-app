<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reglas de arquitectura (constitución)
|--------------------------------------------------------------------------
|
| Estos tests fallan si alguien rompe una regla de la constitución, aunque el
| resto de la aplicación siga funcionando.
|
*/

use Illuminate\Database\Eloquent\SoftDeletes;

/** Líneas de código PHP de un directorio, sin comentarios, con su fichero y número. */
function codeLines(string $directory): Generator
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) as $index => $line) {
            $trimmed = ltrim($line);

            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '//')) {
                continue;
            }

            yield $file->getPathname().':'.($index + 1) => $line;
        }
    }
}

describe('capas (principio III)', function () {
    arch('el dominio no conoce la infraestructura')
        ->expect('App\Domain')
        ->not->toUse('App\Infrastructure');

    arch('el dominio no conoce HTTP')
        ->expect('App\Domain')
        ->not->toUse(['Illuminate\Http', 'App\Http']);

    arch('los casos de uso no conocen la infraestructura')
        ->expect('App\Application')
        ->not->toUse('App\Infrastructure');
});

describe('inmutabilidad (principio II)', function () {
    arch('ningún documento usa borrado lógico')
        ->expect('App\Domain\Documents')
        ->not->toUse(SoftDeletes::class);
});

describe('sin cuentas de usuario (principio V)', function () {
    arch('no se usa Fortify ni Sanctum')
        ->expect('App')
        ->not->toUse(['Laravel\Fortify', 'Laravel\Sanctum']);

    it('ninguna ruta exige autenticación', function () {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $authenticated = $routes->filter(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($middleware) => str_starts_with((string) $middleware, 'auth')));

        expect($authenticated->map->uri()->values()->all())->toBe([]);
    });
});

describe('importes exactos (principio I)', function () {
    it('el dominio no usa float salvo la excepción documentada de presentación', function () {
        $offending = [];

        foreach (codeLines(app_path('Domain')) as $location => $line) {
            if (preg_match('/\bfloat\b/', $line) === 1 && ! str_contains($line, 'float-ok')) {
                $offending[] = $location.' → '.trim($line);
            }
        }

        expect($offending)->toBe([]);
    });

    it('el dominio no redondea con round() de PHP', function () {
        $offending = [];

        foreach (codeLines(app_path('Domain')) as $location => $line) {
            $callsPhpRound = preg_match('/(?<![\w\$>:])round\s*\(/', $line) === 1;

            if ($callsPhpRound && ! str_contains($line, 'function round')) {
                $offending[] = $location.' → '.trim($line);
            }
        }

        expect($offending)->toBe([]);
    });
});
