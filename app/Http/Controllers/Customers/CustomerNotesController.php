<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Application\Customers\UpdateCustomerNotes;
use App\Domain\Customers\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Guarda las notas del cliente desde su ficha, sin pasar por el formulario completo. */
final class CustomerNotesController extends Controller
{
    public function __invoke(Request $request, Customer $customer, UpdateCustomerNotes $updateNotes): RedirectResponse
    {
        $validated = $request->validate(
            ['notes' => ['nullable', 'string', 'max:'.CustomerRequest::MAX_NOTES_LENGTH]],
            attributes: ['notes' => 'notas'],
        );

        $updateNotes($customer, $validated['notes'] ?? null);

        Inertia::flash('success', 'Notas guardadas.');

        return back();
    }
}
