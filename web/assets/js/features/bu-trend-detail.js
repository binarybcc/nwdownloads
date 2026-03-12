/**
 * BU Trend Detail Modal
 * Opens a large drill-down from the mini 12-week trend chart on BU cards.
 * Shows: dual-panel Chart.js charts (top: total line + renewals bars;
 *        bottom: stops/new-starts bars + net line),
 *        explanatory note, and a data table.
 *
 * Globals provided: openTrendDetail(businessUnit), closeTrendDetail()
 */

/* global Chart, formatNumber, exportStopEventsList */

(function () {
  'use strict';

  let topChartInstance = null;
  let bottomChartInstance = null;
  let debounceTimer = null;

  // --- Inject modal HTML on DOMContentLoaded ---
  document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.createElement('div');
    overlay.id = 'trend-detail-overlay';
    overlay.className = 'fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center';

    // Static template only — no dynamic user data
    const panel = document.createElement('div');
    panel.className =
      'bg-white rounded-2xl shadow-2xl w-[92vw] max-w-[1200px] max-h-[92vh] flex flex-col overflow-hidden';

    // Header — flex-shrink-0 prevents it from collapsing when body content is tall
    const header = document.createElement('div');
    header.className =
      'flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0';

    const title = document.createElement('h2');
    title.id = 'trend-detail-title';
    title.className = 'text-xl font-bold text-gray-800';

    const controls = document.createElement('div');
    controls.className = 'flex items-center gap-4';

    const label = document.createElement('label');
    label.className = 'text-sm text-gray-600';
    label.textContent = 'Show last ';

    const weeksInput = document.createElement('input');
    weeksInput.id = 'trend-detail-weeks';
    weeksInput.type = 'number';
    weeksInput.min = '4';
    weeksInput.max = '52';
    weeksInput.value = '26';
    weeksInput.className =
      'mx-2 w-16 border border-gray-300 rounded px-2 py-1 text-center font-semibold focus:ring-2 focus:ring-blue-400 focus:outline-none';

    label.appendChild(weeksInput);
    label.appendChild(document.createTextNode(' weeks'));

    const closeBtn = document.createElement('button');
    closeBtn.id = 'trend-detail-close';
    closeBtn.className = 'text-gray-400 hover:text-gray-700 text-2xl leading-none';
    closeBtn.textContent = '\u00D7';

    const printBtn = document.createElement('button');
    printBtn.id = 'trend-detail-print';
    printBtn.className = 'text-gray-400 hover:text-blue-600 text-lg leading-none';
    printBtn.title = 'Print';
    printBtn.textContent = '\uD83D\uDDA8'; // printer emoji
    printBtn.addEventListener('click', printTrendDetail);

    controls.appendChild(label);
    controls.appendChild(printBtn);
    controls.appendChild(closeBtn);
    header.appendChild(title);
    header.appendChild(controls);

    // Body — min-h-0 is the classic flexbox fix: allows this flex child to shrink
    // below its content's intrinsic height so overflow-y-auto actually scrolls
    const body = document.createElement('div');
    body.className = 'px-6 pt-4 pb-6 flex-1 overflow-y-auto min-h-0';

    // Loading
    const loading = document.createElement('div');
    loading.id = 'trend-detail-loading';
    loading.className = 'flex items-center justify-center py-12 hidden';
    const spinner = document.createElement('div');
    spinner.className = 'animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600';
    const loadText = document.createElement('span');
    loadText.className = 'ml-3 text-gray-500';
    loadText.textContent = 'Loading trend data...';
    loading.appendChild(spinner);
    loading.appendChild(loadText);

    // Chart — dual-panel layout with shared container
    const chartWrap = document.createElement('div');
    chartWrap.id = 'trend-detail-chart-wrap';
    chartWrap.style.cssText = 'position: relative; flex-shrink: 0;';

    // Top chart panel: Paid Subscribers line + Renewals bars
    const topChartWrap = document.createElement('div');
    topChartWrap.id = 'trend-detail-chart-top-wrap';
    topChartWrap.style.cssText = 'height: 280px; position: relative;';
    const topCanvas = document.createElement('canvas');
    topCanvas.id = 'trend-detail-chart-top';
    topChartWrap.appendChild(topCanvas);

    // Divider label between charts
    const divider = document.createElement('div');
    divider.className = 'text-xs text-gray-500 font-medium text-center py-1';
    divider.textContent = 'Stops & New Starts';

    // Bottom chart panel: Stops bars + New Starts bars + Net line
    const bottomChartWrap = document.createElement('div');
    bottomChartWrap.id = 'trend-detail-chart-bottom-wrap';
    bottomChartWrap.style.cssText = 'height: 160px; position: relative;';
    const bottomCanvas = document.createElement('canvas');
    bottomCanvas.id = 'trend-detail-chart-bottom';
    bottomChartWrap.appendChild(bottomCanvas);

    chartWrap.appendChild(topChartWrap);
    chartWrap.appendChild(divider);
    chartWrap.appendChild(bottomChartWrap);

    // Explanatory note (static content)
    const note = document.createElement('div');
    note.className =
      'mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900 leading-relaxed';
    note.appendChild(buildNoteContent());

    // Table
    const table = document.createElement('table');
    table.className = 'w-full mt-6 text-sm border-collapse';
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    headerRow.className = 'bg-gray-100 text-gray-700';
    [
      'Week',
      'Date',
      'Total',
      'Comp',
      'Paid',
      'Renewals',
      'Stops',
      'New',
      'Net',
      'Paid Net',
    ].forEach(function (text, i) {
      const th = document.createElement('th');
      th.className = i < 2 ? 'text-left px-3 py-2' : 'text-right px-3 py-2';
      th.textContent = text;
      headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    const tbody = document.createElement('tbody');
    tbody.id = 'trend-detail-table-body';
    table.appendChild(thead);
    table.appendChild(tbody);

    // Assemble
    body.appendChild(loading);
    body.appendChild(chartWrap);
    body.appendChild(note);
    body.appendChild(table);
    panel.appendChild(header);
    panel.appendChild(body);
    overlay.appendChild(panel);
    document.body.appendChild(overlay);

    // Event listeners
    closeBtn.addEventListener('click', closeTrendDetail);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeTrendDetail();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !overlay.classList.contains('hidden')) {
        closeTrendDetail();
      }
    });

    weeksInput.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      const bu = overlay.dataset.businessUnit;
      if (!bu) return;
      debounceTimer = setTimeout(function () {
        const weeks = parseInt(weeksInput.value, 10);
        if (weeks >= 4 && weeks <= 52) {
          fetchAndRender(bu, weeks);
        }
      }, 500);
    });
  });

  // Build static explanatory note content using safe DOM methods
  function buildNoteContent() {
    const frag = document.createDocumentFragment();

    const heading = document.createElement('strong');
    heading.textContent = 'Understanding these charts:';
    frag.appendChild(heading);

    // Top chart section
    const topLabel = document.createElement('div');
    topLabel.className = 'mt-2 font-semibold text-blue-800';
    topLabel.textContent = 'Top chart:';
    frag.appendChild(topLabel);

    const topList = document.createElement('ul');
    topList.className = 'mt-1 list-disc list-inside space-y-1';

    const topItems = [
      {
        bold: 'Paid Subscribers',
        text: ' \u2014 paying subscribers each week, excluding complimentary (blue line, from the AllSubscriber report).',
      },
      {
        bold: 'Renewals',
        text: ' \u2014 existing subscribers whose subscriptions came up for renewal and they renewed (green bars, from the Renewal Churn report).',
      },
    ];

    topItems.forEach(function (item) {
      const li = document.createElement('li');
      const b = document.createElement('strong');
      b.textContent = item.bold;
      li.appendChild(b);
      li.appendChild(document.createTextNode(item.text));
      topList.appendChild(li);
    });
    frag.appendChild(topList);

    // Bottom chart section
    const bottomLabel = document.createElement('div');
    bottomLabel.className = 'mt-2 font-semibold text-blue-800';
    bottomLabel.textContent = 'Bottom chart:';
    frag.appendChild(bottomLabel);

    const bottomList = document.createElement('ul');
    bottomList.className = 'mt-1 list-disc list-inside space-y-1';

    const bottomItems = [
      {
        bold: 'Stops',
        text: ' \u2014 subscriptions that expired and were not renewed during the week (red bars).',
      },
      {
        bold: 'New Starts',
        text: ' \u2014 genuinely new first-time subscribers with no prior subscription history (purple bars).',
      },
      {
        bold: 'Net',
        text: ' \u2014 the actual week-over-week change in paid subscribers (orange dashed line).',
      },
    ];

    bottomItems.forEach(function (item) {
      const li = document.createElement('li');
      const b = document.createElement('strong');
      b.textContent = item.bold;
      li.appendChild(b);
      li.appendChild(document.createTextNode(item.text));
      bottomList.appendChild(li);
    });
    frag.appendChild(bottomList);

    // Availability note
    const availability = document.createElement('p');
    availability.className = 'mt-2 text-xs text-blue-700';
    availability.textContent =
      'Renewals, Stops, and New Starts data is only available from December 2025 onward.';
    frag.appendChild(availability);

    return frag;
  }

  // --- Public functions (hoisted for use in DOMContentLoaded listeners) ---
  function openTrendDetail(businessUnit) {
    const overlay = document.getElementById('trend-detail-overlay');
    overlay.dataset.businessUnit = businessUnit;
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    document.getElementById('trend-detail-title').textContent =
      businessUnit + ' \u2014 Subscriber Trends';

    const weeksInput = document.getElementById('trend-detail-weeks');
    const weeks = parseInt(weeksInput.value, 10) || 26;

    fetchAndRender(businessUnit, weeks);
  }

  function closeTrendDetail() {
    const overlay = document.getElementById('trend-detail-overlay');
    overlay.classList.add('hidden');
    document.body.style.overflow = '';

    if (topChartInstance) {
      topChartInstance.destroy();
      topChartInstance = null;
    }
    if (bottomChartInstance) {
      bottomChartInstance.destroy();
      bottomChartInstance = null;
    }

    // Close stops drill-down panel if open
    closeStopsDrillDown();
  }

  // Print the modal content to a single Letter-size page, portrait mode
  function printTrendDetail() {
    const overlay = document.getElementById('trend-detail-overlay');
    if (!overlay || overlay.classList.contains('hidden')) return;

    // Add print class to body so @media print rules kick in
    document.body.classList.add('printing-trend-detail');
    window.print();
    document.body.classList.remove('printing-trend-detail');
  }

  // Expose on window for onclick handlers in app.js
  window.openTrendDetail = openTrendDetail;
  window.closeTrendDetail = closeTrendDetail;

  // --- Inject print styles (one-time) ---
  (function injectPrintStyles() {
    const style = document.createElement('style');
    style.textContent =
      '@media print {' +
      '  body.printing-trend-detail > *:not(#trend-detail-overlay) { display: none !important; }' +
      '  body.printing-trend-detail #trend-detail-overlay {' +
      '    position: static !important; display: block !important;' +
      '    width: 100% !important; height: auto !important;' +
      '    background: white !important; overflow: visible !important;' +
      '  }' +
      '  body.printing-trend-detail #trend-detail-overlay > div {' +
      '    max-height: none !important; overflow: visible !important;' +
      '    box-shadow: none !important; width: 100% !important; max-width: 100% !important;' +
      '    border-radius: 0 !important;' +
      '  }' +
      '  body.printing-trend-detail #trend-detail-overlay .overflow-y-auto {' +
      '    overflow: visible !important; max-height: none !important;' +
      '  }' +
      '  body.printing-trend-detail #trend-detail-chart-wrap {' +
      '    height: auto !important; max-height: none !important;' +
      '  }' +
      '  body.printing-trend-detail #trend-detail-chart-top-wrap {' +
      '    height: 280px !important; max-height: 280px !important; min-height: 280px !important;' +
      '  }' +
      '  body.printing-trend-detail #trend-detail-chart-bottom-wrap {' +
      '    height: 160px !important; max-height: 160px !important; min-height: 160px !important;' +
      '  }' +
      '  body.printing-trend-detail #trend-detail-close,' +
      '  body.printing-trend-detail #trend-detail-print { display: none !important; }' +
      '  @page { size: letter portrait; margin: 0.5in; }' +
      '}';
    document.head.appendChild(style);
  })();

  // --- Fetch API and render chart + table ---
  function fetchAndRender(businessUnit, weeks) {
    const loading = document.getElementById('trend-detail-loading');
    const chartWrap = document.getElementById('trend-detail-chart-wrap');

    loading.classList.remove('hidden');
    chartWrap.style.opacity = '0.3';

    const url =
      'api/get_bu_trend_detail.php?business_unit=' +
      encodeURIComponent(businessUnit) +
      '&weeks=' +
      weeks;

    fetch(url, { credentials: 'same-origin' })
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        loading.classList.add('hidden');
        chartWrap.style.opacity = '1';

        if (!json.success) {
          showTableError(json.error || 'Failed to load data');
          return;
        }

        if (!json.data || json.data.length === 0) {
          showTableError('No trend data available for this business unit.');
          return;
        }

        // Store data for click-to-drill-down on Stops bars
        const overlay = document.getElementById('trend-detail-overlay');
        overlay.dataset.trendData = JSON.stringify(json.data);

        renderChart(json.data);
        renderTable(json.data);
      })
      .catch(function (err) {
        loading.classList.add('hidden');
        chartWrap.style.opacity = '1';
        console.error('Trend detail fetch error:', err);
        showTableError('Network error loading trend data');
      });
  }

  function showTableError(message) {
    const tbody = document.getElementById('trend-detail-table-body');
    tbody.textContent = '';
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 7;
    td.className = 'text-center py-4 text-red-600';
    td.textContent = message;
    tr.appendChild(td);
    tbody.appendChild(tr);
  }

  // --- Render dual-panel Chart.js charts ---
  function renderChart(data) {
    if (topChartInstance) {
      topChartInstance.destroy();
      topChartInstance = null;
    }
    if (bottomChartInstance) {
      bottomChartInstance.destroy();
      bottomChartInstance = null;
    }

    const labels = data.map(function (d) {
      return d.label;
    });
    const totals = data.map(function (d) {
      return d.paid_active !== null && d.paid_active !== undefined ? d.paid_active : d.total_active;
    });
    const starts = data.map(function (d) {
      return d.starts;
    });
    const stops = data.map(function (d) {
      return d.stops;
    });
    const newStarts = data.map(function (d) {
      return d.new_starts;
    });
    const nets = data.map(function (d) {
      return d.paid_net !== null && d.paid_net !== undefined ? d.paid_net : d.net;
    });

    // Compute smart Y-axis min for total subscribers: round down to nearest 100
    const validTotals = totals.filter(function (v) {
      return v !== null;
    });
    const yMin =
      validTotals.length > 0 ? Math.floor(Math.min.apply(null, validTotals) / 100) * 100 : 0;

    // Shared tooltip title callback
    function tooltipTitle(items) {
      const idx = items[0].dataIndex;
      const d = data[idx];
      return d.label + '  (' + d.snapshot_date + ')';
    }

    function tooltipLabel(item) {
      const val = item.raw;
      if (val === null || val === undefined) return item.dataset.label + ': \u2014';
      return item.dataset.label + ': ' + fmtNum(val);
    }

    // --- Top chart: Paid Subscribers (line) + Renewals (bars) ---
    const topCtx = document.getElementById('trend-detail-chart-top').getContext('2d');

    topChartInstance = new Chart(topCtx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            type: 'line',
            label: 'Paid Subscribers',
            data: totals,
            yAxisID: 'y',
            borderColor: '#3B82F6',
            backgroundColor: '#3B82F6',
            borderWidth: 4,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.3,
            fill: false,
            order: 1,
          },
          {
            type: 'bar',
            label: 'Renewals',
            data: starts,
            yAxisID: 'y2',
            backgroundColor: 'rgba(34, 197, 94, 0.6)',
            borderColor: 'rgba(34, 197, 94, 0.9)',
            borderWidth: 1,
            order: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            position: 'top',
            labels: { usePointStyle: true, padding: 16 },
          },
          tooltip: {
            callbacks: {
              title: tooltipTitle,
              label: tooltipLabel,
            },
          },
        },
        scales: {
          x: {
            ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 20 },
          },
          y: {
            type: 'linear',
            position: 'left',
            min: yMin,
            title: { display: true, text: 'Paid Subscribers' },
            ticks: {
              callback: function (v) {
                return fmtNum(v);
              },
            },
            grid: { drawOnChartArea: true },
          },
          y2: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: 'Renewals' },
            beginAtZero: true,
            grid: { drawOnChartArea: false },
          },
        },
      },
    });

    // --- Bottom chart: Stops (bars) + New Starts (bars) + Net (line) ---
    const bottomCtx = document.getElementById('trend-detail-chart-bottom').getContext('2d');

    bottomChartInstance = new Chart(bottomCtx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            type: 'bar',
            label: 'Stops',
            data: stops,
            yAxisID: 'y',
            backgroundColor: 'rgba(239, 68, 68, 0.6)',
            borderColor: 'rgba(239, 68, 68, 0.9)',
            borderWidth: 1,
            order: 2,
          },
          {
            type: 'bar',
            label: 'New Starts',
            data: newStarts,
            yAxisID: 'y',
            backgroundColor: 'rgba(147, 51, 234, 0.6)',
            borderColor: 'rgba(147, 51, 234, 0.9)',
            borderWidth: 1,
            order: 3,
          },
          {
            type: 'line',
            label: 'Net',
            data: nets,
            yAxisID: 'y',
            borderColor: '#F59E0B',
            backgroundColor: '#F59E0B',
            borderWidth: 2,
            borderDash: [6, 3],
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0,
            fill: false,
            spanGaps: false,
            order: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            position: 'top',
            labels: { usePointStyle: true, padding: 16 },
          },
          tooltip: {
            callbacks: {
              title: tooltipTitle,
              label: tooltipLabel,
            },
          },
        },
        scales: {
          x: {
            display: false,
          },
          y: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'Stops / New / Net' },
            beginAtZero: false,
            grace: '15%',
            grid: {
              drawOnChartArea: true,
              color: function (context) {
                return context.tick.value === 0 ? 'rgba(0, 0, 0, 0.35)' : 'rgba(0, 0, 0, 0.08)';
              },
              lineWidth: function (context) {
                return context.tick.value === 0 ? 2 : 1;
              },
            },
          },
        },
      },
    });

    // Attach click/cursor listeners for Stops drill-down
    attachBottomChartListeners();
  }

  // --- Click handler on bottom chart for Stops bar drill-down ---
  function attachBottomChartListeners() {
    const canvas = document.getElementById('trend-detail-chart-bottom');
    if (!canvas || !bottomChartInstance) return;

    // Click: drill into Stops bar
    canvas.addEventListener('click', function (evt) {
      if (!bottomChartInstance) return;
      const elements = bottomChartInstance.getElementsAtEventForMode(
        evt,
        'nearest',
        { intersect: true },
        false
      );
      if (elements.length === 0) return;

      const el = elements[0];
      // Dataset index 0 = Stops (red bars) in the bottom chart
      if (el.datasetIndex !== 0) return;

      const overlay = document.getElementById('trend-detail-overlay');
      const bu = overlay.dataset.businessUnit;
      const dataStr = overlay.dataset.trendData;
      if (!bu || !dataStr) return;

      const data = JSON.parse(dataStr);
      const point = data[el.index];
      if (!point || point.stops === null || point.stops === 0) return;

      showStopsDrillDown(bu, point.week_num, point.year, point.label);
    });

    // Cursor hint: pointer only on Stops bars
    canvas.addEventListener('mousemove', function (evt) {
      if (!bottomChartInstance) return;
      const elements = bottomChartInstance.getElementsAtEventForMode(
        evt,
        'nearest',
        { intersect: true },
        false
      );
      if (elements.length > 0 && elements[0].datasetIndex === 0) {
        canvas.style.cursor = 'pointer';
      } else {
        canvas.style.cursor = '';
      }
    });

    canvas.addEventListener('mouseleave', function () {
      canvas.style.cursor = '';
    });
  }

  // --- Render data table using safe DOM methods ---
  function renderTable(data) {
    const tbody = document.getElementById('trend-detail-table-body');
    tbody.textContent = '';

    for (let i = 0; i < data.length; i++) {
      const d = data[i];
      const tr = document.createElement('tr');
      tr.className = 'border-t border-gray-100 hover:bg-gray-50';

      // Week label
      appendCell(tr, d.label, 'px-3 py-2');
      // Date
      appendCell(tr, d.snapshot_date, 'px-3 py-2 text-gray-500');
      // Total
      appendCell(tr, fmtNum(d.total_active), 'px-3 py-2 text-right font-medium');
      // Comp
      appendCell(
        tr,
        d.comp_count !== null && d.comp_count !== undefined ? fmtNum(d.comp_count) : '\u2014',
        'px-3 py-2 text-right text-gray-400'
      );
      // Paid (total minus comp)
      const paid = d.paid_active !== null && d.paid_active !== undefined ? d.paid_active : null;
      appendCell(tr, paid !== null ? fmtNum(paid) : '\u2014', 'px-3 py-2 text-right font-medium');
      // Renewals (data field is still d.starts from API)
      appendCell(
        tr,
        d.starts !== null ? fmtNum(d.starts) : '\u2014',
        'px-3 py-2 text-right' + (d.starts === null ? ' text-gray-300' : '')
      );
      // Stops
      appendCell(
        tr,
        d.stops !== null ? fmtNum(d.stops) : '\u2014',
        'px-3 py-2 text-right' + (d.stops === null ? ' text-gray-300' : '')
      );
      // New Starts
      appendCell(
        tr,
        d.new_starts !== null ? fmtNum(d.new_starts) : '\u2014',
        'px-3 py-2 text-right' + (d.new_starts !== null ? ' text-purple-600' : ' text-gray-300')
      );
      // Net (total)
      let netClass = 'px-3 py-2 text-right text-gray-500';
      let netText = '\u2014';
      if (d.net !== null) {
        if (d.net > 0) {
          netClass = 'px-3 py-2 text-right text-green-600 font-medium';
          netText = '+' + fmtNum(d.net);
        } else if (d.net < 0) {
          netClass = 'px-3 py-2 text-right text-red-600 font-medium';
          netText = fmtNum(d.net);
        } else {
          netText = fmtNum(d.net);
        }
      } else {
        netClass = 'px-3 py-2 text-right text-gray-300';
      }
      appendCell(tr, netText, netClass);
      // Paid Net (excludes comp changes)
      let paidNetClass = 'px-3 py-2 text-right text-gray-500';
      let paidNetText = '\u2014';
      if (d.paid_net !== null && d.paid_net !== undefined) {
        if (d.paid_net > 0) {
          paidNetClass = 'px-3 py-2 text-right text-green-600 font-medium';
          paidNetText = '+' + fmtNum(d.paid_net);
        } else if (d.paid_net < 0) {
          paidNetClass = 'px-3 py-2 text-right text-red-600 font-medium';
          paidNetText = fmtNum(d.paid_net);
        } else {
          paidNetText = fmtNum(d.paid_net);
        }
      } else {
        paidNetClass = 'px-3 py-2 text-right text-gray-300';
      }
      appendCell(tr, paidNetText, paidNetClass);

      tbody.appendChild(tr);
    }
  }

  function appendCell(tr, text, className) {
    const td = document.createElement('td');
    td.className = className;
    td.textContent = text;
    tr.appendChild(td);
  }

  // --- Number formatting helper ---
  function fmtNum(n) {
    if (n === null || n === undefined) return '\u2014';
    return typeof formatNumber === 'function' ? formatNumber(n) : n.toLocaleString();
  }

  // ==========================================================================
  // Stops Drill-Down Panel (standalone slide-out)
  // Mirrors SubscriberTablePanel design with red color scheme
  // ==========================================================================

  let stopsPanelEl = null; // reference to the panel DOM element
  let currentStopsData = null; // cached for export

  /**
   * Fetch stop events for a given BU + week and display in a slide-out panel.
   */
  function showStopsDrillDown(businessUnit, weekNum, year, weekLabel) {
    ensureStopsPanel();
    const panel = stopsPanelEl;
    const backdrop = document.getElementById('stops-drilldown-backdrop');

    // Reset export data
    currentStopsData = null;

    // Show with animation
    backdrop.style.opacity = '0';
    backdrop.classList.remove('hidden');
    requestAnimationFrame(function () {
      backdrop.style.opacity = '1';
      panel.style.right = '0';
    });

    // Set title
    const titleEl = panel.querySelector('[data-stops-title]');
    titleEl.textContent = 'Stops \u2014 ' + businessUnit;
    const subtitleEl = panel.querySelector('[data-stops-subtitle]');
    subtitleEl.textContent = weekLabel + ' \u00B7 Loading\u2026';

    // Hide export bar during loading
    const exportBar = panel.querySelector('[data-stops-export-bar]');
    if (exportBar) exportBar.style.display = 'none';

    // Loading state
    const bodyEl = panel.querySelector('[data-stops-body]');
    bodyEl.textContent = '';
    const spinner = document.createElement('div');
    spinner.style.cssText =
      'display:flex;align-items:center;justify-content:center;padding:3rem 0;';
    const spinnerInner = document.createElement('div');
    spinnerInner.style.cssText =
      'width:2rem;height:2rem;border-radius:50%;border:3px solid #FEE2E2;' +
      'border-top-color:#DC2626;animation:spin 0.8s linear infinite;';
    spinner.appendChild(spinnerInner);
    const spinText = document.createElement('span');
    spinText.style.cssText = 'margin-left:0.75rem;color:#6B7280;';
    spinText.textContent = 'Loading stop events\u2026';
    spinner.appendChild(spinText);
    bodyEl.appendChild(spinner);

    // Fetch
    const url =
      'api/get_stop_events.php?business_unit=' +
      encodeURIComponent(businessUnit) +
      '&week_num=' +
      weekNum +
      '&year=' +
      year;

    fetch(url, { credentials: 'same-origin' })
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        if (!json.success) {
          bodyEl.textContent = '';
          const err = document.createElement('p');
          err.style.cssText = 'color:#DC2626;padding:2rem 0;text-align:center;';
          err.textContent = json.error || 'Failed to load stop events';
          bodyEl.appendChild(err);
          return;
        }

        subtitleEl.textContent = weekLabel + ' \u00B7 ' + json.count + ' stops';

        // Cache data for export
        currentStopsData = {
          business_unit: businessUnit,
          week_num: weekNum,
          year: year,
          stops: json.stops,
        };

        // Show export bar with count
        if (exportBar) {
          exportBar.style.display = 'flex';
          const countEl = panel.querySelector('[data-stops-count]');
          if (countEl) countEl.textContent = 'Total: ' + json.count + ' stops';
        }

        renderStopsTable(bodyEl, json.stops);
      })
      .catch(function (err) {
        console.error('Stop events fetch error:', err);
        bodyEl.textContent = '';
        const errEl = document.createElement('p');
        errEl.style.cssText = 'color:#DC2626;padding:2rem 0;text-align:center;';
        errEl.textContent = 'Network error loading stop events';
        bodyEl.appendChild(errEl);
      });
  }

  /** Helper: build a button with icon + label (safe DOM, no innerHTML). */
  function buildExportButton(iconText, labelText, styles) {
    const btn = document.createElement('button');
    btn.style.cssText = styles;
    const icon = document.createElement('span');
    icon.style.cssText = 'font-size:16px;';
    icon.textContent = iconText;
    const lbl = document.createElement('span');
    lbl.textContent = labelText;
    btn.appendChild(icon);
    btn.appendChild(lbl);
    return btn;
  }

  /**
   * Create the stops drill-down panel DOM (once).
   * Uses inline styles to match SubscriberTablePanel design with red color scheme.
   */
  function ensureStopsPanel() {
    if (stopsPanelEl) return;

    // Backdrop
    const backdrop = document.createElement('div');
    backdrop.id = 'stops-drilldown-backdrop';
    backdrop.style.cssText =
      'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);' +
      'z-index:9998;opacity:0;transition:opacity 300ms cubic-bezier(0.4,0,0.2,1);' +
      'backdrop-filter:blur(2px);';
    backdrop.classList.add('hidden');
    backdrop.addEventListener('click', closeStopsDrillDown);

    // Panel
    const panel = document.createElement('div');
    panel.id = 'stops-drilldown-panel';
    panel.style.cssText =
      'position:fixed;top:0;right:-75%;width:75%;height:100vh;' +
      'background:#FEF2F2;box-shadow:-8px 0 32px rgba(0,0,0,0.15);' +
      'z-index:9999;display:flex;flex-direction:column;' +
      'transition:right 350ms cubic-bezier(0.4,0,0.2,1);';

    panel.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    // ── Header (red gradient) ──
    const header = document.createElement('div');
    header.style.cssText =
      'background:linear-gradient(135deg,#DC2626 0%,#991B1B 100%);' +
      'color:white;padding:2rem;box-shadow:0 4px 12px rgba(0,0,0,0.1);flex-shrink:0;';

    // Top row: title + close
    const topRow = document.createElement('div');
    topRow.style.cssText =
      'display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;';

    const titleWrap = document.createElement('div');
    titleWrap.style.cssText = 'flex:1;';
    const title = document.createElement('h2');
    title.style.cssText = 'font-size:1.75rem;font-weight:700;margin:0 0 0.5rem 0;';
    title.setAttribute('data-stops-title', '');
    const subtitle = document.createElement('p');
    subtitle.style.cssText = 'font-size:0.95rem;margin:0;opacity:0.95;';
    subtitle.setAttribute('data-stops-subtitle', '');
    titleWrap.appendChild(title);
    titleWrap.appendChild(subtitle);

    const closeBtn = document.createElement('button');
    closeBtn.style.cssText =
      'background:rgba(255,255,255,0.2);border:none;color:white;width:40px;height:40px;' +
      'border-radius:50%;cursor:pointer;transition:background 200ms;display:flex;align-items:center;' +
      'justify-content:center;font-size:24px;line-height:1;flex-shrink:0;';
    closeBtn.textContent = '\u00D7';
    closeBtn.title = 'Close (ESC)';
    closeBtn.addEventListener('click', closeStopsDrillDown);
    closeBtn.addEventListener('mouseover', function () {
      this.style.background = 'rgba(255,255,255,0.3)';
    });
    closeBtn.addEventListener('mouseout', function () {
      this.style.background = 'rgba(255,255,255,0.2)';
    });

    topRow.appendChild(titleWrap);
    topRow.appendChild(closeBtn);
    header.appendChild(topRow);

    // Bottom row: export buttons + count badge
    const exportBar = document.createElement('div');
    exportBar.style.cssText = 'display:none;gap:0.75rem;flex-wrap:wrap;align-items:center;';
    exportBar.setAttribute('data-stops-export-bar', '');

    // Excel button (safe DOM)
    const excelBtn = buildExportButton(
      '\uD83D\uDCD7',
      'Export to Excel',
      'background:white;color:#DC2626;border:none;padding:0.625rem 1.25rem;border-radius:8px;' +
        'cursor:pointer;font-weight:600;font-size:0.9rem;display:flex;align-items:center;gap:0.5rem;' +
        'transition:all 200ms;box-shadow:0 2px 6px rgba(0,0,0,0.1);'
    );
    excelBtn.addEventListener('mouseover', function () {
      this.style.transform = 'translateY(-2px)';
      this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    });
    excelBtn.addEventListener('mouseout', function () {
      this.style.transform = 'translateY(0)';
      this.style.boxShadow = '0 2px 6px rgba(0,0,0,0.1)';
    });
    excelBtn.addEventListener('click', function () {
      if (currentStopsData && typeof exportStopEventsList !== 'undefined') {
        exportStopEventsList(currentStopsData, 'excel');
      } else {
        alert('No stop data available to export.');
      }
    });

    // CSV button (safe DOM)
    const csvBtn = buildExportButton(
      '\uD83D\uDCCA',
      'Export to CSV',
      'background:rgba(255,255,255,0.15);color:white;border:2px solid white;' +
        'padding:0.625rem 1.25rem;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.9rem;' +
        'display:flex;align-items:center;gap:0.5rem;transition:all 200ms;'
    );
    csvBtn.addEventListener('mouseover', function () {
      this.style.background = 'rgba(255,255,255,0.25)';
    });
    csvBtn.addEventListener('mouseout', function () {
      this.style.background = 'rgba(255,255,255,0.15)';
    });
    csvBtn.addEventListener('click', function () {
      if (currentStopsData && typeof exportStopEventsList !== 'undefined') {
        exportStopEventsList(currentStopsData, 'csv');
      } else {
        alert('No stop data available to export.');
      }
    });

    // Count badge
    const countBadge = document.createElement('div');
    countBadge.style.cssText =
      'margin-left:auto;background:rgba(255,255,255,0.2);padding:0.625rem 1rem;' +
      'border-radius:8px;font-weight:600;font-size:0.9rem;';
    countBadge.setAttribute('data-stops-count', '');

    exportBar.appendChild(excelBtn);
    exportBar.appendChild(csvBtn);
    exportBar.appendChild(countBadge);
    header.appendChild(exportBar);

    // ── Body (scrollable) ──
    const body = document.createElement('div');
    body.style.cssText = 'flex:1;overflow-y:auto;padding:2rem;min-height:0;';
    body.setAttribute('data-stops-body', '');

    panel.appendChild(header);
    panel.appendChild(body);

    document.body.appendChild(backdrop);
    document.body.appendChild(panel);
    stopsPanelEl = panel;

    // ESC to close
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !backdrop.classList.contains('hidden')) {
        closeStopsDrillDown();
      }
    });
  }

  function closeStopsDrillDown() {
    const backdrop = document.getElementById('stops-drilldown-backdrop');
    const panel = document.getElementById('stops-drilldown-panel');
    if (!backdrop || !panel) return;

    backdrop.style.opacity = '0';
    panel.style.right = '-75%';

    setTimeout(function () {
      backdrop.classList.add('hidden');
    }, 350);
  }

  /**
   * Render the stops table inside the panel body.
   * Matches SubscriberTablePanel styling with red color scheme.
   */
  function renderStopsTable(container, stops) {
    container.textContent = '';

    if (!stops || stops.length === 0) {
      const emptyWrap = document.createElement('div');
      emptyWrap.style.cssText =
        'text-align:center;padding:4rem 2rem;background:white;border-radius:12px;' +
        'box-shadow:0 2px 8px rgba(0,0,0,0.08);';
      const emptyIcon = document.createElement('div');
      emptyIcon.style.cssText = 'font-size:4rem;margin-bottom:1rem;opacity:0.3;';
      emptyIcon.textContent = '\uD83D\uDED1';
      const emptyTitle = document.createElement('h3');
      emptyTitle.style.cssText =
        'font-size:1.25rem;font-weight:600;color:#1F2937;margin:0 0 0.5rem 0;';
      emptyTitle.textContent = 'No Stop Events Found';
      const emptyText = document.createElement('p');
      emptyText.style.cssText = 'color:#6B7280;margin:0;';
      emptyText.textContent = 'There are no stop events for this week.';
      emptyWrap.appendChild(emptyIcon);
      emptyWrap.appendChild(emptyTitle);
      emptyWrap.appendChild(emptyText);
      container.appendChild(emptyWrap);
      return;
    }

    // ── Stop Reason Summary ──
    const reasonCounts = {};
    stops.forEach(function (s) {
      const r = s.stop_reason || 'Unknown';
      reasonCounts[r] = (reasonCounts[r] || 0) + 1;
    });

    const summaryWrap = document.createElement('div');
    summaryWrap.style.cssText =
      'margin-bottom:1rem;padding:0.75rem 1rem;background:#FEF2F2;border:1px solid #FECACA;' +
      'border-radius:8px;font-size:0.8rem;';
    const summaryTitle = document.createElement('strong');
    summaryTitle.style.cssText = 'color:#991B1B;';
    summaryTitle.textContent = 'Stop Reasons:';
    summaryWrap.appendChild(summaryTitle);

    const summaryList = document.createElement('div');
    summaryList.style.cssText = 'margin-top:0.35rem;display:flex;flex-wrap:wrap;gap:0.5rem;';

    const sortedReasons = Object.keys(reasonCounts).sort(function (a, b) {
      return reasonCounts[b] - reasonCounts[a];
    });
    sortedReasons.forEach(function (reason) {
      const badge = document.createElement('span');
      const bStyle = getReasonBadgeStyle(reason);
      badge.style.cssText =
        'display:inline-block;padding:0.15rem 0.5rem;border-radius:4px;font-size:0.7rem;font-weight:600;' +
        'background:' +
        bStyle.bg +
        ';color:' +
        bStyle.text +
        ';';
      badge.textContent = reason + ' (' + reasonCounts[reason] + ')';
      summaryList.appendChild(badge);
    });
    summaryWrap.appendChild(summaryList);
    container.appendChild(summaryWrap);

    // ── Table ──
    const tableWrap = document.createElement('div');
    tableWrap.style.cssText =
      'background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.08);' +
      'overflow-x:auto;overflow-y:visible;';

    const table = document.createElement('table');
    table.style.cssText =
      'width:100%;border-collapse:collapse;font-size:0.75rem;table-layout:auto;';

    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    headerRow.style.cssText = 'background:#DC2626;color:white;';

    const columns = [
      { label: 'Acct', align: 'left' },
      { label: 'Name', align: 'left' },
      { label: 'Phone', align: 'left' },
      { label: 'Email', align: 'left' },
      { label: 'Paper', align: 'center' },
      { label: 'Rate', align: 'center' },
      { label: 'Stop Date', align: 'center' },
      { label: 'Paid Thru', align: 'center' },
      { label: 'Stop Reason', align: 'left' },
      { label: 'Remarks', align: 'left' },
    ];

    columns.forEach(function (col) {
      const th = document.createElement('th');
      th.style.cssText =
        'padding:0.5rem;text-align:' +
        col.align +
        ';font-weight:600;white-space:nowrap;' +
        'border-right:1px solid rgba(255,255,255,0.2);position:sticky;top:0;z-index:10;background:#DC2626;';
      th.textContent = col.label;
      headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');

    stops.forEach(function (s, index) {
      const isAlt = index % 2 === 1;
      const bgColor = isAlt ? '#FEF2F2' : 'white';
      const tr = document.createElement('tr');
      tr.style.cssText = 'background:' + bgColor + ';transition:background 150ms;';
      tr.addEventListener('mouseover', function () {
        this.style.background = '#FEE2E2';
      });
      tr.addEventListener('mouseout', function () {
        this.style.background = bgColor;
      });

      // Account ID — monospace, bold, red
      stopsAppendStyledCell(
        tr,
        s.sub_num || '',
        'font-family:monospace;font-weight:600;color:#DC2626;white-space:nowrap;'
      );

      // Name — bold
      stopsAppendStyledCell(
        tr,
        (s.subscriber_name || '').trim(),
        'font-weight:500;white-space:nowrap;'
      );

      // Phone — monospace
      stopsAppendStyledCell(
        tr,
        s.phone || '\u2014',
        'font-family:monospace;white-space:nowrap;' + (!s.phone ? 'color:#D1D5DB;' : '')
      );

      // Email — clickable mailto link
      const emailTd = document.createElement('td');
      emailTd.style.cssText =
        'padding:0.35rem 0.5rem;border-bottom:1px solid #E5E7EB;white-space:nowrap;';
      if (s.email) {
        const emailLink = document.createElement('a');
        emailLink.href = 'mailto:' + s.email;
        emailLink.style.cssText = 'color:#DC2626;text-decoration:none;';
        emailLink.textContent = s.email;
        emailTd.appendChild(emailLink);
      } else {
        emailTd.style.color = '#D1D5DB';
        emailTd.textContent = '\u2014';
      }
      tr.appendChild(emailTd);

      // Paper — bold
      stopsAppendStyledCell(
        tr,
        s.paper_code || '',
        'font-weight:600;white-space:nowrap;text-align:center;'
      );

      // Rate
      stopsAppendStyledCell(
        tr,
        s.rate || '\u2014',
        'text-align:center;white-space:nowrap;' + (!s.rate ? 'color:#D1D5DB;' : '')
      );

      // Stop Date — monospace
      stopsAppendStyledCell(
        tr,
        s.stop_date || '',
        'font-family:monospace;text-align:center;white-space:nowrap;'
      );

      // Paid Thru — monospace
      stopsAppendStyledCell(
        tr,
        s.paid_date || '\u2014',
        'font-family:monospace;text-align:center;white-space:nowrap;' +
          (!s.paid_date ? 'color:#D1D5DB;' : '')
      );

      // Stop reason — color-coded pill badge
      const reasonTd = document.createElement('td');
      reasonTd.style.cssText = 'padding:0.35rem 0.5rem;border-bottom:1px solid #E5E7EB;';
      if (s.stop_reason) {
        const reasonBadge = document.createElement('span');
        const rStyle = getReasonBadgeStyle(s.stop_reason);
        reasonBadge.style.cssText =
          'display:inline-block;padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:600;' +
          'background:' +
          rStyle.bg +
          ';color:' +
          rStyle.text +
          ';';
        reasonBadge.textContent = s.stop_reason;
        reasonTd.appendChild(reasonBadge);
      } else {
        reasonTd.style.color = '#D1D5DB';
        reasonTd.textContent = '\u2014';
      }
      tr.appendChild(reasonTd);

      // Remarks — truncated with tooltip
      const remarkTd = document.createElement('td');
      remarkTd.style.cssText =
        'padding:0.35rem 0.5rem;border-bottom:1px solid #E5E7EB;max-width:200px;' +
        'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;' +
        (s.remark ? 'color:#4B5563;' : 'color:#D1D5DB;');
      remarkTd.textContent = s.remark || '\u2014';
      if (s.remark) remarkTd.title = s.remark;
      tr.appendChild(remarkTd);

      tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    tableWrap.appendChild(table);
    container.appendChild(tableWrap);
  }

  /** Append a styled table cell with base padding + border. */
  function stopsAppendStyledCell(tr, text, extraStyle) {
    const td = document.createElement('td');
    td.style.cssText = 'padding:0.35rem 0.5rem;border-bottom:1px solid #E5E7EB;' + extraStyle;
    td.textContent = text;
    tr.appendChild(td);
  }

  /** Return inline-style color values for stop reason badges by category. */
  function getReasonBadgeStyle(reason) {
    const r = (reason || '').toUpperCase();
    if (r.indexOf('EXPIRE') !== -1) return { bg: '#FFEDD5', text: '#C2410C' };
    if (r.indexOf('NON-PAY') !== -1) return { bg: '#FEE2E2', text: '#B91C1C' };
    if (r.indexOf('COST') !== -1) return { bg: '#FEF3C7', text: '#92400E' };
    if (r.indexOf('DECEASED') !== -1) return { bg: '#E5E7EB', text: '#4B5563' };
    if (r.indexOf('HOSPITAL') !== -1) return { bg: '#DBEAFE', text: '#1E40AF' };
    if (r.indexOf('MOVED') !== -1) return { bg: '#EDE9FE', text: '#6D28D9' };
    return { bg: '#FEF2F2', text: '#DC2626' };
  }
})();
