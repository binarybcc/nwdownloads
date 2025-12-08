# Deep Marketing Knowledgebase (DMP) Strategy

> **Goal:** Transform a "Subscriber List" into a "Community Data Asset."  
> **Inputs:** Newzware Data + Voter Rolls + Property Records + Digital Signals.  
> **Outputs:** Hyper-targeted internal marketing & High-value advertiser audiences.

---

## 1. The Core Concept: "The Golden Record"

Most newspapers have specific lists (subscribers, voters, email leads). The goal is to merge them into a single **Person-Centric Profile**.

**The Data Layer Cake:**
*   **Layer 1 (The Bedrock):** Voter Registration Data (Name, Address, Age, Party, Voting History). *Why? It's the most accurate name/address source available.*
*   **Layer 2 (The Customer):** Your Newzware Data (Subscription Status, Payment Rate, Delivery).
*   **Layer 3 (The Asset):** Property Data (Home Value, Mortgage Date, Length of Residence).
*   **Layer 4 (The Signal):** Digital Behavior (Email clicks, Website visits, SMS responses).

---

## 2. Enrichment Sources & Value

| Source Data | Key Fields to Extract | Marketing Application |
| :--- | :--- | :--- |
| **Voter Rolls** | DOB (Age), Party, Voting Frequency | **Political Ad Sales:** "Target Super-Voters aged 55+ in County X." (Premium CPM). |
| **Property Records** | Home Value, Purchase Date | **Real Estate/Service Sales:** "New Homeowners (<6mo)" are prime targets for Furniture/Landscaping ads. |
| **US Census (Free)** | Block Group Income, Education | **Demographic Modeling:** "Match our subscribers against Census Blocks." If you penetrate 40% of "High Income" blocks but 5% of "Low Income," adjust ad rates. |
| **Validation APIs** | Mobile Carrier, Email Validity | **SMS Marketing:** Filter "Landlines" out of your SMS blasts to save money. |

---

## 3. Playbook: Internal Growth (Circulation)

**A. The "Look-Alike" Acquisition Model**
*   **Process:**
    1.  Analyze your Top 10% Best Subscribers (e.g., "Paid > $150/yr, Active > 5yrs").
    2.  Find their common traits in External Data (e.g., "Homeowner, Age 55-70, Voted in last 2 primaries").
    3.  Query the **Voter Roll** for *non-subscribers* who match this profile.
*   **Result:** A "Laser-Focused" direct mail/social list. Response rates will be 3-5x higher than "saturation" mailings.

**B. The "Political Power" Play**
*   **Process:** Overlay subscription list with Voting History.
*   **Pitch to Campaigns:** "We reach 72% of the 'Likely Voters' in District 9."
*   **Value:** Political ads pay huge premiums. You can prove *reach*, not just print count.

**C. Churn Prediction (Life Events)**
*   **Process:** Monitor Property Transfers (Deed recordings).
*   **Trigger:** When a subscriber's address appears in "Just Sold" lists.
*   **Action:**
    *   *To Seller:* "Moving? Take us with you! Switch to Digital."
    *   *To Buyer:* "Welcome to the neighborhood! Here's 1 month free."

---

## 4. Playbook: External Monetization (Ad Sales Agents)

You stop selling "Newspaper Ads" and start selling **"Audience Access."**

**A. "The Welcome Wagon" (SMS/Email)**
*   **Client:** Local Hardware Store.
*   **The Query:** "Send an SMS offer to all homeowners who purchased in the last 6 months within 5 miles of the store."
*   **Your Data:** Voter/Property List + Subscriber Cell Phones.

**B. "The Silver Tsunami" (Healthcare/Wealth)**
*   **Client:** Wealth Manager or Hearing Aid Clinic.
*   **The Query:** "Households with Age 65+ head of household, Verified Homeowner, Income est $80k+."
*   **Why You Win:** Facebook/Google have removed many of these targeting options due to privacy/discrimination rules. **First-party data** (which you own) is compliant to use.

**C. Email Matching (Ad Syndication)**
*   **Process:** Hash your email list (SHA256).
*   **Action:** Upload to Facebook/Google as "Custom Audience."
*   **Pitch:** "We can show your display ad to our subscribers *while they are on Facebook*." (Audience Extension).

---

## 5. Technical Architecture (The "How")

You don't need a million-dollar Salesforce setup. You can build a **"Data Warehouse Lite"** using your existing stack.

1.  **Ingestion:**
    *   Scripts import `AllSubscriberReport.csv` (Weekly).
    *   Scripts import `VoterRoll.csv` (Quarterly/Annually).
2.  **Matching (The "Key"):**
    *   Normalize Addresses (CASS Standardization or simple Regex).
    *   Join on `Address + Last Name`.
3.  **Storage:**
    *   A simple SQL database (MariaDB/MySQL).
    *   Table `MASTER_PROFILE` links `SubID` to `VoterID`.
4.  **Interface (The Web App):**
    *   **"Audience Builder" Tool:** A simple query builder for your Ad Reps.
    *   *Input:* "Show me Homeowners in Zip 29631."
    *   *Output:* "Count: 4,520 (we can reach 1,200 via Email, 800 via SMS)."

---

## 🚀 Immediate Action Plan

1.  **Secure the Voter List:** Request the file from your County Election Board (usually free or cheap for news orgs).
2.  **The "Match Rate" Test:**
    *   Import Voter List.
    *   Run a script to match your Subscribers against it.
    *   *Goal:* If you match >60%, you have a viable data product.
3.  **Build the "Audience Card":**
    *   Update your Web App CSR/Sales view to show "Likely Homeowner" or "Super Voter" badges next to subscribers based on the match.

**The Pivot:** You are no longer just a "Publisher." You are a **"Local Data Broker"** with a printing press attached.
