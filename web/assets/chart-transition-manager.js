/**
 * Chart Transition Manager
 * Handles smooth transitions between chart views and historical trend displays
 * Includes breadcrumb navigation and animation management
 * Date: 2025-12-05
 */

/**
 * LOAD ORDER: 10 of 11
 *
 * DEPENDENCIES:
 * - app.js: Chart.js instances
 *
 * PROVIDES:
 * - ChartTransitionManager
 */

class ChartTransitionManager {
    constructor(containerElementId) {
        this.containerElement = document.getElementById(containerElementId);
        if (!this.containerElement) {
            console.error(`Container element #${containerElementId} not found`);
            return;
        }

        this.originalChart = null;
        this.originalChartType = null;
        this.trendChart = null;
        this.navigationStack = [];
        this.isTransitioning = false;
        this.currentView = 'original'; // 'original' or 'trend'

        // Store original HTML for restoration
        this.originalHTML = this.containerElement.innerHTML;

        // Bind methods
        this.goBack = this.goBack.bind(this);
    }

    /**
     * Show historical trend for a metric
     * Animates from current chart to trend line chart
     */
    async showTrend(options) {
        if (this.isTransitioning) {
            console.warn('Transition already in progress');
            return;
        }

        this.isTransitioning = true;
        this.currentView = 'trend';

        const {
            chartType,
            metric,
            timeRange,
            data,
            businessUnit,
            snapshotDate,
            onBack
        } = options;

        // Store navigation state
        this.navigationStack.push({
            view: 'trend',
            options: options
        });

        try {
            // Step 1: Slide out current chart (to the left)
            await this.slideOut('left');

            // Step 2: Build trend view HTML
            const trendHTML = this.buildTrendViewHTML({
                chartType,
                metric,
                timeRange,
                businessUnit,
                snapshotDate
            });

            // Step 3: Insert trend view (offscreen right)
            this.containerElement.innerHTML = trendHTML;
            this.containerElement.style.transform = 'translateX(100%)';

            // Step 4: Slide in trend view (from right)
            await this.slideIn();

            // Step 5: Render trend chart
            this.renderTrendChart(data, metric, timeRange);

            // Step 6: Set up back button
            const backBtn = document.getElementById('trendBackBtn');
            if (backBtn) {
                backBtn.addEventListener('click', () => {
                    this.goBack(onBack);
                });
            }

        } catch (error) {
            console.error('Error showing trend:', error);
        } finally {
            this.isTransitioning = false;
        }
    }

    /**
     * Build HTML for trend view
     */
    buildTrendViewHTML(options) {
        const { chartType, metric, timeRange, businessUnit, snapshotDate } = options;

        const timeRangeLabels = {
            '4weeks': '4 Weeks',
            '12weeks': '12 Weeks',
            '26weeks': '26 Weeks',
            '52weeks': '52 Weeks'
        };

        return `
            <!-- Breadcrumb Navigation -->
            <div style="
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 1.5rem;
                padding: 1rem;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            ">
                <button id="trendBackBtn"
                        style="
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                            background: #F1F5F9;
                            border: none;
                            padding: 0.5rem 1rem;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 600;
                            font-size: 0.9rem;
                            color: #1F2937;
                            transition: all 200ms;
                        "
                        onmouseover="this.style.background='#E2E8F0'"
                        onmouseout="this.style.background='#F1F5F9'"
                        title="Back to chart view">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M10 12L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Back</span>
                </button>

                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: #6B7280;">
                    <span>${chartType === 'expiration' ? 'Expirations' : chartType === 'rate' ? 'Rate Distribution' : 'Subscription Length'}</span>
                    <span style="opacity: 0.5;">›</span>
                    <span style="color: #0369A1; font-weight: 600;">${metric}</span>
                    <span style="opacity: 0.5;">›</span>
                    <span>Trend</span>
                </div>

                <div style="
                    margin-left: auto;
                    display: flex;
                    gap: 0.5rem;
                " id="timeRangeSelector">
                    ${['4weeks', '12weeks', '26weeks', '52weeks'].map(range => `
                        <button
                            data-range="${range}"
                            class="time-range-btn ${range === timeRange ? 'active' : ''}"
                            style="
                                padding: 0.375rem 0.75rem;
                                border: 1px solid #E5E7EB;
                                border-radius: 6px;
                                background: ${range === timeRange ? '#0369A1' : 'white'};
                                color: ${range === timeRange ? 'white' : '#6B7280'};
                                cursor: pointer;
                                font-size: 0.8rem;
                                font-weight: 500;
                                transition: all 200ms;
                            "
                            onmouseover="if(!this.classList.contains('active')) { this.style.background='#F3F4F6'; this.style.borderColor='#0369A1'; }"
                            onmouseout="if(!this.classList.contains('active')) { this.style.background='white'; this.style.borderColor='#E5E7EB'; }">
                            ${timeRangeLabels[range]}
                        </button>
                    `).join('')}
                </div>
            </div>

            <!-- Trend Chart Card -->
            <div style="
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                padding: 1.5rem;
            ">
                <h3 style="
                    font-size: 1.125rem;
                    font-weight: 600;
                    color: #1F2937;
                    margin: 0 0 1.5rem 0;
                ">
                    📈 ${metric} - Historical Trend
                </h3>

                <div style="position: relative; height: 400px;">
                    <canvas id="trendLineChart"></canvas>
                </div>

                <div style="
                    margin-top: 1.5rem;
                    padding-top: 1.5rem;
                    border-top: 1px solid #E5E7EB;
                    font-size: 0.875rem;
                    color: #6B7280;
                ">
                    <p style="margin: 0;">
                        <strong style="color: #1F2937;">Business Unit:</strong> ${businessUnit} •
                        <strong style="color: #1F2937;">Period:</strong> Last ${timeRangeLabels[timeRange]} •
                        <strong style="color: #1F2937;">As of:</strong> ${snapshotDate}
                    </p>
                </div>
            </div>
        `;
    }

    /**
     * Render trend line chart
     */
    renderTrendChart(data, metric, timeRange) {
        const ctx = document.getElementById('trendLineChart');
        if (!ctx) {
            console.error('Trend chart canvas not found');
            return;
        }

        // Destroy existing chart if any
        if (this.trendChart) {
            this.trendChart.destroy();
        }

        const labels = data.map(d => {
            const date = new Date(d.snapshot_date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        const counts = data.map(d => d.count);
        const changes = data.map(d => d.change_from_previous);

        this.trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: metric,
                    data: counts,
                    borderColor: '#0369A1',
                    backgroundColor: 'rgba(3, 105, 161, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 5,
                    pointBackgroundColor: '#0369A1',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7
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
                                const index = context.dataIndex;
                                const count = counts[index];
                                const change = changes[index];
                                const changeStr = change >= 0 ? `+${change}` : change;
                                return [
                                    `Count: ${count.toLocaleString()}`,
                                    `Change: ${changeStr} from previous week`
                                ];
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    /**
     * Go back to previous view
     */
    async goBack(onBackCallback) {
        if (this.isTransitioning) {
            console.warn('Transition already in progress');
            return;
        }

        this.isTransitioning = true;
        this.currentView = 'original';

        try {
            // Step 1: Slide out trend view (to the right)
            await this.slideOut('right');

            // Step 2: Restore original HTML (offscreen left)
            this.containerElement.innerHTML = this.originalHTML;
            this.containerElement.style.transform = 'translateX(-100%)';

            // Step 3: Slide in original view (from left)
            await this.slideIn();

            // Step 4: Destroy trend chart
            if (this.trendChart) {
                this.trendChart.destroy();
                this.trendChart = null;
            }

            // Step 5: Pop navigation stack
            this.navigationStack.pop();

            // Step 6: Call back callback if provided
            if (onBackCallback && typeof onBackCallback === 'function') {
                onBackCallback();
            }

        } catch (error) {
            console.error('Error going back:', error);
        } finally {
            this.isTransitioning = false;
        }
    }

    /**
     * Slide out animation
     * @param {string} direction - 'left' or 'right'
     */
    slideOut(direction) {
        return new Promise((resolve) => {
            const translateValue = direction === 'left' ? '-100%' : '100%';

            this.containerElement.style.transition = 'transform 400ms cubic-bezier(0.4, 0, 0.2, 1), opacity 400ms';
            this.containerElement.style.transform = `translateX(${translateValue})`;
            this.containerElement.style.opacity = '0';

            setTimeout(resolve, 400);
        });
    }

    /**
     * Slide in animation
     */
    slideIn() {
        return new Promise((resolve) => {
            // Small delay to ensure DOM is ready
            setTimeout(() => {
                this.containerElement.style.transition = 'transform 400ms cubic-bezier(0.4, 0, 0.2, 1), opacity 400ms';
                this.containerElement.style.transform = 'translateX(0)';
                this.containerElement.style.opacity = '1';

                setTimeout(resolve, 400);
            }, 50);
        });
    }

    /**
     * Reset to original state
     */
    reset() {
        if (this.trendChart) {
            this.trendChart.destroy();
            this.trendChart = null;
        }

        this.containerElement.innerHTML = this.originalHTML;
        this.containerElement.style.transform = 'translateX(0)';
        this.containerElement.style.opacity = '1';
        this.containerElement.style.transition = '';

        this.navigationStack = [];
        this.currentView = 'original';
        this.isTransitioning = false;
    }

    /**
     * Destroy and cleanup
     */
    destroy() {
        this.reset();
        this.originalHTML = null;
        this.containerElement = null;
    }
}

// Export globally
window.ChartTransitionManager = ChartTransitionManager;

console.log('ChartTransitionManager module loaded');
