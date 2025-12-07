/**
 * UI/UX Enhancements
 * Quick Win improvements from UI/UX Pro Max skill
 */

/**
 * LOAD ORDER: 6 of 11
 *
 * DEPENDENCIES:
 * - app.js: Core functions
 * - detail_panel.js: Panel functions for shortcuts
 *
 * PROVIDES:
 * - initializeUIEnhancements()
 * - updateExportMenuAria()
 */

// SVG Icon Templates
const icons = {
    arrowUp: `<svg fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
    </svg>`,

    arrowDown: `<svg fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
    </svg>`,

    equals: `<svg fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd" d="M3 7a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 13a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
    </svg>`
};

/**
 * Enhance comparison badges with icons
 * Adds appropriate arrow icons based on positive/negative/neutral state
 */
function enhanceComparisonBadges() {
    const badges = document.querySelectorAll('.comparison-badge');

    badges.forEach(badge => {
        // Skip if already has an icon
        if (badge.querySelector('svg')) return;

        let icon = '';
        if (badge.classList.contains('positive')) {
            icon = icons.arrowUp;
        } else if (badge.classList.contains('negative')) {
            icon = icons.arrowDown;
        } else if (badge.classList.contains('neutral')) {
            icon = icons.equals;
        }

        if (icon) {
            // Insert icon at the beginning
            const iconSpan = document.createElement('span');
            iconSpan.innerHTML = icon;
            iconSpan.setAttribute('aria-hidden', 'true');
            badge.insertBefore(iconSpan, badge.firstChild);
        }
    });
}

/**
 * Add keyboard navigation to interactive cards
 * Makes cards keyboard accessible with Enter and Space keys
 */
function addKeyboardNavigation() {
    // Paper cards
    const paperCards = document.querySelectorAll('.paper-card');
    paperCards.forEach(card => {
        // Make focusable
        if (!card.hasAttribute('tabindex')) {
            card.setAttribute('tabindex', '0');
        }

        // Add role if not present
        if (!card.hasAttribute('role')) {
            card.setAttribute('role', 'button');
        }

        // Add keyboard event listener
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Business unit cards (if they exist and are clickable)
    const businessCards = document.querySelectorAll('#businessUnits > div[onclick], #businessUnits > div.cursor-pointer');
    businessCards.forEach(card => {
        if (!card.hasAttribute('tabindex')) {
            card.setAttribute('tabindex', '0');
        }

        if (!card.hasAttribute('role')) {
            card.setAttribute('role', 'button');
        }

        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
}

/**
 * Update aria-expanded state for export menu
 */
function updateExportMenuAria(isOpen) {
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
}

/**
 * Screen reader announcement helper
 * Announces updates to screen reader users
 */
function announceToScreenReader(message, priority = 'polite') {
    const announcement = document.createElement('div');
    announcement.setAttribute('role', 'status');
    announcement.setAttribute('aria-live', priority);
    announcement.className = 'sr-only';
    announcement.textContent = message;
    document.body.appendChild(announcement);

    // Remove after announcement
    setTimeout(() => announcement.remove(), 3000);
}

/**
 * Initialize all UI enhancements
 * Call this after the DOM is loaded and data is populated
 */
function initializeUIEnhancements() {
    enhanceComparisonBadges();
    addKeyboardNavigation();

    // Re-enhance badges when data updates
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Check if it's a comparison badge or contains one
                        if (node.classList && node.classList.contains('comparison-badge')) {
                            enhanceComparisonBadges();
                        } else if (node.querySelector && node.querySelector('.comparison-badge')) {
                            enhanceComparisonBadges();
                        }

                        // Check for new paper cards
                        if (node.classList && node.classList.contains('paper-card')) {
                            addKeyboardNavigation();
                        }
                    }
                });
            }
        });
    });

    // Observe the main content area for changes
    const mainContent = document.getElementById('mainContent');
    if (mainContent) {
        observer.observe(mainContent, {
            childList: true,
            subtree: true
        });
    }
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        enhanceComparisonBadges,
        addKeyboardNavigation,
        updateExportMenuAria,
        announceToScreenReader,
        initializeUIEnhancements
    };
}
