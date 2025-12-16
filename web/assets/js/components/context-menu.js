/**
 * Context Menu Component
 * Professional right-click context menu for chart interactions
 * Date: 2025-12-05
 */

/**
 * LOAD ORDER: 7 of 11
 *
 * DEPENDENCIES:
 * - app.js: Chart instances
 *
 * PROVIDES:
 * - Context menu creation and handling
 */

class ContextMenu {
    constructor(options = {}) {
        this.items = options.items || [];
        this.onSelect = options.onSelect || (() => {});
        this.element = null;
        this.context = null;
        this.isOpen = false;

        // Bind methods
        this.handleClick = this.handleClick.bind(this);
        this.handleClickOutside = this.handleClickOutside.bind(this);
        this.handleEscape = this.handleEscape.bind(this);
    }

    /**
     * Show context menu at specified position
     * @param {number} x - X coordinate
     * @param {number} y - Y coordinate
     * @param {object} context - Context data to pass to action handlers
     */
    show(x, y, context = {}) {
        this.context = context;

        // Close any existing menu
        this.close();

        // Create menu element
        this.element = this.createMenuElement();

        // Add to DOM
        document.body.appendChild(this.element);

        // Position menu
        this.positionMenu(x, y);

        // Add event listeners
        this.element.addEventListener('click', this.handleClick);
        document.addEventListener('click', this.handleClickOutside);
        document.addEventListener('keydown', this.handleEscape);

        this.isOpen = true;
    }

    /**
     * Create menu DOM element
     */
    createMenuElement() {
        const menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.style.cssText = `
            position: fixed;
            background: white;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            min-width: 220px;
            padding: 6px;
            z-index: 9999;
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 150ms, transform 150ms;
        `;

        // Add items
        this.items.forEach((item, index) => {
            if (item.type === 'divider') {
                const divider = document.createElement('div');
                divider.className = 'context-menu-divider';
                divider.style.cssText = `
                    height: 1px;
                    background: #E2E8F0;
                    margin: 6px 0;
                `;
                menu.appendChild(divider);
            } else {
                const menuItem = document.createElement('div');
                menuItem.className = 'context-menu-item';
                menuItem.dataset.itemId = item.id;
                menuItem.dataset.index = index;

                if (item.disabled) {
                    menuItem.classList.add('disabled');
                }

                menuItem.style.cssText = `
                    padding: 10px 14px;
                    border-radius: 6px;
                    cursor: ${item.disabled ? 'not-allowed' : 'pointer'};
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    transition: background 150ms;
                    opacity: ${item.disabled ? '0.5' : '1'};
                    user-select: none;
                `;

                // Icon
                if (item.icon) {
                    const icon = document.createElement('span');
                    icon.textContent = item.icon;
                    icon.style.cssText = 'font-size: 16px; line-height: 1;';
                    menuItem.appendChild(icon);
                }

                // Label
                const label = document.createElement('span');
                label.textContent = item.label;
                label.style.cssText = 'flex: 1; font-size: 14px; color: #1F2937;';
                menuItem.appendChild(label);

                // Keyboard shortcut (if provided)
                if (item.shortcut) {
                    const shortcut = document.createElement('span');
                    shortcut.textContent = item.shortcut;
                    shortcut.style.cssText = 'font-size: 12px; color: #9CA3AF;';
                    menuItem.appendChild(shortcut);
                }

                // Hover effect (if not disabled)
                if (!item.disabled) {
                    menuItem.addEventListener('mouseenter', () => {
                        menuItem.style.background = '#F1F5F9';
                    });
                    menuItem.addEventListener('mouseleave', () => {
                        menuItem.style.background = 'transparent';
                    });
                }

                menu.appendChild(menuItem);
            }
        });

        // Fade in animation
        requestAnimationFrame(() => {
            menu.style.opacity = '1';
            menu.style.transform = 'scale(1)';
        });

        return menu;
    }

    /**
     * Position menu intelligently (avoid screen edges)
     */
    positionMenu(x, y) {
        const menu = this.element;
        const menuRect = menu.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        let finalX = x;
        let finalY = y;

        // Adjust horizontal position if menu would overflow right edge
        if (x + menuRect.width > viewportWidth) {
            finalX = viewportWidth - menuRect.width - 10;
        }

        // Adjust vertical position if menu would overflow bottom edge
        if (y + menuRect.height > viewportHeight) {
            finalY = viewportHeight - menuRect.height - 10;
        }

        // Ensure minimum padding from edges
        finalX = Math.max(10, finalX);
        finalY = Math.max(10, finalY);

        menu.style.left = finalX + 'px';
        menu.style.top = finalY + 'px';
    }

    /**
     * Handle menu item click
     */
    handleClick(e) {
        const menuItem = e.target.closest('.context-menu-item');
        if (!menuItem || menuItem.classList.contains('disabled')) {
            return;
        }

        const itemId = menuItem.dataset.itemId;
        const index = parseInt(menuItem.dataset.index);
        const item = this.items[index];

        // Close menu
        this.close();

        // Call handler with item ID and context
        this.onSelect(itemId, this.context, item);
    }

    /**
     * Handle click outside menu (close menu)
     */
    handleClickOutside(e) {
        if (this.element && !this.element.contains(e.target)) {
            this.close();
        }
    }

    /**
     * Handle ESC key (close menu)
     */
    handleEscape(e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            this.close();
        }
    }

    /**
     * Close and cleanup menu
     */
    close() {
        if (!this.element) return;

        // Fade out animation
        this.element.style.opacity = '0';
        this.element.style.transform = 'scale(0.95)';

        // Remove after animation
        setTimeout(() => {
            if (this.element && this.element.parentNode) {
                this.element.parentNode.removeChild(this.element);
            }
            this.element = null;
        }, 150);

        // Remove event listeners
        document.removeEventListener('click', this.handleClickOutside);
        document.removeEventListener('keydown', this.handleEscape);

        this.isOpen = false;
    }

    /**
     * Update menu items (useful for dynamic menus)
     */
    updateItems(newItems) {
        this.items = newItems;
        if (this.isOpen) {
            // Re-render menu at current position
            const rect = this.element.getBoundingClientRect();
            this.show(rect.left, rect.top, this.context);
        }
    }

    /**
     * Destroy menu and cleanup
     */
    destroy() {
        this.close();
        this.items = [];
        this.onSelect = null;
        this.context = null;
    }
}

/**
 * Helper: Create standard chart context menu
 * Returns configured menu items for chart interactions
 */
function createChartContextMenu(options = {}) {
    const chartSpecificItems = options.chartSpecificItems || [];
    const includeDefaults = options.includeDefaults !== false;

    const defaultItems = [
        { id: 'trend', icon: '📈', label: 'Show trend over time' },
        { id: 'subscribers', icon: '👥', label: 'View subscribers' }
    ];

    let items = includeDefaults ? [...defaultItems] : [];

    // Add divider if we have chart-specific items
    if (chartSpecificItems.length > 0) {
        items.push({ type: 'divider' });
        items = items.concat(chartSpecificItems);
    }

    return new ContextMenu({
        items: items,
        onSelect: options.onSelect || (() => {})
    });
}

// Export for use in other modules
window.ContextMenu = ContextMenu;
window.createChartContextMenu = createChartContextMenu;

console.log('ContextMenu module loaded');
