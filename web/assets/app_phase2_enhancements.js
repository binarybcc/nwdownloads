/**
 * Phase 2 Enhancements - Add-on file
 * Advanced comparisons, drill-down, analytics, export
 * Include this AFTER app_enhanced.js
 */

// ========================================
// PHASE 2: Export Functions
// ========================================

/**
 * Export current view to CSV
 */
function exportToCSV() {
    const data = dashboardData;

    let csv = 'Period,' + data.week.label + '\n';
    csv += 'Date Range,' + data.week.date_range + '\n';
    csv += '\n';

    csv += 'Business Unit,Paper,Total Active,On Vacation,Deliverable,Mail,Digital,Carrier\n';

    for (const [unit, unitData] of Object.entries(data.by_business_unit)) {
        csv += `${unit},ALL,${unitData.total},${unitData.on_vacation},${unitData.deliverable},${unitData.mail},${unitData.digital},${unitData.carrier}\n`;
    }

    for (const [paper, paperData] of Object.entries(data.by_edition)) {
        csv += `${paperData.business_unit},${paper},${paperData.total},${paperData.on_vacation},${paperData.deliverable},${paperData.mail},${paperData.digital},${paperData.carrier}\n`;
    }

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `circulation-${data.week.label.replace(/\s/g, '-')}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Export to Excel (multi-sheet)
 */
function exportToExcel() {
    if (typeof XLSX === 'undefined') {
        alert('Excel export library not loaded. Please refresh the page.');
        return;
    }

    const wb = XLSX.utils.book_new();
    const data = dashboardData;

    // Sheet 1: Summary
    const summaryData = [
        ['Circulation Dashboard Export'],
        ['Period', data.week.label],
        ['Date Range', data.week.date_range],
        [''],
        ['Overall Metrics', ''],
        ['Total Active', data.current.total_active],
        ['On Vacation', data.current.on_vacation],
        ['Deliverable', data.current.deliverable],
        [''],
        ['Delivery Methods', ''],
        ['Mail', data.current.mail],
        ['Digital', data.current.digital],
        ['Carrier', data.current.carrier]
    ];

    if (data.comparison) {
        summaryData.push(['']);
        summaryData.push(['Comparison', data.comparison.label]);
        summaryData.push(['Change', data.comparison.changes.total_active]);
        summaryData.push(['Change %', data.comparison.changes.total_active_percent + '%']);
    }

    const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

    // Sheet 2: By Business Unit
    const unitData = [['Business Unit', 'Papers', 'Total', 'Vacation', 'Deliverable', 'Mail', 'Digital', 'Carrier']];
    for (const [unit, unitValues] of Object.entries(data.by_business_unit)) {
        const papers = BUSINESS_UNITS[unit]?.papers.join(', ') || '';
        unitData.push([
            unit, papers,
            unitValues.total, unitValues.on_vacation, unitValues.deliverable,
            unitValues.mail, unitValues.digital, unitValues.carrier
        ]);
    }
    const unitSheet = XLSX.utils.aoa_to_sheet(unitData);
    XLSX.utils.book_append_sheet(wb, unitSheet, 'By Business Unit');

    // Sheet 3: By Publication
    const paperData = [['Paper', 'Name', 'Business Unit', 'Total', 'Vacation', 'Deliverable', 'Mail', 'Digital', 'Carrier']];
    for (const [code, paperValues] of Object.entries(data.by_edition)) {
        const info = PAPER_INFO[code];
        paperData.push([
            code, info?.name || code, paperValues.business_unit,
            paperValues.total, paperValues.on_vacation, paperValues.deliverable,
            paperValues.mail, paperValues.digital, paperValues.carrier
        ]);
    }
    const paperSheet = XLSX.utils.aoa_to_sheet(paperData);
    XLSX.utils.book_append_sheet(wb, paperSheet, 'By Publication');

    // Sheet 4: 12-Week Trend
    const trendData = [['Date', 'Total Active', 'Deliverable', 'On Vacation']];
    for (const week of data.trend) {
        trendData.push([week.snapshot_date, week.total_active, week.deliverable, week.on_vacation]);
    }
    const trendSheet = XLSX.utils.aoa_to_sheet(trendData);
    XLSX.utils.book_append_sheet(wb, trendSheet, '12-Week Trend');

    // Download
    XLSX.writeFile(wb, `circulation-export-${data.week.label.replace(/\s/g, '-')}.xlsx`);
}

/**
 * Export to PDF
 */
async function exportToPDF() {
    if (typeof jspdf === 'undefined' || typeof html2canvas === 'undefined') {
        alert('PDF export libraries not loaded. Please refresh the page.');
        return;
    }

    const { jsPDF } = window.jspdf;

    // Show loading message
    const exportBtn = document.getElementById('exportBtn');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<div class="loading" style="width:16px;height:16px"></div><span>Generating PDF...</span>';
    exportBtn.disabled = true;

    try {
        const dashboard = document.querySelector('main');
        const canvas = await html2canvas(dashboard, {
            scale: 2,
            backgroundColor: '#ffffff',
            logging: false
        });

        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        const imgWidth = 210; // A4 width
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        const pageHeight = 297; // A4 height

        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        pdf.save(`circulation-dashboard-${dashboardData.week.label.replace(/\s/g, '-')}.pdf`);
    } catch (error) {
        console.error('PDF generation failed:', error);
        alert('Failed to generate PDF. Please try again.');
    } finally {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    }
}

/**
 * Toggle export menu
 */
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    menu.classList.toggle('hidden');
}

// Close export menu when clicking outside
document.addEventListener('click', function(e) {
    const exportBtn = document.getElementById('exportBtn');
    const exportMenu = document.getElementById('exportMenu');
    if (exportBtn && exportMenu && !exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
        exportMenu.classList.add('hidden');
    }
});

// ========================================
// PHASE 2: Analytics Functions
// ========================================

/**
 * Render analytics insights section
 */
function renderAnalytics() {
    const analytics = dashboardData.analytics;
    if (!analytics) return;

    const container = document.getElementById('analyticsInsights');
    if (!container) return;

    let html = '';

    // Forecast Card
    if (analytics.forecast) {
        const f = analytics.forecast;
        const confidenceColors = {
            'high': 'bg-green-50 text-green-900 border-green-200',
            'medium': 'bg-blue-50 text-blue-900 border-blue-200',
            'low': 'bg-amber-50 text-amber-900 border-amber-200'
        };
        const confidenceColor = confidenceColors[f.confidence] || confidenceColors.medium;

        html += `
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow p-6 border border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-blue-900">Next Week Forecast</div>
                    <div class="text-2xl">🔮</div>
                </div>
                <div class="text-3xl font-bold text-blue-900">${formatNumber(f.value)}</div>
                <div class="text-sm text-blue-700 mt-2">
                    <span class="${f.change >= 0 ? 'text-green-700' : 'text-red-700'} font-medium">
                        ${formatChange(f.change)} (${f.change_percent > 0 ? '+' : ''}${f.change_percent}%)
                    </span>
                </div>
                <div class="mt-3">
                    <span class="inline-block px-2 py-1 rounded text-xs font-medium ${confidenceColor} border">
                        ${f.confidence.toUpperCase()} confidence
                    </span>
                </div>
            </div>
        `;
    }

    // Strongest Growth Card
    if (analytics.performers?.strongest) {
        const p = analytics.performers.strongest;
        html += `
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow p-6 border border-green-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-green-900">Strongest Growth</div>
                    <div class="text-2xl">🚀</div>
                </div>
                <div class="text-2xl font-bold text-green-900">${p.unit}</div>
                <div class="text-sm text-green-700 mt-2 font-medium">
                    ${formatChange(p.change)} (${p.change_percent > 0 ? '+' : ''}${p.change_percent}%)
                </div>
                <div class="text-xs text-green-600 mt-1">vs last year</div>
            </div>
        `;
    }

    // Anomaly Alert Card
    if (analytics.anomalies) {
        const anomalyCount = analytics.anomalies.length;
        const latestAnomaly = analytics.anomalies[analytics.anomalies.length - 1];

        html += `
            <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow p-6 border border-amber-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-sm font-medium text-amber-900">Unusual Activity</div>
                    <div class="text-2xl">⚠️</div>
                </div>
                <div class="text-3xl font-bold text-amber-900">${anomalyCount}</div>
                <div class="text-sm text-amber-700 mt-2">
                    ${anomalyCount === 0 ? 'No anomalies detected' :
                      anomalyCount === 1 ? 'anomaly in last 12 weeks' :
                      'anomalies in last 12 weeks'}
                </div>
                ${latestAnomaly ? `
                    <div class="text-xs text-amber-600 mt-1">
                        Last: ${new Date(latestAnomaly.date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})}
                        (${latestAnomaly.severity} severity)
                    </div>
                ` : ''}
            </div>
        `;
    }

    container.innerHTML = html;
}

// ========================================
// PHASE 2: Business Unit Drill-Down
// ========================================

/**
 * Toggle business unit detail expansion
 */
async function toggleBusinessUnitDetails(unitName) {
    const detailsId = `details-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    const details = document.getElementById(detailsId);
    const expandBtn = document.getElementById(`expand-${unitName.replace(/\s+/g, '-').toLowerCase()}`);

    if (!details || !expandBtn) return;

    if (details.classList.contains('hidden')) {
        // Expand
        expandBtn.innerHTML = '<span class="loading" style="width:12px;height:12px"></span> Loading...';

        try {
            // Load detailed data from API
            const response = await fetch(`${API_BASE}?action=business_unit_detail&unit=${encodeURIComponent(unitName)}&date=${currentDate || ''}`);
            const result = await response.json();

            if (result.success) {
                renderBusinessUnitDetail(unitName, result.data);
                details.classList.remove('hidden');
                expandBtn.textContent = '▲ Click to collapse';
            } else {
                expandBtn.textContent = '▼ Click for detailed breakdown';
                alert('Failed to load details: ' + (result.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading business unit details:', error);
            expandBtn.textContent = '▼ Click for detailed breakdown';
            alert('Failed to load business unit details. Please try again.');
        }
    } else {
        // Collapse
        details.classList.add('hidden');
        expandBtn.textContent = '▼ Click for detailed breakdown';
    }
}

/**
 * Render business unit detail section
 */
function renderBusinessUnitDetail(unitName, data) {
    const detailsId = `details-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    const container = document.getElementById(detailsId);
    if (!container) return;

    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';

    // Left: Publication breakdown
    html += '<div><h4 class="text-sm font-semibold text-gray-700 mb-3">📰 Publications</h4>';
    html += '<div class="space-y-2">';

    for (const [paperCode, paperData] of Object.entries(data.paper_details)) {
        const mailPercent = paperData.total > 0 ? (paperData.mail / paperData.total * 100).toFixed(1) : 0;
        html += `
            <div class="bg-white p-3 rounded-lg border border-gray-200 hover:border-blue-300 transition">
                <div class="flex justify-between items-start mb-2">
                    <div class="font-medium text-gray-900">${paperData.name}</div>
                    <div class="text-lg font-bold text-gray-700">${formatNumber(paperData.total)}</div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-xs text-gray-600">
                    <div>📮 ${formatNumber(paperData.mail)} (${mailPercent}%)</div>
                    <div>💻 ${formatNumber(paperData.digital)}</div>
                    <div>🚗 ${formatNumber(paperData.carrier)}</div>
                </div>
            </div>
        `;
    }

    html += '</div></div>';

    // Right: Trend chart for this unit
    const trendId = `trend-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    html += `
        <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-3">📊 12-Week Trend</h4>
            <div style="position: relative; height: 200px;">
                <canvas id="${trendId}"></canvas>
            </div>
        </div>
    `;

    html += '</div>';

    // Delivery method breakdown chart
    const deliveryTrendId = `delivery-trend-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
    html += `
        <div class="mt-6 pt-6 border-t border-gray-200">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">📦 Delivery Method Trends</h4>
            <div style="position: relative; height: 150px;">
                <canvas id="${deliveryTrendId}"></canvas>
            </div>
        </div>
    `;

    container.innerHTML = html;

    // Render charts
    setTimeout(() => {
        renderUnitTrendChart(trendId, data.trend);
        renderUnitDeliveryTrend(deliveryTrendId, data.trend);
    }, 100);
}

/**
 * Render unit-specific trend chart
 */
function renderUnitTrendChart(canvasId, trend) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const labels = trend.map(d => {
        const date = new Date(d.snapshot_date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Active',
                data: trend.map(d => d.total_active),
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Render unit delivery method trend
 */
function renderUnitDeliveryTrend(canvasId, trend) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    const labels = trend.map(d => {
        const date = new Date(d.snapshot_date);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Mail',
                    data: trend.map(d => d.mail),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Digital',
                    data: trend.map(d => d.digital),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Carrier',
                    data: trend.map(d => d.carrier),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 10 }
                }
            },
            scales: {
                y: {
                    stacked: false,
                    ticks: {
                        callback: function(value) {
                            return formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

// ========================================
// PHASE 2: Enhanced Business Unit Rendering with Comparisons
// ========================================

/**
 * Render comparison badge with trend indicator
 */
function renderComparisonWithTrend(comparison, trendDirection) {
    if (!comparison) {
        return '<span class="text-xs text-gray-400">No comparison data</span>';
    }

    const trendIcons = {
        'growing': '↗️',
        'declining': '↘️',
        'stable': '→'
    };
    const trendColors = {
        'growing': 'text-green-600',
        'declining': 'text-red-600',
        'stable': 'text-gray-600'
    };

    const icon = trendIcons[trendDirection] || '→';
    const color = trendColors[trendDirection] || 'text-gray-600';
    const changeColor = comparison.change >= 0 ? 'text-green-600' : 'text-red-600';

    return `
        <div class="flex items-center justify-between text-xs">
            <span class="text-gray-600">vs Last Year:</span>
            <span class="${changeColor} font-medium">
                ${formatChange(comparison.change)} (${comparison.change_percent > 0 ? '+' : ''}${comparison.change_percent}%)
            </span>
        </div>
        <div class="flex items-center justify-between mt-1 pt-1 border-t border-gray-100 text-xs">
            <span class="text-gray-600">Trend:</span>
            <span class="${color} font-medium">${icon} ${capitalizeFirst(trendDirection)}</span>
        </div>
    `;
}

/**
 * Capitalize first letter
 */
function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Override renderBusinessUnits to include comparisons
const originalRenderBusinessUnits = window.renderBusinessUnits;
window.renderBusinessUnits = function() {
    const container = document.getElementById('businessUnits');
    const byUnit = dashboardData.by_business_unit;
    const comparisons = dashboardData.business_unit_comparisons || {};

    if (!byUnit) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8">No business unit data available</div>';
        return;
    }

    // Destroy existing business unit charts
    Object.values(businessUnitCharts).forEach(chart => chart.destroy());
    businessUnitCharts = {};

    let html = '';

    for (const [unitName, config] of Object.entries(BUSINESS_UNITS)) {
        const data = byUnit[unitName];
        if (!data) continue;

        const percentage = (data.total / dashboardData.current.total_active * 100).toFixed(1);
        const vacPercent = (data.on_vacation / data.total * 100).toFixed(2);
        const chartId = `chart-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
        const comparison = comparisons[unitName];

        const snapshotDate = dashboardData.current.snapshot_date;
        html += `
            <div class="paper-card bg-white rounded-xl shadow-sm p-6 border-l-4" style="border-left-color: ${config.color}" onclick="openDetailPanel('${unitName}', '${snapshotDate}')">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-1">
                            <span class="text-2xl">${config.icon}</span>
                            <h3 class="text-xl font-bold text-gray-900">${unitName}</h3>
                        </div>
                        <div class="text-sm text-gray-500">${config.papers.join(', ')}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-gray-900">${formatNumber(data.total)}</div>
                        <div class="text-sm text-gray-500">${percentage}% of total</div>
                        ${comparison && (compareMode === 'yoy' ? comparison.yoy : comparison.previous_week) && (compareMode === 'yoy' ? comparison.yoy : comparison.previous_week).change !== undefined ? `<div class="mt-1">${renderComparisonBadge((compareMode === 'yoy' ? comparison.yoy : comparison.previous_week).change, (compareMode === 'yoy' ? comparison.yoy : comparison.previous_week).change_percent, 'vs comparison')}</div>` : ''}
                    </div>
                </div>

                <div class="mb-4">
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="progress-bar h-2 rounded-full" style="width: ${percentage}%; background-color: ${config.color}"></div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-start justify-between">
                        <!-- Chart with center text -->
                        <div class="flex flex-col items-center">
                            <div class="relative" style="width: 160px; height: 160px;">
                                <canvas id="${chartId}"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <div class="text-2xl font-bold text-gray-900">${formatNumber(data.deliverable)}</div>
                                    <div class="text-xs text-gray-500">Deliverable</div>
                                    <div class="text-xs text-gray-400">of ${formatNumber(data.total)}</div>
                                </div>
                            </div>
                            <!-- Vacation info below chart -->
                            <div class="mt-3 text-center">
                                <div class="text-sm text-gray-600">🏖️ On Vacation</div>
                                <div class="text-lg font-semibold text-gray-900">${formatNumber(data.on_vacation)} <span class="text-sm text-gray-500">(${vacPercent}%)</span></div>
                            </div>
                        </div>

                        <!-- Legend and Comparisons -->
                        <div class="flex-1 ml-6 space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                                    <span class="text-gray-600">Mail</span>
                                </div>
                                <span class="font-medium text-gray-900">${formatNumber(data.mail)} <span class="text-xs text-gray-500">(${(data.mail/data.total*100).toFixed(1)}%)</span></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>
                                    <span class="text-gray-600">Digital</span>
                                </div>
                                <span class="font-medium text-gray-900">${formatNumber(data.digital)} <span class="text-xs text-gray-500">(${(data.digital/data.total*100).toFixed(1)}%)</span></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-amber-500"></span>
                                    <span class="text-gray-600">Carrier</span>
                                </div>
                                <span class="font-medium text-gray-900">${formatNumber(data.carrier)} <span class="text-xs text-gray-500">(${(data.carrier/data.total*100).toFixed(1)}%)</span></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        `;
    }

    container.innerHTML = html;

    // Create mini charts after DOM is updated
    for (const [unitName, config] of Object.entries(BUSINESS_UNITS)) {
        const data = byUnit[unitName];
        if (!data) continue;

        const chartId = `chart-${unitName.replace(/\s+/g, '-').toLowerCase()}`;
        const ctx = document.getElementById(chartId);
        if (!ctx) continue;

        businessUnitCharts[unitName] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Mail', 'Digital', 'Carrier'],
                datasets: [{
                    data: [data.mail, data.digital, data.carrier],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${formatNumber(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
};

// Override renderDashboard to include analytics
const originalRenderDashboard = window.renderDashboard;
window.renderDashboard = function() {
    if (!dashboardData) return;

    // Check if this week has data (handle empty state)
    if (!dashboardData.has_data || dashboardData.has_data === false) {
        // Call original to handle empty state
        if (typeof originalRenderDashboard === 'function') {
            originalRenderDashboard();
        }
        return;
    }

    // Has data - render normally
    renderPeriodDisplay();
    renderKeyMetrics();
    renderBusinessUnits();
    renderPaperCards();
    renderTrendChart();
    renderDeliveryChart();
    renderAnalytics();  // PHASE 2: Add analytics
};

console.log('Phase 2 enhancements loaded successfully!');
