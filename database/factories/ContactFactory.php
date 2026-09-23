<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Customers\Contact;
use App\Domain\Customers\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
final class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'is_default' => false,
        ];
    }

    public function default(): self
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
