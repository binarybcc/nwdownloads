/**
 * Donut-to-State Animation System
 *
 * "Shooting Icon" animation that transitions from source element to target with:
 * - Crossfade from source (240ms)
 * - Emerge at 20% opacity
 * - Fade to 100% during first 40% of movement
 * - Stay visible at 100% for 45% of journey
 * - Quick fade-out in last 15%
 *
 * CRITICAL: Target position calculated AFTER layout stabilization (450ms delay)
 *
 * Documentation: /docs/animation-system-reference.md
 * Usage: animateDonutToState(businessUnit, donutElement, callback)
 *
 * Date: 2025-12-05
 */

class DonutToStateAnimator {
    constructor() {
        this.animationDuration = 800; // Total animation time
        this.crossfadeDuration = 240; // Donut fade + state fade (20% faster)
        this.moveDuration = 500; // Icon shrink and move
    }

    /**
     * Animate donut chart to state icon
     * @param {string} businessUnit - Business unit name
     * @param {HTMLElement} donutElement - Donut chart container
     * @param {Function} onComplete - Callback when animation completes
     */
    async animateDonutToState(businessUnit, donutElement, onComplete) {
        console.log('🎬 Starting donut-to-state animation for:', businessUnit);

        // Step 1: Get donut position
        const donutRect = donutElement.getBoundingClientRect();
        const donutCenter = {
            x: donutRect.left + donutRect.width / 2,
            y: donutRect.top + donutRect.height / 2
        };

        // Step 2: Create state icon at donut position
        const stateIcon = this.createFloatingStateIcon(businessUnit, donutCenter);
        document.body.appendChild(stateIcon);

        // Step 3: Crossfade (donut out, state in)
        await this.crossfadeDonutToState(donutElement, stateIcon);

        // Step 4: Wait for panel to finish opening and layout to stabilize
        // Panel has 400ms transition, wait for it to complete + extra for layout
        await new Promise(resolve => setTimeout(resolve, 450));

        // Step 5: NOW calculate target position (after layout is stable)
        const targetPosition = this.calculateSidebarPosition(businessUnit);

        // Step 6: Shrink and move icon to sidebar
        await this.shrinkAndMove(stateIcon, donutCenter, targetPosition);

        // Step 7: Cleanup
        stateIcon.remove();

        if (onComplete) onComplete();
    }

    /**
     * Create floating state icon overlay
     */
    createFloatingStateIcon(businessUnit, position) {
        console.log('📍 Creating floating icon at position:', position);

        // Just use the IMG element directly (no wrapper div background)
        const img = document.createElement('img');
        const iconPath = getStateIconPath(businessUnit);
        img.src = iconPath;
        img.alt = businessUnit;
        img.className = 'floating-state-icon';
        img.style.cssText = `
            position: fixed;
            left: ${position.x}px;
            top: ${position.y}px;
            width: 200px;
            height: 200px;
            transform: translate(-50%, -50%);
            z-index: 99999;
            opacity: 0;
            pointer-events: none;
            object-fit: contain;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,0.4));
        `;

        // Debug: Check if image loads
        img.onload = () => console.log('✅ State icon image loaded:', iconPath);
        img.onerror = () => console.error('❌ Failed to load state icon:', iconPath);

        console.log('✅ Floating icon created with image src:', iconPath);
        return img;
    }

    /**
     * Crossfade: Donut fades out, state icon starts nearly transparent
     */
    async crossfadeDonutToState(donutElement, stateIcon) {
        console.log('🔄 Starting crossfade: donut → state (nearly transparent)');

        return new Promise(resolve => {
            // Fade out donut quickly
            donutElement.style.transition = `opacity ${this.crossfadeDuration}ms ease-out`;
            donutElement.style.opacity = '0';
            console.log('  ↓ Donut fading out...');

            // Fade in state icon to only 20% opacity (nearly transparent)
            // It will continue fading in during the move
            stateIcon.style.transition = `opacity ${this.crossfadeDuration * 0.7}ms ease-in`;
            stateIcon.style.opacity = '0.2';
            console.log('  ↑ State icon fading in to 20% opacity...');

            setTimeout(() => {
                console.log('✅ Crossfade complete (state at 20% opacity)');
                resolve();
            }, this.crossfadeDuration);
        });
    }

    /**
     * Calculate where icon should end up in sidebar
     * Targets the CENTER of the entire .state-nav-item element
     */
    calculateSidebarPosition(businessUnit) {
        // Find the actual state navigation item for this business unit
        const stateNavItem = document.querySelector(`.state-nav-item[data-business-unit="${businessUnit}"]`);

        if (stateNavItem) {
            // Get the center of the ENTIRE state-nav-item (not just the icon wrapper)
            const rect = stateNavItem.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;

            console.log(`📍 Target position for ${businessUnit} (.state-nav-item center):`, { x: centerX, y: centerY });

            return {
                x: centerX,
                y: centerY
            };
        }

        // Fallback to estimated position if element not found
        console.warn(`⚠️ Could not find state nav item for ${businessUnit}, using fallback`);
        const stateOrder = ['South Carolina', 'Michigan', 'Wyoming'];
        const index = stateOrder.indexOf(businessUnit);
        const sidebarLeft = window.innerWidth * 0.18 + (window.innerWidth * 0.82 * 0.04);
        const sidebarTop = 150 + (index * 120);

        return {
            x: sidebarLeft,
            y: sidebarTop
        };
    }

    /**
     * Shrink icon and move to sidebar position while fading IN
     * "Shooting" effect - starts at 20% opacity, fades to full as it moves
     */
    async shrinkAndMove(stateIcon, startPos, endPos) {
        console.log('🎯 Shrinking and moving icon (fading IN during journey)');
        console.log('  Start:', startPos);
        console.log('  End:', endPos);

        return new Promise(resolve => {
            const deltaX = endPos.x - startPos.x;
            const deltaY = endPos.y - startPos.y;

            console.log('  Delta:', { x: deltaX, y: deltaY });

            // Move and shrink with easing, fade IN to full opacity FASTER
            stateIcon.style.transition = `
                transform ${this.moveDuration}ms cubic-bezier(0.4, 0, 0.2, 1),
                opacity ${this.moveDuration * 0.4}ms ease-in
            `;
            stateIcon.style.transform = `translate(calc(-50% + ${deltaX}px), calc(-50% + ${deltaY}px)) scale(0.25)`;

            // Fade to full opacity quickly (in first 40% of movement)
            stateIcon.style.opacity = '1';
            console.log('  ↑ Icon fading IN to 100% (first 40% of journey)...');

            // Stay at 100% until 85% of the journey, then quick fade out
            setTimeout(() => {
                stateIcon.style.transition = `opacity ${this.moveDuration * 0.15}ms ease-out`;
                stateIcon.style.opacity = '0';
                console.log('  ↓ Icon quick fade out at destination...');
            }, this.moveDuration * 0.85);

            setTimeout(() => {
                console.log('✅ Move complete - icon should be in sidebar now');
                resolve();
            }, this.moveDuration);
        });
    }

    /**
     * Reverse animation: State icon from sidebar back to donut
     * (For panel close animation)
     */
    async animateStateToDonut(businessUnit, donutElement) {
        // TODO: Implement reverse animation if needed
        console.log('Reverse animation not yet implemented');
    }
}

// Create global instance
window.donutAnimator = new DonutToStateAnimator();

console.log('Donut-to-State animation system loaded');
