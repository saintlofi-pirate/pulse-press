<?php

use Tests\TestCase;

require_once __DIR__ . '/Stubs/wp_functions.php';

uses(TestCase::class)->in('Unit');

uses()
    ->beforeEach(function (): void {
        \Tests\Stubs\OptionStore::reset();
        \Tests\Stubs\DbDeltaSpy::reset();
        \Tests\Stubs\ErrorLogSpy::reset();
        \Tests\Stubs\TransientStore::reset();
        \Tests\Stubs\FilterRegistry::reset();
        \Tests\Stubs\WpSaltStub::reset();
        \Tests\Stubs\PostRegistry::reset();
        \Tests\Stubs\RestRouteSpy::reset();
        \Tests\Stubs\WpEnv::reset();
        \Tests\Stubs\AssetSpy::reset();
    })
    ->in('Unit');
