/**
 * Vacation Information Display Module
 * Handles display of vacation counts and longest vacations
 */

/**
 * Format vacation duration for display
 * @param {number} weeks - Number of weeks on vacation
 * @returns {string} Formatted duration string
 */
function formatVacationDuration(weeks) {
    if (weeks === 1) return '1 week';
    if (weeks < 4) return `${weeks} weeks`;

    const months = Math.floor(weeks / 4);
    const remainingWeeks = weeks % 4;

    if (remainingWeeks === 0) {
        return months === 1 ? '1 month' : `${months} months`;
    }

    return `${months}mo ${remainingWeeks}wk`;
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
        <div class="flex items-center justify-between text-xs hover:bg-gray-50 p-1.5 rounded cursor-pointer"
             oncontextmenu="showVacationSubscriberMenu(event, ${vac.sub_num}); return false;"
             onclick="showVacationDetails(${vac.sub_num})">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <span class="flex-shrink-0 w-4 h-4 rounded-full bg-amber-100 text-amber-600 text-[10px] font-bold flex items-center justify-center">
                    ${index + 1}
                </span>
                <span class="font-medium text-gray-700 truncate">${vac.subscriber_name}</span>
                <span class="text-gray-400">•</span>
                <span class="text-gray-500 font-mono text-[10px]">${vac.paper_code}</span>
            </div>
            <div class="text-amber-600 font-semibold ml-2 flex-shrink-0">
                ${formatVacationDuration(vac.weeks_on_vacation)}
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
        <div class="flex items-center justify-between text-xs hover:bg-teal-50 p-1.5 rounded cursor-pointer"
             oncontextmenu="showVacationSubscriberMenu(event, ${vac.sub_num}, '${businessUnit}'); return false;"
             onclick="showVacationDetails(${vac.sub_num})">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <span class="flex-shrink-0 w-4 h-4 rounded-full bg-amber-100 text-amber-600 text-[10px] font-bold flex items-center justify-center">
                    ${index + 1}
                </span>
                <span class="font-medium text-gray-700 truncate">${vac.subscriber_name}</span>
                <span class="text-gray-400">•</span>
                <span class="text-gray-500 font-mono text-[10px]">${vac.paper_code}</span>
            </div>
            <div class="text-amber-600 font-semibold ml-2 flex-shrink-0">
                ${formatVacationDuration(vac.weeks_on_vacation)}
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

    // Create context menu HTML
    const menu = document.createElement('div');
    menu.id = 'vacationContextMenu';
    menu.className = 'fixed bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50';
    menu.style.left = `${event.pageX}px`;
    menu.style.top = `${event.pageY}px`;

    menu.innerHTML = `
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 border-b border-gray-200">
            Vacation Options
        </div>
        <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2"
                onclick="showVacationTrends('${context}')">
            <span>📈</span>
            <span>View Vacation Trends</span>
        </button>
        <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2"
                onclick="showVacationSubscriberList('${context}')">
            <span>👥</span>
            <span>View All On Vacation</span>
        </button>
        <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center gap-2"
                onclick="exportVacationList('${context}')">
            <span>📊</span>
            <span>Export to CSV</span>
        </button>
    `;

    // Remove existing menu
    const existing = document.getElementById('vacationContextMenu');
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
 * Show context menu for individual vacation subscriber
 * @param {Event} event - Context menu event
 * @param {string} subNum - Subscriber number
 * @param {string} context - Business unit context (optional)
 */
function showVacationSubscriberMenu(event, subNum, context = '') {
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
function showVacationSubscriberList(context) {
    console.log('Show vacation subscriber list for:', context);
    // TODO: Implement subscriber list modal
    alert(`Vacation subscriber list for ${context} - Coming soon!`);
    document.getElementById('vacationContextMenu')?.remove();
}

/**
 * Export vacation list to CSV
 * @param {string} context - 'overall' or business unit name
 */
function exportVacationList(context) {
    console.log('Export vacation list for:', context);
    // TODO: Implement CSV export
    alert(`Export vacation data for ${context} - Coming soon!`);
    document.getElementById('vacationContextMenu')?.remove();
}

/**
 * Show detailed vacation information for a subscriber
 * @param {string} subNum - Subscriber number
 */
function showVacationDetails(subNum) {
    console.log('Show vacation details for subscriber:', subNum);
    // TODO: Implement detail modal
    alert(`Vacation details for subscriber ${subNum} - Coming soon!`);
    document.getElementById('vacationSubscriberMenu')?.remove();
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
