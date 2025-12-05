/**
 * Chart Layout Manager with Drag & Drop
 * Allows users to rearrange charts and saves preferences to localStorage
 * Date: 2025-12-05
 */

class ChartLayoutManager {
    constructor() {
        this.defaultLayout = [
            'expiration',
            'rateDistribution',
            'subscriptionLength'
        ];

        this.currentLayout = this.loadLayout();
        this.draggedElement = null;
        this.draggedIndex = null;
    }

    /**
     * Load chart layout from localStorage
     */
    loadLayout() {
        try {
            const saved = localStorage.getItem('chartLayout');
            if (saved) {
                const parsed = JSON.parse(saved);
                // Validate that all expected charts are present
                if (this.validateLayout(parsed)) {
                    return parsed;
                }
            }
        } catch (error) {
            console.warn('Failed to load chart layout:', error);
        }
        return [...this.defaultLayout];
    }

    /**
     * Save chart layout to localStorage
     */
    saveLayout() {
        try {
            localStorage.setItem('chartLayout', JSON.stringify(this.currentLayout));
            console.log('Chart layout saved:', this.currentLayout);
        } catch (error) {
            console.error('Failed to save chart layout:', error);
        }
    }

    /**
     * Validate layout contains all required charts
     */
    validateLayout(layout) {
        if (!Array.isArray(layout) || layout.length !== this.defaultLayout.length) {
            return false;
        }
        const hasAll = this.defaultLayout.every(chart => layout.includes(chart));
        return hasAll;
    }

    /**
     * Get current layout order
     */
    getLayout() {
        return [...this.currentLayout];
    }

    /**
     * Reset layout to default
     */
    resetLayout() {
        this.currentLayout = [...this.defaultLayout];
        this.saveLayout();
        return this.currentLayout;
    }

    /**
     * Reorder chart at oldIndex to newIndex
     */
    reorderChart(oldIndex, newIndex) {
        if (oldIndex === newIndex) return;

        const layout = [...this.currentLayout];
        const [moved] = layout.splice(oldIndex, 1);
        layout.splice(newIndex, 0, moved);

        this.currentLayout = layout;
        this.saveLayout();
        return this.currentLayout;
    }

    /**
     * Initialize drag and drop for chart containers
     */
    initializeDragAndDrop() {
        const chartContainers = document.querySelectorAll('.chart-draggable');

        chartContainers.forEach((container, index) => {
            // Make draggable
            container.setAttribute('draggable', 'true');
            container.dataset.chartIndex = index;

            // Drag start
            container.addEventListener('dragstart', (e) => {
                this.draggedElement = container;
                this.draggedIndex = parseInt(container.dataset.chartIndex);
                container.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', container.innerHTML);
            });

            // Drag end
            container.addEventListener('dragend', (e) => {
                container.classList.remove('dragging');
                this.draggedElement = null;
                this.draggedIndex = null;

                // Remove all drag-over classes
                document.querySelectorAll('.chart-draggable').forEach(el => {
                    el.classList.remove('drag-over');
                });
            });

            // Drag over
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                if (this.draggedElement && this.draggedElement !== container) {
                    container.classList.add('drag-over');
                }
                return false;
            });

            // Drag enter
            container.addEventListener('dragenter', (e) => {
                if (this.draggedElement && this.draggedElement !== container) {
                    container.classList.add('drag-over');
                }
            });

            // Drag leave
            container.addEventListener('dragleave', (e) => {
                container.classList.remove('drag-over');
            });

            // Drop
            container.addEventListener('drop', (e) => {
                e.stopPropagation();
                e.preventDefault();

                container.classList.remove('drag-over');

                if (this.draggedElement && this.draggedElement !== container) {
                    const dropIndex = parseInt(container.dataset.chartIndex);

                    // Reorder in layout
                    const newLayout = this.reorderChart(this.draggedIndex, dropIndex);

                    // Trigger re-render
                    if (typeof window.renderDetailPanelCharts === 'function') {
                        window.renderDetailPanelCharts();
                    }
                }

                return false;
            });
        });

        console.log('Drag and drop initialized for', chartContainers.length, 'charts');
    }

    /**
     * Get chart metadata by ID
     */
    getChartMetadata(chartId) {
        const metadata = {
            expiration: {
                id: 'expiration',
                title: '4-Week Expiration View',
                icon: '📅',
                description: 'Subscriptions expiring over the next 4 weeks'
            },
            rateDistribution: {
                id: 'rateDistribution',
                title: 'Rate Distribution',
                icon: '💰',
                description: 'Breakdown of subscription rates'
            },
            subscriptionLength: {
                id: 'subscriptionLength',
                title: 'Subscription Length',
                icon: '📊',
                description: 'Distribution of subscription durations'
            }
        };
        return metadata[chartId] || { id: chartId, title: chartId, icon: '📈' };
    }
}

// Create global instance
window.chartLayoutManager = new ChartLayoutManager();

console.log('Chart Layout Manager loaded');
