<?php

namespace Umutcangungormus\LaravelImportExport\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Umutcangungormus\LaravelImportExport\Contracts\Exportable;
use Umutcangungormus\LaravelImportExport\Contracts\Importable;
use Umutcangungormus\LaravelImportExport\Support\HasImportExport;

/**
 * Lightweight Eloquent model used only by the test suite to exercise the
 * full importer with a real database table.
 */
class FakeImportModel extends Model implements Exportable, Importable
{
    use HasImportExport;

    protected $table = 'fake_import_items';

    protected $guarded = [];

    public $timestamps = true;
}
