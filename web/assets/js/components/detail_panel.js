/**
 * Detail Panel for Business Unit Deep Dive
 * Provides animated slide-out panel with charts for:
 * - 4-week expiration view
 * - Rate distribution
 * - Subscription length distribution
 * Date: 2025-12-02
 */

/**
 * LOAD ORDER: 5 of 11
 *
 * DEPENDENCIES:
 * - app.js: dashboardData, formatNumber, BUSINESS_UNITS, API_BASE
 * - state-icons.js: getStateAbbr, getStateIconImg
 *
 * PROVIDES:
 * - openDetailPanel(businessUnit, snapshotDate)
 * - closeDetailPanel()
 * - switchBusinessUnit(unit)
 * - window.CircDashboard.detailPanel state
 */

// Extend CircDashboard namespace for detail panel
// (CircDashboard created in app.js, but handle timing issues)
window.CircDashboard = window.CircDashboard || {};
window.window.CircDashboard.detailPanel = window.window.CircDashboard.detailPanel || {
    charts: {
        expiration: null,
        rateDistribution: null,
        subscriptionLength: null,
    },
    currentBusinessUnit: null,
    currentSnapshotDate: null,
    data: null,
    availableBusinessUnits: ['South Carolina', 'Michigan', 'Wyoming'],
};

// Chart instances
let expirationChart = window.CircDashboard.detailPanel.charts.expiration = null;
let rateDistributionChart = window.CircDashboard.detailPanel.charts.rateDistribution = null;
let subscriptionLengthChart = window.CircDashboard.detailPanel.charts.subscriptionLength = null;

// Current state
let currentBusinessUnit = window.CircDashboard.detailPanel.currentBusinessUnit = null;
let currentSnapshotDate = window.CircDashboard.detailPanel.currentSnapshotDate = null;
let detailPanelData = window.CircDashboard.detailPanel.data = null;
let availableBusinessUnits = ['South Carolina', 'Michigan', 'Wyoming'];  // Will be populated from dashboard data

// Keyboard shortcut handler
let keyboardShortcutHandler = null;

/**
 * Blend two colors based on percentage
 * @param {number} value - The data value
 * @param {number} maxValue - Maximum value in the dataset
 * @param {Object} baseColor - RGB base color {r, g, b}
 * @param {Object} targetColor - RGB target color {r, g, b}
 * @param {number} opacity - Opacity (0-1), default 0.8
 * @returns {string} RGBA color string
 */
function blendColors(value, maxValue, baseColor, targetColor, opacity = 0.8) {
    // Calculate percentage (0-100)
    const percent = maxValue > 0 ? (value / maxValue) * 100 : 0;

    // Blend factor: 0 = pure base color, 1 = pure target color
    // Use a curve to make low values stay closer to base color
    const t = Math.pow(percent / 100, 0.8); // Power curve for smoother gradient

    // Interpolate RGB values
    const r = Math.round(baseColor.r + (targetColor.r - baseColor.r) * t);
    const g = Math.round(baseColor.g + (targetColor.g - baseColor.g) * t);
    const b = Math.round(baseColor.b + (targetColor.b - baseColor.b) * t);

    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

/**
 * Normalize subscription length labels to canonical values
 * Handles different naming conventions for same duration
 *
 * REUSABLE PATTERN: This normalization function demonstrates a pattern
 * for consolidating data with different labels that represent the same value.
 * Can be adapted for other scenarios like:
 * - State names (South Carolina, SC, S. Carolina)
 * - Publication codes with aliases
 * - Date format standardization
 * - Any field with multiple representations of the same concept
 *
 * @param {string} length - Raw subscription length label
 * @returns {string} Normalized canonical label
 */
function normalizeSubscriptionLength(length) {
    const normalized = length.toUpperCase().trim();

    // 1 Year variations
    if (normalized.match(/^(12\s*M|1\s*Y|52\s*W|365\s*D)/i) ||
        normalized.includes('1 YEAR') ||
        normalized.includes('12 MONTH')) {
        return '1 Year';
    }

    // 6 Months variations
    if (normalized.match(/^(6\s*M|26\s*W|182\s*D)/i) ||
        normalized.includes('6 MONTH')) {
        return '6 Months';
    }

    // 3 Months variations
    if (normalized.match(/^(3\s*M|13\s*W|90\s*D)/i) ||
        normalized.includes('3 MONTH')) {
        return '3 Months';
    }

    // 2 Months variations
    if (normalized.match(/^(2\s*M|8\s*W|60\s*D)/i) ||
        normalized.includes('2 MONTH')) {
        return '2 Months';
    }

    // 1 Month variations
    if (normalized.match(/^(1\s*M|4\s*W|30\s*D)/i) ||
        normalized.includes('1 MONTH')) {
        return '1 Month';
    }

    // If no match, return original (capitalized)
    return length.trim();
}

/**
 * Populate state navigation sidebar
 * Creates clickable state icons for each business unit
 */
function populateStateNavigation(businessUnits) {
    const sidebar = document.getElementById('stateNavSidebar');
    if (!sidebar) return;

    // Store available units
    availableBusinessUnits = businessUnits || availableBusinessUnits;

    // Build HTML for each state
    const navHTML = availableBusinessUnits.map(unit => {
        const abbr = getStateAbbr(unit);
        const iconImg = getStateIconImg(unit);
        const isActive = unit === currentBusinessUnit;
        const activeClass = isActive ? 'active' : '';

        return `
            <div class="state-nav-item ${activeClass}"
                 data-business-unit="${unit}"
                 onclick="switchBusinessUnit('${unit}')"
                 role="button"
                 tabindex="0"
                 aria-label="Switch to ${unit}"
                 title="${unit}">
                <div class="state-icon-wrapper">
                    ${iconImg}
                </div>
                <div class="state-abbr">${abbr}</div>
                <div class="state-count" id="stateCount${abbr}">...</div>
            </div>
        `;
    }).join('');

    sidebar.innerHTML = navHTML;

    // Add keyboard support for state nav items
    document.querySelectorAll('.state-nav-item').forEach(item => {
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const unit = this.dataset.businessUnit;
                switchBusinessUnit(unit);
            }
        });
    });
}

/**
 * Update state navigation active state
 */
function updateStateNavActive(businessUnit) {
    document.querySelectorAll('.state-nav-item').forEach(item => {
        if (item.dataset.businessUnit === businessUnit) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
}

/**
 * Switch to different business unit
 * Triggers crossfade animation and loads new data
 */
async function switchBusinessUnit(businessUnit) {
    if (businessUnit === currentBusinessUnit) return;  // Already showing this state

    // Reset any open trend views before switching
    if (typeof cleanupChartContextMenus === 'function') {
        cleanupChartContextMenus();
    }

    // Update navigation
    updateStateNavActive(businessUnit);

    // Fade out current content
    const content = document.getElementById('detailPanelContent');
    content.style.opacity = '0';
    content.style.transition = 'opacity 200ms ease-out';

    // Wait for fade out
    await new Promise(resolve => setTimeout(resolve, 200));

    // Load new data
    currentBusinessUnit = window.CircDashboard.detailPanel.currentBusinessUnit = businessUnit;
    await loadBusinessUnitData(businessUnit, currentSnapshotDate);

    // Fade in new content
    content.style.opacity = '1';
    content.style.transition = 'opacity 200ms ease-in';
}

/**
 * Load business unit data
 * Separated from openDetailPanel to allow state switching
 */
async function loadBusinessUnitData(businessUnit, snapshotDate) {
    // Update title
    document.getElementById('detailPanelTitle').textContent = `${businessUnit} - Details`;
    document.getElementById('detailPanelSubtitle').textContent = `Requested: ${snapshotDate}`;

    // Show loading state
    document.getElementById('detailPanelLoading').classList.remove('hidden');
    document.getElementById('detailPanelContent').classList.add('hidden');

    // Fetch data
    try {
        const response = await fetch(`api.php?action=detail_panel&business_unit=${encodeURIComponent(businessUnit)}&snapshot_date=${snapshotDate}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load detail data');
        }

        detailPanelData = window.CircDashboard.detailPanel.data = result.data;

        // CRITICAL: Update currentSnapshotDate to use ACTUAL snapshot date from API
        // The API resolves the requested date to the actual available snapshot
        // (e.g., requested 2025-11-29 Monday → actual 2025-11-30 Sunday)
        if (result.data.snapshot_date) {
            currentSnapshotDate = window.CircDashboard.detailPanel.currentSnapshotDate = result.data.snapshot_date;
        }

        // Render content
        renderDetailPanelContent();

    } catch (error) {
        console.error('Error loading detail panel data:', error);
        document.getElementById('detailPanelLoading').innerHTML = `
            <div class="text-center py-12">
                <div class="text-red-600 mb-2">Failed to load details</div>
                <div class="text-sm text-gray-500">${error.message}</div>
                <button onclick="loadBusinessUnitData('${businessUnit}', '${snapshotDate}')"
                        class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Retry
                </button>
            </div>
        `;
    }
}

/**
 * Enable keyboard shortcuts for detail panel
 * - ESC key to close panel
 * - Focus trap to keep keyboard navigation within panel
 */
function enableKeyboardShortcuts() {
    keyboardShortcutHandler = function(e) {
        // ESC key closes the panel
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeDetailPanel();
            return;
        }

        // Tab key focus trap
        if (e.key === 'Tab') {
            const panel = document.getElementById('detailPanel');
            if (!panel.classList.contains('open')) return;

            const focusableElements = panel.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (focusableElements.length === 0) return;

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            // Shift+Tab on first element -> focus last element
            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            }
            // Tab on last element -> focus first element
            else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    };

    document.addEventListener('keydown', keyboardShortcutHandler);
}

/**
 * Disable keyboard shortcuts when panel closes
 */
function disableKeyboardShortcuts() {
    if (keyboardShortcutHandler) {
        document.removeEventListener('keydown', keyboardShortcutHandler);
        keyboardShortcutHandler = null;
    }
}

/**
 * Open detail panel for a business unit
 * Enhanced with state navigation, backdrop, keyboard support, and smooth animations
 * Includes donut-to-state icon animation
 */
async function openDetailPanel(businessUnit, snapshotDate) {
    currentBusinessUnit = window.CircDashboard.detailPanel.currentBusinessUnit = businessUnit;
    currentSnapshotDate = window.CircDashboard.detailPanel.currentSnapshotDate = snapshotDate;

    // Find the donut chart for this business unit (for animation)
    const donutElement = findDonutChartElement(businessUnit);
    console.log('🎯 Found donut element for', businessUnit, ':', donutElement);

    // Show panel and backdrop
    const panel = document.getElementById('detailPanel');
    const mainContent = document.getElementById('mainContent');
    const backdrop = document.getElementById('detailPanelBackdrop');

    panel.classList.remove('hidden');
    panel.setAttribute('aria-hidden', 'false');

    // Populate state navigation (only needs to happen once, but safe to call multiple times)
    populateStateNavigation(availableBusinessUnits);

    // Start donut-to-state animation (runs in parallel with panel slide)
    if (donutElement && window.donutAnimator) {
        window.donutAnimator.animateDonutToState(businessUnit, donutElement, () => {
            // Animation complete - restore donut opacity
            if (donutElement) {
                donutElement.style.opacity = '1';
            }
        });
    }

    // Trigger panel slide animation after a brief delay (allows CSS transition)
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            panel.classList.add('open');
            mainContent.classList.add('docked');
            backdrop.classList.add('visible');
        });
    });

    // Focus trap and keyboard support
    panel.focus();
    enableKeyboardShortcuts();

    // Load business unit data
    await loadBusinessUnitData(businessUnit, snapshotDate);
}

/**
 * Find the donut chart element for a business unit
 */
function findDonutChartElement(businessUnit) {
    // Find the paper card for this business unit
    const cards = document.querySelectorAll('.paper-card');
    for (const card of cards) {
        const title = card.querySelector('h3');
        if (title && title.textContent.includes(businessUnit)) {
            // Find the canvas element (donut chart)
            const canvas = card.querySelector('canvas');
            if (canvas) {
                return canvas.parentElement; // Return the container, not just canvas
            }
        }
    }
    return null;
}

/**
 * Close detail panel
 */
function closeDetailPanel() {
    const panel = document.getElementById('detailPanel');
    const mainContent = document.getElementById('mainContent');
    const backdrop = document.getElementById('detailPanelBackdrop');

    // Remove open states
    panel.classList.remove('open');
    mainContent.classList.remove('docked');
    backdrop.classList.remove('visible');

    // Disable keyboard shortcuts
    disableKeyboardShortcuts();

    // Hide panel after animation completes (250ms transition)
    setTimeout(() => {
        panel.classList.add('hidden');
        panel.setAttribute('aria-hidden', 'true');
    }, 250);

    // Destroy charts to free memory
    if (expirationChart) {
        expirationChart.destroy();
        expirationChart = window.CircDashboard.detailPanel.charts.expiration = null;
    }
    if (rateDistributionChart) {
        rateDistributionChart.destroy();
        rateDistributionChart = window.CircDashboard.detailPanel.charts.rateDistribution = null;
    }
    if (subscriptionLengthChart) {
        subscriptionLengthChart.destroy();
        subscriptionLengthChart = window.CircDashboard.detailPanel.charts.subscriptionLength = null;
    }
}

/**
 * Render detail panel content
 */
function renderDetailPanelContent() {
    const data = detailPanelData;

    // Hide loading, show content
    document.getElementById('detailPanelLoading').classList.add('hidden');
    document.getElementById('detailPanelContent').classList.remove('hidden');

    // Update subtitle with actual data date (may differ from requested)
    const actualDate = data.snapshot_date;
    const requestedDate = currentSnapshotDate;
    if (actualDate !== requestedDate) {
        document.getElementById('detailPanelSubtitle').textContent = `Showing data from ${actualDate} (most recent available for week of ${requestedDate})`;
    } else {
        document.getElementById('detailPanelSubtitle').textContent = `Snapshot: ${actualDate}`;
    }

    // Update summary stats
    const breakdown = data.delivery_breakdown;
    document.getElementById('detailTotalActive').textContent = formatNumber(breakdown.total_active);
    document.getElementById('detailDeliverable').textContent = formatNumber(breakdown.deliverable);
    document.getElementById('detailVacation').textContent = formatNumber(breakdown.on_vacation);

    // Update delivery breakdown with percentages
    const total = breakdown.total_active;
    const mailPct = total > 0 ? ((breakdown.mail_delivery / total) * 100).toFixed(1) : '0.0';
    const digitalPct = total > 0 ? ((breakdown.digital_only / total) * 100).toFixed(1) : '0.0';
    const carrierPct = total > 0 ? ((breakdown.carrier_delivery / total) * 100).toFixed(1) : '0.0';

    document.getElementById('detailMail').textContent = `${formatNumber(breakdown.mail_delivery)} (${mailPct}%)`;
    document.getElementById('detailDigital').textContent = `${formatNumber(breakdown.digital_only)} (${digitalPct}%)`;
    document.getElementById('detailCarrier').textContent = `${formatNumber(breakdown.carrier_delivery)} (${carrierPct}%)`;

    // Render comparison data if available
    renderComparisonData(data.comparison);

    // Render charts
    renderExpirationChart(data.expiration_chart);
    renderRateDistributionChart(data.rate_distribution);
    renderSubscriptionLengthChart(data.subscription_length);

    // Initialize context menus for charts (Phase 3)
    if (typeof initializeChartContextMenus === 'function') {
        // Delay slightly to ensure charts are fully rendered
        setTimeout(() => {
            initializeChartContextMenus();
        }, 100);
    }
}

/**
 * Render 4-week expiration chart
 */
function renderExpirationChart(chartData) {
    const canvas = document.getElementById('expirationChart');
    if (!canvas) {
        console.warn('Expiration chart canvas not found - may be in trend view');
        return;
    }

    const ctx = canvas.getContext('2d');

    // Destroy existing chart
    if (expirationChart) expirationChart.destroy();

    const labels = chartData.map(d => d.week_bucket);
    const counts = chartData.map(d => d.count);

    // Color scheme: red for past due/this week, amber for next weeks, gray for later
    const backgroundColors = labels.map(label => {
        if (label === 'Past Due') return 'rgba(239, 68, 68, 0.8)';
        if (label === 'This Week') return 'rgba(251, 146, 60, 0.8)';
        if (label === 'Next Week') return 'rgba(251, 191, 36, 0.8)';
        if (label === 'Week +2') return 'rgba(253, 224, 71, 0.8)';
        return 'rgba(156, 163, 175, 0.8)';
    });

    expirationChart = window.CircDashboard.detailPanel.charts.expiration = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Subscriptions Expiring',
                data: counts,
                backgroundColor: backgroundColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${formatNumber(context.parsed.y)} subscriptions`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
            // onClick removed - now handled by right-click context menu
        }
    });
}

/**
 * Render rate distribution chart
 */
function renderRateDistributionChart(chartData) {
    const canvas = document.getElementById('rateDistributionChart');
    if (!canvas) {
        console.warn('Rate distribution chart canvas not found - may be in trend view');
        return;
    }

    const ctx = canvas.getContext('2d');

    // Destroy existing chart
    if (rateDistributionChart) rateDistributionChart.destroy();

    const labels = chartData.map(d => d.rate_name);
    const counts = chartData.map(d => d.count);

    // Set container height based on number of rows (30px per row for proper spacing)
    const heightPerRow = 30;
    const calculatedHeight = Math.max(600, labels.length * heightPerRow);
    const container = ctx.canvas.parentElement;
    container.style.height = `${calculatedHeight}px`;

    // For display purposes, ensure small values have a minimum visible bar
    // But keep the actual value for tooltips
    const maxCount = Math.max(...counts);
    const minVisiblePercent = 0.5; // 0.5% minimum bar width for visibility
    const displayCounts = counts.map(count => {
        const percent = (count / maxCount) * 100;
        if (percent < minVisiblePercent && count > 0) {
            // Make tiny bars visible but not to scale
            return maxCount * (minVisiblePercent / 100);
        }
        return count;
    });

    // Generate heat map colors (blue → red blend based on subscriber count)
    const baseBlue = { r: 59, g: 130, b: 246 };   // Original blue
    const targetRed = { r: 239, g: 68, b: 68 };   // Red for high values
    const backgroundColors = counts.map(count =>
        blendColors(count, maxCount, baseBlue, targetRed, 0.8)
    );

    rateDistributionChart = window.CircDashboard.detailPanel.charts.rateDistribution = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Subscribers',
                data: displayCounts,
                backgroundColor: backgroundColors,
                borderWidth: 0,
                minBarLength: 3,
                categoryPercentage: 0.6,  // 60% of category space (40% becomes gaps)
                barPercentage: 1.0  // 100% of bar space for thicker bars
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y', // Horizontal bar chart
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            // Use actual count, not display count
                            const actualCount = counts[context.dataIndex];
                            const total = counts.reduce((a, b) => a + b, 0);
                            const percent = ((actualCount / total) * 100).toFixed(1);
                            return `${formatNumber(actualCount)} subscribers (${percent}%)`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                },
                y: {
                    ticks: {
                        autoSkip: false // Show all rate names
                    }
                }
            }
            // onClick removed - now handled by right-click context menu
        }
    });
}

/**
 * Render subscription length chart
 */
function renderSubscriptionLengthChart(chartData) {
    const canvas = document.getElementById('subscriptionLengthChart');
    if (!canvas) {
        console.warn('Subscription length chart canvas not found - may be in trend view');
        return;
    }

    const ctx = canvas.getContext('2d');

    // Destroy existing chart
    if (subscriptionLengthChart) subscriptionLengthChart.destroy();

    // Normalize and aggregate subscription lengths
    // Maps different labels (12M, 1Y, 52Wk) to same canonical value (1 Year)
    // Also track original labels for API queries
    const aggregated = {};
    const originalLabelsMap = {}; // Maps normalized label → array of original labels

    chartData.forEach(d => {
        const normalized = normalizeSubscriptionLength(d.subscription_length);
        if (aggregated[normalized]) {
            aggregated[normalized] += d.count;
            // Use Set to automatically deduplicate
            if (!originalLabelsMap[normalized].has(d.subscription_length)) {
                originalLabelsMap[normalized].add(d.subscription_length);
            }
        } else {
            aggregated[normalized] = d.count;
            originalLabelsMap[normalized] = new Set([d.subscription_length]);
        }
    });

    // Convert aggregated object to sorted arrays
    const sortOrder = ['1 Month', '2 Months', '3 Months', '6 Months', '1 Year'];
    const labels = Object.keys(aggregated).sort((a, b) => {
        const aIndex = sortOrder.indexOf(a);
        const bIndex = sortOrder.indexOf(b);
        // If both in sortOrder, sort by index; otherwise by count (descending)
        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
        if (aIndex !== -1) return -1;
        if (bIndex !== -1) return 1;
        return aggregated[b] - aggregated[a];
    });
    const counts = labels.map(label => aggregated[label]);

    // Store original labels mapping for API calls (trends, subscribers)
    // Convert Sets to Arrays for API compatibility
    window.subscriptionLengthOriginalLabels = {};
    for (const [normalized, originalSet] of Object.entries(originalLabelsMap)) {
        window.subscriptionLengthOriginalLabels[normalized] = Array.from(originalSet);
    }

    // For display purposes, ensure small values have a minimum visible bar
    // But keep the actual value for tooltips
    const maxCount = Math.max(...counts);
    const minVisiblePercent = 2.0; // 2% minimum bar height for visibility (higher than horizontal charts)
    const displayCounts = counts.map(count => {
        const percent = (count / maxCount) * 100;
        if (percent < minVisiblePercent && count > 0) {
            // Make tiny bars visible but not to scale
            return maxCount * (minVisiblePercent / 100);
        }
        return count;
    });

    // Generate heat map colors (teal → yellow blend based on subscriber count)
    const baseTeal = { r: 16, g: 185, b: 129 };    // Original teal
    const targetYellow = { r: 251, g: 191, b: 36 }; // Yellow for high values
    const backgroundColors = counts.map(count =>
        blendColors(count, maxCount, baseTeal, targetYellow, 0.8)
    );

    subscriptionLengthChart = window.CircDashboard.detailPanel.charts.subscriptionLength = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Subscribers',
                data: displayCounts,
                backgroundColor: backgroundColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            // Use actual count, not display count
                            const actualCount = counts[context.dataIndex];
                            const total = counts.reduce((a, b) => a + b, 0);
                            const percent = ((actualCount / total) * 100).toFixed(1);
                            return `${formatNumber(actualCount)} subscribers (${percent}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
            // onClick removed - now handled by right-click context menu
        }
    });
}

/**
 * Render comparison data (YoY, Previous Week, Trend)
 */
function renderComparisonData(comparison) {
    const comparisonSection = document.getElementById('comparisonSection');

    if (!comparison) {
        comparisonSection.classList.add('hidden');
        return;
    }

    let hasData = false;

    // Year-over-Year
    if (comparison.yoy) {
        const yoyDiv = document.getElementById('yoyComparison');
        const change = comparison.yoy.change;
        const percent = comparison.yoy.change_percent;
        const changeColor = change >= 0 ? 'text-green-600' : 'text-red-600';
        const arrow = change >= 0 ? '↑' : '↓';

        document.getElementById('yoyChange').innerHTML = `<span class="${changeColor}">${arrow} ${formatNumber(Math.abs(change))}</span>`;
        document.getElementById('yoyPercent').innerHTML = `<span class="${changeColor}">${change >= 0 ? '+' : ''}${percent}%</span>`;

        yoyDiv.classList.remove('hidden');
        hasData = true;
    }

    // Previous Week
    if (comparison.previous_week) {
        const prevWeekDiv = document.getElementById('prevWeekComparison');
        const change = comparison.previous_week.change;
        const percent = comparison.previous_week.change_percent;
        const changeColor = change >= 0 ? 'text-green-600' : 'text-red-600';
        const arrow = change >= 0 ? '↑' : '↓';

        document.getElementById('prevWeekChange').innerHTML = `<span class="${changeColor}">${arrow} ${formatNumber(Math.abs(change))}</span>`;
        document.getElementById('prevWeekPercent').innerHTML = `<span class="${changeColor}">${change >= 0 ? '+' : ''}${percent}%</span>`;

        prevWeekDiv.classList.remove('hidden');
        hasData = true;
    }

    // Trend Direction
    if (comparison.trend_direction) {
        const trendDiv = document.getElementById('trendDirection');
        const trend = comparison.trend_direction;

        const trendConfig = {
            'growing': { icon: '📈', text: 'Growing', color: 'text-green-600' },
            'declining': { icon: '📉', text: 'Declining', color: 'text-red-600' },
            'stable': { icon: '➡️', text: 'Stable', color: 'text-gray-600' }
        };

        const config = trendConfig[trend] || trendConfig['stable'];
        document.getElementById('trendText').innerHTML = `<span class="${config.color}">${config.icon} ${config.text}</span>`;

        trendDiv.classList.remove('hidden');
        hasData = true;
    }

    // Show section if we have any data
    if (hasData) {
        comparisonSection.classList.remove('hidden');
    } else {
        comparisonSection.classList.add('hidden');
    }
}

/**
 * Format number with commas
 */
function formatNumber(num) {
    if (num === null || num === undefined) return '--';
    return num.toLocaleString();
}

// Make functions globally available
window.openDetailPanel = openDetailPanel;
window.closeDetailPanel = closeDetailPanel;
window.switchBusinessUnit = switchBusinessUnit;
window.loadBusinessUnitData = loadBusinessUnitData;
window.populateStateNavigation = populateStateNavigation;

// console.log('Detail panel module loaded with state navigation');
