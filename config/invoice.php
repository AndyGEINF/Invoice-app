<?php

use App\Domain\Documents\Enums\ExemptionCode;

/*
|--------------------------------------------------------------------------
| Configuración de dominio de INVOICE
|--------------------------------------------------------------------------
|
| Valores fiscales y de negocio que usan el motor de impuestos, la emisión y
| la interfaz. Los porcentajes van como cadena con dos decimales para no pasar
| nunca por float (constitución, principio I).
|
*/

return [

    'tax' => [
        // Tipos de IVA admitidos en territorio común.
        'vat_rates' => ['21.00', '10.00', '4.00', '0.00'],

        // Tipo que propone una línea nueva.
        'default_vat_rate' => '21.00',

        // Recargo de equivalencia asociado a cada tipo de IVA.
        'surcharge_rates' => ['5.20', '1.40', '0.50', '0.00'],
        'surcharge_by_vat_rate' => [
            '21.00' => '5.20',
            '10.00' => '1.40',
            '4.00' => '0.50',
            '0.00' => '0.00',
        ],

        // Tipos de retención de IRPF sugeridos (el emisor puede fijar otro por defecto).
        'irpf_rates' => ['15.00', '7.00', '0.00'],

        // Causas de exención (códigos AEAT E1–E6) y operación no sujeta.
        // El texto legal vive en el enum ExemptionCode para no duplicarlo.
        'exemption_codes' => array_reduce(
            ExemptionCode::cases(),
            static fn (array $codes, ExemptionCode $code): array => $codes + [$code->value => $code->legalText()],
            [],
        ),
    ],

    'series' => [
        // Relleno de ceros del número (F2026-0001).
        'default_padding' => 4,

        // Series que se crean en la instalación.
        'defaults' => [
            ['document_type' => 'invoice', 'code' => 'F', 'prefix' => 'F'],
            ['document_type' => 'credit_note', 'code' => 'R', 'prefix' => 'R'],
            ['document_type' => 'quote', 'code' => 'P', 'prefix' => 'P'],
        ],
    ],

    'quote' => [
        // Días de validez por defecto de un presupuesto.
        'default_validity_days' => 30,
    ],

    'invoice' => [
        // Importe a partir del cual una factura simplificada (F2) genera un aviso.
        // Es un aviso, no un bloqueo: hay excepciones sectoriales.
        'simplified_limit_cents' => 40000,

        // Plazo de pago por defecto si el cliente no tiene uno.
        'default_payment_terms_days' => 30,
    ],

    'storage' => [
        // Disco donde se guardan los PDFs emitidos ('documents' local o 's3').
        'documents_disk' => env('INVOICE_DOCUMENTS_DISK', 'documents'),
        // Disco público de logotipos.
        'logos_disk' => env('INVOICE_LOGOS_DISK', 'logos'),
    ],

    'pdf' => [
        // Browsershot / Chromium. Si están vacíos, Browsershot busca los binarios en el PATH.
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 60),
    ],

    'issuer' => [
        // Logotipo obligatorio para emitir.
        'logo_max_kb' => 2048,
        'logo_mimes' => ['png', 'jpg', 'jpeg', 'svg'],
        // Muestras del selector de color de marca (también se puede elegir otro).
        'brand_colors' => ['#2563eb', '#4f46e5', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#16a34a', '#0d9488', '#334155'],
    ],

    // Validación de números de IVA intracomunitarios (siempre en cola, nunca bloquea).
    'vies' => [
        // "rest" consulta VIES de verdad; "fake" responde siempre válido sin red (tests).
        'driver' => env('VIES_DRIVER', 'rest'),
        'endpoint' => env('VIES_ENDPOINT', 'https://ec.europa.eu/taxation_customs/vies/rest-api'),
        'timeout_seconds' => 5,
        // Un número validado hace menos de esto no se vuelve a consultar.
        'revalidate_after_days' => 30,
        'tries' => 5,
        // Espera antes de cada reintento si VIES no responde (crece de forma exponencial).
        'backoff_seconds' => [30, 120, 480, 1920],
    ],

];
