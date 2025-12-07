# Animation System Reference

**Date**: 2025-12-05
**Author**: Claude
**Purpose**: Reusable animation patterns for UI transitions

---

## Donut-to-State Icon Animation

### Overview
A polished "shooting icon" animation that transitions from a source element (donut chart) to a target element (state navigation item) with opacity and scale transitions.

### Visual Effect Description
1. **Source fades out** (donut chart disappears)
2. **Icon emerges faintly** (20% opacity) at source position
3. **Icon materializes** while moving (fades to 100% opacity in first 40% of journey)
4. **Icon stays visible** at full opacity for most of the journey
5. **Quick fade out** at destination (last 15% of movement)

### Algorithm

#### Phase 1: Crossfade (240ms)
```javascript
// Source element fades out
sourceElement.style.transition = 'opacity 240ms ease-out';
sourceElement.style.opacity = '0';

// Floating icon fades in to 20% (nearly transparent)
floatingIcon.style.transition = 'opacity 168ms ease-in'; // 70% of crossfade
floatingIcon.style.opacity = '0.2';
```

#### Phase 2: Layout Stabilization (450ms)
```javascript
// Wait for panel/layout to finish rendering
await new Promise(resolve => setTimeout(resolve, 450));

// NOW calculate target position using getBoundingClientRect()
const targetRect = targetElement.getBoundingClientRect();
const targetCenter = {
    x: targetRect.left + targetRect.width / 2,
    y: targetRect.top + targetRect.height / 2
};
```

#### Phase 3: Movement with Opacity Transition (500ms)
```javascript
// Simultaneous transform and opacity changes
floatingIcon.style.transition = `
    transform 500ms cubic-bezier(0.4, 0, 0.2, 1),
    opacity 200ms ease-in  // 40% of movement
`;
floatingIcon.style.transform = `
    translate(calc(-50% + ${deltaX}px), calc(-50% + ${deltaY}px))
    scale(0.25)
`;
floatingIcon.style.opacity = '1'; // Fade to 100%

// At 85% of journey (425ms), quick fade out
setTimeout(() => {
    floatingIcon.style.transition = 'opacity 75ms ease-out'; // 15% of movement
    floatingIcon.style.opacity = '0';
}, 425);
```

### Key Timing Parameters

| Phase | Duration | Purpose |
|-------|----------|---------|
| Crossfade | 240ms | Quick transition from source to icon |
| Layout Wait | 450ms | Ensures target element is in final position |
| Movement | 500ms | Smooth travel with opacity changes |
| Fade-in | 200ms (40%) | Icon becomes fully visible |
| Full Opacity | 225ms (45%) | Icon clearly visible during approach |
| Fade-out | 75ms (15%) | Quick disappearance at destination |

**Total Animation Time**: ~1190ms (1.2 seconds)

---

## Critical Implementation Details

### 1. Positioning Calculation Timing
**CRITICAL**: Calculate target position AFTER layout stabilization, not before.

```javascript
// ❌ WRONG - calculates before layout is final
const targetPos = calculatePosition();
await layoutTransition();
animateToPosition(targetPos);

// ✅ RIGHT - calculates after layout is stable
await layoutTransition();
await new Promise(resolve => setTimeout(resolve, 50)); // Extra buffer
const targetPos = calculatePosition(); // NOW measure
animateToPosition(targetPos);
```

**Why**: DOM elements may not be in their final positions during CSS transitions. `getBoundingClientRect()` must be called after all layout changes complete.

### 2. Opacity Easing Functions

| Stage | Easing | Reason |
|-------|--------|--------|
| Fade-in | `ease-in` | Gradual appearance feels natural |
| Fade-out | `ease-out` | Quick disappearance at end |
| Transform | `cubic-bezier(0.4, 0, 0.2, 1)` | Material Design standard easing |

### 3. Z-Index Hierarchy

```
Floating Icon:  99999  (top)
Backdrop:       59
Panel:          60
Main Content:   1
```

**Why 99999**: Must appear above all other UI elements including backdrop overlay.

### 4. Transform Origin
```css
transform: translate(-50%, -50%); /* Centers element at x,y coordinates */
```

All positioning uses center-based coordinates for consistent behavior regardless of element size.

---

## Reusability Pattern

### Generic Animation Class Structure

```javascript
class ElementTransitionAnimator {
    constructor(options = {}) {
        this.crossfadeDuration = options.crossfadeDuration || 240;
        this.moveDuration = options.moveDuration || 500;
        this.layoutStabilizationDelay = options.layoutDelay || 450;

        // Opacity milestones (as percentage of movement duration)
        this.fadeInDuration = 0.4;  // First 40%
        this.fadeOutStart = 0.85;   // Start at 85%
        this.fadeOutDuration = 0.15; // Last 15%
    }

    async animate(sourceElement, targetElement, floatingElement, options = {}) {
        // 1. Get source position
        const startPos = this.getElementCenter(sourceElement);

        // 2. Create floating element at source
        const floating = this.createFloatingElement(floatingElement, startPos);

        // 3. Crossfade
        await this.crossfade(sourceElement, floating, options.initialOpacity || 0.2);

        // 4. Wait for layout
        await this.waitForLayout();

        // 5. Calculate target position
        const endPos = this.getElementCenter(targetElement);

        // 6. Move with opacity transition
        await this.moveWithFade(floating, startPos, endPos, options.scale || 0.25);

        // 7. Cleanup
        floating.remove();
    }

    getElementCenter(element) {
        const rect = element.getBoundingClientRect();
        return {
            x: rect.left + rect.width / 2,
            y: rect.top + rect.height / 2
        };
    }

    async waitForLayout() {
        return new Promise(resolve =>
            setTimeout(resolve, this.layoutStabilizationDelay)
        );
    }

    // ... other methods
}
```

---

## Usage Example

```javascript
// Initialize animator
const animator = new DonutToStateAnimator();

// Trigger animation
await animator.animateDonutToState(
    'South Carolina',  // Business unit name
    donutChartElement, // Source element
    () => {            // Completion callback
        console.log('Animation complete!');
    }
);
```

---

## Variations for Future Use

### Fast Transition (50% speed increase)
```javascript
crossfadeDuration: 160,  // -33%
moveDuration: 333        // -33%
```

### Slow, Dramatic Transition
```javascript
crossfadeDuration: 400,  // +67%
moveDuration: 800        // +60%
```

### No Fade-In (Immediate Visibility)
```javascript
// In crossfade method:
stateIcon.style.opacity = '1'; // Start at 100% instead of 0.2
```

### Bounce Effect at End
```javascript
// In moveWithFade method:
stateIcon.style.transition = `
    transform 500ms cubic-bezier(0.68, -0.55, 0.265, 1.55), // Bounce easing
    opacity 200ms ease-in
`;
```

---

## Performance Considerations

### GPU Acceleration
```css
will-change: transform, opacity;
transform: translateZ(0); /* Forces GPU layer */
```

**Use sparingly**: Only apply during animation, remove after completion.

### Image Preloading
```javascript
// Preload state icons on page load
const stateIcons = ['SC', 'MI', 'WY'];
stateIcons.forEach(state => {
    const img = new Image();
    img.src = `assets/${state}_transparent.png`;
});
```

### Debouncing
```javascript
// Prevent multiple simultaneous animations
let animationInProgress = false;

async function animateIfReady() {
    if (animationInProgress) return;
    animationInProgress = true;
    await animator.animate();
    animationInProgress = false;
}
```

---

## Debugging Tips

### Visual Debug Mode
```javascript
// Add colored border and slower timing
floatingIcon.style.border = '3px dashed red';
this.crossfadeDuration *= 2;
this.moveDuration *= 2;
```

### Console Logging
```javascript
console.log('📍 Start position:', startPos);
console.log('🎯 Target position:', endPos);
console.log('📐 Delta:', { x: deltaX, y: deltaY });
```

### Position Verification
```javascript
// Draw debug markers at start/end positions
function drawDebugMarker(x, y, color) {
    const marker = document.createElement('div');
    marker.style.cssText = `
        position: fixed;
        left: ${x}px;
        top: ${y}px;
        width: 10px;
        height: 10px;
        background: ${color};
        border-radius: 50%;
        z-index: 999999;
        transform: translate(-50%, -50%);
    `;
    document.body.appendChild(marker);
}

drawDebugMarker(startPos.x, startPos.y, 'red');
drawDebugMarker(endPos.x, endPos.y, 'green');
```

---

## Files

- **Implementation**: `/web/assets/donut-to-state-animation.js`
- **Usage**: `/web/assets/detail_panel.js` (line ~233)
- **Documentation**: `/docs/animation-system-reference.md` (this file)

---

## Future Enhancements

### Potential Improvements:
1. **Curved Path**: Bezier curve trajectory instead of straight line
2. **Rotation**: Rotate icon during movement
3. **Elastic Easing**: Spring-like bounce at destination
4. **Particle Trail**: SVG path following icon
5. **Sound Effects**: Subtle whoosh sound during movement
6. **Reverse Animation**: Animate back to donut on panel close

### Configuration Object Pattern:
```javascript
const config = {
    timing: {
        crossfade: 240,
        movement: 500,
        layoutDelay: 450
    },
    opacity: {
        initial: 0.2,
        fadeInDuration: 0.4,
        fadeOutStart: 0.85
    },
    transform: {
        startScale: 1.0,
        endScale: 0.25,
        easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
    }
};
```

---

## Testing Checklist

- [ ] Animation works at various window widths (responsive)
- [ ] Target position accurate after panel opens
- [ ] Icon reaches 100% opacity during movement
- [ ] Quick fade-out at destination (not premature)
- [ ] No flickering or jumps
- [ ] Smooth on mobile devices
- [ ] Works with all three business units (SC, MI, WY)
- [ ] Performance acceptable (60fps target)

---

*Last Updated: 2025-12-05*
