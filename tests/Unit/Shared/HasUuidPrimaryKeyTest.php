<?php

declare(strict_types=1);

use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

function uuidModel(): Model
{
    return new class extends Model
    {
        use HasUuidPrimaryKey;

        protected $table = 'ejemplos';
    };
}

describe('HasUuidPrimaryKey', function () {
    it('usa claves de texto no autoincrementales', function () {
        $model = uuidModel();

        expect($model->getIncrementing())->toBeFalse()
            ->and($model->getKeyType())->toBe('string')
            ->and($model->uniqueIds())->toBe(['id']);
    });

    it('genera un UUID de versión 7', function () {
        $uuid = uuidModel()->newUniqueId();

        expect($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });

    it('genera identificadores ordenados en el tiempo', function () {
        $model = uuidModel();

        $primero = $model->newUniqueId();
        usleep(2000);
        $segundo = $model->newUniqueId();

        expect(strcmp($segundo, $primero))->toBeGreaterThan(0);
    });
});
