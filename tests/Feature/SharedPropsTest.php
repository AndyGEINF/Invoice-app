<?php

declare(strict_types=1);

use Database\Factories\IssuerFactory;
use Inertia\Testing\AssertableInertia as Assert;

describe('datos compartidos en todas las páginas', function () {
    it('avisan de lo que falta con un emisor sin configurar', function () {
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('issuer.isComplete', false)
            ->where('issuer.missing', ['name', 'logo', 'tax_id', 'address'])
            ->where('issuer.logoUrl', null)
        );
    });

    it('muestran nombre, empresa y logotipo con el emisor completo', function () {
        configuredIssuer();

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('issuer.isComplete', true)
            ->where('issuer.missing', [])
            ->where('issuer.name', 'Andy Moreno')
            ->where('issuer.companyName', 'Demo SL')
            ->where('issuer.legalName', 'Demo SL')
            ->where('issuer.logoUrl', fn (string $url) => str_ends_with($url, '/storage/logos/'.IssuerFactory::TEST_LOGO_PATH))
        );
    });

    it('incluyen los tipos impositivos y las causas de exención', function () {
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('invoiceConfig.vatRates', ['21.00', '10.00', '4.00', '0.00'])
            ->where('invoiceConfig.surchargeByVatRate', [
                '21.00' => '5.20',
                '10.00' => '1.40',
                '4.00' => '0.50',
                '0.00' => '0.00',
            ])
            ->has('invoiceConfig.exemptionCodes.E1')
            ->has('invoiceConfig.exemptionCodes.NS')
        );
    });
});
