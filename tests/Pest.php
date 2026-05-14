<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Umutcangungormus\LaravelImportExport\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');
uses(RefreshDatabase::class)->in('Unit', 'Feature');
