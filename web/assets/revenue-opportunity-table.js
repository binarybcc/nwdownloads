/**
 * Revenue Opportunity Table Component
 * Displays per-publication revenue breakdown with market vs legacy rates
 *
 * Features:
 * - Table format with sortable columns
 * - Inline stacked bar charts
 * - Click row to open detail slider
 * - Responsive mobile/tablet/desktop views
 */

class RevenueOpportunityTable {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        console.log('RevenueOpportunityTable container:', this.container);
        if (!this.container) {
            console.error('Container not found:', containerId);
        }
        this.data = null;
    }

    /**
     * Render table with per-publication breakdown
     * @param {Object} data - Revenue opportunity data
     */
    render(data) {
        console.log('render() called, container exists:', !!this.container);
        this.data = data;
        const html = this.buildTableHTML();
        console.log('HTML built, length:', html.length);
        if (this.container) {
            this.container.innerHTML = html;
            console.log('innerHTML set successfully');
        } else {
            console.error('Cannot render: container is null');
        }
    }

    /**
     * Build table HTML
     * @returns {string} HTML string
     */
    buildTableHTML() {
        // Sort by opportunity size (largest first)
        const sorted = this.sortByOpportunity(this.data.by_publication);

        // Build rows
        const rows = sorted.map(pub => this.buildRowHTML(pub)).join('');

        // Build total row
        const totalRow = this.buildTotalRowHTML(this.data.totals);

        return `
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="mr-2">💎</span>
                    <span>Revenue Opportunity by Publication</span>
                </h3>

                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-300 text-xs">
                                <th class="text-left py-3 px-4 font-semibold text-gray-700">Publication</th>
                                <th class="text-right py-3 px-4 font-semibold text-gray-700">Current<br/>Total MRR</th>
                                <th class="text-right py-3 px-4 font-semibold text-gray-700">Legacy Subs<br/>(Count)</th>
                                <th class="text-right py-3 px-4 font-semibold text-gray-700">Legacy<br/>Current MRR</th>
                                <th class="text-right py-3 px-4 font-semibold text-gray-700">If Converted<br/>to Market</th>
                                <th class="text-right py-3 px-4 font-semibold text-gray-700">Opportunity</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                        <tfoot>
                            ${totalRow}
                        </tfoot>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden space-y-4">
                    ${sorted.map(pub => this.buildMobileCardHTML(pub)).join('')}
                    ${this.buildMobileTotalCardHTML(this.data.totals)}
                </div>
            </div>
        `;
    }

    /**
     * Build individual row HTML (desktop)
     * @param {Object} pub - Publication data
     * @returns {string} Row HTML
     */
    buildRowHTML(pub) {
        const currentTotalMRR = pub.current_total_mrr;
        const legacyCount = pub.legacy_subscribers;
        const legacyCurrentMRR = pub.current_legacy_mrr;
        const ifConvertedMRR = pub.if_converted_mrr;
        const opportunityMRR = pub.opportunity_mrr;

        return `
            <tr class="border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition"
                data-paper="${pub.paper_code}"
                onclick="openPublicationDetail('${pub.paper_code}')">
                <td class="py-4 px-4">
                    <div class="font-semibold text-gray-900">${pub.paper_name}</div>
                    <div class="text-xs text-gray-500">${pub.business_unit} • ${pub.total_subscribers} subs</div>
                </td>
                <td class="py-4 px-4 text-right font-medium text-gray-900">
                    ${this.formatCurrency(currentTotalMRR)}
                </td>
                <td class="py-4 px-4 text-right font-medium text-gray-600">
                    ${legacyCount}
                </td>
                <td class="py-4 px-4 text-right font-medium text-amber-700">
                    ${this.formatCurrency(legacyCurrentMRR)}
                </td>
                <td class="py-4 px-4 text-right font-medium text-green-700">
                    ${this.formatCurrency(ifConvertedMRR)}
                </td>
                <td class="py-4 px-4 text-right font-bold text-blue-700">
                    +${this.formatCurrency(opportunityMRR)}
                </td>
            </tr>
        `;
    }

    /**
     * Build mobile card HTML
     * @param {Object} pub - Publication data
     * @returns {string} Card HTML
     */
    buildMobileCardHTML(pub) {
        return `
            <div class="bg-white rounded-lg shadow p-4 cursor-pointer"
                 onclick="openPublicationDetail('${pub.paper_code}')">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-bold text-gray-900">${pub.paper_name}</div>
                    <div class="text-sm text-gray-500">${pub.business_unit}</div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Current Total MRR:</span>
                        <span class="font-semibold text-gray-900">${this.formatCurrency(pub.current_total_mrr)}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Legacy Subscribers:</span>
                        <span class="font-semibold text-gray-600">${pub.legacy_subscribers}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Legacy Current MRR:</span>
                        <span class="font-semibold text-amber-700">${this.formatCurrency(pub.current_legacy_mrr)}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">If Converted:</span>
                        <span class="font-semibold text-green-700">${this.formatCurrency(pub.if_converted_mrr)}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Opportunity:</span>
                        <span class="font-bold text-blue-700">+${this.formatCurrency(pub.opportunity_mrr)}</span>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Build stacked horizontal bar chart
     * @param {number} marketPercent - Market rate percentage
     * @param {number} legacyPercent - Legacy rate percentage
     * @returns {string} Bar HTML
     */
    buildStackedBar(marketPercent, legacyPercent) {
        return `
            <div class="flex items-center gap-2">
                <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden flex">
                    <div class="bg-green-500 h-full transition-all duration-300"
                         style="width: ${marketPercent}%"
                         title="Market Rate: ${marketPercent}%"></div>
                    <div class="bg-amber-500 h-full transition-all duration-300"
                         style="width: ${legacyPercent}%"
                         title="Legacy Rate: ${legacyPercent}%"></div>
                </div>
                <div class="text-xs text-gray-500 w-12 text-right">
                    ${legacyPercent}%
                </div>
            </div>
        `;
    }

    /**
     * Build total row HTML (desktop)
     * @param {Object} totals - Aggregate totals
     * @returns {string} Total row HTML
     */
    buildTotalRowHTML(totals) {
        return `
            <tr class="border-t-2 border-gray-300 bg-gray-50">
                <td class="py-4 px-4 font-bold text-gray-900" colspan="1">TOTAL</td>
                <td class="py-4 px-4 text-right font-bold text-gray-900">
                    ${this.formatCurrency(totals.total_current_mrr)}
                </td>
                <td class="py-4 px-4 text-right font-bold text-gray-600">
                    —
                </td>
                <td class="py-4 px-4 text-right font-bold text-amber-700">
                    ${this.formatCurrency(totals.total_legacy_current_mrr)}
                </td>
                <td class="py-4 px-4 text-right font-bold text-green-700">
                    ${this.formatCurrency(totals.total_if_converted_mrr)}
                </td>
                <td class="py-4 px-4 text-right font-bold text-blue-700">
                    +${this.formatCurrency(totals.total_opportunity_mrr)}
                </td>
            </tr>
        `;
    }

    /**
     * Build mobile total card HTML
     * @param {Object} totals - Aggregate totals
     * @returns {string} Card HTML
     */
    buildMobileTotalCardHTML(totals) {
        return `
            <div class="bg-gray-50 rounded-lg shadow p-4 border-2 border-gray-300">
                <div class="font-bold text-gray-900 mb-3">TOTAL</div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Current Total MRR:</span>
                        <span class="font-bold text-gray-900">${this.formatCurrency(totals.total_current_mrr)}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Legacy Current MRR:</span>
                        <span class="font-bold text-amber-700">${this.formatCurrency(totals.total_legacy_current_mrr)}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">If Converted:</span>
                        <span class="font-bold text-green-700">${this.formatCurrency(totals.total_if_converted_mrr)}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Opportunity:</span>
                        <span class="font-bold text-blue-700">+${this.formatCurrency(totals.total_opportunity_mrr)}</span>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Sort publications by opportunity size (descending)
     * @param {Array} publications - Array of publication objects
     * @returns {Array} Sorted array
     */
    sortByOpportunity(publications) {
        return publications.sort((a, b) => {
            return b.opportunity_mrr - a.opportunity_mrr; // Descending
        });
    }

    /**
     * Format currency
     * @param {number} amount - Dollar amount
     * @returns {string} Formatted string
     */
    formatCurrency(amount) {
        return '$' + Math.round(amount).toLocaleString('en-US');
    }
}

// Global function to open publication detail
// Implementation in publication-revenue-detail.js
