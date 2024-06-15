<?php

/**
 * @file tests/jobs/notifications/StatisticsReportMailTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for statistics report mail job.
 */

namespace PKP\tests\jobs\notifications;

use Mockery;
use PKP\db\DAORegistry;
use APP\core\Application;
use PKP\tests\PKPTestCase;
use APP\user\Repository as UserRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;
use PKP\jobs\notifications\StatisticsReportMail;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class StatisticsReportMailTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo0MzoiUEtQXGpvYnNcbm90aWZpY2F0aW9uc1xTdGF0aXN0aWNzUmVwb3J0TWFpbCI6Njp7czoxMDoiACoAdXNlcklkcyI7TzoyOToiSWxsdW1pbmF0ZVxTdXBwb3J0XENvbGxlY3Rpb24iOjI6e3M6ODoiACoAaXRlbXMiO2E6NTp7aTowO2k6MTtpOjE7aToyO2k6MjtpOjM7aTozO2k6NDtpOjQ7aTo2O31zOjI4OiIAKgBlc2NhcGVXaGVuQ2FzdGluZ1RvU3RyaW5nIjtiOjA7fXM6MTI6IgAqAGNvbnRleHRJZCI7aToxO3M6MTI6IgAqAGRhdGVTdGFydCI7TzoxNzoiRGF0ZVRpbWVJbW11dGFibGUiOjM6e3M6NDoiZGF0ZSI7czoyNjoiMjAyNC0wNS0wMSAwMDowMDowMC4wMDAwMDAiO3M6MTM6InRpbWV6b25lX3R5cGUiO2k6MztzOjg6InRpbWV6b25lIjtzOjEwOiJBc2lhL0RoYWthIjt9czoxMDoiACoAZGF0ZUVuZCI7TzoxNzoiRGF0ZVRpbWVJbW11dGFibGUiOjM6e3M6NDoiZGF0ZSI7czoyNjoiMjAyNC0wNi0wMSAwMDowMDowMC4wMDAwMDAiO3M6MTM6InRpbWV6b25lX3R5cGUiO2k6MztzOjg6InRpbWV6b25lIjtzOjEwOiJBc2lhL0RoYWthIjt9czoxMDoiY29ubmVjdGlvbiI7czo4OiJkYXRhYmFzZSI7czo1OiJxdWV1ZSI7czo1OiJxdWV1ZSI7fQ==';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            StatisticsReportMail::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var StatisticsReportMail $statisticsReportMailJob */
        $statisticsReportMailJob = unserialize(base64_decode($this->serializedJobData));

        $this->mockRequest();

        $this->mockMail();

        $contextDaoClass = get_class(Application::getContextDAO());

        $contextMock = Mockery::mock(get_class(Application::getContextDAO()->newDataObject()))
            ->makePartial()
            ->shouldReceive([
                'getId' => 0,
                'getPrimaryLocale' => 'en',
                'getContactEmail' => 'testmail@mail.test',
                'getContactName' => 'Test User',
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

        // Need to replace the container binding of `editorialStats` with mock object
        \APP\core\Services::register(
            new class extends \APP\services\OJSServiceProvider
            {
                public function register(\Pimple\Container $pimple)
                {
                    $pimple['editorialStats'] = Mockery::mock(\APP\services\StatsEditorialService::class)
                        ->makePartial()
                        ->shouldReceive([
                            'getOverview' => [
                                [
                                    'key' => 'submissionsReceived',
                                    'name' => 'stats.name.submissionsReceived',
                                    'value' => 0,
                                ],
                                [
                                    'key' => 'submissionsAccepted',
                                    'name' => 'stats.name.submissionsAccepted',
                                    'value' => 0,
                                ],
                                [
                                    'key' => 'submissionsDeclined',
                                    'name' => 'stats.name.submissionsDeclined',
                                    'value' => 0,
                                ],
                                [
                                    'key' => 'submissionsSkipped',
                                    'name' => 'stats.name.submissionsSkipped',
                                    'value' => 0,
                                ],
                            ],
                            'countSubmissionsReceived' => 0,
                        ])
                        ->withAnyArgs()
                        ->getMock();
                }
            }
        );

        $userMock = Mockery::mock(\PKP\user\User::class)
            ->makePartial()
            ->shouldReceive('getId')
            ->withAnyArgs()
            ->andReturn(0)
            ->getMock();

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($userMock)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $this->assertNull($statisticsReportMailJob->handle());
    }
}
