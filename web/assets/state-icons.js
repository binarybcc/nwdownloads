/**
 * State Icon Images
 * Real state outlines (SVG format, scalable vector graphics)
 * Date: 2025-12-06 (Updated to use SVG files)
 */

/**
 * LOAD ORDER: 2 of 11
 *
 * DEPENDENCIES: None
 *
 * PROVIDES:
 * - getStateAbbr(businessUnit): Returns state abbreviation
 * - getStateIconImg(businessUnit): Returns img tag for state icon
 */

/* exported getStateIconImg, getStateColor */

const STATE_ICON_PATHS = {
    'South Carolina': 'assets/south_carolina_transparent.png',
    'Michigan': 'assets/michigan_transparent.png',
    'Wyoming': 'assets/wyoming_placeholder.svg'
};

/**
 * Get state icon image path by business unit name
 */
function getStateIconPath(businessUnit) {
    return STATE_ICON_PATHS[businessUnit] || STATE_ICON_PATHS['South Carolina'];
}

/**
 * Get state icon as IMG tag
 */
function getStateIconImg(businessUnit) {
    const path = getStateIconPath(businessUnit);
    const _abbr = getStateAbbr(businessUnit);
    return `<img src="${path}" alt="${businessUnit} state outline" class="state-icon" />`;
}

/**
 * Get state abbreviation
 */
function getStateAbbr(businessUnit) {
    const abbr = {
        'South Carolina': 'SC',
        'Michigan': 'MI',
        'Wyoming': 'WY'
    };
    return abbr[businessUnit] || '??';
}

/**
 * Get state color scheme
 */
function getStateColor(businessUnit) {
    const colors = {
        'South Carolina': '#0369A1',  // Blue
        'Michigan': '#10B981',         // Green
        'Wyoming': '#F59E0B'           // Amber
    };
    return colors[businessUnit] || '#0369A1';
}

console.log('State icons module loaded');
