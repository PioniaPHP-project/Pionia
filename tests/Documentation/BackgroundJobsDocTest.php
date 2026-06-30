<?php

namespace Documentation;

use Pionia\Http\Background\JobSubmission;
use Pionia\TestSuite\PioniaTestCase;

class BackgroundJobsDocTest extends PioniaTestCase
{
    public function testAsyncHelperIsRegistered(): void
    {
        $this->assertTrue(function_exists('async'));
    }

    public function testAwaitHelperIsRegistered(): void
    {
        $this->assertTrue(function_exists('await'));
    }

    public function testPromiseHelpersAreRegistered(): void
    {
        $this->assertTrue(function_exists('promiseCatch'));
        $this->assertTrue(function_exists('promiseFinally'));
    }

    public function testAgentsMdDocumentsAsyncSection(): void
    {
        $agents = file_get_contents(dirname(__DIR__, 2) . '/AGENTS.md');

        $this->assertIsString($agents);
        $this->assertStringContainsString('Background work', $agents);
        $this->assertStringContainsString('async(', $agents);
    }

    public function testJobSubmissionClassExists(): void
    {
        $submission = new JobSubmission('job-1');

        $this->assertSame('job-1', $submission->jobId);
        $this->assertTrue($submission->queued);
    }
}
