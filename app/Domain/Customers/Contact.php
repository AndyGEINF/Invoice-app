<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use App\Domain\Shared\Concerns\HasUuidPrimaryKey;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persona de contacto de un cliente, a quien se envían los documentos.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_default
 */
#[UseFactory(ContactFactory::class)]
final class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $fillable = ['name', 'email', 'phone', 'is_default'];

    protected $attributes = ['is_default' => false];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
