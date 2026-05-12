<?php

use Tests\TestCase;

require_once __DIR__ . '/Stubs/wp_functions.php';

uses(TestCase::class)->in('Unit');

uses()
    ->beforeEach(function (): void {
        \Tests\Stubs\OptionStore::reset();
        \Tests\Stubs\DbDeltaSpy::reset();
        \Tests\Stubs\ErrorLogSpy::reset();
    })
    ->in('Unit');
