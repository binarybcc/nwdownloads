# MyCommPilot Call Log Integration Guide

## Overview

We have a working method to programmatically extract call logs from the MyCommPilot (BroadWorks) phone system at `ws2.mycommpilot.com`. This document covers everything needed to integrate this into another project (e.g., the circulation renewal dashboard) via PHP, cron, or other workflow.

---

## System Details

| Item            | Value                               |
| --------------- | ----------------------------------- |
| **Platform**    | BroadWorks by BroadSoft (now Cisco) |
| **Portal URL**  | `https://ws2.mycommpilot.com`       |
| **Login type**  | Group admin (not individual user)   |
| **Auth method** | Session-based web login (form POST) |
| **Credentials** | Stored in `.env` — see below        |
| **Data format** | HTML tables, parsed via regex       |

### Credentials (.env)

```
MYCOMMPILOT_USERNAME=EdwardsGroup
MYCOMMPILOT_PASSWORD=Ice$8Mojave
MYCOMMPILOT_URL=https://ws2.mycommpilot.com/Login/
```

### Monitored Phone Lines

| Line                  | BroadWorks User ID | Internal Key                                            |
| --------------------- | ------------------ | ------------------------------------------------------- |
| BC (Brittany Carroll) | `8649736678EgP`    | `West+Carolina+Telephone::Edwards_Group::8649736678EgP` |
| CW (Chloe Welch)      | `8649736689EgP`    | `West+Carolina+Telephone::Edwards_Group::8649736689EgP` |

To add more users, search for them at `/Group/Members/` — the key format is `{ServiceProvider}::{Group}::{UserId}`.

---

## Authentication Flow

BroadWorks uses session-based auth through a JSP web portal. Here's the exact sequence:

### Step 1: Get initial cookies

```
GET https://ws2.mycommpilot.com/Login/
```

Captures `JSESSIONID` and two `TS*` cookies.

### Step 2: Submit login form

```
POST https://ws2.mycommpilot.com/servlet/Login
Content-Type: application/x-www-form-urlencoded

EnteredUserID=EdwardsGroup&UserID=EdwardsGroup&Password=Ice%248Mojave&domain=
```

- Note: `$` in password must be URL-encoded as `%24`
- Response is a JavaScript redirect page (HTTP 200, not a 302)
- The redirect target contains `folder_contents.jsp` — use this to verify login success

### Step 3: Establish session on HTTP endpoint

```
GET http://ws2.mycommpilot.com:80/Common/folder_contents.jsp?menuId=1
```

- **Important**: After login via HTTPS, the portal redirects to plain HTTP on port 80
- All subsequent requests use `http://ws2.mycommpilot.com:80`
- Cookies must be carried across both HTTPS and HTTP

### Step 4: Logout when done

```
GET http://ws2.mycommpilot.com:80/servlet/Logout
```

---

## Fetching Call Logs

### Set user context

Before accessing a user's call logs, you must "enter" their profile:

```
GET http://ws2.mycommpilot.com:80/Group/Members/Modify/index.jsp?key={URL-encoded key}
```

Example:

```
GET http://ws2.mycommpilot.com:80/Group/Members/Modify/index.jsp?key=West+Carolina+Telephone%3A%3AEdwards_Group%3A%3A8649736678EgP
```

### Fetch call log pages

| Call Type | URL                                    |
| --------- | -------------------------------------- |
| Placed    | `/User/BasicCallLogs/`                 |
| Received  | `/User/BasicCallLogs/index.jsp?type=1` |
| Missed    | `/User/BasicCallLogs/index.jsp?type=2` |

### Parsing the HTML response

Call log data is in an HTML table. Each entry has 3 cells (Name, Phone Number, Date/Time) that follow this pattern:

```html
<td valign="top"><input type="CHECKBOX" name="checkbox_45195401239:0" /></td>
<td>Voice&#x20;Portal&#x20;Voice&#x20;Portal</td>
<td width="0" valign="top">&nbsp;</td>
<td>8649737731</td>
<td width="0" valign="top">&nbsp;</td>
<td>3&#x2F;20&#x2F;26&#x20;8:02&#x20;AM</td>
```

**Regex to extract values (Python):**

```python
import re, html
raw_values = re.findall(r'</td>\s*<td\s*>([^<\n]+)', html_content)
clean = [html.unescape(v.strip()) for v in raw_values if v.strip()]
# Group into triples: clean[0::3]=names, clean[1::3]=phones, clean[2::3]=datetimes
```

**Equivalent PHP regex:**

```php
preg_match_all('/<\/td>\s*<td\s*>([^<\n]+)/', $html, $matches);
$values = array_map(fn($v) => trim(html_entity_decode($v, ENT_QUOTES | ENT_HTML5)), $matches[1]);
$values = array_filter($values);
// Group into chunks of 3: array_chunk($values, 3)
```

### Data format

Each call log entry yields:

| Field        | Example           | Notes                                                                       |
| ------------ | ----------------- | --------------------------------------------------------------------------- |
| Name         | `Jeremy Power`    | Internal contacts show real names; external show CALLER-ID or "Unavailable" |
| Phone Number | `6706`            | 4-digit = internal extension; 10-digit = external number                    |
| Date/Time    | `3/19/26 8:02 AM` | Format: `M/D/YY h:mm AM/PM`                                                 |

### Important limitations

- **Only 20 entries per call type** are shown at any time (BroadWorks limitation)
- Data is a rolling window — older entries fall off as new calls come in
- **Must scrape regularly** (daily recommended) to build a complete history
- No pagination or date-range filtering available through the web portal

---

## XSI REST API (Not Currently Working)

BroadWorks has a REST API called XSI (Xtended Services Interface) at:

```
https://ws2.mycommpilot.com/com.broadsoft.xsi-actions/v2.0/
```

The endpoint exists and returns `401 Unauthorized` with HTTP Basic Auth, which means it's active but our group admin credentials don't have XSI access configured. If Segra (the carrier) enables XSI for this account, the API would provide:

- Proper pagination and date filtering
- JSON/XML response format
- Call detail records with duration
- No HTML parsing needed

**XSI call logs endpoint (if enabled):**

```
GET /com.broadsoft.xsi-actions/v2.0/user/{userId}/directories/CallLogs
Authorization: Basic {base64(userId:password)}
```

Worth asking Segra about enabling XSI access — it would be a much cleaner integration.

---

## PHP Integration Example

```php
class MyCommPilotScraper {
    private string $baseUrl = 'https://ws2.mycommpilot.com';
    private string $httpBase = 'http://ws2.mycommpilot.com:80';
    private $ch;
    private string $cookieFile;

    public function __construct(
        private string $username,
        private string $password
    ) {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'mcp_');
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
        ]);
    }

    public function __destruct() {
        curl_close($this->ch);
        @unlink($this->cookieFile);
    }

    private function get(string $url): string {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, false);
        return curl_exec($this->ch);
    }

    private function post(string $url, array $data): string {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
        return curl_exec($this->ch);
    }

    public function login(): bool {
        // Step 1: Get cookies
        $this->get("{$this->baseUrl}/Login/");

        // Step 2: Submit login
        $resp = $this->post("{$this->baseUrl}/servlet/Login", [
            'EnteredUserID' => $this->username,
            'UserID' => $this->username,
            'Password' => $this->password,
            'domain' => '',
        ]);

        // Step 3: Establish HTTP session
        $this->get("{$this->httpBase}/Common/folder_contents.jsp?menuId=1");

        return str_contains($resp, 'folder_contents.jsp');
    }

    public function logout(): void {
        $this->get("{$this->httpBase}/servlet/Logout");
    }

    /**
     * Fetch call logs for a specific user.
     *
     * @param string $userKey Full BroadWorks key, e.g.:
     *   "West+Carolina+Telephone::Edwards_Group::8649736678EgP"
     * @param string $type "placed", "received", or "missed"
     * @return array[] Each entry: ['name' => ..., 'phone' => ..., 'datetime' => ...]
     */
    public function getCallLogs(string $userKey, string $type = 'placed'): array {
        // Set user context
        $encodedKey = str_replace('::', '%3A%3A', $userKey);
        $this->get("{$this->httpBase}/Group/Members/Modify/index.jsp?key={$encodedKey}");

        // Fetch the right tab
        $typeMap = ['placed' => '', 'received' => '1', 'missed' => '2'];
        $typeParam = $typeMap[$type] ?? '';
        $url = $typeParam === ''
            ? "{$this->httpBase}/User/BasicCallLogs/"
            : "{$this->httpBase}/User/BasicCallLogs/index.jsp?type={$typeParam}";

        $html = $this->get($url);
        return $this->parseCallLogs($html);
    }

    private function parseCallLogs(string $html): array {
        preg_match_all('/<\/td>\s*<td\s*>([^<\n]+)/', $html, $matches);

        $values = array_values(array_filter(
            array_map(
                fn($v) => trim(html_entity_decode($v, ENT_QUOTES | ENT_HTML5)),
                $matches[1]
            )
        ));

        $entries = [];
        for ($i = 0; $i + 2 < count($values); $i += 3) {
            if (preg_match('/\d{1,2}\/\d{1,2}\/\d{2}/', $values[$i + 2])) {
                $entries[] = [
                    'name' => $values[$i],
                    'phone' => $values[$i + 1],
                    'datetime' => $values[$i + 2],
                ];
            }
        }
        return $entries;
    }
}
```

### Usage example

```php
$scraper = new MyCommPilotScraper('EdwardsGroup', 'Ice$8Mojave');

if ($scraper->login()) {
    $bcKey = 'West+Carolina+Telephone::Edwards_Group::8649736678EgP';

    $placed   = $scraper->getCallLogs($bcKey, 'placed');
    $received = $scraper->getCallLogs($bcKey, 'received');
    $missed   = $scraper->getCallLogs($bcKey, 'missed');

    // Insert into database, update dashboard, etc.
    foreach ($placed as $call) {
        echo "{$call['name']} | {$call['phone']} | {$call['datetime']}\n";
    }

    $scraper->logout();
}
```

---

## Cron Integration

### Simple: Run the Python script on a schedule

```bash
# crontab -e
# Run every 4 hours during business hours (Mon-Fri, 7am-7pm)
0 7,11,15,19 * * 1-5 cd /path/to/call-logs/csv && python3 fetch_call_logs.py >> /var/log/call-log-fetch.log 2>&1
```

### Better: PHP cron that writes directly to a database

```php
// In your Laravel/WordPress cron or scheduled task:
$scraper = new MyCommPilotScraper($username, $password);
$scraper->login();

$users = [
    ['key' => 'West+Carolina+Telephone::Edwards_Group::8649736678EgP', 'label' => 'BC'],
    ['key' => 'West+Carolina+Telephone::Edwards_Group::8649736689EgP', 'label' => 'CW'],
];

foreach ($users as $user) {
    foreach (['placed', 'received', 'missed'] as $type) {
        $calls = $scraper->getCallLogs($user['key'], $type);
        foreach ($calls as $call) {
            // INSERT IGNORE deduplicates on the unique key
            $db->query(
                "INSERT IGNORE INTO call_logs
                    (line_label, call_type, name, phone, call_datetime)
                VALUES (?, ?, ?, ?, ?)",
                [$user['label'], $type, $call['name'], $call['phone'], $call['datetime']]
            );
        }
    }
}

$scraper->logout();
```

### Database schema suggestion

```sql
CREATE TABLE call_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    line_label VARCHAR(10) NOT NULL,
    call_type ENUM('placed','received','missed') NOT NULL,
    name VARCHAR(100),
    phone VARCHAR(20),
    call_datetime VARCHAR(30),
    call_timestamp DATETIME,
    is_extension BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY dedup (line_label, call_type, phone, call_datetime)
);
```

---

## Key Gotchas

1. **HTTPS to HTTP redirect**: Login happens over HTTPS, but the portal redirects to plain HTTP on port 80 for all subsequent pages. Your HTTP client must handle cookies across both protocols.

2. **20-entry limit**: BroadWorks Basic Call Logs only shows the 20 most recent calls per type. No pagination. Must scrape frequently to avoid data gaps.

3. **User context is session-based**: You must visit `/Group/Members/Modify/index.jsp?key={key}` before accessing `/User/BasicCallLogs/` — the server uses your session to know which user's logs to show.

4. **HTML entity encoding**: Names and dates use hex entities (`&#x20;` for space, `&#x2F;` for `/`). Always decode HTML entities before using the data.

5. **Commas in names**: Some caller ID names contain commas (e.g., `CONDREY,GLENDA`). Handle this in CSV output by quoting, or store in a database instead.

6. **"Private" callers**: Some entries have `Private` for both name and phone number — no usable data for these.

7. **Session timeout**: Sessions expire after inactivity. For cron jobs, always do a fresh login each run rather than trying to reuse sessions.

8. **No call duration**: Basic Call Logs don't include call duration. Only name, number, and timestamp. Enhanced Call Logs or CDR exports (if available) would have duration.

---

## File Reference

| File                        | Purpose                                                                               |
| --------------------------- | ------------------------------------------------------------------------------------- |
| `.env`                      | Credentials (do not commit to git)                                                    |
| `csv/fetch_call_logs.py`    | Python scraper — login, fetch, deduplicate, update CSVs                               |
| `csv/convert_to_xlsx.py`    | Convert individual CSVs to formatted XLSX                                             |
| `csv/create_merged_xlsx.py` | Merge all call types into one chronological XLSX with color coding and summary tables |

---

## Future Improvements

- **Ask Segra about XSI API access** — would eliminate HTML parsing entirely
- **Enhanced Call Logs** — BroadWorks has these if enabled, includes call duration
- **CDR (Call Detail Records)** — group-level export if Segra provides access, includes all calls with full metadata
- **Webhook/event subscription** — BroadWorks XSI-Events can push real-time call events if enabled
