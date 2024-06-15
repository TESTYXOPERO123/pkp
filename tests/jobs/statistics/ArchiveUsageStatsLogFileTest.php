<?php

/**
 * @file tests/jobs/statistics/ArchiveUsageStatsLogFileTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for archiving usage stats log file job.
 */

namespace PKP\tests\jobs\statistics;

use ReflectionClass;
use PKP\task\FileLoader;
use PKP\tests\PKPTestCase;
use APP\statistics\StatisticsHelper;
use PKP\jobs\statistics\ArchiveUsageStatsLogFile;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class ArchiveUsageStatsLogFileTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo0NDoiUEtQXGpvYnNcc3RhdGlzdGljc1xBcmNoaXZlVXNhZ2VTdGF0c0xvZ0ZpbGUiOjQ6e3M6OToiACoAbG9hZElkIjtzOjI1OiJ1c2FnZV9ldmVudHNfMjAyNDAxMzAubG9nIjtzOjc6IgAqAHNpdGUiO086MTM6IlBLUFxzaXRlXFNpdGUiOjY6e3M6NToiX2RhdGEiO2E6MTY6e3M6ODoicmVkaXJlY3QiO2k6MDtzOjEzOiJwcmltYXJ5TG9jYWxlIjtzOjI6ImVuIjtzOjE3OiJtaW5QYXNzd29yZExlbmd0aCI7aTo2O3M6MTY6Imluc3RhbGxlZExvY2FsZXMiO2E6Mjp7aTowO3M6MjoiZW4iO2k6MTtzOjU6ImZyX0NBIjt9czoxNjoic3VwcG9ydGVkTG9jYWxlcyI7YToyOntpOjA7czoyOiJlbiI7aToxO3M6NToiZnJfQ0EiO31zOjE3OiJjb21wcmVzc1N0YXRzTG9ncyI7YjowO3M6MTI6ImNvbnRhY3RFbWFpbCI7YToxOntzOjI6ImVuIjtzOjIzOiJwa3BhZG1pbkBtYWlsaW5hdG9yLmNvbSI7fXM6MTE6ImNvbnRhY3ROYW1lIjthOjI6e3M6MjoiZW4iO3M6MjA6Ik9wZW4gSm91cm5hbCBTeXN0ZW1zIjtzOjU6ImZyX0NBIjtzOjIwOiJPcGVuIEpvdXJuYWwgU3lzdGVtcyI7fXM6MTY6ImVuYWJsZUJ1bGtFbWFpbHMiO2E6Mjp7aTowO2k6MTtpOjE7aToyO31zOjE5OiJlbmFibGVHZW9Vc2FnZVN0YXRzIjtzOjg6ImRpc2FibGVkIjtzOjI3OiJlbmFibGVJbnN0aXR1dGlvblVzYWdlU3RhdHMiO2I6MDtzOjE5OiJpc1NpdGVTdXNoaVBsYXRmb3JtIjtiOjA7czoxNjoiaXNTdXNoaUFwaVB1YmxpYyI7YjoxO3M6MTk6ImtlZXBEYWlseVVzYWdlU3RhdHMiO2I6MDtzOjE1OiJ0aGVtZVBsdWdpblBhdGgiO3M6NzoiZGVmYXVsdCI7czoxMjoidW5pcXVlU2l0ZUlkIjtzOjM2OiJBNTcxN0Q0MS05NTlDLTREOTQtODNEQy1FQjRGMTBCQkU1QUYiO31zOjIwOiJfaGFzTG9hZGFibGVBZGFwdGVycyI7YjowO3M6Mjc6Il9tZXRhZGF0YUV4dHJhY3Rpb25BZGFwdGVycyI7YTowOnt9czoyNToiX2V4dHJhY3Rpb25BZGFwdGVyc0xvYWRlZCI7YjowO3M6MjY6Il9tZXRhZGF0YUluamVjdGlvbkFkYXB0ZXJzIjthOjA6e31zOjI0OiJfaW5qZWN0aW9uQWRhcHRlcnNMb2FkZWQiO2I6MDt9czoxMDoiY29ubmVjdGlvbiI7czo4OiJkYXRhYmFzZSI7czo1OiJxdWV1ZSI7czo1OiJxdWV1ZSI7fQ==';

    /**
     * Content example from OJS 3.4.0
     */
    protected $dummyFileContent = '{"time":"2023-08-07 17:27:11","ip":"228dc4e5b6424e9dad52f21261cb2ab5f4651d9cb426d6fdb3d71d5ab8e2ae83","userAgent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/115.0","canonicalUrl":"http:\/\/ojs-stable-3_4_0.test\/index.php\/publicknowledge\/index","assocType":256,"contextId":1,"submissionId":null,"representationId":null,"submissionFileId":null,"fileType":null,"country":null,"region":null,"city":null,"institutionIds":[],"version":"3.4.0.0","issueId":null,"issueGalleyId":null}';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            ArchiveUsageStatsLogFile::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var ArchiveUsageStatsLogFile $archiveUsageStatsLogFileJob */
        $archiveUsageStatsLogFileJob = unserialize(base64_decode($this->serializedJobData));

        // we need to create a dummy file if not existed as to avoid mocking PHP's built in functions
        $dummyFileName = $this->createDummyFileIfNeeded($archiveUsageStatsLogFileJob, 'loadId');

        $this->assertNull($archiveUsageStatsLogFileJob->handle());

        if ($dummyFileName) {
            unlink(
                StatisticsHelper::getUsageStatsDirPath()
                    . '/'
                    . FileLoader::FILE_LOADER_PATH_ARCHIVE
                    . '/'
                    .$dummyFileName
            );
        }
    }

    /**
     * Create the dummy file with dummy content if required
     */
    protected function createDummyFileIfNeeded(ArchiveUsageStatsLogFile $job, string $propertyName): ?string
    {
        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $fileName = $property->getValue($job);

        $filePath = StatisticsHelper::getUsageStatsDirPath()
            . DIRECTORY_SEPARATOR
            . FileLoader::FILE_LOADER_PATH_DISPATCH
            . DIRECTORY_SEPARATOR;

        if (!file_exists($filePath . $fileName)) {
            file_put_contents($filePath . $fileName, $this->dummyFileContent);
            return $fileName;
        }

        return null;
    }
}
