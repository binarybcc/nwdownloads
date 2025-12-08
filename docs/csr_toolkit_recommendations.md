# CSR Command Center: 1-Second Access Tools

> **User Persona:** Customer Service Rep (Phone/Walk-in)  
> **Pain Point:** Newzware takes 30-120s to generate reports. Customers are waiting.  
> **Solution:** "Instant-Read" interface driven by local CSV data.  
> **Goal:** Answer the 5 most common questions in <5 seconds.

---

## 🚀 Module 1: The "Flash Search" Bar
**The Need:** "I don't care about trends right now. I have Mrs. Smith on Line 1."

**Feature:** A global search bar always visible in the web app.
*   **Inputs:** Partial Name, Phone Number (any format), Email, or Account #.
*   **Speed:** Instant client-side filter of the ~8,000 row CSV.
*   **Result View:** A "Flash Card" summary (not a spreadsheet row).

### The "Subscriber Flash Card" UI
When a CSR clicks a search result, pop up a high-contrast card with **only** decision-critical info:

| **Status** | **Data Point** | **CSR Script / Action** |
| :--- | :--- | :--- |
| **Paid Thru** | `12/27/2025` (Color coded) | "I see you are paid up through next December, no worries there." |
| **Delivery** | `Carrier (C007)` | "You are on Route 7. I'll message the carrier." (Instant toggle vs Mail) |
| **Digital** | `Login: jsmith` / `Last: 2 days ago` | "I see you logged in Tuesday. Is the password working?" |
| **Balance** | `LAST PAY: -$169.99` | "We received your payment of $169.99. You're set." |

**Why this helps:**
It prevents the CSR from having to tab through 4 different Newzware screens to find "Date" vs "Amount" vs "Route".

---

## 🛠 Module 2: Digital Triage Assistant
**The Need:** "I can't log in!" or "Why can't I verify my account?"  
**Newzware Gap:** Digital fields (`Login ID`) are often buried in tabs.

**Feature:** A dedicated "Digital Debugger" view for any selected account.

**Logic from CSV:**
1.  **Metric:** Check `Login ID` column.
    *   *If Empty:* **ALERT: "No Digital Account Set Up."**
    *   *Script:* "I see you haven't activated online yet. What email do you want to use?"
2.  **Metric:** Check `Last Login` date.
    *   *If Empty:* **ALERT: "Account created but NEVER accessed."**
    *   *Script:* "It looks like you set it up but never got in. Let's reset your password."
    *   *If > 30 Days:* **ALERT: "Dormant."**
3.  **Cross-Check:** Compare `Email` column vs `Login ID`.
    *   *If Different:* "Are you trying to log in with [Email] or [Login ID]? Our system expects [Login ID]."

---

## 📡 Module 3: The "Renewal Radar" (Proactive Low-Hanging Fruit)
**The Need:** CSRs have downtime between calls. They *should* be calling expired accounts, but finding "Who expired yesterday?" in Newzware is a report-running nightmare.

**Feature:** A "Work Queue" sidebar.

**Logic:**
Filter the CSV for **"Expiring in next 7 days"** and **"Expired < 14 days ago (Grace Period)."**
*   **Display:** Simple list: Name | Phone | Due Date.
*   **Action:** CSR clicks name -> Sees Flash Card -> Dials number.
*   **Gamification:** "5 accounts processed today."

**Why this helps:**
It turns "downtime" into "revenue time" without the friction of running a query.

---

## 🔄 Module 4: Price & Term Intelligence (Upsell Tool)
**The Need:** Customer calls to pay a bill. CSR simply takes payment. **Missed opportunity.**

**Feature:** "Upsell prompt" on the Flash Card.

**Logic from CSV:**
1.  **Check `LEN` (Length):** Is it `1 M` (1 Month)?
    *   **Prompt:** "⚠️ MONTHLY SUBSCRIBER"
    *   *Script:* "Mrs. Jones, I see you pay $15.99 every month. If you switch to yearly today, you'll save $22. Want to do that?"
2.  **Check `LAST PAY`:** Is it a legacy rate (< $100/yr)?
    *   **Prompt:** "⚠️ LEGACY RATE"
    *   *Script:* (If policy allows) "Just so you know, the rate will be adjusting on your next renewal." (Manage expectations).

---

## 🕵️‍♀️ Module 5: "Gap Patrol" (Data Hygiene)
**The Need:** We can't market if data is missing.

**Feature:** High-visibility "Missing Info" badges on the Flash Card.

**Logic:**
*   If `Email` is missing: Show big yellow button **"ASK FOR EMAIL"**.
*   If `Phone` is missing: Show big yellow button **"ASK FOR NUMBER"**.

**Why this helps:**
It reminds the CSR to capture data *while the person is already on the phone*. "Oh, by the way, I see we don't have an email for renewal notices. Can I grab that?"

---

## 📈 Module 6: "The Time Machine" (History from Over-Time Data)
**The Need:** "My bill went up!" or "I never had these delivery issues before."
**Newzware Gap:** Looking up history requires digging into archives.

**Feature:** If you store weekly CSV snapshots, the Web App can show a sparkline.

**Logic:**
*   Compare current row to row with same `SUB NUM` from 6 months ago.
*   **Display:**
    *   "Rate History: $52 -> $52 -> $170 (Price Jump!)" -> CSR knows *why* they are angry immediately.
    *   "Delivery History: Carrier -> Mail" -> CSR knows they were switched, explains the complaint.

---

## Summary of Recommended Additions
1.  **Search Bar:** Sub-second lookup by any field.
2.  **Flash Card:** The "One Screen" view of a subscriber.
3.  **Digital Debugger:** Instant "Why can't I login?" answers.
4.  **Grace Period List:** "Who should I call right now?"
5.  **Missing Info Badges:** "Get the email."
