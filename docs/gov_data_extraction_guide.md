# Government Data Extraction Strategy

> **Challenge:** High-value public data (Property, Business, Permits) is often locked behind clunky, anti-scraping search forms.  
> **Goal:** Automate ingestion for (1) News Content (Transfer lists) and (2) Marketing Intelligence (New homeowner targeting).

---

## 1. The "Front Door" (Open Data Portals)
Before scraping, check if they are hiding the CSV in plain sight.
*   **Socrata / CKAN:** Many counties use these platforms. Look for `/data.json` or "Developer" links in the footer.
*   **The "Bulk" Option:** Some Assessors sell the entire SQL dump of the county for a nominal fee (e.g., $50/CD-ROM). This is often cheaper than building a scraper.
    *   *Action:* Call the County Assessor's tech department directly. Ask for the "Master Assessment Roll" export.

---

## 2. The "Side Door" (Hidden APIs)
Most "manual" search sites are actually modern Single Page Apps (SPAs) talking to a JSON API. They just didn't document it.

**The Tactic:**
1.  Open Chrome DevTools (`F12`) -> **Network Tab**.
2.  Perform a search on the site (e.g., "Smith").
3.  Look for the XHR/Fetch request.
4.  **The Gold Mine:** You will often find a request like:
    `GET /api/v1/search?q=Smith`
    Or better:
    `POST /api/search { "date_range": "last_30_days", "limit": 100 }`

**Why this works:**
*   You can often re-play this request in Python/PHP without the browser.
*   You can remove the "Limit" filters (e.g., change `limit=10` to `limit=5000`).

---

## 3. The "Back Door" (Automated Browser Automation)
If they use CAPTCHAs or heavy obfuscation (e.g., ViewState tokens in ASP.NET sites), you need a **Headless Browser**.

**Tools:**
*   **Puppeteer / Playwright:** These are programmable Chrome browsers. They "see" the page exactly like a human.
*   **The Workflow:**
    1.  Script enters website.
    2.  Script types a wildcard `%` or a date range (1/1/2025 - 1/2/2025) into the search box.
    3.  Script clicks "Next Page" and saves the results.

**Bypassing Limits:**
*   **"Iterative Scraping":** If they limit results to 100, don't search everything. Search:
    *   "A*" -> Get 100 results.
    *   "B*" -> Get 100 results.
    *   ...
    *   "Z*" -> Get 100 results.
    *   *Result:* You get the whole database by "nibbling" it.

---

## 4. The "Legal Key" (FOIA / Public Records Request)
Government data is **public record**. They cannot legally hide it from you, though they can charge for "programming time."

**The Tactic:**
*   **Standing Request:** File a monthly/weekly FOIA request for "All building permits issued in the previous week in CSV format."
*   **Automated FOIA:** Use services like *MuckRock* to automate the legal nagging.
*   **News Tactic:** If they maintain a "PDF only" list, use **OCR (Optical Character Recognition)** API (like AWS Textract) to convert the PDF back to Excel automatically.

---

## 5. Specific Strategies by Data Type

| Data Class | Best Extraction Method | Application |
| :--- | :--- | :--- |
| **Property Transfers** (Deeds) | **County Clerk / Register of Deeds** sites often have "FTP" access for title companies. Ask for "Commercial Access." | **News:** "Home Sales Report" content. <br> **Biz:** New Homeowner marketing. |
| **Business Licenses** | **Secretary of State** usually has a bulk CSV download. County-level business license portals are often separate. | **News:** "New Business Watch." <br> **Biz:** B2B Sales leads. |
| **Building Permits** | **CityGov Portals (Accela / EnerGov)**. These platforms almost ALWAYS have hidden APIs. Look for "citizen access" URLs. | **News:** Development tracking. <br> **Biz:** Construction lead gen. |
| **Mugshots / Arrests** | **Sheriff's Roster**. Often primitive HTML tables. Easiest to verify with simple scraping. | **News:** Police blotter content. |

---

## ⚠️ Ethical & Legal "Rules of the Road"

1.  **Don't DDoS the Government:** Rate limit your requests (1 request per 2 seconds). If you crash their server, you become the news.
2.  **Respect "No Trespassing":** If `robots.txt` says "Disallow", you should arguably respect it or ask permission, though case law (HiQ v LinkedIn) suggests public data is scrappable.
3.  **The "News Exception":** As a publisher, you have a stronger moral/legal claim to access public records than a marketer. Use your press credentials to get the "Bulk Export" rather than scraping.

## Recommendation for You:
Start with the **Hidden API** check on your local County Assessor site. 9 times out of 10, the "Search" button is just calling a hidden URL you can use directly.
