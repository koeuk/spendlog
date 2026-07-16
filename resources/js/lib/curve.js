/**
 * Monotone cubic interpolation (Fritsch–Carlson), the same curve d3 calls
 * curveMonotoneX.
 *
 * Why not a plain Catmull-Rom spline: it overshoots around spikes, and spend
 * data is all spikes — a $0 day next to a $80 one would bow the curve *below*
 * the baseline and draw negative spending. Monotone cannot overshoot: between
 * two points it never leaves their value range.
 *
 * @param {Array<{x: number, y: number}>} points
 * @returns {string} an SVG path `d`
 */
export function monotonePath(points) {
    const n = points.length;

    if (n === 0) {
        return '';
    }

    if (n === 1) {
        return `M ${points[0].x} ${points[0].y}`;
    }

    // Secant slopes between consecutive points.
    const dx = [];
    const dy = [];
    const slope = [];

    for (let i = 0; i < n - 1; i++) {
        dx[i] = points[i + 1].x - points[i].x;
        dy[i] = points[i + 1].y - points[i].y;
        slope[i] = dy[i] / dx[i];
    }

    // Tangent at each point.
    const m = new Array(n);
    m[0] = slope[0];
    m[n - 1] = slope[n - 2];

    for (let i = 1; i < n - 1; i++) {
        if (slope[i - 1] * slope[i] <= 0) {
            // A local peak or trough: flatten, or the curve sails past it.
            m[i] = 0;
        } else {
            const w1 = 2 * dx[i] + dx[i - 1];
            const w2 = dx[i] + 2 * dx[i - 1];
            m[i] = (w1 + w2) / (w1 / slope[i - 1] + w2 / slope[i]);
        }
    }

    let d = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < n - 1; i++) {
        const h = dx[i] / 3;
        d +=
            ` C ${points[i].x + h} ${points[i].y + m[i] * h}` +
            `, ${points[i + 1].x - h} ${points[i + 1].y - m[i + 1] * h}` +
            `, ${points[i + 1].x} ${points[i + 1].y}`;
    }

    return d;
}
