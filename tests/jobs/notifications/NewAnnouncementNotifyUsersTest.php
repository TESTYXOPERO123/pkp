<?php

/**
 * @file tests/jobs/notifications/NewAnnouncementNotifyUsersTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for new announcement notification ot users job.
 */

namespace PKP\tests\jobs\notifications;

use Mockery;
use PKP\db\DAORegistry;
use APP\core\Application;
use PKP\tests\PKPTestCase;
use APP\user\Repository as UserRepository;
use PKP\jobs\notifications\NewAnnouncementNotifyUsers;
use PKP\announcement\Repository as AnnouncementRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class NewAnnouncementNotifyUsersTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo0OToiUEtQXGpvYnNcbm90aWZpY2F0aW9uc1xOZXdBbm5vdW5jZW1lbnROb3RpZnlVc2VycyI6Nzp7czoxNToiACoAcmVjaXBpZW50SWRzIjtPOjI5OiJJbGx1bWluYXRlXFN1cHBvcnRcQ29sbGVjdGlvbiI6Mjp7czo4OiIAKgBpdGVtcyI7YTozOntpOjA7aToyO2k6MTtpOjM7aToyO2k6NDt9czoyODoiACoAZXNjYXBlV2hlbkNhc3RpbmdUb1N0cmluZyI7YjowO31zOjEyOiIAKgBjb250ZXh0SWQiO2k6MTtzOjE3OiIAKgBhbm5vdW5jZW1lbnRJZCI7aToxO3M6OToiACoAbG9jYWxlIjtzOjI6ImVuIjtzOjk6IgAqAHNlbmRlciI7TzoxMzoiUEtQXHVzZXJcVXNlciI6Nzp7czo1OiJfZGF0YSI7YToyMjp7czoyOiJpZCI7aToxO3M6ODoidXNlck5hbWUiO3M6NToiYWRtaW4iO3M6ODoicGFzc3dvcmQiO3M6NjA6IiQyeSQxMCR1Rm1ZWGc4L1VmYTBIYnNreVc1N0JlMjJzdEZHWTVxdHhKWm1UT2FlM1BmREI4NlYzeDdCVyI7czo1OiJlbWFpbCI7czoyMzoicGtwYWRtaW5AbWFpbGluYXRvci5jb20iO3M6MzoidXJsIjtOO3M6NToicGhvbmUiO047czoxNDoibWFpbGluZ0FkZHJlc3MiO047czoxNDoiYmlsbGluZ0FkZHJlc3MiO047czo3OiJjb3VudHJ5IjtOO3M6NzoibG9jYWxlcyI7YTowOnt9czo2OiJnb3NzaXAiO047czoxMzoiZGF0ZUxhc3RFbWFpbCI7TjtzOjE0OiJkYXRlUmVnaXN0ZXJlZCI7czoxOToiMjAyMy0wMi0yOCAyMDoxOTowNyI7czoxMzoiZGF0ZVZhbGlkYXRlZCI7TjtzOjEzOiJkYXRlTGFzdExvZ2luIjtzOjE5OiIyMDI0LTA1LTIyIDE5OjA1OjAzIjtzOjE4OiJtdXN0Q2hhbmdlUGFzc3dvcmQiO047czo3OiJhdXRoU3RyIjtOO3M6ODoiZGlzYWJsZWQiO2I6MDtzOjE0OiJkaXNhYmxlZFJlYXNvbiI7TjtzOjEwOiJpbmxpbmVIZWxwIjtiOjE7czoxMDoiZmFtaWx5TmFtZSI7YToxOntzOjI6ImVuIjtzOjU6ImFkbWluIjt9czo5OiJnaXZlbk5hbWUiO2E6MTp7czoyOiJlbiI7czo1OiJhZG1pbiI7fX1zOjIwOiJfaGFzTG9hZGFibGVBZGFwdGVycyI7YjowO3M6Mjc6Il9tZXRhZGF0YUV4dHJhY3Rpb25BZGFwdGVycyI7YTowOnt9czoyNToiX2V4dHJhY3Rpb25BZGFwdGVyc0xvYWRlZCI7YjowO3M6MjY6Il9tZXRhZGF0YUluamVjdGlvbkFkYXB0ZXJzIjthOjA6e31zOjI0OiJfaW5qZWN0aW9uQWRhcHRlcnNMb2FkZWQiO2I6MDtzOjk6IgAqAF9yb2xlcyI7YTowOnt9fXM6MTA6ImNvbm5lY3Rpb24iO3M6ODoiZGF0YWJhc2UiO3M6NToicXVldWUiO3M6NToicXVldWUiO30=';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            NewAnnouncementNotifyUsers::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        $this->mockMail();
        
        $this->mockRequest();

        /** @var NewAnnouncementNotifyUsers $newAnnouncementNotifyUsersJob */
        $newAnnouncementNotifyUsersJob = unserialize(base64_decode($this->serializedJobData));

        $announcementMock = Mockery::mock(\PKP\announcement\Announcement::class)
            ->makePartial()
            ->shouldReceive([
                'getAssocId' => 0,
                'getLocalizedTitle' => '',
            ])
            ->withAnyArgs()
            ->getMock();

        $announcementRepoMock = Mockery::mock(app(AnnouncementRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($announcementMock)
            ->getMock();
        
        app()->instance(AnnouncementRepository::class, $announcementRepoMock);

        $contextDaoClass = get_class(Application::getContextDAO());

        $contextMock = Mockery::mock(get_class(Application::getContextDAO()->newDataObject()))
            ->makePartial()
            ->shouldReceive([
                'getId' => 0,
            ])
            ->withAnyArgs()
            ->getMock();

        $contextDaoMock = Mockery::mock($contextDaoClass)
            ->makePartial()
            ->shouldReceive('getById')
            ->withAnyArgs()
            ->andReturn($contextMock)
            ->getMock();

        DAORegistry::registerDAO(substr(strrchr($contextDaoClass, '\\'), 1), $contextDaoMock);

        $emailTemplateMock = Mockery::mock(\PKP\emailTemplate\EmailTemplate::class)
            ->makePartial()
            ->shouldReceive([
                'getLocalizedData' => '',
            ])
            ->withAnyArgs()
            ->getMock();

        $emailTemplateRepoMock = Mockery::mock(app(EmailTemplateRepository::class))
            ->makePartial()
            ->shouldReceive([
                'getByKey' => $emailTemplateMock,
            ])
            ->withAnyArgs()
            ->getMock();

        app()->instance(EmailTemplateRepository::class, $emailTemplateRepoMock);

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn(new \PKP\user\User)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $this->assertNull($newAnnouncementNotifyUsersJob->handle());
    }
}
