<?php

/**
 * MyCommPilot (BroadWorks) Call Log Scraper
 *
 * Authenticates to the BroadWorks MyCommPilot portal via session-based
 * web login, switches user contexts, and scrapes call log HTML tables
 * for placed, received, and missed calls.
 *
 * @package CirculationDashboard
 */

namespace CirculationDashboard;

class MyCommPilotScraper
{
    private string $baseUrl = 'https://ws2.mycommpilot.com';
    private string $httpBase = 'http://ws2.mycommpilot.com:80';
    private $ch;
    private string $cookieFile;
    private string $username;
    private string $password;

    /**
     * @param string $username BroadWorks group admin username
     * @param string $password BroadWorks group admin password
     */
    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'mcp_');
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'CirculationDashboard/2.0',
        ]);
    }

    public function __destruct()
    {
        if (is_resource($this->ch) || $this->ch instanceof \CurlHandle) {
            curl_close($this->ch);
        }
        @unlink($this->cookieFile);
    }

    // ── Authentication ───────────────────────────────────────────────────────

    /**
     * Three-step BroadWorks authentication flow.
     *
     * 1. GET /Login/ to capture session cookies
     * 2. POST /servlet/Login with credentials
     * 3. GET /Common/folder_contents.jsp to establish HTTP session
     *
     * @return bool True if login succeeded (response contains folder_contents.jsp redirect)
     */
    public function login(): bool
    {
        // Step 1: Get initial cookies (JSESSIONID, TS*)
        $this->get("{$this->baseUrl}/Login/");

        // Step 2: Submit login form
        $loginResponse = $this->post("{$this->baseUrl}/servlet/Login", [
            'EnteredUserID' => $this->username,
            'UserID'        => $this->username,
            'Password'      => $this->password,
            'domain'        => '',
        ]);

        if ($loginResponse === false) {
            return false;
        }

        // Step 3: Establish session on HTTP endpoint (portal redirects HTTPS -> HTTP)
        $this->get("{$this->httpBase}/Common/folder_contents.jsp?menuId=1");

        return str_contains($loginResponse, 'folder_contents.jsp');
    }

    /**
     * End the BroadWorks session.
     */
    public function logout(): void
    {
        $this->get("{$this->httpBase}/servlet/Logout");
    }

    // ── Call Log Retrieval ───────────────────────────────────────────────────

    /**
     * Fetch call logs for a specific user.
     *
     * Sets user context first (BroadWorks is session-based), then fetches
     * the appropriate call log page and parses the HTML table.
     *
     * @param string $userKey Full BroadWorks key, e.g.:
     *   "West+Carolina+Telephone::Edwards_Group::8649736678EgP"
     * @param string $type "placed", "received", or "missed"
     * @return array[] Each entry: ['name' => ..., 'phone' => ..., 'datetime' => ...]
     * @throws \RuntimeException If page has content but parsing yields zero entries
     */
    public function getCallLogs(string $userKey, string $type = 'placed'): array
    {
        // Set user context — server uses session to determine whose logs to show
        $encodedKey = str_replace('::', '%3A%3A', $userKey);
        $this->get("{$this->httpBase}/Group/Members/Modify/index.jsp?key={$encodedKey}");

        // Fetch the correct call log tab
        $typeMap = [
            'placed'   => '/User/BasicCallLogs/',
            'received' => '/User/BasicCallLogs/index.jsp?type=1',
            'missed'   => '/User/BasicCallLogs/index.jsp?type=2',
        ];
        $path = $typeMap[$type] ?? '/User/BasicCallLogs/';
        $html = $this->get("{$this->httpBase}{$path}");

        if ($html === false) {
            throw new \RuntimeException("Failed to fetch {$type} call logs — cURL error");
        }

        $entries = $this->parseCallLogs($html);

        // Detect broken parsing: page has substantial content but regex found nothing
        if ($this->hasContent($html) && empty($entries)) {
            throw new \RuntimeException(
                "HTML content found but no call log entries parsed for {$type} — possible HTML structure change"
            );
        }

        return $entries;
    }

    // ── Parsing ──────────────────────────────────────────────────────────────

    /**
     * Parse call log entries from BroadWorks HTML table.
     *
     * Extracts name/phone/datetime triples using regex on the HTML table
     * structure, decodes HTML entities, and validates each triple by checking
     * the datetime field matches the expected M/D/YY pattern.
     *
     * @param string $html Raw HTML from BroadWorks call log page
     * @return array[] Validated entries: ['name', 'phone', 'datetime']
     */
    private function parseCallLogs(string $html): array
    {
        preg_match_all('/<\/td>\s*<td\s*>([^<\n]+)/', $html, $matches);

        $values = array_values(array_filter(
            array_map(
                fn($v) => trim(html_entity_decode($v, ENT_QUOTES | ENT_HTML5)),
                $matches[1]
            )
        ));

        $entries = [];
        for ($i = 0; $i + 2 < count($values); $i += 3) {
            // Validate: third field in each triple must contain a date pattern
            if (preg_match('/\d{1,2}\/\d{1,2}\/\d{2}/', $values[$i + 2])) {
                $entries[] = [
                    'name'     => $values[$i],
                    'phone'    => $values[$i + 1],
                    'datetime' => $values[$i + 2],
                ];
            }
        }

        return $entries;
    }

    // ── Utility Methods ──────────────────────────────────────────────────────

    /**
     * Normalize phone number to bare 10-digit string.
     *
     * Matches AllSubscriberImporter::normalizePhone() logic exactly:
     * strip non-digits, remove leading '1' from 11-digit numbers,
     * return 10-digit result or null.
     *
     * @param string $phone Raw phone from BroadWorks (e.g., "8649736678", "6706", "Private")
     * @return string|null 10-digit bare number, or null for extensions/invalid
     */
    public function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);
        // Strip leading country code '1' from 11-digit numbers
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        return (strlen($digits) === 10) ? $digits : null;
    }

    /**
     * Parse BroadWorks datetime to MySQL DATETIME format.
     *
     * BroadWorks format: "3/19/26 8:02 AM" -> "2026-03-19 08:02:00"
     *
     * @param string $bwDatetime Raw datetime string from BroadWorks
     * @return string|null MySQL DATETIME string, or null if parse fails
     */
    public function parseBroadWorksDatetime(string $bwDatetime): ?string
    {
        $dt = \DateTime::createFromFormat('n/j/y g:i A', $bwDatetime);
        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    /**
     * Check if HTML page has substantial content.
     *
     * Distinguishes an empty/error page from a page with real content
     * where the regex simply found no matches (broken parsing).
     *
     * @param string $html Raw HTML response
     * @return bool True if page has more than 500 bytes of content
     */
    public function hasContent(string $html): bool
    {
        return strlen($html) > 500;
    }

    // ── Private HTTP Helpers ─────────────────────────────────────────────────

    /**
     * Execute a GET request.
     *
     * @param string $url Full URL to fetch
     * @return string|false Response body, or false on cURL error
     */
    private function get(string $url): string|false
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, false);
        return curl_exec($this->ch);
    }

    /**
     * Execute a POST request with form-encoded data.
     *
     * @param string $url Full URL to post to
     * @param array $data Key-value pairs for form fields
     * @return string|false Response body, or false on cURL error
     */
    private function post(string $url, array $data): string|false
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
        return curl_exec($this->ch);
    }
}
