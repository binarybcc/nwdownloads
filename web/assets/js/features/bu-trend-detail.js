/**
 * BU Trend Detail Modal
 * Opens a large drill-down from the mini 12-week trend chart on BU cards.
 * Shows: mixed Chart.js chart (total line + starts/stops bars + net line),
 *        explanatory note, and a data table.
 *
 * Globals provided: openTrendDetail(businessUnit), closeTrendDetail()
 */

/* global Chart, formatNumber */

(function () {
  'use strict';

  let chartInstance = null;
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

    // Chart — use max-height + flex-shrink so it adapts to smaller viewports
    const chartWrap = document.createElement('div');
    chartWrap.id = 'trend-detail-chart-wrap';
    chartWrap.style.cssText =
      'min-height: 250px; max-height: 350px; height: 40vh; position: relative; flex-shrink: 0;';
    const canvas = document.createElement('canvas');
    canvas.id = 'trend-detail-chart';
    chartWrap.appendChild(canvas);

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
    ['Week', 'Date', 'Total', 'Starts', 'Stops', 'Net'].forEach(function (text, i) {
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
    const strong = document.createElement('strong');
    strong.textContent = 'Understanding this chart:';
    frag.appendChild(strong);

    const ul = document.createElement('ul');
    ul.className = 'mt-1 list-disc list-inside space-y-1';

    const items = [
      {
        bold: 'Total Subscribers',
        text: ' \u2014 the full count of active subscribers each week (from the AllSubscriber report).',
      },
      {
        bold: 'Starts',
        text: ' \u2014 subscriptions that renewed during the week (from the Renewal Churn report). Note: this captures renewals only \u2014 brand-new first-time subscribers are not tracked separately and are included in the net change instead.',
      },
      {
        bold: 'Stops',
        text: ' \u2014 subscriptions that expired and were not renewed during the week.',
      },
      {
        bold: 'Net',
        text: ' \u2014 the actual week-over-week change in total subscribers. This is the most accurate measure of growth or decline. Starts and Stops data is only available from December 2025 onward.',
      },
    ];

    items.forEach(function (item) {
      const li = document.createElement('li');
      const b = document.createElement('strong');
      b.textContent = item.bold;
      li.appendChild(b);
      li.appendChild(document.createTextNode(item.text));
      ul.appendChild(li);
    });

    frag.appendChild(ul);
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

    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }
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
      '    height: 280px !important; max-height: 280px !important; min-height: 280px !important;' +
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
    td.colSpan = 6;
    td.className = 'text-center py-4 text-red-600';
    td.textContent = message;
    tr.appendChild(td);
    tbody.appendChild(tr);
  }

  // --- Render Chart.js mixed chart ---
  function renderChart(data) {
    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }

    const ctx = document.getElementById('trend-detail-chart').getContext('2d');

    const labels = data.map(function (d) {
      return d.label;
    });
    const totals = data.map(function (d) {
      return d.total_active;
    });
    const starts = data.map(function (d) {
      return d.starts;
    });
    const stops = data.map(function (d) {
      return d.stops;
    });
    const nets = data.map(function (d) {
      return d.net;
    });

    // Compute smart Y-axis min: round down to nearest 100 below the data minimum
    // This zooms in on the variation instead of starting at 0
    const minTotal = Math.min.apply(
      null,
      totals.filter(function (v) {
        return v !== null;
      })
    );
    const yMin = Math.floor(minTotal / 100) * 100;

    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            type: 'line',
            label: 'Total Subscribers',
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
            label: 'Starts',
            data: starts,
            yAxisID: 'y2',
            backgroundColor: 'rgba(34, 197, 94, 0.6)',
            borderColor: 'rgba(34, 197, 94, 0.9)',
            borderWidth: 1,
            order: 3,
          },
          {
            type: 'bar',
            label: 'Stops',
            data: stops,
            yAxisID: 'y2',
            backgroundColor: 'rgba(239, 68, 68, 0.6)',
            borderColor: 'rgba(239, 68, 68, 0.9)',
            borderWidth: 1,
            order: 4,
          },
          {
            type: 'line',
            label: 'Net',
            data: nets,
            yAxisID: 'y2',
            borderColor: '#F59E0B',
            backgroundColor: '#F59E0B',
            borderWidth: 2,
            borderDash: [6, 3],
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.3,
            fill: false,
            spanGaps: false,
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
              title: function (items) {
                const idx = items[0].dataIndex;
                const d = data[idx];
                return d.label + '  (' + d.snapshot_date + ')';
              },
              label: function (item) {
                const val = item.raw;
                if (val === null || val === undefined) return item.dataset.label + ': \u2014';
                return item.dataset.label + ': ' + fmtNum(val);
              },
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
            title: { display: true, text: 'Total Subscribers' },
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
            title: { display: true, text: 'Starts / Stops / Net' },
            beginAtZero: false,
            grid: { drawOnChartArea: false },
          },
        },
      },
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
      // Starts
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
      // Net
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
})();
