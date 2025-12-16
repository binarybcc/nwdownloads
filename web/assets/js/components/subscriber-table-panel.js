/**
 * Subscriber Table Panel
 * Slide-out panel with distinct color scheme for viewing subscriber lists
 * Includes export functionality (Excel/CSV)
 * Date: 2025-12-05
 */

/**
 * LOAD ORDER: 9 of 11
 *
 * DEPENDENCIES:
 * - app.js: API_BASE, formatNumber
 * - detail_panel.js: currentBusinessUnit, currentSnapshotDate
 *
 * PROVIDES:
 * - Subscriber table rendering and pagination
 */

class SubscriberTablePanel {
    constructor(options = {}) {
        this.colorScheme = options.colorScheme || 'teal';
        this.onClose = options.onClose || (() => {});
        this.panel = null;
        this.backdrop = null;
        this.data = null;
        this.isOpen = false;

        // Bind methods
        this.close = this.close.bind(this);
        this.handleEscape = this.handleEscape.bind(this);
        this.handleExportExcel = this.handleExportExcel.bind(this);
        this.handleExportCSV = this.handleExportCSV.bind(this);
    }

    /**
     * Show panel with subscriber data
     */
    show(data) {
        this.data = data;

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
     */
    render() {
        // Create backdrop
        this.backdrop = document.createElement('div');
        this.backdrop.id = 'subscriberTableBackdrop';
        this.backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 9998;
            opacity: 0;
            transition: opacity 300ms cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(2px);
        `;
        this.backdrop.addEventListener('click', this.close);

        // Create panel
        this.panel = document.createElement('div');
        this.panel.id = 'subscriberTablePanel';
        this.panel.style.cssText = `
            position: fixed;
            top: 0;
            right: -75%;
            width: 75%;
            height: 100vh;
            background: #F0FDFA;
            box-shadow: -8px 0 32px rgba(0,0,0,0.15);
            z-index: 9999;
            overflow-y: auto;
            transition: right 350ms cubic-bezier(0.4, 0, 0.2, 1);
        `;

        // Build panel content
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
        document.getElementById('closeSubscriberTable').addEventListener('click', this.close);
        document.getElementById('exportExcelBtn').addEventListener('click', this.handleExportExcel);
        document.getElementById('exportCSVBtn').addEventListener('click', this.handleExportCSV);

        this.isOpen = true;
    }

    /**
     * Build panel HTML
     */
    buildPanelHTML() {
        const { title, subtitle, data } = this.data;
        // data can be either an array directly or an object with subscribers property
        const subscribers = Array.isArray(data) ? data : (data?.subscribers || []);
        const count = subscribers.length;

        return `
            <!-- Header -->
            <div style="
                background: linear-gradient(135deg, #14B8A6 0%, #0891B2 100%);
                color: white;
                padding: 2rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            ">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div style="flex: 1;">
                        <h2 style="font-size: 1.75rem; font-weight: 700; margin: 0 0 0.5rem 0;">
                            ${title || 'Subscriber List'}
                        </h2>
                        <p style="font-size: 0.95rem; margin: 0; opacity: 0.95;">
                            ${subtitle || ''}
                        </p>
                    </div>
                    <button id="closeSubscriberTable"
                            style="
                                background: rgba(255,255,255,0.2);
                                border: none;
                                color: white;
                                width: 40px;
                                height: 40px;
                                border-radius: 50%;
                                cursor: pointer;
                                transition: background 200ms;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 24px;
                                line-height: 1;
                                flex-shrink: 0;
                            "
                            onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                            onmouseout="this.style.background='rgba(255,255,255,0.2)'"
                            title="Close (ESC)">
                        ×
                    </button>
                </div>

                <!-- Export Buttons -->
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button id="exportExcelBtn"
                            style="
                                background: white;
                                color: #0891B2;
                                border: none;
                                padding: 0.625rem 1.25rem;
                                border-radius: 8px;
                                cursor: pointer;
                                font-weight: 600;
                                font-size: 0.9rem;
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                                transition: all 200ms;
                                box-shadow: 0 2px 6px rgba(0,0,0,0.1);
                            "
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 6px rgba(0,0,0,0.1)'">
                        <span style="font-size: 16px;">📗</span>
                        <span>Export to Excel</span>
                    </button>

                    <button id="exportCSVBtn"
                            style="
                                background: rgba(255,255,255,0.15);
                                color: white;
                                border: 2px solid white;
                                padding: 0.625rem 1.25rem;
                                border-radius: 8px;
                                cursor: pointer;
                                font-weight: 600;
                                font-size: 0.9rem;
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                                transition: all 200ms;
                            "
                            onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                            onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                        <span style="font-size: 16px;">📊</span>
                        <span>Export to CSV</span>
                    </button>

                    <div style="
                        margin-left: auto;
                        background: rgba(255,255,255,0.2);
                        padding: 0.625rem 1rem;
                        border-radius: 8px;
                        font-weight: 600;
                        font-size: 0.9rem;
                    ">
                        <span style="opacity: 0.9;">Total:</span> ${count.toLocaleString()} subscribers
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div style="padding: 2rem;">
                ${count > 0 ? this.buildTableHTML(subscribers) : this.buildEmptyStateHTML()}
            </div>
        `;
    }

    /**
     * Build subscriber table HTML
     */
    buildTableHTML(subscribers) {
        const headers = [
            'Account ID',
            'Subscriber Name',
            'Phone',
            'Email',
            'Mailing Address',
            'Paper',
            'Current Rate',
            'Rate Amount',
            'Last Payment',
            'Expiration Date',
            'Delivery Type'
        ];

        let tableHTML = `
            <div style="
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                overflow-x: auto;
                overflow-y: visible;
            ">
                <table style="
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 0.75rem;
                    table-layout: auto;
                ">
                    <thead>
                        <tr style="background: #14B8A6; color: white;">
                            ${headers.map(h => `
                                <th style="
                                    padding: 0.5rem 0.5rem;
                                    text-align: left;
                                    font-weight: 600;
                                    white-space: nowrap;
                                    border-right: 1px solid rgba(255,255,255,0.2);
                                    position: sticky;
                                    top: 0;
                                    z-index: 10;
                                    background: #14B8A6;
                                ">
                                    ${h}
                                </th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
        `;

        subscribers.forEach((sub, index) => {
            const isAlternate = index % 2 === 1;
            const bgColor = isAlternate ? '#F0FDFA' : 'white';

            tableHTML += `
                <tr style="background: ${bgColor}; transition: background 150ms;"
                    onmouseover="this.style.background='#CCFBF1'"
                    onmouseout="this.style.background='${bgColor}'">
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; font-family: monospace; font-weight: 600; color: #0891B2; white-space: nowrap;">${sub.account_id}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; font-weight: 500; white-space: nowrap;">${sub.subscriber_name}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; font-family: monospace; white-space: nowrap;">${sub.phone}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; color: #0891B2; white-space: nowrap;"><a href="mailto:${sub.email}" style="color: inherit; text-decoration: none;">${sub.email}</a></td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; white-space: nowrap; max-width: 250px; overflow: hidden; text-overflow: ellipsis;">${sub.mailing_address}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; font-weight: 600; white-space: nowrap;">${sub.paper_code}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; white-space: nowrap;">${sub.current_rate}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; font-weight: 600; color: #059669; white-space: nowrap;">$${sub.rate_amount}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; white-space: nowrap;">$${sub.last_payment_amount}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; font-family: monospace; white-space: nowrap;">${sub.expiration_date}</td>
                    <td style="padding: 0.35rem 0.5rem; border-bottom: 1px solid #E5E7EB; white-space: nowrap;">
                        <span style="
                            display: inline-block;
                            padding: 0.15rem 0.5rem;
                            border-radius: 9999px;
                            font-size: 0.7rem;
                            font-weight: 600;
                            background: ${this.getDeliveryTypeColor(sub.delivery_type).bg};
                            color: ${this.getDeliveryTypeColor(sub.delivery_type).text};
                        ">
                            ${sub.delivery_type}
                        </span>
                    </td>
                </tr>
            `;
        });

        tableHTML += `
                    </tbody>
                </table>
            </div>
        `;

        return tableHTML;
    }

    /**
     * Get delivery type badge colors
     */
    getDeliveryTypeColor(type) {
        const colors = {
            'MAIL': { bg: '#DBEAFE', text: '#1E40AF' },
            'CARR': { bg: '#D1FAE5', text: '#065F46' },
            'INTE': { bg: '#FEF3C7', text: '#92400E' }
        };
        return colors[type] || { bg: '#F3F4F6', text: '#374151' };
    }

    /**
     * Build empty state HTML
     */
    buildEmptyStateHTML() {
        return `
            <div style="
                text-align: center;
                padding: 4rem 2rem;
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            ">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">📋</div>
                <h3 style="font-size: 1.25rem; font-weight: 600; color: #1F2937; margin: 0 0 0.5rem 0;">
                    No Subscribers Found
                </h3>
                <p style="color: #6B7280; margin: 0;">
                    There are no subscribers matching this criteria.
                </p>
            </div>
        `;
    }

    /**
     * Handle Excel export
     */
    handleExportExcel() {
        if (typeof exportSubscriberList !== 'undefined') {
            // Use exportData if available (includes metadata), otherwise try to construct from data
            const exportPayload = this.data.exportData || this.data.data;
            exportSubscriberList(exportPayload, 'excel');
        } else {
            console.error('Export function not available');
            alert('Export functionality not loaded. Please refresh the page.');
        }
    }

    /**
     * Handle CSV export
     */
    handleExportCSV() {
        if (typeof exportSubscriberList !== 'undefined') {
            // Use exportData if available (includes metadata), otherwise try to construct from data
            const exportPayload = this.data.exportData || this.data.data;
            exportSubscriberList(exportPayload, 'csv');
        } else {
            console.error('Export function not available');
            alert('Export functionality not loaded. Please refresh the page.');
        }
    }

    /**
     * Handle ESC key
     */
    handleEscape(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            this.close();
        }
    }

    /**
     * Close panel
     */
    close() {
        if (!this.isOpen) return;

        // Animate out
        if (this.backdrop) {
            this.backdrop.style.opacity = '0';
        }
        if (this.panel) {
            this.panel.style.right = '-75%';
        }

        // Remove after animation
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

        // Call onClose callback
        this.onClose();
    }

    /**
     * Destroy panel
     */
    destroy() {
        this.close();
        this.data = null;
        this.onClose = null;
    }
}

// Export globally
window.SubscriberTablePanel = SubscriberTablePanel;

// console.log('SubscriberTablePanel module loaded');
