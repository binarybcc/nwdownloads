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
 * - CircDashboard.detailPanel state
 */

// Extend CircDashboard namespace for detail panel
// (CircDashboard already declared in app.js)
CircDashboard.detailPanel = CircDashboard.detailPanel || {
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
let expirationChart = CircDashboard.detailPanel.charts.expiration = null;
let rateDistributionChart = CircDashboard.detailPanel.charts.rateDistribution = null;
let subscriptionLengthChart = CircDashboard.detailPanel.charts.subscriptionLength = null;

// Current state
let currentBusinessUnit = CircDashboard.detailPanel.currentBusinessUnit = null;
let currentSnapshotDate = CircDashboard.detailPanel.currentSnapshotDate = null;
let detailPanelData = CircDashboard.detailPanel.data = null;
let availableBusinessUnits = ['South Carolina', 'Michigan', 'Wyoming'];  // Will be populated from dashboard data

// Keyboard shortcut handler
let keyboardShortcutHandler = null;

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

    // Update navigation
    updateStateNavActive(businessUnit);

    // Fade out current content
    const content = document.getElementById('detailPanelContent');
    content.style.opacity = '0';
    content.style.transition = 'opacity 200ms ease-out';

    // Wait for fade out
    await new Promise(resolve => setTimeout(resolve, 200));

    // Load new data
    currentBusinessUnit = CircDashboard.detailPanel.currentBusinessUnit = businessUnit;
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

        detailPanelData = CircDashboard.detailPanel.data = result.data;

        // CRITICAL: Update currentSnapshotDate to use ACTUAL snapshot date from API
        // The API resolves the requested date to the actual available snapshot
        // (e.g., requested 2025-11-29 Monday → actual 2025-11-30 Sunday)
        if (result.data.snapshot_date) {
            currentSnapshotDate = CircDashboard.detailPanel.currentSnapshotDate = result.data.snapshot_date;
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
    currentBusinessUnit = CircDashboard.detailPanel.currentBusinessUnit = businessUnit;
    currentSnapshotDate = CircDashboard.detailPanel.currentSnapshotDate = snapshotDate;

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
        expirationChart = CircDashboard.detailPanel.charts.expiration = null;
    }
    if (rateDistributionChart) {
        rateDistributionChart.destroy();
        rateDistributionChart = CircDashboard.detailPanel.charts.rateDistribution = null;
    }
    if (subscriptionLengthChart) {
        subscriptionLengthChart.destroy();
        subscriptionLengthChart = CircDashboard.detailPanel.charts.subscriptionLength = null;
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
    const ctx = document.getElementById('expirationChart').getContext('2d');

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

    expirationChart = CircDashboard.detailPanel.charts.expiration = new Chart(ctx, {
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
    const ctx = document.getElementById('rateDistributionChart').getContext('2d');

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

    rateDistributionChart = CircDashboard.detailPanel.charts.rateDistribution = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Subscribers',
                data: displayCounts,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
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
    const ctx = document.getElementById('subscriptionLengthChart').getContext('2d');

    // Destroy existing chart
    if (subscriptionLengthChart) subscriptionLengthChart.destroy();

    const labels = chartData.map(d => d.subscription_length);
    const counts = chartData.map(d => d.count);

    subscriptionLengthChart = CircDashboard.detailPanel.charts.subscriptionLength = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Subscribers',
                data: counts,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
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
                            const total = counts.reduce((a, b) => a + b, 0);
                            const percent = ((context.parsed.y / total) * 100).toFixed(1);
                            return `${formatNumber(context.parsed.y)} subscribers (${percent}%)`;
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

console.log('Detail panel module loaded with state navigation');
