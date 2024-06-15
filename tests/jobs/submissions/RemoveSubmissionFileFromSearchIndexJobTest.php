<?php

/**
 * @file tests/jobs/submissions/RemoveSubmissionFileFromSearchIndexJobTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for removal of submission file from search index job.
 */

namespace PKP\tests\jobs\submissions;

use Mockery;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use PKP\jobs\submissions\RemoveSubmissionFileFromSearchIndexJob;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class RemoveSubmissionFileFromSearchIndexJobTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo1OToiUEtQXGpvYnNcc3VibWlzc2lvbnNcUmVtb3ZlU3VibWlzc2lvbkZpbGVGcm9tU2VhcmNoSW5kZXhKb2IiOjQ6e3M6MTU6IgAqAHN1Ym1pc3Npb25JZCI7aToyNTtzOjE5OiIAKgBzdWJtaXNzaW9uRmlsZUlkIjtpOjU1O3M6MTA6ImNvbm5lY3Rpb24iO3M6ODoiZGF0YWJhc2UiO3M6NToicXVldWUiO3M6NToicXVldWUiO30=';

    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    public function testUnserializationGetJobInstance(): void
    {
        $this->assertInstanceOf(
            RemoveSubmissionFileFromSearchIndexJob::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }
    
    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var RemoveSubmissionFileFromSearchIndexJob $removeSubmissionFileFromSearchIndexJob */
        $removeSubmissionFileFromSearchIndexJob = unserialize(base64_decode($this->serializedJobData));

        $submissionSearchDAOMock = Mockery::mock(\PKP\search\SubmissionSearchDAO::class)
            ->makePartial()
            ->shouldReceive(['deleteSubmissionKeywords' => null])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO('ArticleSearchDAO', $submissionSearchDAOMock);     // for OJS
        DAORegistry::registerDAO('MonographSearchDAO', $submissionSearchDAOMock);   // for OMP
        DAORegistry::registerDAO('PreprintSearchDAO', $submissionSearchDAOMock);    // for OPS

        // Test that the job can be handled without causing an exception.
        $this->assertNull($removeSubmissionFileFromSearchIndexJob->handle());
    }
}

