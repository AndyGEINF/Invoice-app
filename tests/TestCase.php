<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /** Emitir genera el PDF: en los tests no debe llegar a storage/app. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('invoice.storage.documents_disk'));
    }
}
