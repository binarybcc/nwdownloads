/**
 * Trend Slider Panel
 * Slide-out panel for viewing trend charts over time
 * Uses Chart.js area charts with color continuity from source chart
 * Date: 2025-12-07
 *
 * SECURITY NOTE: This module uses innerHTML for template rendering.
 * All content is from controlled template literals - no user input is rendered directly.
 * Data from API is escaped via encodeURIComponent in fetch calls.
 * Chart.js handles data rendering safely within canvas context.
 */

/**
 * LOAD ORDER: 10 of 11
 *
 * DEPENDENCIES:
 * - Chart.js: Global Chart object
 * - app.js: API_BASE
 *
 * PROVIDES:
 * - Trend chart visualization in separate slider panel
 */

class TrendSlider {
    constructor() {
        this.panel = null;
        this.backdrop = null;
        this.chart = null;
        this.isOpen = false;
        this.context = null; // Stores chart context (type, metric, color, etc.)

        // Bind methods
        this.close = this.close.bind(this);
        this.handleEscape = this.handleEscape.bind(this);
        this.handleTimeRangeChange = this.handleTimeRangeChange.bind(this);
    }

    /**
     * Open trend slider with context
     * @param {Object} context - Chart context
     * @param {string} context.chartType - 'expiration', 'rate', or 'subscription_length'
     * @param {string} context.metric - The metric value (e.g., 'Past Due', '55.00/week')
     * @param {string} context.color - Hex color from clicked bar
     * @param {string} context.businessUnit - Business unit name
     * @param {string} context.snapshotDate - Snapshot date (YYYY-MM-DD)
     * @param {string} context.timeRange - '4weeks', '12weeks', '26weeks', or '52weeks'
     * @param {number} context.count - Current count for this metric
     */
    open(context) {
        console.log('🎨 TrendSlider.open() received context:', context);
        this.context = {
            ...context,
            timeRange: context.timeRange || '4weeks'
        };
        console.log('🎨 TrendSlider.context stored:', this.context);

        // Close any existing panel
        if (this.isOpen) {
            this.close();
            // Wait for close animation
            setTimeout(() => this.render(), 300);
        } else {
            this.render();
        }
    }

    /**
     * Render panel DOM
     * SECURITY: Uses innerHTML with controlled template literals only
     */
    render() {
        // Create backdrop
        this.backdrop = document.createElement('div');
        this.backdrop.id = 'trendSliderBackdrop';
        this.backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            opacity: 0;
            transition: opacity 300ms cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(3px);
        `;
        this.backdrop.addEventListener('click', this.close);

        // Create panel
        this.panel = document.createElement('div');
        this.panel.id = 'trendSliderPanel';
        this.panel.style.cssText = `
            position: fixed;
            top: 0;
            right: -80%;
            width: 80%;
            height: 100vh;
            background: linear-gradient(to bottom, #F8FAFC 0%, #F1F5F9 100%);
            box-shadow: -8px 0 32px rgba(0,0,0,0.2);
            z-index: 9999;
            overflow-y: auto;
            transition: right 350ms cubic-bezier(0.4, 0, 0.2, 1);
        `;

        // Build panel content (safe: controlled template, no user input)
        this.panel.innerHTML = this.buildPanelHTML();

        // Add to DOM
        document.body.appendChild(this.backdrop);
        document.body.appendChild(this.panel);

        // Trigger animations
        requestAnimationFrame(() => {
            this.backdrop.style.opacity = '1';
            this.panel.style.right = '0';
        });

        // Add event listeners
        document.addEventListener('keydown', this.handleEscape);
        document.getElementById('closeTrendSlider').addEventListener('click', this.close);

        // Add time range button listeners
        document.querySelectorAll('.trend-time-range-btn').forEach(btn => {
            btn.addEventListener('click', this.handleTimeRangeChange);
        });

        this.isOpen = true;

        // Load trend data and render chart
        this.loadTrendData();
    }

    /**
     * Build panel HTML
     * Returns safe HTML string from controlled template
     */
    buildPanelHTML() {
        const { chartType, metric, businessUnit, snapshotDate, timeRange, count, color } = this.context;
        console.log('🎨 buildPanelHTML() color:', color);

        // Chart type display names
        const chartTypeNames = {
            'expiration': 'Subscription Expirations',
            'rate': 'Rate Distribution',
            'subscription_length': 'Subscription Length'
        };

        // Time range labels
        const timeRangeLabels = {
            '4weeks': '4 Weeks',
            '12weeks': '12 Weeks',
            '26weeks': '26 Weeks',
            '52weeks': '52 Weeks'
        };

        // All values are from controlled context object, not user input
        return `
            <!-- Header -->
            <div style="
                background: linear-gradient(135deg, ${color} 0%, ${this.adjustBrightness(color, -20)} 100%);
                padding: 2rem 2.5rem;
                border-bottom: 1px solid rgba(0,0,0,0.1);
                position: sticky;
                top: 0;
                z-index: 10;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            ">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1;">
                        <!-- Breadcrumb -->
                        <div style="
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                            margin-bottom: 1rem;
                            font-size: 0.875rem;
                            color: rgba(255,255,255,0.9);
                            font-weight: 500;
                        ">
                            <span>${chartTypeNames[chartType]}</span>
                            <span style="opacity: 0.6;">›</span>
                            <span>${metric}</span>
                            <span style="opacity: 0.6;">›</span>
                            <span style="opacity: 0.9;">Historical Trend</span>
                        </div>

                        <!-- Title -->
                        <h2 style="
                            margin: 0;
                            font-size: 1.75rem;
                            font-weight: 700;
                            color: white;
                            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                        ">
                            📈 ${metric} - Trend Analysis
                        </h2>

                        <!-- Subtitle -->
                        <p style="
                            margin: 0.5rem 0 0 0;
                            font-size: 1rem;
                            color: rgba(255,255,255,0.9);
                            font-weight: 500;
                        ">
                            ${businessUnit} • Current: ${count.toLocaleString()} subscribers
                        </p>
                    </div>

                    <!-- Close Button -->
                    <button id="closeTrendSlider" style="
                        background: rgba(255,255,255,0.2);
                        border: 1px solid rgba(255,255,255,0.3);
                        color: white;
                        width: 40px;
                        height: 40px;
                        border-radius: 8px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 200ms;
                        flex-shrink: 0;
                    " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 5L5 15M5 5l10 10"/>
                        </svg>
                    </button>
                </div>

                <!-- Time Range Selector -->
                <div style="
                    margin-top: 1.5rem;
                    display: flex;
                    gap: 0.75rem;
                ">
                    ${['4weeks', '12weeks', '26weeks', '52weeks'].map(range => `
                        <button
                            class="trend-time-range-btn"
                            data-range="${range}"
                            style="
                                padding: 0.5rem 1rem;
                                border: 2px solid ${range === timeRange ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.3)'};
                                border-radius: 8px;
                                background: ${range === timeRange ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.1)'};
                                color: white;
                                cursor: pointer;
                                font-size: 0.875rem;
                                font-weight: ${range === timeRange ? '700' : '600'};
                                transition: all 200ms;
                                text-shadow: 0 1px 2px rgba(0,0,0,0.2);
                            "
                            onmouseover="if(!this.classList.contains('active')) { this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.5)'; }"
                            onmouseout="if(!this.classList.contains('active')) { this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.3)'; }"
                        >
                            ${timeRangeLabels[range]}
                        </button>
                    `).join('')}
                </div>
            </div>

            <!-- Chart Container -->
            <div style="padding: 2.5rem;">
                <!-- Loading State -->
                <div id="trendChartLoading" style="
                    text-align: center;
                    padding: 4rem 0;
                    color: #64748B;
                ">
                    <div style="
                        width: 48px;
                        height: 48px;
                        border: 4px solid #E2E8F0;
                        border-top-color: ${color};
                        border-radius: 50%;
                        margin: 0 auto 1rem;
                        animation: spin 1s linear infinite;
                    "></div>
                    <p style="margin: 0; font-size: 1rem; font-weight: 500;">Loading trend data...</p>
                </div>

                <!-- Data Availability Note (populated by renderChart) -->
                <div id="trendDataAvailabilityNote"></div>

                <!-- Chart Card -->
                <div id="trendChartCard" style="
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                    padding: 2rem;
                    display: none;
                ">
                    <div style="position: relative; height: 500px; width: 100%;">
                        <canvas id="trendChartCanvas"></canvas>
                    </div>

                    <!-- Chart Footer -->
                    <div style="
                        margin-top: 2rem;
                        padding-top: 2rem;
                        border-top: 2px solid #E5E7EB;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        font-size: 0.875rem;
                        color: #6B7280;
                    ">
                        <div>
                            <strong style="color: #1F2937;">Business Unit:</strong> ${businessUnit} •
                            <strong style="color: #1F2937;">As of:</strong> ${snapshotDate}
                        </div>
                        <div style="
                            padding: 0.5rem 1rem;
                            background: ${color}20;
                            border-radius: 6px;
                            color: ${color};
                            font-weight: 600;
                        ">
                            📊 Chart.js (Phase 1)
                        </div>
                    </div>
                </div>
            </div>

            <style>
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>
        `;
    }

    /**
     * Load trend data from API
     */
    async loadTrendData() {
        const { chartType, metric, businessUnit, snapshotDate, timeRange } = this.context;

        try {
            // Handle aggregated subscription lengths (e.g., "1 Year" = ["12M (1 Year)", "1Y", etc.])
            // For these, we need to query all original labels and combine the results
            const isAggregatedSubscriptionLength = chartType === 'subscription_length' &&
                window.subscriptionLengthOriginalLabels &&
                window.subscriptionLengthOriginalLabels[metric];

            let result;

            if (isAggregatedSubscriptionLength) {
                // Query for each original label and aggregate results
                const originalLabels = window.subscriptionLengthOriginalLabels[metric];
                console.log('🔍 Aggregating trend data for:', metric);
                console.log('🔍 Original labels to query:', originalLabels);
                const allDataPoints = {};

                for (const originalLabel of originalLabels) {
                    const url = `api.php?action=get_trend&business_unit=${encodeURIComponent(businessUnit)}&metric_type=${chartType}&metric_value=${encodeURIComponent(originalLabel)}&time_range=${timeRange}&end_date=${snapshotDate}`;
                    console.log('🔍 Fetching:', url);
                    const response = await fetch(url);
                    const labelResult = await response.json();
                    console.log('🔍 API response for', originalLabel, ':', labelResult);

                    if (labelResult.success && labelResult.data && labelResult.data.data_points) {
                        console.log('🔍 Data points found:', labelResult.data.data_points.length);
                        // Aggregate data points by date
                        labelResult.data.data_points.forEach(point => {
                            console.log('🔍 Processing point:', point);
                            if (allDataPoints[point.snapshot_date]) {
                                allDataPoints[point.snapshot_date] += point.count;
                                console.log('🔍 Accumulated', point.snapshot_date, ':', allDataPoints[point.snapshot_date]);
                            } else {
                                allDataPoints[point.snapshot_date] = point.count;
                                console.log('🔍 First value for', point.snapshot_date, ':', point.count);
                            }
                        });
                    } else {
                        console.log('🔍 No data returned for', originalLabel);
                    }
                }

                console.log('🔍 Final aggregated data points:', allDataPoints);

                // Convert aggregated data back to array format
                const dataPoints = Object.keys(allDataPoints)
                    .sort()
                    .map((date, index, dates) => ({
                        snapshot_date: date,
                        count: allDataPoints[date],
                        change_from_previous: index > 0 ? allDataPoints[date] - allDataPoints[dates[index - 1]] : 0
                    }));

                console.log('🔍 Converted to array format:', dataPoints);

                result = {
                    success: true,
                    data: { data_points: dataPoints }
                };
            } else {
                // Standard single-metric query
                const response = await fetch(
                    `api.php?action=get_trend&business_unit=${encodeURIComponent(businessUnit)}&metric_type=${chartType}&metric_value=${encodeURIComponent(metric)}&time_range=${timeRange}&end_date=${snapshotDate}`
                );
                result = await response.json();
            }

            if (!result.success) {
                throw new Error(result.error || 'Failed to load trend data');
            }

            // Hide loading, show chart
            document.getElementById('trendChartLoading').style.display = 'none';
            document.getElementById('trendChartCard').style.display = 'block';

            // Render chart (Chart.js handles data safely)
            this.renderChart(result.data.data_points);

        } catch (error) {
            console.error('Error loading trend data:', error);
            // Safe: error.message is from controlled Error object
            document.getElementById('trendChartLoading').innerHTML = `
                <div style="
                    padding: 2rem;
                    background: #FEE2E2;
                    border-left: 4px solid #DC2626;
                    border-radius: 8px;
                    color: #991B1B;
                ">
                    <strong>⚠️ Error loading trend data</strong><br>
                    ${error.message}
                </div>
            `;
        }
    }

    /**
     * Adjust opacity of a color (works with both hex and rgba)
     * @param {string} color - Color string (hex or rgba)
     * @param {number} opacity - Opacity value (0-1)
     * @returns {string} Color with adjusted opacity
     */
    adjustOpacity(color, opacity) {
        // If rgba, replace the alpha value
        if (color.startsWith('rgba')) {
            return color.replace(/[\d.]+\)$/, `${opacity})`);
        }
        // If rgb, convert to rgba
        if (color.startsWith('rgb')) {
            return color.replace('rgb', 'rgba').replace(')', `, ${opacity})`);
        }
        // If hex, append alpha (convert opacity 0-1 to hex 00-FF)
        if (color.startsWith('#')) {
            const alpha = Math.round(opacity * 255).toString(16).padStart(2, '0');
            return color + alpha;
        }
        // Fallback
        return color;
    }

    /**
     * Render Chart.js area chart
     */
    renderChart(dataPoints) {
        const { metric, color, timeRange } = this.context;
        console.log('🎨 renderChart() color value:', color, 'type:', typeof color);

        // Handle empty data
        if (!dataPoints || dataPoints.length === 0) {
            this.renderEmptyChart();
            return;
        }

        // Calculate actual data span
        const timeRangeWeeks = parseInt(timeRange.replace('weeks', ''));
        const actualWeeks = dataPoints.length;

        // Add data availability note if limited
        const noteContainer = document.getElementById('trendDataAvailabilityNote');
        if (actualWeeks < timeRangeWeeks) {
            noteContainer.innerHTML = `
                <div style="
                    padding: 1rem 1.5rem;
                    background: #FEF3C7;
                    border-left: 4px solid #F59E0B;
                    border-radius: 8px;
                    margin-bottom: 1.5rem;
                    font-size: 0.875rem;
                    color: #92400E;
                ">
                    <strong>⚠️ Limited data available:</strong> Showing ${actualWeeks} week${actualWeeks !== 1 ? 's' : ''} of data (requested ${timeRangeWeeks} weeks)
                </div>
            `;
        } else {
            noteContainer.innerHTML = '';
        }

        // Prepare chart data
        const labels = dataPoints.map(d => {
            const date = new Date(d.snapshot_date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        const counts = dataPoints.map(d => d.count);
        const changes = dataPoints.map(d => d.change_from_previous);

        // Get canvas context
        const ctx = document.getElementById('trendChartCanvas').getContext('2d');

        // Destroy existing chart if any
        if (this.chart) {
            this.chart.destroy();
        }

        // Create area chart with color matching
        // Area fill uses same color as bar, border line is fully opaque for contrast
        const borderColor = this.adjustOpacity(color, 1.0); // Fully opaque border
        console.log('🎨 Chart.js colors - borderColor:', borderColor, 'backgroundColor:', color);
        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: metric,
                    data: counts,
                    borderColor: borderColor,
                    backgroundColor: color, // Same as original bar
                    borderWidth: 3,
                    fill: true, // Makes it an area chart
                    tension: 0.4, // Smooth curves
                    pointRadius: 6,
                    pointBackgroundColor: borderColor, // Darker points for visibility
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: borderColor,
                    pointHoverBorderColor: 'white',
                    pointHoverBorderWidth: 3
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
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                const index = context.dataIndex;
                                const count = counts[index];
                                const change = changes[index];
                                const changeStr = change >= 0 ? `+${change}` : change;
                                const changeColor = change >= 0 ? '🟢' : '🔴';
                                return [
                                    `Count: ${count.toLocaleString()}`,
                                    `${changeColor} Change: ${changeStr} from previous week`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            color: '#64748B'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#E2E8F0',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            color: '#64748B',
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                animation: {
                    duration: 750,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    /**
     * Render empty state when no data available
     */
    renderEmptyChart() {
        const { metric, timeRange } = this.context;
        const timeRangeLabels = {
            '4weeks': '4 weeks',
            '12weeks': '12 weeks',
            '26weeks': '26 weeks',
            '52weeks': '52 weeks'
        };

        const noteContainer = document.getElementById('trendDataAvailabilityNote');
        noteContainer.innerHTML = `
            <div style="
                padding: 2rem;
                background: #FEE2E2;
                border-left: 4px solid #DC2626;
                border-radius: 8px;
                margin-bottom: 1.5rem;
                font-size: 0.875rem;
                color: #991B1B;
                text-align: center;
            ">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                <strong style="font-size: 1.125rem;">No data available</strong><br>
                <span style="opacity: 0.8;">for "${metric}" over the last ${timeRangeLabels[timeRange]}</span>
            </div>
        `;
    }

    /**
     * Handle time range button clicks
     */
    async handleTimeRangeChange(event) {
        const newTimeRange = event.currentTarget.dataset.range;
        if (newTimeRange === this.context.timeRange) return;

        // Update context
        this.context.timeRange = newTimeRange;

        // Update button states
        document.querySelectorAll('.trend-time-range-btn').forEach(btn => {
            const isActive = btn.dataset.range === newTimeRange;
            btn.style.borderColor = isActive ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.3)';
            btn.style.background = isActive ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.1)';
            btn.style.fontWeight = isActive ? '700' : '600';
        });

        // Show loading state
        document.getElementById('trendChartCard').style.display = 'none';
        document.getElementById('trendChartLoading').style.display = 'block';
        document.getElementById('trendChartLoading').innerHTML = `
            <div style="
                width: 48px;
                height: 48px;
                border: 4px solid #E2E8F0;
                border-top-color: ${this.context.color};
                border-radius: 50%;
                margin: 0 auto 1rem;
                animation: spin 1s linear infinite;
            "></div>
            <p style="margin: 0; font-size: 1rem; font-weight: 500;">Loading ${newTimeRange.replace('weeks', ' weeks')} of data...</p>
        `;

        // Reload data
        await this.loadTrendData();
    }

    /**
     * Close panel
     */
    close() {
        if (!this.isOpen) return;

        // Destroy chart
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }

        // Animate out
        if (this.backdrop) {
            this.backdrop.style.opacity = '0';
        }
        if (this.panel) {
            this.panel.style.right = '-80%';
        }

        // Remove from DOM after animation
        setTimeout(() => {
            if (this.backdrop && this.backdrop.parentNode) {
                this.backdrop.parentNode.removeChild(this.backdrop);
            }
            if (this.panel && this.panel.parentNode) {
                this.panel.parentNode.removeChild(this.panel);
            }
            this.backdrop = null;
            this.panel = null;
        }, 350);

        // Remove event listeners
        document.removeEventListener('keydown', this.handleEscape);

        this.isOpen = false;
    }

    /**
     * Handle escape key
     */
    handleEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    /**
     * Utility: Adjust color brightness
     * @param {string} hex - Hex color code
     * @param {number} percent - Percentage to adjust (-100 to 100)
     */
    adjustBrightness(hex, percent) {
        // Remove # if present
        hex = hex.replace('#', '');

        // Convert to RGB
        let r = parseInt(hex.substring(0, 2), 16);
        let g = parseInt(hex.substring(2, 4), 16);
        let b = parseInt(hex.substring(4, 6), 16);

        // Adjust brightness
        r = Math.max(0, Math.min(255, r + (r * percent / 100)));
        g = Math.max(0, Math.min(255, g + (g * percent / 100)));
        b = Math.max(0, Math.min(255, b + (b * percent / 100)));

        // Convert back to hex
        r = Math.round(r).toString(16).padStart(2, '0');
        g = Math.round(g).toString(16).padStart(2, '0');
        b = Math.round(b).toString(16).padStart(2, '0');

        return '#' + r + g + b;
    }
}

// Create global instance
window.trendSlider = new TrendSlider();

console.log('TrendSlider module loaded');
