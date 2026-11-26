<?php

// SPDX-License-Identifier: Apache-2.0

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $storage = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Artifacts land in a throwaway storage directory, never in the real one.
        $this->storage = $storage = sys_get_temp_dir().'/odac-site-tests-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($storage.'/app');
        File::ensureDirectoryExists($storage.'/framework/testing');
        File::ensureDirectoryExists($storage.'/framework/views');
        File::ensureDirectoryExists($storage.'/logs');
        $this->app->useStoragePath($storage);

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        if ($this->storage !== '') {
            File::deleteDirectory($this->storage);
        }

        parent::tearDown();
    }
}
