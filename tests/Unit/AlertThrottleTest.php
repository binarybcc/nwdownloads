<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CirculationDashboard\AlertThrottle;

/**
 * Tests for alert throttling.
 *
 * Regression context: the 2026-09-03 portal outage sent roughly 430 identical
 * alert emails over five days — six per run, every hour, for five days.
 */
class AlertThrottleTest extends TestCase
{
    private string $stateFile;

    protected function setUp(): void
    {
        require_once PROJECT_ROOT . '/web/lib/AlertThrottle.php';
        $this->stateFile = sys_get_temp_dir() . '/alert_throttle_test_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->stateFile);
    }

    public function testHealthyRunSendsNothing(): void
    {
        $throttle = new AlertThrottle($this->stateFile);
        $this->assertSame('none', $throttle->evaluate([])['action']);
    }

    public function testFirstFailureAlerts(): void
    {
        $throttle = new AlertThrottle($this->stateFile);
        $this->assertSame('alert', $throttle->evaluate(['BC/placed: broken'])['action']);
    }

    /**
     * The actual outage shape: the same six failures, hourly, for days.
     */
    public function testRepeatedIdenticalFailureIsSuppressed(): void
    {
        $throttle = new AlertThrottle($this->stateFile, 21600);
        $failures = ['BC/placed: broken', 'BC/missed: broken', 'CW/placed: broken'];

        $start = 1757000000;
        $this->assertSame('alert', $throttle->evaluate($failures, $start)['action']);

        // Hourly runs across the next five hours stay quiet.
        $sent = 0;
        for ($hour = 1; $hour <= 5; $hour++) {
            if ($throttle->evaluate($failures, $start + ($hour * 3600))['action'] === 'alert') {
                $sent++;
            }
        }
        $this->assertSame(0, $sent, 'Identical failures within the quiet period must not re-alert.');
    }

    public function testUnchangedFailureRealertsAfterQuietPeriod(): void
    {
        $throttle = new AlertThrottle($this->stateFile, 21600);
        $failures = ['BC/placed: broken'];
        $start = 1757000000;

        $throttle->evaluate($failures, $start);
        $this->assertSame('none', $throttle->evaluate($failures, $start + 21599)['action']);

        $result = $throttle->evaluate($failures, $start + 21600);
        $this->assertSame('alert', $result['action']);
        $this->assertGreaterThan(0, $result['suppressed'], 'Re-alert should report how many runs were suppressed.');
    }

    public function testDifferentFailureAlertsImmediately(): void
    {
        $throttle = new AlertThrottle($this->stateFile);
        $start = 1757000000;

        $throttle->evaluate(['BC/placed: broken'], $start);
        $this->assertSame(
            'alert',
            $throttle->evaluate(['BC/placed: broken', 'CW/missed: also broken'], $start + 60)['action'],
            'A failure that changes shape is new information and must be reported.'
        );
    }

    public function testFailureOrderDoesNotCountAsNewProblem(): void
    {
        $throttle = new AlertThrottle($this->stateFile);
        $start = 1757000000;

        $throttle->evaluate(['a: broken', 'b: broken'], $start);
        $this->assertSame(
            'none',
            $throttle->evaluate(['b: broken', 'a: broken'], $start + 60)['action']
        );
    }

    public function testRecoveryIsAnnouncedOnceThenSilence(): void
    {
        $throttle = new AlertThrottle($this->stateFile);
        $start = 1757000000;

        $throttle->evaluate(['BC/placed: broken'], $start);

        $recovery = $throttle->evaluate([], $start + 3600);
        $this->assertSame('recovery', $recovery['action']);
        $this->assertSame($start, $recovery['since'], 'Recovery should report when the problem started.');

        $this->assertSame('none', $throttle->evaluate([], $start + 7200)['action']);
    }

    /**
     * Five days of hourly failures should produce one alert and one recovery,
     * not one email per failing scrape per run.
     */
    public function testOutageProducesTwoEmailsPerQuietPeriodNotHundreds(): void
    {
        $throttle = new AlertThrottle($this->stateFile, 21600);
        $failures = array_map(fn($i) => "combo{$i}: broken", range(1, 6));
        $start = 1757000000;

        $emails = 0;
        // 12 business-hour runs a day for 5 days.
        for ($run = 0; $run < 60; $run++) {
            if ($throttle->evaluate($failures, $start + ($run * 3600))['action'] === 'alert') {
                $emails++;
            }
        }
        if ($throttle->evaluate([], $start + (60 * 3600))['action'] === 'recovery') {
            $emails++;
        }

        // One initial alert, a re-alert every 6 hours, plus one recovery.
        $this->assertLessThanOrEqual(12, $emails);
        $this->assertGreaterThan(0, $emails);
    }
}
