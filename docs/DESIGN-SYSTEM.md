# Design System & Component Library
**NWDownloads Circulation Dashboard**
**Version:** 1.0.0
**Last Updated:** December 10, 2025

---

## 🎨 Design Philosophy

**Consistency is key.** Every component follows established patterns to ensure:
- ✅ Visual consistency across the dashboard
- ✅ Reusability and maintainability
- ✅ Easy updates to the design system propagate everywhere
- ✅ No bespoke, one-off implementations

---

## 📦 Core Components

### 1. **Metric Card Grid** - Responsive Card Container

**Purpose:** Responsive grid layout for metric cards

**Pattern:**
```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Metric cards go here -->
</div>
```

**Responsive Behavior:**
- **Mobile** (< 768px): 1 column (vertical stack)
- **Tablet** (768px - 1023px): 2 columns (2×2 grid)
- **Desktop** (1024px+): 4 columns (single row)

**When to Use:**
- ✅ Any section with 4 metric cards
- ✅ Key Metrics section
- ✅ Revenue Intelligence cards
- ✅ Business unit summaries
- ✅ Any dashboard metrics that need responsive layout

**Variations:**

**3-Column Layout** (Analytics, comparisons):
```html
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
```

**2-Column Layout** (Large cards, detailed metrics):
```html
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
```

**Important:** Always use this grid pattern for card layouts. Never create custom grid breakpoints - consistency is key!

---

### 2. **Metric Card** - Standard Dashboard Card

**Purpose:** Display key metrics with optional comparison data

**Pattern:**
```html
<div class="metric-card bg-white rounded-xl shadow p-6"
     role="region"
     aria-labelledby="metric-label-id">
    <!-- Header with icon -->
    <div class="flex items-center justify-between mb-2">
        <div id="metric-label-id" class="text-sm font-medium text-gray-600">
            Metric Title
        </div>
        <div class="text-2xl" aria-hidden="true">📊</div>
    </div>

    <!-- Main value -->
    <div class="text-3xl font-bold text-gray-900" id="metricValue">
        8,226
    </div>

    <!-- Subtitle/comparison -->
    <div class="text-sm text-gray-500 mt-2">
        <span>↓ 5 (-0.06%)</span> Previous Week
    </div>
</div>
```

**CSS Classes:**
- `.metric-card` - Base class with hover effect
- `bg-white` - White background
- `rounded-xl` - Large rounded corners
- `shadow` - Standard shadow
- `p-6` - Consistent padding

**When to Use:**
- ✅ Key metrics section
- ✅ Business unit cards
- ✅ Any metric display that needs comparison data
- ✅ Clickable cards that trigger modals/panels

**Colored Card Variants** (for severity/heat map visualization):

```html
<!-- Red - Critical/Urgent -->
<div class="metric-card card-red bg-white rounded-xl shadow p-6 cursor-pointer">

<!-- Orange - Warning/Soon -->
<div class="metric-card card-orange bg-white rounded-xl shadow p-6 cursor-pointer">

<!-- Yellow - Caution/Moderate -->
<div class="metric-card card-yellow bg-white rounded-xl shadow p-6 cursor-pointer">

<!-- Green - Good/Safe -->
<div class="metric-card card-green bg-white rounded-xl shadow p-6 cursor-pointer">
```

**Color Variants Features:**
- ✅ Full 2px colored border on hover (surrounds entire card)
- ✅ Color-matched subtle background on hover (10% opacity)
- ✅ Color-matched shadow on hover (20% opacity)
- ✅ Visual urgency indicator via border glow
- ✅ Clean white card in normal state
- ✅ Same pattern as vacation card, but heat-mapped

**When to Use Color Variants:**
- ✅ Expiration risk cards (red = expired, orange = urgent, yellow = soon, green = good)
- ✅ Alert/warning levels
- ✅ Status indicators (critical, warning, info, success)
- ✅ Priority-based metrics
- ❌ NOT for general metrics without urgency context

**Examples:**
- Total Active Subscribers (no color - neutral metric)
- Subscribers On Vacation (no color - informational)
- Deliverable Subscribers (no color - neutral metric)
- **Expired subscriptions** (card-red - urgent action needed)
- **Expiring 0-4 weeks** (card-orange - contact soon)
- **Expiring 5-8 weeks** (card-yellow - plan outreach)
- **Expiring 9-12 weeks** (card-green - good standing)

---

### 2. **ContextMenu** - Right-Click Context Menus

**Purpose:** Provide action menus for interactive elements (cards, charts, etc.)

**JavaScript API:**
```javascript
// Initialize menu with items
const menu = new ContextMenu({
    items: [
        {
            id: 'view-subscribers',
            icon: '👥',
            label: 'View subscribers',
            action: (context) => showSubscriberList(context)
        },
        {
            id: 'show-trend',
            icon: '📈',
            label: 'Show trend over time',
            action: (context) => showTrendChart(context)
        },
        { type: 'divider' },
        {
            id: 'export',
            icon: '📊',
            label: 'Export data',
            shortcut: '⌘E',
            action: (context) => exportData(context)
        }
    ]
});

// Show menu on click/right-click
element.addEventListener('contextmenu', (e) => {
    e.preventDefault();
    menu.show(e.clientX, e.clientY, { bucket: 'Expired', data: {...} });
});
```

**Menu Item Properties:**
```javascript
{
    id: string,           // Unique identifier
    icon: string,         // Emoji or icon
    label: string,        // Display text
    action: function,     // Click handler (receives context)
    shortcut: string,     // Optional keyboard shortcut display
    disabled: boolean,    // Optional - gray out item
    type: 'divider'      // Optional - horizontal separator
}
```

**Features:**
- ✅ Professional styling with shadows and animations
- ✅ Auto-positioning (stays within viewport)
- ✅ Close on Escape key
- ✅ Close on click outside
- ✅ Hover effects
- ✅ Keyboard shortcut display
- ✅ Dividers for grouping

**When to Use:**
- ✅ Chart bar/segment interactions
- ✅ Metric card actions (View list, Show trend, Export)
- ✅ Table row actions
- ✅ Business unit card actions
- ✅ Any element with multiple possible actions

**File Location:** `web/assets/context-menu.js`

---

### 3. **SubscriberTablePanel** - Reusable Slide-Out Panel

**Purpose:** Display lists of subscribers with export functionality

**JavaScript API:**
```javascript
// Initialize panel
const panel = new SubscriberTablePanel({
    colorScheme: 'teal'  // or 'amber', 'blue', etc.
});

// Show panel with data
panel.show({
    title: 'Subscribers On Vacation - South Carolina',
    subtitle: '26 subscribers • Snapshot: 2025-12-01',
    data: {
        subscribers: [...],  // Array of subscriber objects
        count: 26,
        businessUnit: 'South Carolina',
        snapshotDate: '2025-12-01',
        metricType: 'vacation'  // or 'expiration', 'legacy_rate', etc.
    }
});
```

**Required Data Structure:**
```javascript
{
    title: string,           // Panel header title
    subtitle: string,        // Subtitle with count and date
    data: {
        subscribers: Array,  // Subscriber objects
        count: number,       // Total count
        businessUnit: string,  // Optional
        snapshotDate: string,
        metricType: string   // 'vacation', 'expiration', 'legacy_rate', etc.
    }
}
```

**Subscriber Object Fields:**
```javascript
{
    sub_num: string,          // Account ID
    name: string,             // Subscriber Name
    paper_code: string,       // TJ, TA, TR, etc.
    business_unit: string,    // South Carolina, Michigan, Wyoming
    phone: string,            // Phone number
    email: string,            // Email address
    address: string,          // Mailing address
    city_state_postal: string,  // City, State, ZIP
    // Metric-specific fields:
    paid_thru: string,        // For expiration lists
    days_until_expiration: number,  // For expiration lists
    vacation_start: string,   // For vacation lists
    vacation_end: string,     // For vacation lists
    vacation_weeks: number,   // For vacation lists
    last_payment_amount: number,  // For revenue lists
    delivery_type: string,    // MAIL, CARR, INTE
    rate_name: string,        // Rate plan name
}
```

**Features:**
- ✅ Slide-out from right side (75% width)
- ✅ Teal gradient header
- ✅ Export to Excel button
- ✅ Export to CSV button
- ✅ Responsive table layout
- ✅ Close on Escape key
- ✅ Close on backdrop click
- ✅ Smooth animations

**When to Use:**
- ✅ Vacation subscriber lists
- ✅ Expiration risk subscriber lists
- ✅ Legacy rate subscriber lists
- ✅ ANY list of subscribers
- ✅ Any metric that needs drill-down to individual subscribers

**Current Implementations:**
- Vacation subscribers (by business unit or overall)
- **Should also be used for:** Expiration risk buckets, Legacy rate lists

**File Location:** `web/assets/subscriber-table-panel.js`

---

## 🚀 Implementation Guidelines

### Creating a New Metric Card

**1. Follow the standard pattern:**
```html
<div class="metric-card bg-white rounded-xl shadow p-6 cursor-pointer"
     onclick="showSubscriberList('my-metric')">
    <div class="flex items-center justify-between mb-2">
        <div class="text-sm font-medium text-gray-600">Metric Title</div>
        <div class="text-2xl">🎯</div>
    </div>
    <div class="text-3xl font-bold text-gray-900" id="metricValue">--</div>
    <div class="text-sm text-gray-500 mt-2">Subtitle</div>
</div>
```

**2. Populate with data:**
```javascript
document.getElementById('metricValue').textContent = formatNumber(value);
```

**3. Add click handler to show subscriber list:**
```javascript
function showSubscriberList(metricType) {
    // Fetch subscriber data
    fetch(`api/endpoint.php?metric=${metricType}`)
        .then(r => r.json())
        .then(data => {
            // Show using SubscriberTablePanel
            const panel = new SubscriberTablePanel({ colorScheme: 'teal' });
            panel.show({
                title: 'Metric Title',
                subtitle: `${data.count} subscribers • ${snapshotDate}`,
                data: {
                    subscribers: data.subscribers,
                    count: data.count,
                    metricType: metricType
                }
            });
        });
}
```

---

### Adding a New Subscriber List Type

**1. Create API endpoint:**
```php
// In api.php or dedicated endpoint
if (isset($_GET['action']) && $_GET['action'] === 'my_metric_subscribers') {
    handleMyMetricSubscribers();
    exit();
}

function handleMyMetricSubscribers() {
    // Query subscriber_snapshots table
    $sql = "SELECT * FROM subscriber_snapshots WHERE ...";
    // Return JSON with subscribers array
    echo json_encode([
        'success' => true,
        'subscribers' => $subscribers,
        'count' => count($subscribers)
    ]);
}
```

**2. Create click handler:**
```javascript
async function showMyMetricSubscribers() {
    const response = await fetch('api.php?action=my_metric_subscribers');
    const data = await response.json();

    const panel = new SubscriberTablePanel({ colorScheme: 'blue' });
    panel.show({
        title: 'My Metric Subscribers',
        subtitle: `${data.count} subscribers • ${snapshotDate}`,
        data: {
            subscribers: data.subscribers,
            count: data.count,
            metricType: 'my_metric'
        }
    });
}
```

**3. Attach to card:**
```html
<div class="metric-card ... cursor-pointer"
     onclick="showMyMetricSubscribers()">
    ...
</div>
```

---

## ❌ Anti-Patterns (What NOT to Do)

### ❌ Don't Create Custom Modals

**WRONG:**
```html
<!-- Custom modal HTML in index.php -->
<div id="myCustomModal" class="fixed inset-0 ...">
    <div class="custom-header">...</div>
    <table>...</table>
</div>
```

**RIGHT:**
```javascript
// Use SubscriberTablePanel
const panel = new SubscriberTablePanel();
panel.show(data);
```

### ❌ Don't Create Gradient Background Cards

**WRONG:**
```html
<div class="bg-gradient-to-br from-red-50 to-red-100 border-2 border-red-200 ...">
    <div class="text-2xl font-bold text-red-900">1,072</div>
</div>
```

**RIGHT:**
```html
<div class="metric-card bg-white rounded-xl shadow p-6">
    <div class="text-3xl font-bold text-gray-900">1,072</div>
</div>
```

### ❌ Don't Inline Styles

**WRONG:**
```html
<div style="background: white; padding: 24px; border-radius: 12px;">
```

**RIGHT:**
```html
<div class="bg-white p-6 rounded-xl">
```

---

## 🎨 Color Schemes

### Metric Cards
- **Background:** White (`bg-white`)
- **Shadow:** Standard (`shadow`)
- **Text:** Gray-900 for values, Gray-600 for labels
- **Hover:** Slight lift with enhanced shadow

### SubscriberTablePanel
- **Header:** Teal gradient (`#14B8A6` → `#0891B2`)
- **Background:** Teal-50 (`#F0FDFA`)
- **Buttons:** White with teal text

### Status Colors
- **Success/Good:** Green (`text-green-600`)
- **Warning:** Yellow/Orange (`text-orange-600`)
- **Error/Critical:** Red (`text-red-600`)
- **Info:** Blue (`text-blue-600`)

---

## 📝 Refactoring Checklist

When you find non-standard implementations:

**1. Metric Cards**
- [ ] Uses `.metric-card` class?
- [ ] White background instead of gradients?
- [ ] Standard text sizes (3xl for value, sm for label)?
- [ ] Icon in top-right corner?
- [ ] Clickable with `cursor-pointer`?

**2. Subscriber Lists**
- [ ] Uses `SubscriberTablePanel` instead of custom modal?
- [ ] Passes data in standard format?
- [ ] Has proper API endpoint?
- [ ] Includes export functionality?

**3. General**
- [ ] No inline styles?
- [ ] Uses Tailwind classes?
- [ ] Follows accessibility guidelines (ARIA labels)?
- [ ] Mobile responsive?

---

## 🔄 Migration Plan

### Phase 1: Revenue Intelligence Cards (Current)
- [x] Identify non-standard gradient cards
- [ ] Refactor to use standard metric card pattern
- [ ] Replace custom modal with SubscriberTablePanel
- [ ] Test all click handlers and exports

### Phase 2: Future Features
- [ ] All new metrics use standard components
- [ ] No new custom modals or panels
- [ ] Document any new patterns in this file

---

## 📚 Resources

**Component Files:**
- `web/assets/subscriber-table-panel.js` - Subscriber list panel
- `web/index.php` - Metric card examples (Key Metrics section)
- `web/assets/vacation-display.js` - Example implementation

**Styling:**
- Tailwind CSS classes (via CDN)
- Custom `.metric-card` hover effect in `<style>` block

**Related Docs:**
- `/docs/KNOWLEDGE-BASE.md` - Complete system reference
- `/docs/TROUBLESHOOTING.md` - Common issues

---

**Remember:** Consistency makes the dashboard maintainable. Always use existing components before creating new ones!
