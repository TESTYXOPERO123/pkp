<?php

/**
 * @file tests/jobs/statistics/CompileMonthlyMetricsTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for compiling monthly metrics job.
 */

namespace PKP\tests\jobs\statistics;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\jobs\statistics\CompileMonthlyMetrics;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class CompileMonthlyMetricsTest extends PKPTestCase
{
    /**
     * base64_encoded serializion from OJS 3.4.0
     */
    protected string $serializedJobData = 'Tzo0MToiUEtQXGpvYnNcc3RhdGlzdGljc1xDb21waWxlTW9udGhseU1ldHJpY3MiOjQ6e3M6ODoiACoAbW9udGgiO3M6NjoiMjAyNDA0IjtzOjc6IgAqAHNpdGUiO086MTM6IlBLUFxzaXRlXFNpdGUiOjY6e3M6NToiX2RhdGEiO2E6MTY6e3M6ODoicmVkaXJlY3QiO2k6MDtzOjEzOiJwcmltYXJ5TG9jYWxlIjtzOjI6ImVuIjtzOjE3OiJtaW5QYXNzd29yZExlbmd0aCI7aTo2O3M6MTY6Imluc3RhbGxlZExvY2FsZXMiO2E6Mjp7aTowO3M6MjoiZW4iO2k6MTtzOjU6ImZyX0NBIjt9czoxNjoic3VwcG9ydGVkTG9jYWxlcyI7YToyOntpOjA7czoyOiJlbiI7aToxO3M6NToiZnJfQ0EiO31zOjE3OiJjb21wcmVzc1N0YXRzTG9ncyI7YjowO3M6MTI6ImNvbnRhY3RFbWFpbCI7YToxOntzOjI6ImVuIjtzOjIzOiJwa3BhZG1pbkBtYWlsaW5hdG9yLmNvbSI7fXM6MTE6ImNvbnRhY3ROYW1lIjthOjI6e3M6MjoiZW4iO3M6MjA6Ik9wZW4gSm91cm5hbCBTeXN0ZW1zIjtzOjU6ImZyX0NBIjtzOjIwOiJPcGVuIEpvdXJuYWwgU3lzdGVtcyI7fXM6MTY6ImVuYWJsZUJ1bGtFbWFpbHMiO2E6Mjp7aTowO2k6MTtpOjE7aToyO31zOjE5OiJlbmFibGVHZW9Vc2FnZVN0YXRzIjtzOjg6ImRpc2FibGVkIjtzOjI3OiJlbmFibGVJbnN0aXR1dGlvblVzYWdlU3RhdHMiO2I6MDtzOjE5OiJpc1NpdGVTdXNoaVBsYXRmb3JtIjtiOjA7czoxNjoiaXNTdXNoaUFwaVB1YmxpYyI7YjoxO3M6MTk6ImtlZXBEYWlseVVzYWdlU3RhdHMiO2I6MDtzOjE1OiJ0aGVtZVBsdWdpblBhdGgiO3M6NzoiZGVmYXVsdCI7czoxMjoidW5pcXVlU2l0ZUlkIjtzOjM2OiJBNTcxN0Q0MS05NTlDLTREOTQtODNEQy1FQjRGMTBCQkU1QUYiO31zOjIwOiJfaGFzTG9hZGFibGVBZGFwdGVycyI7YjowO3M6Mjc6Il9tZXRhZGF0YUV4dHJhY3Rpb25BZGFwdGVycyI7YTowOnt9czoyNToiX2V4dHJhY3Rpb25BZGFwdGVyc0xvYWRlZCI7YjowO3M6MjY6Il9tZXRhZGF0YUluamVjdGlvbkFkYXB0ZXJzIjthOjA6e31zOjI0OiJfaW5qZWN0aW9uQWRhcHRlcnNMb2FkZWQiO2I6MDt9czoxMDoiY29ubmVjdGlvbiI7czo4OiJkYXRhYmFzZSI7czo1OiJxdWV1ZSI7czo1OiJxdWV1ZSI7fQ==';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            CompileMonthlyMetrics::class,
            unserialize(base64_decode($this->serializedJobData))
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob()
    {
        /** @var CompileMonthlyMetrics $compileMonthlyMetricsJob */
        $compileMonthlyMetricsJob = unserialize(base64_decode($this->serializedJobData));

        // Need to replace the container binding of `geoStats` and `sushiStats` with mock objects
        \APP\core\Services::register(
            new class extends \APP\services\OJSServiceProvider
            {
                public function register(\Pimple\Container $pimple)
                {
                    $pimple['geoStats'] = Mockery::mock(\PKP\services\PKPStatsGeoService::class)
                        ->makePartial()
                        ->shouldReceive([
                            'deleteMonthlyMetrics' => null,
                            'addMonthlyMetrics' => null,
                            'deleteDailyMetrics' => null,
                        ])
                        ->withAnyArgs()
                        ->getMock();
                    
                    $pimple['sushiStats'] = Mockery::mock(\PKP\services\PKPStatsSushiService::class)
                        ->makePartial()
                        ->shouldReceive([
                            'deleteMonthlyMetrics' => null,
                            'addMonthlyMetrics' => null,
                            'deleteDailyMetrics' => null,
                        ])
                        ->withAnyArgs()
                        ->getMock();
                }
            }
        );

        $this->assertNull($compileMonthlyMetricsJob->handle());
    }
}
