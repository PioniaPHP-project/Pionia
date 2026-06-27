<?php

namespace Utils;

use Pionia\TestSuite\PioniaTestCase;
use Pionia\Utils\Filesystem;
use Pionia\Utils\Ulid;

class NativeUtilsTest extends PioniaTestCase
{
    public function testFilesystemDumpAndRemove(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pionia_fs_' . uniqid();
        $file = $base . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'demo.txt';

        $fs = new Filesystem();
        $fs->dumpFile($file, 'payload');

        $this->assertFileExists($file);
        $this->assertSame('payload', file_get_contents($file));

        $fs->remove($base);
        $this->assertDirectoryDoesNotExist($base);
    }

    public function testUlidExtractsTimestamp(): void
    {
        $value = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $this->assertTrue(Ulid::isValid($value));

        $ulid = Ulid::fromString($value);
        $this->assertSame(2016, (int) $ulid->getDateTime()->format('Y'));
    }
}
