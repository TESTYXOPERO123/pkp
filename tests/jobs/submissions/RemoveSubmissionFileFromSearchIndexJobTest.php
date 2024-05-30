<?php

/**
 * @file tests/jobs/submissions/UpdateSubmissionSearchJobTest.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the submission search reindexing job.
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
     * Serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'O:59:"PKP\jobs\submissions\RemoveSubmissionFileFromSearchIndexJob":4:{s:15:" * submissionId";i:25;s:19:" * submissionFileId";i:55;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperDepositIssueJobInstance(): void
    {
        $this->assertInstanceOf(
            RemoveSubmissionFileFromSearchIndexJob::class,
            unserialize($this->serializedJobData)
        );
    }
    
    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var RemoveSubmissionFileFromSearchIndexJob $removeSubmissionFileFromSearchIndexJob */
        $removeSubmissionFileFromSearchIndexJob = unserialize($this->serializedJobData);

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

