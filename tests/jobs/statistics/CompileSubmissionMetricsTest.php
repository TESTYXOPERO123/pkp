<?php

/**
 * @file tests/jobs/statistics/CompileSubmissionMetricsTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for compiling submission metrics job.
 */

namespace PKP\tests\jobs\statistics;

use Mockery;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use PKP\jobs\statistics\CompileSubmissionMetrics;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class CompileSubmissionMetricsTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo0NDoiUEtQXGpvYnNcc3RhdGlzdGljc1xDb21waWxlU3VibWlzc2lvbk1ldHJpY3MiOjM6e3M6OToiACoAbG9hZElkIjtzOjI1OiJ1c2FnZV9ldmVudHNfMjAyNDAxMzAubG9nIjtzOjEwOiJjb25uZWN0aW9uIjtzOjg6ImRhdGFiYXNlIjtzOjU6InF1ZXVlIjtzOjU6InF1ZXVlIjt9';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            CompileSubmissionMetrics::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var CompileSubmissionMetrics $compileSubmissionMetricsJob */
        $compileSubmissionMetricsJob = unserialize(base64_decode($this->serializedJobData));

        $temporaryTotalsDAOMock = Mockery::mock(\APP\statistics\TemporaryTotalsDAO::class)
            ->makePartial()
            ->shouldReceive([
                'removeDoubleClicks' => null,
            ])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO('TemporaryTotalsDAO', $temporaryTotalsDAOMock);

        $this->assertNull($compileSubmissionMetricsJob->handle());
    }
}
