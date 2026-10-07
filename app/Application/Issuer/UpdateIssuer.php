<?php

declare(strict_types=1);

namespace App\Application\Issuer;

use App\Application\Issuer\Data\IssuerData;
use App\Application\Issuer\Data\LogoFile;
use App\Domain\Issuer\Issuer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Guarda los datos del emisor (la fila única).
 *
 * El logotipo se guarda con el hash de su contenido como nombre
 * (`{sha256}.{ext}`) y nunca se borra ni se sobrescribe: las facturas ya
 * emitidas apuntan a su logotipo de entonces desde el snapshot congelado.
 * Devuelve el emisor actualizado: `isComplete()` y `missing()` dicen si ya puede
 * emitir.
 */
final readonly class UpdateIssuer
{
    private const string HASH_ALGORITHM = 'sha256';

    public function __invoke(IssuerData $data): Issuer
    {
        $issuer = Issuer::current();

        $issuer->fill([
            'name' => $data->name,
            'company_name' => $data->companyName,
            'tax_id' => $data->taxId->value,
            'tax_id_type' => $data->taxId->type,
            'address' => $data->address,
            'vat_regime' => $data->vatRegime,
            'default_irpf_rate' => $data->defaultIrpfRate,
            'email' => $data->email,
            'phone' => $data->phone,
            'website' => $data->website,
            'invoice_footer' => $data->invoiceFooter,
        ]);

        if ($data->logo !== null) {
            $issuer->logo_path = $this->storeLogo($data->logo);
        }

        $issuer->save();

        return $issuer->refresh();
    }

    /** Mismo contenido ⇒ mismo nombre: subir dos veces el mismo logotipo no duplica ficheros. */
    private function storeLogo(LogoFile $logo): string
    {
        $contents = file_get_contents($logo->path);

        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el logotipo subido.');
        }

        $path = hash(self::HASH_ALGORITHM, $contents).'.'.strtolower($logo->extension);
        $disk = Storage::disk(config('invoice.storage.logos_disk'));

        if (! $disk->exists($path)) {
            $disk->put($path, $contents);
        }

        return $path;
    }
}
