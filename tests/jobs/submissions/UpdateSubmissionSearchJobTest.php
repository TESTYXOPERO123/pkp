<?php

/**
 * @file tests/jobs/submissions/UpdateSubmissionSearchJobTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the submission search reindexing job.
 */

namespace PKP\tests\jobs\submissions;

use PKP\tests\PKPTestCase;
use PKP\jobs\submissions\UpdateSubmissionSearchJob;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class UpdateSubmissionSearchJobTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo0NjoiUEtQXGpvYnNcc3VibWlzc2lvbnNcVXBkYXRlU3VibWlzc2lvblNlYXJjaEpvYiI6Mzp7czoxNToiACoAc3VibWlzc2lvbklkIjtpOjE3O3M6MTA6ImNvbm5lY3Rpb24iO3M6ODoiZGF0YWJhc2UiO3M6NToicXVldWUiO3M6NToicXVldWUiO30=';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            UpdateSubmissionSearchJob::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }
    
    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var UpdateSubmissionSearchJob $updateSubmissionSearchJob */
        $updateSubmissionSearchJob = unserialize(base64_decode($this->serializedJobData));

        // Mock the Submission facade to return a fake submission when Repo::submission()->get($id) is called
        $mock = Mockery::mock(app(\APP\submission\Repository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn(new \APP\submission\Submission())
            ->getMock();

        app()->instance(\APP\submission\Repository::class, $mock);

        // Test that the job can be handled without causing an exception.
        $this->assertNull($updateSubmissionSearchJob->handle());
    }
}

