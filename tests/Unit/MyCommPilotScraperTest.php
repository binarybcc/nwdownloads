<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use CirculationDashboard\MyCommPilotScraper;

/**
 * Tests for the MyCommPilot (BroadWorks) call log scraper.
 *
 * Regression context: on 2026-09-03 every scrape began failing with
 * "no call log entries parsed". The portal had stopped serving authenticated
 * sessions over plaintext http://ws2.mycommpilot.com:80 — those requests are
 * redirected to the HTTPS login page, so the scraper parsed a login page
 * instead of a call log table.
 */
class MyCommPilotScraperTest extends TestCase
{
    private function scraper(): MyCommPilotScraper
    {
        require_once PROJECT_ROOT . '/web/lib/MyCommPilotScraper.php';
        return new MyCommPilotScraper('test-user', 'test-pass');
    }

    /**
     * Root cause guard: the portal drops the session on plaintext HTTP.
     * Every base URL the scraper builds requests from must be HTTPS.
     */
    public function testAllPortalBaseUrlsUseHttps(): void
    {
        $scraper = $this->scraper();
        $props = (new ReflectionClass($scraper))->getProperties();

        $checked = 0;
        foreach ($props as $prop) {
            $value = $prop->getValue($scraper);
            if (!is_string($value) || !str_contains($value, 'mycommpilot.com')) {
                continue;
            }
            $checked++;
            $this->assertStringStartsWith(
                'https://',
                $value,
                "Property \${$prop->getName()} uses a non-HTTPS portal URL ({$value}). "
                . 'The BroadWorks portal drops the session on plaintext HTTP and redirects to /Login/.'
            );
            $this->assertStringNotContainsString(
                ':80',
                $value,
                "Property \${$prop->getName()} pins port 80 ({$value}); the portal serves sessions over 443 only."
            );
        }

        $this->assertGreaterThan(0, $checked, 'Expected at least one portal base URL to verify.');
    }

    /**
     * The BroadWorks table encodes cell text (9&#x2F;8&#x2F;26 for 9/8/26).
     * Entities must be decoded before the date validation runs.
     */
    public function testParsesEntityEncodedCallLogRows(): void
    {
        $scraper = $this->scraper();
        $html = file_get_contents(__DIR__ . '/fixtures/mycommpilot_call_logs.html');

        $parse = new ReflectionMethod($scraper, 'parseCallLogs');
        $entries = $parse->invoke($scraper, $html);

        $this->assertCount(2, $entries);
        $this->assertSame(
            ['name' => 'Jane Doe', 'phone' => '6333', 'datetime' => '9/8/26 8:22 AM'],
            $entries[0]
        );
        $this->assertSame(
            ['name' => 'Unavailable', 'phone' => '8645550147', 'datetime' => '9/3/26 3:38 PM'],
            $entries[1]
        );
    }

    /**
     * A login page must never be mistaken for an empty call log.
     */
    public function testLoginPageYieldsNoEntries(): void
    {
        $scraper = $this->scraper();
        $loginHtml = '<html><head><title>Login</title></head><body>'
            . '<table><tr><td>User ID</td><td ><input name="EnteredUserID"></td></tr></table>'
            . '</body></html>';

        $parse = new ReflectionMethod($scraper, 'parseCallLogs');

        $this->assertSame([], $parse->invoke($scraper, $loginHtml));
    }

    /**
     * A bounced session must be reported as an auth failure, not a parser failure.
     * Misreporting this cost several days of chasing a non-existent markup change.
     */
    public function testLoginPageIsDetectedAsLostSession(): void
    {
        $scraper = $this->scraper();
        $detect = new ReflectionMethod($scraper, 'isLoginPage');

        $loginHtml = '<html><title>Login</title><input name="EnteredUserID"></html>';
        $callLogHtml = file_get_contents(__DIR__ . '/fixtures/mycommpilot_call_logs.html');

        $this->assertTrue($detect->invoke($scraper, $loginHtml));
        $this->assertFalse($detect->invoke($scraper, $callLogHtml));
    }

    public function testParsesBroadWorksDatetime(): void
    {
        $this->assertSame(
            '2026-09-08 08:22:00',
            $this->scraper()->parseBroadWorksDatetime('9/8/26 8:22 AM')
        );
    }

    public function testNormalizePhoneStripsFormattingAndCountryCode(): void
    {
        $scraper = $this->scraper();
        $this->assertSame('8645550147', $scraper->normalizePhone('8645550147'));
        $this->assertSame('8645550147', $scraper->normalizePhone('18645550147'));
        $this->assertNull($scraper->normalizePhone('6333'));
        $this->assertNull($scraper->normalizePhone('Private'));
    }
}
