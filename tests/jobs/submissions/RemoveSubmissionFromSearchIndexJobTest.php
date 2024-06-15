<?php

/**
 * @file tests/jobs/submissions/RemoveSubmissionFromSearchIndexJobTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for removal of submission from search index job.
 */

namespace PKP\tests\jobs\submissions;

use Mockery;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use PKP\jobs\submissions\RemoveSubmissionFromSearchIndexJob;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class RemoveSubmissionFromSearchIndexJobTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo1NToiUEtQXGpvYnNcc3VibWlzc2lvbnNcUmVtb3ZlU3VibWlzc2lvbkZyb21TZWFyY2hJbmRleEpvYiI6Mzp7czoxNToiACoAc3VibWlzc2lvbklkIjtpOjI2O3M6MTA6ImNvbm5lY3Rpb24iO3M6ODoiZGF0YWJhc2UiO3M6NToicXVldWUiO3M6NToicXVldWUiO30=';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            RemoveSubmissionFromSearchIndexJob::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }
    
    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var RemoveSubmissionFromSearchIndexJob $removeSubmissionFromSearchIndexJob */
        $removeSubmissionFromSearchIndexJob = unserialize(base64_decode($this->serializedJobData));

        $submissionSearchDAOMock = Mockery::mock(\PKP\search\SubmissionSearchDAO::class)
            ->makePartial()
            ->shouldReceive(['deleteSubmissionKeywords' => null])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO('ArticleSearchDAO', $submissionSearchDAOMock);     // for OJS
        DAORegistry::registerDAO('MonographSearchDAO', $submissionSearchDAOMock);   // for OMP
        DAORegistry::registerDAO('PreprintSearchDAO', $submissionSearchDAOMock);    // for OPS

        // Test that the job can be handled without causing an exception.
        $this->assertNull($removeSubmissionFromSearchIndexJob->handle());
    }
}

