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
 * Get XLSX fill color for call status
 * @param {object} sub - subscriber object with call_status field
 * @returns {object} SheetJS fill object with fgColor rgb
 */
function getExportStatusFill(sub) {
  if (!sub) return {};
  // Monthly subscriber with no call activity: no fill (plain white row)
  if (sub.is_monthly && !sub.call_status) return {};
  // Annual subscriber with no call activity: red urgency fill
  if (!sub.call_status) return { fgColor: { rgb: 'FEE2E2' } };
  if (sub.call_status === 'placed') return { fgColor: { rgb: 'DCFCE7' } }; // green - placed
  return { fgColor: { rgb: 'FEF9C3' } }; // yellow - received/missed
}

/**
 * Export data to formatted Excel file
 * Includes styling, frozen headers, auto-filters, and status-based row colors
 *
 * @param {Array} data - Array of objects to export
 * @param {string} filename - Output filename (without extension)
 * @param {object} options - Export options (sheetName, syncTimestamp, subscribers)
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

    const hasSyncTimestamp = options.syncTimestamp;
    const rawSubscribers = options.subscribers || [];
    let rowOffset = 0;

    if (hasSyncTimestamp) {
      // Shift all existing cells down by 1 row to make room for timestamp
      const origRange = XLSX.utils.decode_range(ws['!ref']);
      for (let R = origRange.e.r; R >= origRange.s.r; --R) {
        for (let C = origRange.s.c; C <= origRange.e.c; ++C) {
          const oldRef = XLSX.utils.encode_cell({ r: R, c: C });
          const newRef = XLSX.utils.encode_cell({ r: R + 1, c: C });
          if (ws[oldRef]) {
            ws[newRef] = ws[oldRef];
            delete ws[oldRef];
          }
        }
      }

      // Format: "Call data as of 2026-03-20 14:02"
      const syncDt = new Date(options.syncTimestamp);
      const pad = n => String(n).padStart(2, '0');
      const syncStr = `Call data as of ${syncDt.getFullYear()}-${pad(syncDt.getMonth() + 1)}-${pad(syncDt.getDate())} ${pad(syncDt.getHours())}:${pad(syncDt.getMinutes())}`;

      ws['A1'] = {
        v: syncStr,
        t: 's',
        s: {
          font: { italic: true, sz: 10, color: { rgb: '666666' } },
          alignment: { horizontal: 'left' },
        },
      };

      const lastCol = origRange.e.c;
      ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: lastCol } }];
      ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: origRange.e.r + 1, c: origRange.e.c },
      });
      rowOffset = 1;
    }

    const updatedRange = XLSX.utils.decode_range(ws['!ref']);

    // Apply header styling (with rowOffset)
    const headerRow = rowOffset;
    for (let col = updatedRange.s.c; col <= updatedRange.e.c; col++) {
      const cellRef = XLSX.utils.encode_cell({ r: headerRow, c: col });
      if (!ws[cellRef]) continue;

      ws[cellRef].s = {
        fill: { fgColor: { rgb: '0891B2' } },
        font: { bold: true, color: { rgb: 'FFFFFF' }, sz: 12 },
        alignment: { horizontal: 'center', vertical: 'center' },
      };
    }

    // Apply status-based row fills (replaces alternating teal when subscribers data available)
    const dataStartRow = rowOffset + 1;
    for (let row = dataStartRow; row <= updatedRange.e.r; row++) {
      const dataIndex = row - dataStartRow;
      const sub = rawSubscribers[dataIndex];
      const fill = sub && rawSubscribers.length > 0 ? getExportStatusFill(sub) : {};

      for (let col = updatedRange.s.c; col <= updatedRange.e.c; col++) {
        const cellRef = XLSX.utils.encode_cell({ r: row, c: col });
        if (!ws[cellRef]) continue;

        ws[cellRef].s = {
          ...(ws[cellRef].s || {}),
          fill: fill,
          border: {
            top: { style: 'thin', color: { rgb: 'E5E7EB' } },
            bottom: { style: 'thin', color: { rgb: 'E5E7EB' } },
            left: { style: 'thin', color: { rgb: 'E5E7EB' } },
            right: { style: 'thin', color: { rgb: 'E5E7EB' } },
          },
        };
      }
    }

    // Calculate column widths
    const colWidths = calculateColumnWidths(data);
    ws['!cols'] = colWidths;

    // Freeze header row (with rowOffset) — xlsx-js-style uses !views not !freeze
    if (!ws['!views']) ws['!views'] = [];
    ws['!views'][0] = {
      state: 'frozen',
      xSplit: 0,
      ySplit: rowOffset + 1,
    };

    // Add auto-filter (with rowOffset)
    ws['!autofilter'] = {
      ref: XLSX.utils.encode_range({
        s: { r: rowOffset, c: updatedRange.s.c },
        e: { r: updatedRange.e.r, c: updatedRange.e.c },
      }),
    };

    // Create workbook
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, options.sheetName || 'Subscribers');

    // Generate filename with timestamp
    const timestamp = formatDateForFilename(new Date());
    const finalFilename = `${filename}_${timestamp}.xlsx`;

    // Write file
    XLSX.writeFile(wb, finalFilename);

    console.log(`Excel export successful: ${finalFilename}`);
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

    console.log(`CSV export successful: ${finalFilename}`);
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
    for (const row of data.slice(0, 100)) {
      // Sample first 100 rows for performance
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
 * Includes 3 call status columns: Call Status, Last Contact, Agent
 */
function formatSubscriberDataForExport(subscribers) {
  return subscribers.map(sub => ({
    'Account ID': sub.account_id,
    'Subscriber Name': sub.subscriber_name,
    Phone: sub.phone,
    Email: sub.email,
    'Mailing Address': sub.mailing_address,
    Paper: sub.paper_name,
    'Current Rate': sub.current_rate,
    'Rate Amount': `$${sub.rate_amount}`,
    'Last Payment': `$${sub.last_payment_amount}`,
    'Expiration Date': sub.expiration_date,
    'Delivery Type': sub.delivery_type,
    'Call Status': sub.call_status
      ? sub.call_status.charAt(0).toUpperCase() + sub.call_status.slice(1)
      : '',
    'Last Contact': sub.last_call_datetime || '',
    Agent: sub.call_agent || '',
  }));
}

/**
 * Export subscriber list with proper formatting
 * High-level wrapper for common use case
 *
 * @param {object} subscriberData - API response with subscribers array
 * @param {string} exportType - 'excel' or 'csv'
 * @param {string|null} syncTimestamp - call_data_as_of timestamp for XLSX header row
 */
function exportSubscriberList(subscriberData, exportType = 'excel', syncTimestamp = null) {
  const { business_unit, metric, count, snapshot_date, subscribers } = subscriberData;

  // Format filename
  const filename = `${business_unit}_${metric}_${snapshot_date}`.replace(/[^a-z0-9_-]/gi, '_');

  // Format data for export
  const formattedData = formatSubscriberDataForExport(subscribers);

  // Export based on type
  if (exportType === 'excel') {
    exportToExcel(formattedData, filename, {
      sheetName: `${metric} (${count})`,
      syncTimestamp: syncTimestamp,
      subscribers: subscribers,
    });
  } else if (exportType === 'csv') {
    exportToCSV(formattedData, filename);
  } else {
    console.error('Invalid export type:', exportType);
  }
}

/**
 * Format stop event data for export
 * Ensures consistent column order and formatting
 */
function formatStopDataForExport(stops) {
  return stops.map(function (s) {
    return {
      'Account ID': s.sub_num || '',
      'Subscriber Name': (s.subscriber_name || '').trim(),
      Phone: s.phone || '',
      Email: s.email || '',
      Address: s.mailing_address || '',
      Paper: s.paper_code || '',
      Rate: s.rate || '',
      'Start Date': s.start_date || '',
      'Stop Date': s.stop_date || '',
      'Paid Through': s.paid_date || '',
      'Stop Reason': s.stop_reason || '',
      Remarks: s.remark || '',
    };
  });
}

/**
 * Export stop events list with proper formatting
 * High-level wrapper for stop analysis exports
 */
function exportStopEventsList(stopData, exportType) {
  const bu = stopData.business_unit || 'Unknown';
  const weekNum = stopData.week_num || '';
  const year = stopData.year || '';
  const stops = stopData.stops || [];

  const filename = (bu + '_Stops_W' + weekNum + '_' + year).replace(/[^a-z0-9_-]/gi, '_');
  const formattedData = formatStopDataForExport(stops);

  if (exportType === 'excel') {
    exportToExcel(formattedData, filename, {
      sheetName: 'Stops W' + weekNum + ' (' + stops.length + ')',
    });
  } else if (exportType === 'csv') {
    exportToCSV(formattedData, filename);
  }
}

// Export functions globally
window.exportToExcel = exportToExcel;
window.exportToCSV = exportToCSV;
window.exportSubscriberList = exportSubscriberList;
window.formatSubscriberDataForExport = formatSubscriberDataForExport;
window.exportStopEventsList = exportStopEventsList;

// console.log('Export utilities loaded');
