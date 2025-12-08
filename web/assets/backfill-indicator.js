/**
 * Backfill Indicator Module
 * Shows visual indicators for backfilled data on the dashboard
 *
 * Features:
 * - Backfill badge next to week label
 * - Prominent warning for backfills >2 weeks old
 * - Data source tooltip
 */

class BackfillIndicator {
    constructor() {
        this.currentBackfillInfo = null;
    }

    /**
     * Update backfill indicators based on API response
     * @param {Object} backfillData - Backfill info from API response
     */
    update(backfillData) {
        this.currentBackfillInfo = backfillData;

        if (!backfillData) {
            this.hide();
            return;
        }

        // Show badge if data is backfilled
        if (backfillData.is_backfilled) {
            this.showBadge(backfillData);

            // Show prominent warning if >2 weeks old
            if (backfillData.backfill_weeks > 2) {
                this.showWarning(backfillData);
            } else {
                this.hideWarning();
            }
        } else {
            this.hide();
        }
    }

    /**
     * Show backfill badge
     */
    showBadge(data) {
        // Find or create badge container
        let badgeContainer = document.getElementById('backfill-badge-container');

        if (!badgeContainer) {
            // Create badge container next to header
            const header = document.querySelector('h1');
            if (!header) return;

            badgeContainer = document.createElement('div');
            badgeContainer.id = 'backfill-badge-container';
            badgeContainer.className = 'inline-block ml-3';

            // Insert after h1
            header.parentNode.insertBefore(badgeContainer, header.nextSibling);
        }

        // Create badge HTML
        const weeksText = data.backfill_weeks === 1 ? '1 week' : `${data.backfill_weeks} weeks`;
        const sourceDate = new Date(data.source_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});

        badgeContainer.innerHTML = `
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200"
                  title="Data backfilled ${weeksText} from ${sourceDate} upload">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                Backfilled (${weeksText})
            </span>
        `;
    }

    /**
     * Show prominent warning for old backfills
     */
    showWarning(data) {
        // Find or create warning container
        let warningContainer = document.getElementById('backfill-warning');

        if (!warningContainer) {
            // Create warning container at top of main content
            const mainContent = document.querySelector('main') || document.querySelector('[role="main"]');
            if (!mainContent) return;

            warningContainer = document.createElement('div');
            warningContainer.id = 'backfill-warning';
            warningContainer.className = 'mb-6';

            // Insert at beginning of main content
            mainContent.insertBefore(warningContainer, mainContent.firstChild);
        }

        const weeksText = data.backfill_weeks === 1 ? '1 week' : `${data.backfill_weeks} weeks`;
        const sourceDate = new Date(data.source_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});

        warningContainer.innerHTML = `
            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-amber-800">
                            ⚠️ Backfilled Data Warning
                        </h3>
                        <div class="mt-2 text-sm text-amber-700">
                            <p>
                                This week's data was <strong>backfilled ${weeksText}</strong> from a ${sourceDate} upload.
                                This represents subscriber counts from ${sourceDate}, not the actual week being viewed.
                            </p>
                            <p class="mt-2">
                                <strong>What this means:</strong> The numbers shown are estimates based on a later snapshot.
                                For accurate historical data, upload the actual CSV from this week's date.
                            </p>
                        </div>
                        ${data.source_filename ? `
                        <div class="mt-2 text-xs text-amber-600">
                            Source: <code class="bg-amber-100 px-1 py-0.5 rounded">${data.source_filename}</code>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Hide warning
     */
    hideWarning() {
        const warning = document.getElementById('backfill-warning');
        if (warning) {
            warning.remove();
        }
    }

    /**
     * Hide all indicators
     */
    hide() {
        const badge = document.getElementById('backfill-badge-container');
        if (badge) {
            badge.remove();
        }
        this.hideWarning();
    }

    /**
     * Get current backfill info for debugging
     */
    getInfo() {
        return this.currentBackfillInfo;
    }
}

// Create global instance
window.backfillIndicator = new BackfillIndicator();
