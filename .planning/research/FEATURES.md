# Feature Research

**Domain:** Newspaper circulation retention dashboard — call log overlay on expiration data
**Researched:** 2026-03-20
**Confidence:** MEDIUM — Core patterns grounded in CRM/retention industry norms; newspaper-specific timing based on industry-standard 2-3 week pre-expiration outreach window. Call status UX patterns extrapolated from general CRM table design literature (no single authoritative source found for this exact niche).

---

## Context: What Is Already Built

This is a subsequent milestone. The following are existing, deployed features that new features depend on:

- Subscription expiration chart — 4 buckets (Past Due, This Week, Next Week, Week +2), covers 21 days
- Right-click context menu on chart bars shows subscriber list with phone numbers
- Subscriber list table includes name, phone, paper, expiration date, subscriber type
- XLSX export of subscriber lists
- Automated CSV import pipeline (5 importers via `auto_process.php`)
- BU detail slide-out panel with trend charts

The new milestone adds: call log scraper, call status overlay, expanded expiration view, and an import bug fix.

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features that make the call integration feel complete. Missing these = staff can't do their job with the tool.

| Feature                                                  | Why Expected                                                                                                | Complexity | Notes                                                                                               |
| -------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- | ---------- | --------------------------------------------------------------------------------------------------- |
| Call status indicator per subscriber row                 | Core purpose of the integration — staff need to see at a glance who has/hasn't been called                  | MEDIUM     | Phone icon + row background color; already decided as Option B in PROJECT.md                        |
| "Last called" timestamp in subscriber table              | Staff need to know when a call happened, not just that one did                                              | LOW        | Pull from `call_logs` table; format as relative ("2 days ago") + tooltip with exact datetime        |
| Distinguish Brittany (BC) vs Chloe (CW) call attribution | Two staff members share the workload; each needs to see who called whom                                     | LOW        | Separate icon or badge per agent; data available from `line_label` field in schema                  |
| Call status carries into XLSX export                     | Staff print and share contact lists; color-coded rows must persist                                          | MEDIUM     | SheetJS (already in use) supports cell fill color; requires mapping CSS colors to ARGB hex for xlsx |
| Not-called subscriber sort/filter                        | Staff want to work down a list of uncalled subscribers efficiently                                          | LOW        | Default sort: not-called first, then by expiration date ascending                                   |
| 8-week expiration view (vs current 4-week)               | Print renewals need 2-3 weeks lead time minimum; a 4-week window misses subscribers who expire in weeks 5-8 | MEDIUM     | Extend SQL CASE from 4 buckets to 8 (Week +3 through Week +6); chart scrollable or compressed       |

### Differentiators (Value Beyond Bare Minimum)

Features that make this tool genuinely better than a spreadsheet or basic report.

| Feature                                             | Value Proposition                                                                                                                  | Complexity | Notes                                                                                                                                                                                                                                        |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Call status shown directly on expiration chart bars | Zero-click insight: staff see "how many of the expiring subscribers in this week's bar have been called" without opening the table | HIGH       | Requires aggregating call match data per bucket and overlaying as a stacked bar or badge on existing Chart.js bars; HIGH complexity because Chart.js 4.x doesn't have built-in stacked sub-coloring on bar segments without data restructure |
| Per-agent call activity summary                     | Managers can see Brittany called 12 subscribers this week, Chloe called 8                                                          | LOW        | Simple count aggregation from `call_logs`; a small summary row above or below the subscriber table                                                                                                                                           |
| "Expiring soon" emphasis on buckets weeks 1-2       | Visually de-emphasize weeks 5-8 (lower urgency) vs weeks 1-2 (action needed now)                                                   | LOW        | CSS opacity or color saturation on chart bars by bucket position; no new dependencies                                                                                                                                                        |
| Voicemail vs live answer distinction                | Not all "placed calls" reached a human; knowing a voicemail was left is different from a live conversation                         | HIGH       | Requires manual annotation or a separate status field — BroadWorks Basic Call Logs do not provide call outcome; DEFER unless a manual "update status" workflow is added                                                                      |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature                                                             | Why Requested                                                    | Why Problematic                                                                                                                                                                                                                                       | Alternative                                                                                                                                               |
| ------------------------------------------------------------------- | ---------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Real-time call status refresh (WebSocket/polling)                   | Staff want to see call status update the moment a call is logged | BroadWorks scraping has a 20-entry rolling window; real-time would require polling every few minutes, adding session load and scraping fragility; no push event API available                                                                         | Hourly scrape 8am-8pm covers business hours; manual page refresh is acceptable for a low-call-volume operation (~2 staff, ~30-50 calls/day max)           |
| Call outcome tracking (reached, voicemail, no answer, wrong number) | Obvious CRM utility; would enable follow-up prioritization       | Basic Call Logs provide only name/number/timestamp — no outcome data. Storing "placed call" as a proxy for "contacted" is a deliberate simplification given data constraints                                                                          | If outcome tracking becomes critical, add a manual "Update Status" button per subscriber row that writes to a `call_outcomes` table; keep this out of MVP |
| 12-week or rolling expiration view                                  | More data = more complete picture                                | Subscribers expiring 10+ weeks out have no actionable urgency; showing them dilutes focus and inflates the "work to do" count. Industry practice is 2-4 week active outreach window with 8 weeks being the outer bound for scheduling                 | Cap at 8 weeks; show a "Later" count as a non-interactive summary number, not a clickable bar                                                             |
| Missed/received call log integration                                | Inbound data could indicate subscriber-initiated contact         | Staff are focused on outbound retention calls; inbound calls to the circulation desk come from all callers, not just expiring subscribers. Phone number matching against subscriber list would have high false-positive rate for common local numbers | Scrape placed calls only for the call status overlay; store all call types in DB for future use but don't surface inbound/missed in the dashboard         |
| Call scheduling / callback reminders                                | CRM-style follow-up tasks                                        | Builds toward a full CRM which is out of scope; existing Newzware system handles subscription management; dashboard is read-only monitoring with action indicators, not a task manager                                                                | "Not called + expiring soonest" sort order achieves the same prioritization passively                                                                     |

---

## Feature Dependencies

```
[call_logs DB table]
    └──required by──> [call status indicator per row]
                          └──required by──> [call status in XLSX export]
                          └──required by──> [per-agent activity summary]
                          └──required by──> [chart bar call overlay] (HIGH complexity — optional)

[hourly launchd scraper (Mac)]
    └──feeds──> [call_logs DB table]
    └──depends on──> [MyCommPilotScraper PHP class] (already documented in MYCOMMPILOT-INTEGRATION.md)

[8-week expiration SQL buckets]
    └──required by──> [8-week expiration chart]
                          └──enhances──> [call status per bucket view]

[existing subscriber table (right-click context menu)]
    └──extended by──> [call status indicator per row]
    └──extended by──> [last called timestamp column]
    └──extended by──> [not-called sort as default]

[new starts date format fix]
    └──independent──> all call features (import bug fix, no dependencies)
```

### Dependency Notes

- **call_logs table required before any call UI:** The scraper must be built and running before the dashboard can display call status. This means the scraper + DB schema must be Phase 1.
- **8-week expiration is independent of call features:** Can be built without call log integration. Recommended to ship together since it expands the table that call status overlays.
- **XLSX export depends on call status row coloring:** The color mapping (CSS class to SheetJS ARGB) must be designed when the row color scheme is decided, not retrofitted later.
- **Chart bar call overlay conflicts with simplicity:** The stacked-bar chart overlay requires restructuring the existing Chart.js data model. Mark as optional stretch goal, not MVP.

---

## MVP Definition

### This Milestone (v2.1)

Minimum feature set that delivers the core value: "staff can see which expiring subscribers have been called."

- [ ] Fix new starts CSV date format (M/D/YY compatibility) — prerequisite, unblocks data quality
- [ ] `call_logs` DB table + `MyCommPilotScraper` PHP class integrated into dashboard codebase
- [ ] Hourly launchd job (Mac) scraping BC and CW placed calls, writing to `call_logs` with INSERT IGNORE dedup
- [ ] 8-week expiration SQL + chart (extend from 4 to 8 buckets)
- [ ] Call status indicator in subscriber table: row background color (green = called within 7 days, yellow = called 8-14 days ago, none = not called) + phone icon with agent initials (BC/CW)
- [ ] "Last called" column in subscriber table (relative time + exact datetime tooltip)
- [ ] Default sort: not-called first, then expiration date ascending
- [ ] Call status columns carry into XLSX export with matching fill colors

### Add After Validation (v2.x)

- [ ] Per-agent call activity summary panel — add once staff use the tool for a few weeks and want performance visibility
- [ ] "Expiring soon" visual de-emphasis for weeks 5-8 — easy win, add when chart is live and feedback confirms the 8-week view feels cluttered
- [ ] Inbound/missed call data surfacing — only if staff request it; DB already stores all call types

### Defer to Future (v3+)

- [ ] Manual call outcome annotation (reached / voicemail / wrong number) — requires UI for staff to update; medium effort, high value only if scraper data alone proves insufficient
- [ ] Chart bar call status overlay (stacked bars) — high complexity Chart.js restructure; only worth it if summary view is insufficient for managers
- [ ] XSI REST API migration — if Segra enables it, this replaces the scraper entirely with cleaner data; worth asking them now

---

## Feature Prioritization Matrix

| Feature                            | User Value                 | Implementation Cost | Priority |
| ---------------------------------- | -------------------------- | ------------------- | -------- |
| Import date format fix             | HIGH (unblocks data)       | LOW                 | P1       |
| `call_logs` schema + scraper       | HIGH (foundation)          | MEDIUM              | P1       |
| launchd hourly job                 | HIGH (data collection)     | LOW                 | P1       |
| 8-week expiration chart            | HIGH (expanded visibility) | MEDIUM              | P1       |
| Call status row indicator + colors | HIGH (core UI value)       | MEDIUM              | P1       |
| Last called timestamp column       | HIGH (actionable context)  | LOW                 | P1       |
| Default sort (uncalled first)      | MEDIUM (workflow aid)      | LOW                 | P1       |
| XLSX export with colors            | MEDIUM (shareability)      | MEDIUM              | P1       |
| Per-agent activity summary         | MEDIUM (management view)   | LOW                 | P2       |
| Expiring-soon visual de-emphasis   | LOW (polish)               | LOW                 | P2       |
| Manual call outcome annotation     | MEDIUM (richer data)       | HIGH                | P3       |
| Chart bar call overlay             | LOW (nice to have)         | HIGH                | P3       |
| XSI API migration                  | HIGH if available          | MEDIUM              | P3       |

**Priority key:**

- P1: Must have for this milestone
- P2: Add when core is stable
- P3: Future consideration

---

## Industry Norms: Expiration Lookahead Window

**Finding (MEDIUM confidence — multiple subscription industry sources, not newspaper-specific):**

- 2-3 weeks before expiration: industry standard for renewal notice timing for print subscriptions (Minnesota AG consumer guidance, newspaper FAQ sources)
- 10-14 days before expiration: highest-urgency intervention window per subscription churn research (Recurly, Churnbuster)
- 30 days: payment method update notifications; less relevant for print billing
- 90 days: risk prediction modeling; too far out for manual phone outreach

**Recommendation for this project:** 8 weeks is the right outer bound. Weeks 1-2 (Past Due + This Week) are the urgent action zone. Weeks 3-8 are pipeline visibility for scheduling. Going beyond 8 weeks adds noise without adding actionable leads for a 2-person outreach team.

The current 4-week window (21 days) is too short — it misses the week 3-4 window where outreach can still prevent cancellation. 8 weeks (56 days) catches the full practical outreach window without overwhelming the chart.

---

## Industry Norms: Call Status UX Patterns

**Finding (MEDIUM confidence — general CRM/data table design literature, not newspaper-specific):**

Standard CRM practice for contacted/not-contacted status in data tables:

- Row background color: the dominant pattern in sales CRM tools (green/yellow/red or green/grey)
- Status badge/icon: paired with color, never color alone (accessibility and printability)
- Color should NOT be the only signal — icon + color + (optional) text label is the convention
- Sort by status as default: uncalled contacts first is universal in outbound calling tools

**For this project:** The already-decided "Option B" (phone icon + row background color) aligns with industry norms. The icon provides the accessibility layer (color alone fails for colorblind users and grayscale XLSX prints). Recommended colors:

- Green background (`bg-green-50`): called within 7 days — subscriber has been reached recently
- Yellow background (`bg-yellow-50`): called 8-14 days ago — may need follow-up
- No highlight (default white): not called — primary action target

---

## Sources

- PROJECT.md and MYCOMMPILOT-INTEGRATION.md (project context, HIGH confidence)
- [SimpleCirc newspaper subscription management](https://simplecirc.com/newspaper-subscription-management-software) — industry software reference
- [Recurly: 17 Strategies to Reduce Subscriber Churn](https://recurly.com/blog/reduce-churn/) — subscription retention timing norms
- [Churnbuster: Dunning Best Practices](https://churnbuster.io/dunning-best-practices) — outreach window guidance
- [Microsoft Dynamics 365: Predict subscription churn](https://learn.microsoft.com/en-us/dynamics365/customer-insights/data/predict-subscription-churn) — 90-day prediction window context
- [Pencil & Paper: Data Table Design UX Patterns](https://www.pencilandpaper.io/articles/ux-pattern-analysis-enterprise-data-tables) — table UX best practices
- [UX Movement: 10 Design Tips for Better Data Tables](https://uxmovement.medium.com/10-design-tips-for-a-better-data-table-interface-8d6705e56be2) — status badge patterns
- [Minnesota AG: Magazine Subscription Solicitations](https://www.ag.state.mn.us/consumer/Publications/MagazineSubscription.asp) — 2-3 week renewal notice timing
- [IJNet: Practices to encourage newspaper subscribers to renew](https://ijnet.org/en/story/practices-encourage-newspaper-subscribers-renew-their-subscription) — industry renewal outreach context
- MyCommPilot BroadWorks documentation via reverse-engineering (MYCOMMPILOT-INTEGRATION.md)

---

_Feature research for: NWDownloads v2.1 — Call Log Integration & Dashboard Enhancements_
_Researched: 2026-03-20_
