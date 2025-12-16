/**
 * Publication Revenue Detail Slider Component
 * Shows deep dive into individual publication's revenue opportunity
 *
 * Features:
 * - Donut chart showing market vs legacy revenue split
 * - Historical stacked area chart (12-week trend)
 * - Trend indicator (opportunity growing/shrinking)
 * - Export functionality
 */

/* exported openPublicationDetail, closeRevenueDetail, exportLegacySubscribers */

class PublicationRevenueDetail {
    constructor() {
        this.panel = null;
        this.charts = {};
        this.paperCode = null;
        this.data = null;
    }

    /**
     * Show detail panel for a publication
     * @param {string} paperCode - Publication code (TJ, TA, etc.)
     */
    async show(paperCode) {
        this.paperCode = paperCode;

        try {
            // Fetch detailed data from API
            const response = await fetch(`api/revenue_intelligence.php?action=publication_detail&paper=${paperCode}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load publication detail');
            }

            this.data = data;

            // Create and show panel
            this.createPanel(data);
            this.renderCurrentStateDonut(data.current);
            this.renderHistoricalTrend(data.historical);

        } catch (error) {
            console.error('Error loading publication detail:', error);
            alert('Failed to load publication detail: ' + error.message);
        }
    }

    /**
     * Create slide-out panel
     * @param {Object} data - Publication revenue data
     */
    createPanel(data) {
        // Remove any existing panel
        this.close();

        const panelHTML = `
            <div id="revenueDetailPanel" class="fixed inset-y-0 right-0 w-full md:w-3/4 bg-white shadow-2xl z-50 transform transition-transform duration-300 overflow-y-auto">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 sticky top-0 z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold">${data.paper_name} Revenue Opportunity</h2>
                            <p class="text-blue-100 mt-1">${data.business_unit}</p>
                        </div>
                        <button onclick="closeRevenueDetail()" class="text-white hover:text-blue-100 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6 space-y-6">
                    <!-- Current State -->
                    <section>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Current State</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Donut Chart -->
                            <div class="bg-gray-50 rounded-lg p-4 flex items-center justify-center">
                                <canvas id="revenueDonutChart" width="300" height="300"></canvas>
                            </div>

                            <!-- Metrics -->
                            <div class="space-y-4">
                                <div class="bg-white rounded-lg p-4 shadow">
                                    <div class="text-sm text-gray-600">Legacy Rate Subscribers</div>
                                    <div class="text-3xl font-bold text-gray-900">${this.formatNumber(data.current.legacy_subscribers)}</div>
                                </div>
                                <div class="bg-white rounded-lg p-4 shadow">
                                    <div class="text-sm text-gray-600">Average Legacy Rate</div>
                                    <div class="text-3xl font-bold text-amber-700">${this.formatCurrency(data.current.avg_legacy_rate)}/yr</div>
                                    <div class="text-sm text-gray-500 mt-1">Market rate: ${this.formatCurrency(data.current.market_rate)}/yr</div>
                                </div>
                                <div class="bg-white rounded-lg p-4 shadow">
                                    <div class="text-sm text-gray-600">Monthly Opportunity</div>
                                    <div class="text-3xl font-bold text-blue-700">+${this.formatCurrency(data.current.monthly_opportunity)}</div>
                                    <div class="text-sm text-gray-500 mt-1">Annual: +${this.formatCurrency(data.current.monthly_opportunity * 12)}</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Historical Trend -->
                    <section>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Trend Over Time (Last 12 Weeks)</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <canvas id="revenueTrendChart" style="max-height: 400px;"></canvas>
                        </div>
                        <div class="mt-4 text-center">
                            ${this.buildTrendBadge(data.trend_direction, data.trend_percent)}
                        </div>
                    </section>

                    <!-- Actions -->
                    <section>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <button onclick="exportLegacySubscribers('${data.paper_code}')" class="flex-1 bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition font-medium">
                                📊 Export Legacy Rate Subscribers
                            </button>
                            <button onclick="window.location.href='rates.php'" class="flex-1 bg-gray-600 text-white px-4 py-3 rounded-lg hover:bg-gray-700 transition font-medium">
                                ⚙️ Manage Rates
                            </button>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Backdrop -->
            <div id="revenueDetailBackdrop" class="fixed inset-0 bg-black bg-opacity-50 z-40" onclick="closeRevenueDetail()"></div>
        `;

        document.body.insertAdjacentHTML('beforeend', panelHTML);

        // Add escape key listener
        this.escapeHandler = (e) => {
            if (e.key === 'Escape') this.close();
        };
        document.addEventListener('keydown', this.escapeHandler);
    }

    /**
     * Build trend indicator badge
     */
    buildTrendBadge(direction, percent) {
        if (direction === 'unknown' || percent === 0) {
            return `
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                    ℹ️ Insufficient data for trend analysis
                </span>
            `;
        }

        if (direction === 'growing') {
            return `
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    ⚠️ Opportunity Growing ↑ ${percent}%
                    <span class="ml-2 text-xs">(More subscribers falling to legacy rates)</span>
                </span>
            `;
        } else {
            return `
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    ✓ Opportunity Shrinking ↓ ${percent}%
                    <span class="ml-2 text-xs">(Legacy rate subscribers decreasing)</span>
                </span>
            `;
        }
    }

    /**
     * Render donut chart showing market vs legacy split
     * @param {Object} data - Current state data
     */
    renderCurrentStateDonut(data) {
        const ctx = document.getElementById('revenueDonutChart').getContext('2d');

        const marketRevenue = data.market_revenue;
        const legacyRevenue = data.legacy_revenue;
        const _opportunity = marketRevenue - legacyRevenue;

        this.charts.donut = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    `Market Rate Revenue ($${this.formatNumber(Math.round(marketRevenue))}/mo)`,
                    `Legacy Rate Revenue ($${this.formatNumber(Math.round(legacyRevenue))}/mo)`
                ],
                datasets: [{
                    data: [marketRevenue, legacyRevenue],
                    backgroundColor: ['#10B981', '#F59E0B'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = marketRevenue;
                                const percent = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Render stacked area chart showing historical trend
     * @param {Array} data - Historical data (weekly snapshots)
     */
    renderHistoricalTrend(data) {
        const ctx = document.getElementById('revenueTrendChart').getContext('2d');

        if (!data || data.length === 0) {
            ctx.font = '14px Inter, sans-serif';
            ctx.fillStyle = '#6B7280';
            ctx.textAlign = 'center';
            ctx.fillText('Insufficient historical data', ctx.canvas.width / 2, ctx.canvas.height / 2);
            return;
        }

        // Extract dates and values
        const dates = data.map(d => {
            const date = new Date(d.snapshot_date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        const marketRevenue = data.map(d => d.market_revenue);
        const legacyRevenue = data.map(d => d.legacy_revenue);

        this.charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Market Rate Potential',
                        data: marketRevenue,
                        backgroundColor: 'rgba(16, 185, 129, 0.3)',
                        borderColor: '#10B981',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Legacy Rate Revenue',
                        data: legacyRevenue,
                        backgroundColor: 'rgba(245, 158, 11, 0.3)',
                        borderColor: '#F59E0B',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Week',
                            font: {
                                weight: 'bold'
                            }
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        stacked: false,
                        title: {
                            display: true,
                            text: 'Monthly Recurring Revenue ($)',
                            font: {
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            callback: (value) => '$' + this.formatNumber(Math.round(value))
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y || 0;
                                return `${label}: $${this.formatNumber(Math.round(value))}`;
                            },
                            footer: (tooltipItems) => {
                                if (tooltipItems.length === 2) {
                                    const market = tooltipItems[0].parsed.y;
                                    const legacy = tooltipItems[1].parsed.y;
                                    const gap = market - legacy;
                                    return `Opportunity: +$${this.formatNumber(Math.round(gap))}`;
                                }
                                return '';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Close panel
     */
    close() {
        // Remove panels
        const panel = document.getElementById('revenueDetailPanel');
        const backdrop = document.getElementById('revenueDetailBackdrop');

        if (panel) panel.remove();
        if (backdrop) backdrop.remove();

        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        this.charts = {};

        // Remove escape key listener
        if (this.escapeHandler) {
            document.removeEventListener('keydown', this.escapeHandler);
            this.escapeHandler = null;
        }
    }

    /**
     * Format currency
     */
    formatCurrency(amount) {
        return '$' + this.formatNumber(Math.round(amount));
    }

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return Math.round(num).toLocaleString('en-US');
    }
}

// Global functions
function openPublicationDetail(paperCode) {
    const detail = new PublicationRevenueDetail();
    detail.show(paperCode);
}

function closeRevenueDetail() {
    // Find any existing instance and close it
    const panel = document.getElementById('revenueDetailPanel');
    if (panel) {
        const backdrop = document.getElementById('revenueDetailBackdrop');
        if (panel) panel.remove();
        if (backdrop) backdrop.remove();
    }
}

function exportLegacySubscribers(paperCode) {
    alert(`Export functionality for ${paperCode} legacy subscribers coming soon!`);
    // TODO: Implement export to Excel/CSV
}
