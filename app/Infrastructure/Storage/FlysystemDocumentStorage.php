<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Documents\Contracts\DocumentStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda los PDF en el disco de `invoice.storage.documents_disk`: local por
 * defecto, o S3 compatible.
 */
final class FlysystemDocumentStorage implements DocumentStorage
{
    public function put(string $path, string $bytes): void
    {
        $this->disk()->put($path, $bytes);
    }

    public function get(string $path): string
    {
        return (string) $this->disk()->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('invoice.storage.documents_disk'));
    }
}
