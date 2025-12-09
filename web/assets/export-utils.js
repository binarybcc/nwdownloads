/**
 * Export Utilities - Excel and CSV Export Functions
 * Professional formatted exports for subscriber data
 * Date: 2025-12-05
 */

/**
 * LOAD ORDER: 8 of 11
 *
 * DEPENDENCIES:
 * - app.js: dashboardData, formatNumber
 * - XLSX (CDN): Excel export
 * - jsPDF, html2canvas (CDN): PDF export
 *
 * PROVIDES:
 * - Export utility functions
 */

/**
 * Export data to formatted Excel file
 * Includes styling, frozen headers, auto-filters, and alternating row colors
 *
 * @param {Array} data - Array of objects to export
 * @param {string} filename - Output filename (without extension)
 * @param {object} options - Export options
 */
function exportToExcel(data, filename, options = {}) {
    if (!data || data.length === 0) {
        alert('No data to export');
        return;
    }

    // Check if SheetJS is loaded
    if (typeof XLSX === 'undefined') {
        console.error('SheetJS library not loaded');
        alert('Excel export library not available. Please refresh the page.');
        return;
    }

    try {
        // Create worksheet from data
        const ws = XLSX.utils.json_to_sheet(data);

        // Get range for styling
        const range = XLSX.utils.decode_range(ws['!ref']);

        // Apply header styling
        for (let col = range.s.c; col <= range.e.c; col++) {
            const cellRef = XLSX.utils.encode_cell({ r: 0, c: col });
            if (!ws[cellRef]) continue;

            ws[cellRef].s = {
                fill: { fgColor: { rgb: "0891B2" } },
                font: { bold: true, color: { rgb: "FFFFFF" }, sz: 12 },
                alignment: { horizontal: "center", vertical: "center" }
            };
        }

        // Apply alternating row colors
        for (let row = range.s.r + 1; row <= range.e.r; row++) {
            const isAlternate = row % 2 === 0;
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellRef = XLSX.utils.encode_cell({ r: row, c: col });
                if (!ws[cellRef]) continue;

                ws[cellRef].s = {
                    ...(ws[cellRef].s || {}),
                    fill: isAlternate ? { fgColor: { rgb: "F0FDFA" } } : {},
                    border: {
                        top: { style: "thin", color: { rgb: "E5E7EB" } },
                        bottom: { style: "thin", color: { rgb: "E5E7EB" } },
                        left: { style: "thin", color: { rgb: "E5E7EB" } },
                        right: { style: "thin", color: { rgb: "E5E7EB" } }
                    }
                };
            }
        }

        // Calculate column widths
        const colWidths = calculateColumnWidths(data);
        ws['!cols'] = colWidths;

        // Freeze header row
        ws['!freeze'] = { xSplit: 0, ySplit: 1, topLeftCell: 'A2', activePane: 'bottomLeft', state: 'frozen' };

        // Add auto-filter
        ws['!autofilter'] = { ref: XLSX.utils.encode_range(range) };

        // Create workbook
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, options.sheetName || "Subscribers");

        // Generate filename with timestamp
        const timestamp = formatDateForFilename(new Date());
        const finalFilename = `${filename}_${timestamp}.xlsx`;

        // Write file
        XLSX.writeFile(wb, finalFilename);

        console.log(`✅ Excel export successful: ${finalFilename}`);
    } catch (error) {
        console.error('Excel export error:', error);
        alert('Failed to export to Excel. Please try again.');
    }
}

/**
 * Export data to CSV file
 * Simple, clean CSV for maximum compatibility
 *
 * @param {Array} data - Array of objects to export
 * @param {string} filename - Output filename (without extension)
 */
function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        alert('No data to export');
        return;
    }

    try {
        const headers = Object.keys(data[0]);
        const csvRows = [];

        // Header row
        csvRows.push(headers.map(h => `"${h}"`).join(','));

        // Data rows
        for (const row of data) {
            const values = headers.map(header => {
                const val = row[header] ?? '';
                // Escape double quotes and wrap in quotes
                return `"${String(val).replace(/"/g, '""')}"`;
            });
            csvRows.push(values.join(','));
        }

        // Add BOM for Excel compatibility (UTF-8)
        const csvString = '\uFEFF' + csvRows.join('\n');

        // Create blob
        const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });

        // Generate filename with timestamp
        const timestamp = formatDateForFilename(new Date());
        const finalFilename = `${filename}_${timestamp}.csv`;

        // Download
        downloadBlob(blob, finalFilename);

        console.log(`✅ CSV export successful: ${finalFilename}`);
    } catch (error) {
        console.error('CSV export error:', error);
        alert('Failed to export to CSV. Please try again.');
    }
}

/**
 * Calculate optimal column widths for Excel
 * Based on content length
 */
function calculateColumnWidths(data) {
    if (!data || data.length === 0) return [];

    const headers = Object.keys(data[0]);
    const widths = headers.map(header => {
        // Start with header length
        let maxLen = header.length;

        // Check data for maximum length
        for (const row of data.slice(0, 100)) { // Sample first 100 rows for performance
            const val = String(row[header] || '');
            maxLen = Math.max(maxLen, val.length);
        }

        // Add padding and constrain to reasonable range
        const width = Math.min(Math.max(maxLen + 2, 12), 50);

        return { wch: width };
    });

    return widths;
}

/**
 * Format date for filename
 * Returns: YYYYMMDD_HHMMSS
 */
function formatDateForFilename(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');

    return `${year}${month}${day}_${hours}${minutes}${seconds}`;
}

/**
 * Download blob as file
 */
function downloadBlob(blob, filename) {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.style.display = 'none';

    document.body.appendChild(link);
    link.click();

    // Cleanup
    setTimeout(() => {
        URL.revokeObjectURL(link.href);
        document.body.removeChild(link);
    }, 100);
}

/**
 * Format subscriber data for export
 * Ensures consistent column order and formatting
 */
function formatSubscriberDataForExport(subscribers) {
    return subscribers.map(sub => ({
        'Account ID': sub.account_id,
        'Subscriber Name': sub.subscriber_name,
        'Phone': sub.phone,
        'Email': sub.email,
        'Mailing Address': sub.mailing_address,
        'Paper': sub.paper_name,
        'Current Rate': sub.current_rate,
        'Rate Amount': `$${sub.rate_amount}`,
        'Last Payment': `$${sub.last_payment_amount}`,
        'Expiration Date': sub.expiration_date,
        'Delivery Type': sub.delivery_type
    }));
}

/**
 * Export subscriber list with proper formatting
 * High-level wrapper for common use case
 */
function exportSubscriberList(subscriberData, exportType = 'excel') {
    const { business_unit, metric, count, snapshot_date, subscribers } = subscriberData;

    // Format filename
    const filename = `${business_unit}_${metric}_${snapshot_date}`.replace(/[^a-z0-9_-]/gi, '_');

    // Format data for export
    const formattedData = formatSubscriberDataForExport(subscribers);

    // Export based on type
    if (exportType === 'excel') {
        exportToExcel(formattedData, filename, {
            sheetName: `${metric} (${count})`
        });
    } else if (exportType === 'csv') {
        exportToCSV(formattedData, filename);
    } else {
        console.error('Invalid export type:', exportType);
    }
}

// Export functions globally
window.exportToExcel = exportToExcel;
window.exportToCSV = exportToCSV;
window.exportSubscriberList = exportSubscriberList;
window.formatSubscriberDataForExport = formatSubscriberDataForExport;

console.log('Export utilities loaded');
