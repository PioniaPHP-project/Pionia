<?php

namespace Process;

use Pionia\Process\PhpExecutable;
use Pionia\Process\Process;
use Pionia\TestSuite\PioniaTestCase;

class ProcessTest extends PioniaTestCase
{
    public function testRunsSimpleCommand(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'echo "ok";']);
        $process->setTimeout(5.0);
        $process->run();

        $this->assertTrue($process->isSuccessful());
        $this->assertSame('ok', $process->getOutput());
    }

    public function testCapturesStderr(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'fwrite(STDERR, "err"); exit(1);']);
        $process->setTimeout(5.0);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertSame('err', $process->getErrorOutput());
    }

    public function testFromShellCommandline(): void
    {
        $process = Process::fromShellCommandline(
            escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo "shell";'),
        );
        $process->setTimeout(5.0);
        $process->run();

        $this->assertTrue($process->isSuccessful());
        $this->assertSame('shell', $process->getOutput());
    }

    public function testCallbackReceivesOutputChunks(): void
    {
        $chunks = [];
        $process = new Process([PHP_BINARY, '-r', 'echo "chunk";']);
        $process->setTimeout(5.0);
        $process->run(static function (string $type, string $buffer) use (&$chunks): void {
            $chunks[] = [$type, $buffer];
        });

        $this->assertTrue($process->isSuccessful());
        $this->assertNotEmpty($chunks);
    }

    public function testPhpExecutableFindsBinary(): void
    {
        $binary = PhpExecutable::find(false);

        $this->assertNotSame('', $binary);
        $this->assertTrue(is_executable($binary) || $binary === 'php');
    }
}
