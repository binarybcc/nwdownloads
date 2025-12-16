/**
 * Vacation Information Display Module
 * Handles display of vacation counts and longest vacations
 */

/* exported displayLongestVacationsOverall, displayLongestVacationsForUnit, showVacationContextMenu, showVacationSubscriberMenu, showVacationTrends, exportVacationList, showVacationDetails, showVacationHistory */

/**
 * Format vacation duration for display
 * @param {number} weeks - Number of weeks on vacation (decimal)
 * @returns {string} Formatted duration string in days
 */
function formatVacationDuration(weeks) {
    // Convert weeks to days (whole number)
    const days = Math.round(weeks * 7);

    if (days === 1) return '1 day';

    return `${days} days`;
}

/**
 * Display longest vacations in the overview card
 * @param {Array} vacations - Array of vacation objects
 */
function displayLongestVacationsOverall(vacations) {
    const container = document.getElementById('longestVacationsOverall');

    if (!vacations || vacations.length === 0) {
        container.innerHTML = '<div class="text-xs text-gray-400 italic">No active vacations</div>';
        return;
    }

    const html = vacations.slice(0, 3).map((vac, index) => `
        <div class="flex items-center justify-between text-xs hover:bg-gray-50 p-1.5 rounded">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <span class="flex-shrink-0 w-4 h-4 rounded-full bg-amber-100 text-amber-600 text-[10px] font-bold flex items-center justify-center">
                    ${index + 1}
                </span>
                <span class="font-medium text-gray-700 truncate">${vac.subscriber_name}</span>
                <span class="text-gray-400">•</span>
                <span class="text-gray-500 font-mono text-[10px]">${vac.paper_code}</span>
            </div>
            <div class="text-amber-600 font-semibold ml-2 flex-shrink-0">
                ${formatVacationDuration(vac.vacation_weeks)}
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

/**
 * Display longest vacations for a specific business unit
 * @param {string} businessUnit - Business unit name
 * @param {Array} vacations - Array of vacation objects
 */
function displayLongestVacationsForUnit(businessUnit, vacations) {
    const containerId = `longestVacations${businessUnit.replace(/\s+/g, '')}`;
    const container = document.getElementById(containerId);

    if (!container) {
        console.warn(`Container not found for business unit: ${businessUnit}`);
        return;
    }

    if (!vacations || vacations.length === 0) {
        container.innerHTML = '<div class="text-xs text-gray-400 italic">No active vacations</div>';
        return;
    }

    const html = vacations.slice(0, 3).map((vac, index) => `
        <div class="flex items-center justify-between text-xs hover:bg-teal-50 p-1.5 rounded">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <span class="flex-shrink-0 w-4 h-4 rounded-full bg-amber-100 text-amber-600 text-[10px] font-bold flex items-center justify-center">
                    ${index + 1}
                </span>
                <span class="font-medium text-gray-700 truncate">${vac.subscriber_name}</span>
                <span class="text-gray-400">•</span>
                <span class="text-gray-500 font-mono text-[10px]">${vac.paper_code}</span>
            </div>
            <div class="text-amber-600 font-semibold ml-2 flex-shrink-0">
                ${formatVacationDuration(vac.vacation_weeks)}
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

/**
 * Show context menu for vacation metrics
 * @param {Event} event - Context menu event
 * @param {string} context - 'overall' or business unit name
 */
function showVacationContextMenu(event, context) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    console.log('showVacationContextMenu called with context:', context);

    // Create context menu HTML
    const menu = document.createElement('div');
    menu.id = 'vacationContextMenu';
    menu.className = 'fixed bg-white rounded-lg shadow-lg border border-gray-200 py-1';
    menu.style.left = `${event.clientX}px`;
    menu.style.top = `${event.clientY}px`;
    menu.style.zIndex = '99999';
    menu.style.minWidth = '200px';
    menu.style.display = 'block';
    menu.style.visibility = 'visible';

    menu.innerHTML = `
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200">
            Vacation Options - ${context === 'overall' ? 'All Units' : context}
        </div>
        <button id="vacationListBtn" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2">
            <span>👥</span>
            <span>View Subscriber List</span>
        </button>
    `;

    // Remove existing menu
    const existing = document.getElementById('vacationContextMenu');
    if (existing) existing.remove();

    document.body.appendChild(menu);

    // Add event listeners with stopPropagation to prevent close handler from firing
    const listBtn = document.getElementById('vacationListBtn');
    if (listBtn) {
        listBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            console.log('Subscriber list button clicked for context:', context);
            menu.remove();
            showVacationSubscriberList(context);
        });
    }

    console.log('Menu created and appended to body. Position:', menu.style.left, menu.style.top);
    console.log('Menu element:', menu);

    // Close menu on click outside - longer delay to prevent immediate closure
    const closeMenu = (e) => {
        console.log('closeMenu triggered, target:', e.target);
        if (!menu.contains(e.target)) {
            console.log('Closing menu');
            menu.remove();
            document.removeEventListener('click', closeMenu);
            document.removeEventListener('contextmenu', closeMenu);
        }
    };

    setTimeout(() => {
        document.addEventListener('click', closeMenu, true);
        console.log('Close handlers added after timeout');
    }, 100);
}

/**
 * Show context menu for individual vacation subscriber
 * @param {Event} event - Context menu event
 * @param {string} subNum - Subscriber number
 * @param {string} context - Business unit context (optional)
 */
function showVacationSubscriberMenu(event, subNum, _context = '') {
    event.preventDefault();
    event.stopPropagation();

    // Create context menu HTML
    const menu = document.createElement('div');
    menu.id = 'vacationSubscriberMenu';
    menu.className = 'fixed bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50';
    menu.style.left = `${event.pageX}px`;
    menu.style.top = `${event.pageY}px`;

    menu.innerHTML = `
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200">
            Subscriber ${subNum}
        </div>
        <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2"
                onclick="showVacationDetails('${subNum}')">
            <span>👤</span>
            <span>View Full Details</span>
        </button>
        <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2"
                onclick="showVacationHistory('${subNum}')">
            <span>📅</span>
            <span>Vacation History</span>
        </button>
    `;

    // Remove existing menu
    const existing = document.getElementById('vacationSubscriberMenu');
    if (existing) existing.remove();

    document.body.appendChild(menu);

    // Close menu on click outside
    const closeMenu = (e) => {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', closeMenu);
        }
    };

    setTimeout(() => {
        document.addEventListener('click', closeMenu);
    }, 10);
}

/**
 * Show vacation trends chart
 * @param {string} context - 'overall' or business unit name
 */
function showVacationTrends(context) {
    console.log('Show vacation trends for:', context);
    // TODO: Implement trend chart modal
    alert(`Vacation trends for ${context} - Coming soon!`);
    document.getElementById('vacationContextMenu')?.remove();
}

/**
 * Show list of all subscribers on vacation
 * @param {string} context - 'overall' or business unit name
 */
async function showVacationSubscriberList(context) {
    console.log('Show vacation subscriber list for:', context);
    document.getElementById('vacationContextMenu')?.remove();

    const snapshotDate = dashboardData.current.snapshot_date;
    const businessUnit = context === 'overall' ? null : context;

    try {
        // Fetch vacation subscribers from dedicated endpoint
        const url = businessUnit
            ? `api.php?action=vacation_subscribers&business_unit=${encodeURIComponent(businessUnit)}&snapshot_date=${snapshotDate}`
            : `api.php?action=vacation_subscribers&snapshot_date=${snapshotDate}`;

        console.log('Fetching vacation subscribers from:', url);
        const response = await fetch(url);
        const result = await response.json();

        console.log('Vacation subscribers response:', result);

        if (!result.success || !result.data) {
            alert('Failed to load vacation subscriber data');
            return;
        }

        const vacationSubscribers = result.data.subscribers;

        if (vacationSubscribers.length === 0) {
            alert(`No subscribers on vacation for ${context === 'overall' ? 'any unit' : context}`);
            return;
        }

        // Prepare data for SubscriberTablePanel
        // The panel expects: { title, subtitle, data: { subscribers: [] } }
        const panelData = {
            title: context === 'overall' ? 'Subscribers On Vacation - All Units' : `Subscribers On Vacation - ${context}`,
            subtitle: `${vacationSubscribers.length} subscriber${vacationSubscribers.length !== 1 ? 's' : ''} • Snapshot: ${snapshotDate}`,
            data: {
                subscribers: vacationSubscribers,
                count: vacationSubscribers.length,
                businessUnit: businessUnit,
                snapshotDate: snapshotDate,
                metricType: 'vacation'
            }
        };

        console.log('Opening panel with data:', panelData);

        // Show using SubscriberTablePanel
        const panel = new SubscriberTablePanel({ colorScheme: 'amber' });
        panel.show(panelData);

    } catch (error) {
        console.error('Error loading vacation subscriber list:', error);
        alert('Failed to load vacation data: ' + error.message);
    }
}

/**
 * Export vacation list to CSV
 * @param {string} context - 'overall' or business unit name
 */
async function exportVacationList(context) {
    console.log('Export vacation list for:', context);
    document.getElementById('vacationContextMenu')?.remove();

    const snapshotDate = dashboardData.current.snapshot_date;

    try {
        const response = await fetch(`api.php?action=get_longest_vacations&snapshot_date=${snapshotDate}`);
        const result = await response.json();

        if (!result.success || !result.data) {
            alert('Failed to load vacation data');
            return;
        }

        let vacations;
        if (context === 'overall') {
            // Get all vacations from all units
            vacations = [];
            if (result.data.by_unit) {
                for (const unitVacs of Object.values(result.data.by_unit)) {
                    vacations.push(...unitVacs);
                }
            }
        } else {
            // Get vacations for specific unit
            vacations = result.data.by_unit?.[context] || [];
        }

        // Sort by vacation duration (longest first)
        vacations.sort((a, b) => b.vacation_weeks - a.vacation_weeks);

        // Create CSV content
        let csv = 'Subscriber Number,Subscriber Name,Paper Code,Business Unit,Vacation Start,Vacation End,Duration (Days)\n';

        vacations.forEach(vac => {
            const days = Math.round(vac.vacation_weeks * 7);
            csv += `${vac.sub_num},"${vac.subscriber_name || vac.name || 'Unknown'}",${vac.paper_code},${vac.business_unit},${vac.vacation_start || ''},${vac.vacation_end || ''},${days}\n`;
        });

        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        const filename = `vacations_${context.replace(/\s+/g, '_')}_${snapshotDate}.csv`;

        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

    } catch (error) {
        console.error('Error exporting vacation list:', error);
        alert('Failed to export vacation data');
    }
}

/**
 * Show detailed vacation information for a subscriber
 * @param {string} subNum - Subscriber number
 */
async function showVacationDetails(subNum) {
    console.log('Show vacation details for subscriber:', subNum);
    document.getElementById('vacationSubscriberMenu')?.remove();

    const snapshotDate = dashboardData.current.snapshot_date;

    try {
        const response = await fetch(`api.php?action=get_longest_vacations&snapshot_date=${snapshotDate}`);
        const result = await response.json();

        if (!result.success || !result.data) {
            alert('Failed to load vacation data');
            return;
        }

        // Find the subscriber in the data
        let subscriber = null;
        if (result.data.by_unit) {
            for (const unitVacs of Object.values(result.data.by_unit)) {
                subscriber = unitVacs.find(vac => vac.sub_num == subNum);
                if (subscriber) break;
            }
        }

        if (!subscriber) {
            alert(`Subscriber ${subNum} not found in vacation data`);
            return;
        }

        const days = Math.round(subscriber.vacation_weeks * 7);
        const weeks = Math.floor(subscriber.vacation_weeks);
        const remainingDays = days - (weeks * 7);

        // Create modal
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.onclick = (e) => {
            if (e.target === modal) modal.remove();
        };

        const modalContent = `
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-900">Vacation Details</h2>
                    <p class="text-sm text-gray-500 mt-1">Subscriber #${subNum}</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <div class="text-sm font-medium text-gray-500">Subscriber Name</div>
                        <div class="text-lg font-semibold text-gray-900">${subscriber.subscriber_name || subscriber.name || 'Unknown'}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-500">Paper</div>
                            <div class="text-base font-semibold text-gray-900 font-mono">${subscriber.paper_code}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">Business Unit</div>
                            <div class="text-base font-semibold text-gray-900">${subscriber.business_unit}</div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-gray-200">
                        <div class="text-sm font-medium text-gray-500 mb-2">Vacation Period</div>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 bg-amber-50 rounded-lg p-3">
                                <div class="text-xs text-amber-600 font-medium">Start Date</div>
                                <div class="text-base font-semibold text-amber-900">${subscriber.vacation_start || 'Not set'}</div>
                            </div>
                            <div class="text-gray-400">→</div>
                            <div class="flex-1 bg-amber-50 rounded-lg p-3">
                                <div class="text-xs text-amber-600 font-medium">End Date</div>
                                <div class="text-base font-semibold text-amber-900">${subscriber.vacation_end || 'Not set'}</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-sm font-medium text-blue-700 mb-1">Total Duration</div>
                        <div class="text-2xl font-bold text-blue-900">${formatVacationDuration(subscriber.vacation_weeks)}</div>
                        <div class="text-sm text-blue-600 mt-1">${weeks} week${weeks !== 1 ? 's' : ''} ${remainingDays > 0 ? `and ${remainingDays} day${remainingDays !== 1 ? 's' : ''}` : ''}</div>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-200 flex justify-end">
                    <button onclick="this.closest('.fixed').remove()"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium">
                        Close
                    </button>
                </div>
            </div>
        `;

        modal.innerHTML = modalContent;
        document.body.appendChild(modal);

    } catch (error) {
        console.error('Error loading subscriber details:', error);
        alert('Failed to load subscriber details');
    }
}

/**
 * Show vacation history for a subscriber
 * @param {string} subNum - Subscriber number
 */
function showVacationHistory(subNum) {
    console.log('Show vacation history for subscriber:', subNum);
    // TODO: Implement history modal
    alert(`Vacation history for subscriber ${subNum} - Coming soon!`);
    document.getElementById('vacationSubscriberMenu')?.remove();
}
