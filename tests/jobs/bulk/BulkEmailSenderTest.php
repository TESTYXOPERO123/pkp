<?php

/**
 * @file tests/jobs/bulk/BulkEmailSenderTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for bulk email sending job.
 */

namespace PKP\tests\jobs\bulk;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\jobs\bulk\BulkEmailSender;
use PKP\user\Collector as UserCollector;
use APP\user\Repository as UserRepository;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class BulkEmailSenderTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'TzoyOToiUEtQXGpvYnNcYnVsa1xCdWxrRW1haWxTZW5kZXIiOjk6e3M6MTA6IgAqAHVzZXJJZHMiO2E6Mzp7aTowO2k6MTtpOjE7aToyO2k6MjtpOjM7fXM6MTI6IgAqAGNvbnRleHRJZCI7aToxO3M6MTA6IgAqAHN1YmplY3QiO3M6MTI6IlRlc3Qgc3ViamVjdCI7czo3OiIAKgBib2R5IjtzOjE2OiI8cD5UZXN0IGJvZHk8L3A+IjtzOjEyOiIAKgBmcm9tRW1haWwiO3M6MjA6InJ2YWNhQG1haWxpbmF0b3IuY29tIjtzOjExOiIAKgBmcm9tTmFtZSI7czoxMToiUmFtaXJvIFZhY2EiO3M6MTA6ImNvbm5lY3Rpb24iO3M6ODoiZGF0YWJhc2UiO3M6NToicXVldWUiO3M6NToicXVldWUiO3M6NzoiYmF0Y2hJZCI7czozNjoiOWMxY2JjMDUtMDE3Yi00YTAyLWJkNWEtYjExM2M5MmE3NzM1Ijt9';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            BulkEmailSender::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        $this->mockMail();
        
        /** @var BulkEmailSender $bulkEmailSenderJob*/
        $bulkEmailSenderJob = unserialize(base64_decode($this->serializedJobData));

        $userCollectorMock = Mockery::mock(app(UserCollector::class))
            ->makePartial()
            ->shouldReceive('getMany')
            ->withAnyArgs()
            ->andReturn(\Illuminate\Support\LazyCollection::make([new \PKP\user\User]))
            ->getMock();
        
        app()->instance(UserCollector::class, $userCollectorMock);

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('getCollector')
            ->withAnyArgs()
            ->andReturn($userCollectorMock)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $this->assertNull($bulkEmailSenderJob->handle());
    }
}
