/**
 * Zone Trend Chart — Subscribers by Zone over weekly snapshots
 * Shows line/stacked chart of rate_name counts per paper, with filters and interactive legend.
 *
 * LOAD ORDER: After detail_panel.js
 *
 * DEPENDENCIES:
 * - app.js: BUSINESS_UNITS, PAPER_INFO, formatNumber
 * - detail_panel.js: openDetailPanel triggers data load
 *
 * PROVIDES:
 * - loadZoneTrendChart(businessUnit) — called when BU detail panel loads
 * - window.CircDashboard.zoneTrend state
 */

/* exported loadZoneTrendChart, destroyZoneTrendChart */

(function () {
  'use strict';

  // State
  let zoneTrendChart = null;
  let zoneTrendData = null; // { weeks: [], zones: { MAILDG: [1786,...], ... } }
  let currentPaperCode = null;
  let hiddenZones = {};
  let chartType = 'line';
  let highlightedDatasetIndex = -1;

  // Color palette (matches standalone chart)
  const palette = [
    '#2563eb',
    '#dc2626',
    '#16a34a',
    '#ea580c',
    '#7c3aed',
    '#0891b2',
    '#ca8a04',
    '#be185d',
    '#4f46e5',
    '#059669',
    '#d97706',
    '#9333ea',
    '#0d9488',
    '#e11d48',
    '#2dd4bf',
    '#a3e635',
    '#f472b6',
    '#38bdf8',
    '#fb923c',
    '#a78bfa',
    '#34d399',
    '#fbbf24',
    '#f87171',
    '#818cf8',
    '#4ade80',
    '#facc15',
    '#fb7185',
    '#67e8f9',
    '#c084fc',
    '#fca5a5',
    '#86efac',
    '#fde047',
    '#f9a8d4',
    '#7dd3fc',
    '#d8b4fe',
    '#fdba74',
    '#a5f3fc',
  ];

  // --- Helpers ---

  function getMaxVal(zone) {
    return Math.max.apply(null, zoneTrendData.zones[zone]);
  }

  function getNetChange(zone) {
    const v = zoneTrendData.zones[zone];
    let lastNonZero = 0;
    for (let i = v.length - 1; i >= 0; i--) {
      if (v[i] > 0) {
        lastNonZero = v[i];
        break;
      }
    }
    let firstNonZero = 0;
    for (let j = 0; j < v.length; j++) {
      if (v[j] > 0) {
        firstNonZero = v[j];
        break;
      }
    }
    return lastNonZero - firstNonZero;
  }

  function getFirstWeekVal(zone) {
    return zoneTrendData.zones[zone][0];
  }

  function getLastWeekVal(zone) {
    const v = zoneTrendData.zones[zone];
    return v[v.length - 1];
  }

  function isDigitalZone(zone) {
    return zone.indexOf('DIG') === 0 || zone === 'DIGITAL';
  }

  function isCompZone(zone) {
    return (
      zone.indexOf('COMP') !== -1 || zone === 'CITYGOV' || zone === 'ECCOMP' || zone === 'ECOMP'
    );
  }

  function clearChildren(el) {
    while (el.firstChild) el.removeChild(el.firstChild);
  }

  // --- Filtering ---

  function getFilteredZones() {
    const preset = document.getElementById('zoneTrendFilter').value;
    const minSubs = parseInt(document.getElementById('zoneTrendMinSubs').value) || 0;
    const zones = Object.keys(zoneTrendData.zones);

    let filtered = zones.filter(function (z) {
      return getMaxVal(z) >= minSubs;
    });

    switch (preset) {
      case 'top10':
        filtered = filtered
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          })
          .slice(0, 10);
        break;
      case 'no-top':
        filtered = filtered.sort(function (a, b) {
          return getMaxVal(b) - getMaxVal(a);
        });
        if (filtered.length > 1) filtered = filtered.slice(1);
        break;
      case 'under500':
        filtered = filtered
          .filter(function (z) {
            return getMaxVal(z) < 500;
          })
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          });
        break;
      case 'under100':
        filtered = filtered
          .filter(function (z) {
            return getMaxVal(z) < 100;
          })
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          });
        break;
      case 'growing':
        filtered = filtered
          .filter(function (z) {
            return getNetChange(z) > 0;
          })
          .sort(function (a, b) {
            return getNetChange(b) - getNetChange(a);
          });
        break;
      case 'declining':
        filtered = filtered
          .filter(function (z) {
            return getNetChange(z) < 0;
          })
          .sort(function (a, b) {
            return getNetChange(a) - getNetChange(b);
          });
        break;
      case 'new':
        filtered = filtered
          .filter(function (z) {
            return getFirstWeekVal(z) === 0 && getLastWeekVal(z) > 0;
          })
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          });
        break;
      case 'retired':
        filtered = filtered
          .filter(function (z) {
            return getFirstWeekVal(z) > 0 && getLastWeekVal(z) === 0;
          })
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          });
        break;
      case 'stable':
        filtered = filtered
          .filter(function (z) {
            return Math.abs(getNetChange(z)) < 5 && getMaxVal(z) > 0;
          })
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          });
        break;
      case 'small':
        filtered = filtered
          .filter(function (z) {
            return getMaxVal(z) < 50;
          })
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          });
        break;
      case 'small25':
        filtered = filtered
          .filter(function (z) {
            return getMaxVal(z) < 25;
          })
          .sort(function (a, b) {
            return getMaxVal(b) - getMaxVal(a);
          });
        break;
      case 'digital':
        filtered = filtered.filter(isDigitalZone).sort(function (a, b) {
          return getMaxVal(b) - getMaxVal(a);
        });
        break;
      case 'comp':
        filtered = filtered.filter(isCompZone).sort(function (a, b) {
          return getMaxVal(b) - getMaxVal(a);
        });
        break;
      default:
        filtered = filtered.sort(function (a, b) {
          return getMaxVal(b) - getMaxVal(a);
        });
    }
    return filtered;
  }

  // --- Totals bar ---

  function buildTotalsBar() {
    const bar = document.getElementById('zoneTrendTotalBar');
    if (!bar) return;
    clearChildren(bar);

    const weeks = zoneTrendData.weeks;
    const zones = zoneTrendData.zones;
    const totals = weeks.map(function (_, i) {
      return Object.values(zones).reduce(function (s, v) {
        return s + v[i];
      }, 0);
    });

    totals.forEach(function (t, i) {
      const delta = i === 0 ? 0 : t - totals[i - 1];
      const cls = delta < 0 ? 'text-red-500' : delta > 0 ? 'text-green-600' : 'text-gray-400';
      const sign = delta > 0 ? '+' : '';

      const card = document.createElement('div');
      card.className =
        'bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-center min-w-[70px] shadow-sm';

      const weekEl = document.createElement('div');
      weekEl.className = 'text-gray-400';
      weekEl.style.fontSize = '0.6rem';
      weekEl.textContent = weeks[i];
      card.appendChild(weekEl);

      const numEl = document.createElement('div');
      numEl.className = 'font-bold text-sm';
      numEl.textContent = t.toLocaleString();
      card.appendChild(numEl);

      const deltaEl = document.createElement('div');
      deltaEl.className = cls;
      deltaEl.style.fontSize = '0.65rem';
      deltaEl.textContent = i === 0 ? '\u2014' : sign + delta;
      card.appendChild(deltaEl);

      bar.appendChild(card);
    });
  }

  // --- Chart ---

  function buildChart() {
    if (!zoneTrendData || !zoneTrendData.weeks.length) return;

    const filtered = getFilteredZones();
    hiddenZones = {};

    const datasets = filtered.map(function (zone, i) {
      return {
        label: zone,
        data: zoneTrendData.zones[zone],
        borderColor: palette[i % palette.length],
        backgroundColor: palette[i % palette.length] + (chartType === 'line' ? '18' : '99'),
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 6,
        tension: 0.3,
        fill: chartType !== 'line',
        hidden: false,
      };
    });

    // Smart Y-axis max
    let yMax = 0;
    datasets.forEach(function (ds) {
      ds.data.forEach(function (v) {
        if (v > yMax) yMax = v;
      });
    });
    yMax = Math.ceil((yMax * 1.1) / 10) * 10;

    const canvas = document.getElementById('zoneTrendChart');
    const loading = document.getElementById('zoneTrendLoading');
    if (!canvas) return;

    canvas.style.display = 'block';
    if (loading) loading.style.display = 'none';

    if (zoneTrendChart) zoneTrendChart.destroy();

    zoneTrendChart = new Chart(canvas, {
      type: chartType === 'stacked' ? 'bar' : 'line',
      data: { labels: zoneTrendData.weeks, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        onHover: function (event, elements) {
          if (!elements.length) {
            highlightedDatasetIndex = -1;
            return;
          }
          const mouseY = event.y;
          let nearest = elements[0];
          let minDist = Infinity;
          elements.forEach(function (el) {
            const dist = Math.abs(el.element.y - mouseY);
            if (dist < minDist) {
              minDist = dist;
              nearest = el;
            }
          });
          highlightedDatasetIndex = nearest.datasetIndex;
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: function (context) {
                const label = context.dataset.label || '';
                const value = context.parsed.y;
                const prefix = context.datasetIndex === highlightedDatasetIndex ? '\u25b6 ' : '  ';
                return prefix + label + ': ' + value.toLocaleString();
              },
              labelTextColor: function (context) {
                return context.datasetIndex === highlightedDatasetIndex ? '#ffffff' : '#cccccc';
              },
              footer: function (items) {
                const total = items.reduce(function (s, item) {
                  return s + item.parsed.y;
                }, 0);
                return 'Visible total: ' + total.toLocaleString();
              },
            },
          },
        },
        scales: {
          x: { grid: { display: false } },
          y: {
            stacked: chartType === 'stacked',
            beginAtZero: true,
            max: chartType === 'stacked' ? undefined : yMax,
            ticks: {
              callback: function (v) {
                return v.toLocaleString();
              },
            },
          },
        },
      },
    });

    buildLegend(filtered);
  }

  // --- Legend ---

  function buildLegend(filtered) {
    const grid = document.getElementById('zoneTrendLegend');
    if (!grid) return;
    clearChildren(grid);

    filtered.forEach(function (zone, i) {
      const item = document.createElement('div');
      item.className =
        'flex items-center gap-1 text-xs cursor-pointer px-1 py-0.5 rounded hover:bg-gray-100 select-none';
      item.setAttribute('data-zone', zone);

      const swatch = document.createElement('span');
      swatch.className = 'inline-block w-3 h-3 rounded-sm flex-shrink-0';
      swatch.style.backgroundColor = palette[i % palette.length];
      item.appendChild(swatch);

      const lastVal = getLastWeekVal(zone);
      const net = getNetChange(zone);
      const arrow = net > 0 ? ' \u2191' : net < 0 ? ' \u2193' : '';

      const label = document.createElement('span');
      label.textContent = zone + ' (' + lastVal.toLocaleString() + arrow + ')';
      item.appendChild(label);

      item.addEventListener('click', function () {
        const idx = filtered.indexOf(zone);
        if (hiddenZones[zone]) {
          delete hiddenZones[zone];
          zoneTrendChart.setDatasetVisibility(idx, true);
          item.style.opacity = '1';
          item.style.textDecoration = 'none';
        } else {
          hiddenZones[zone] = true;
          zoneTrendChart.setDatasetVisibility(idx, false);
          item.style.opacity = '0.35';
          item.style.textDecoration = 'line-through';
        }
        zoneTrendChart.update();
      });
      grid.appendChild(item);
    });
  }

  // --- Paper selector ---

  function populatePaperSelect(businessUnit) {
    const select = document.getElementById('zoneTrendPaperSelect');
    if (!select) return;

    const papers =
      typeof BUSINESS_UNITS !== 'undefined' && BUSINESS_UNITS[businessUnit]
        ? BUSINESS_UNITS[businessUnit].papers
        : [];

    clearChildren(select);
    papers.forEach(function (code) {
      const opt = document.createElement('option');
      opt.value = code;
      const info = typeof PAPER_INFO !== 'undefined' && PAPER_INFO[code] ? PAPER_INFO[code] : {};
      opt.textContent = code + (info.name ? ' \u2014 ' + info.name : '');
      select.appendChild(opt);
    });

    // Default to first paper
    if (papers.length > 0) {
      currentPaperCode = papers[0];
    }

    // Hide select if only 1 paper
    select.style.display = papers.length <= 1 ? 'none' : '';
  }

  // --- API fetch ---

  async function fetchZoneTrends(paperCode) {
    const response = await fetch(
      'api.php?action=get_zone_trends&paper_code=' + encodeURIComponent(paperCode) + '&weeks=13'
    );
    const result = await response.json();
    if (!result.success) {
      throw new Error(result.error || 'Failed to load zone trends');
    }
    return result.data;
  }

  // --- Loading state helpers ---

  function showLoading(message) {
    const loading = document.getElementById('zoneTrendLoading');
    const canvas = document.getElementById('zoneTrendChart');
    if (loading) {
      loading.style.display = 'flex';
      clearChildren(loading);
      const span = document.createElement('span');
      span.className = 'text-gray-400 text-sm';
      span.textContent = message || 'Loading zone data...';
      loading.appendChild(span);
    }
    if (canvas) canvas.style.display = 'none';
  }

  function showError(message) {
    const loading = document.getElementById('zoneTrendLoading');
    if (loading) {
      loading.style.display = 'flex';
      clearChildren(loading);
      const span = document.createElement('span');
      span.className = 'text-red-500 text-sm';
      span.textContent = message || 'Failed to load zone data';
      loading.appendChild(span);
    }
  }

  // --- Public: called when BU detail panel loads ---

  async function loadZoneTrendChart(businessUnit) {
    currentBusinessUnit = businessUnit;
    populatePaperSelect(businessUnit);

    // Reset controls
    document.getElementById('zoneTrendFilter').value = 'top10';
    document.getElementById('zoneTrendMinSubs').value = '0';
    chartType = 'line';
    const btnLine = document.getElementById('zoneTrendBtnLine');
    const btnStacked = document.getElementById('zoneTrendBtnStacked');
    if (btnLine) {
      btnLine.className =
        'text-xs border border-gray-300 rounded-md px-3 py-1 bg-gray-800 text-white';
    }
    if (btnStacked) {
      btnStacked.className =
        'text-xs border border-gray-300 rounded-md px-3 py-1 bg-white hover:bg-gray-100';
    }

    // Show loading
    showLoading('Loading zone data...');
    clearChildren(document.getElementById('zoneTrendTotalBar'));
    clearChildren(document.getElementById('zoneTrendLegend'));

    try {
      zoneTrendData = await fetchZoneTrends(currentPaperCode);
      buildTotalsBar();
      buildChart();
    } catch (err) {
      console.error('Zone trend load error:', err);
      showError('Failed to load zone data');
    }
  }

  // --- Cleanup on panel close ---

  function destroyZoneTrendChart() {
    if (zoneTrendChart) {
      zoneTrendChart.destroy();
      zoneTrendChart = null;
    }
    zoneTrendData = null;
  }

  // --- Event listeners (bound once) ---

  document.addEventListener('DOMContentLoaded', function () {
    const filterEl = document.getElementById('zoneTrendFilter');
    const minSubsEl = document.getElementById('zoneTrendMinSubs');
    const btnLine = document.getElementById('zoneTrendBtnLine');
    const btnStacked = document.getElementById('zoneTrendBtnStacked');
    const paperSelect = document.getElementById('zoneTrendPaperSelect');

    if (filterEl)
      filterEl.addEventListener('change', function () {
        if (zoneTrendData) buildChart();
      });
    if (minSubsEl)
      minSubsEl.addEventListener('change', function () {
        if (zoneTrendData) buildChart();
      });

    if (btnLine)
      btnLine.addEventListener('click', function () {
        chartType = 'line';
        btnLine.className =
          'text-xs border border-gray-300 rounded-md px-3 py-1 bg-gray-800 text-white';
        btnStacked.className =
          'text-xs border border-gray-300 rounded-md px-3 py-1 bg-white hover:bg-gray-100';
        if (zoneTrendData) buildChart();
      });

    if (btnStacked)
      btnStacked.addEventListener('click', function () {
        chartType = 'stacked';
        btnStacked.className =
          'text-xs border border-gray-300 rounded-md px-3 py-1 bg-gray-800 text-white';
        btnLine.className =
          'text-xs border border-gray-300 rounded-md px-3 py-1 bg-white hover:bg-gray-100';
        if (zoneTrendData) buildChart();
      });

    if (paperSelect)
      paperSelect.addEventListener('change', async function () {
        currentPaperCode = paperSelect.value;
        showLoading('Loading zone data...');
        try {
          zoneTrendData = await fetchZoneTrends(currentPaperCode);
          buildTotalsBar();
          buildChart();
        } catch (err) {
          console.error('Zone trend load error:', err);
          showError('Failed to load zone data');
        }
      });
  });

  // Expose globally
  window.loadZoneTrendChart = loadZoneTrendChart;
  window.destroyZoneTrendChart = destroyZoneTrendChart;
})();
