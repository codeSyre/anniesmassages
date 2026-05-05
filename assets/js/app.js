"use strict";
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
if (sidebarToggle !== null) {
    sidebarToggle.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-open');
    });
}
const userMenuTrigger = document.querySelector('[data-user-menu-trigger]');
if (userMenuTrigger !== null) {
    userMenuTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const expanded = userMenuTrigger.getAttribute('aria-expanded') === 'true';
        userMenuTrigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    });
    userMenuTrigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            userMenuTrigger.click();
        }
        if (e.key === 'Escape') {
            userMenuTrigger.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('click', () => {
        userMenuTrigger.setAttribute('aria-expanded', 'false');
    });
}
const incomeTrendForm = document.querySelector('[data-income-trend-filter]');
const incomeTrendMonthInput = document.querySelector('[data-income-trend-month]');
if (incomeTrendForm !== null && incomeTrendMonthInput !== null) {
    const submitIncomeTrendMonth = () => {
        const value = incomeTrendMonthInput.value.trim();
        if (/^\d{4}-\d{2}$/.test(value)) {
            incomeTrendForm.requestSubmit();
        }
    };
    incomeTrendMonthInput.addEventListener('change', submitIncomeTrendMonth);
    incomeTrendMonthInput.addEventListener('search', submitIncomeTrendMonth);
}
const incomeTrendChartShell = document.querySelector('[data-income-trend-chart]');
if (incomeTrendChartShell !== null) {
    const chartSvg = incomeTrendChartShell.querySelector('.income-trend-chart');
    const hoverGuide = incomeTrendChartShell.querySelector('[data-income-trend-hover-guide]');
    const tooltip = incomeTrendChartShell.querySelector('[data-income-trend-tooltip]');
    const tooltipValue = incomeTrendChartShell.querySelector('[data-income-trend-tooltip-value]');
    const tooltipLabel = incomeTrendChartShell.querySelector('[data-income-trend-tooltip-label]');
    const pointsJson = incomeTrendChartShell.dataset.incomeTrendPoints ?? '[]';
    const chartTop = Number.parseFloat(incomeTrendChartShell.dataset.incomeTrendChartTop ?? '0');
    const chartBottom = Number.parseFloat(incomeTrendChartShell.dataset.incomeTrendChartBottom ?? '0');
    let points = [];
    try {
        const parsed = JSON.parse(pointsJson);
        if (Array.isArray(parsed)) {
            points = parsed.filter((point) => {
                if (typeof point !== 'object' || point === null) {
                    return false;
                }
                const candidate = point;
                return typeof candidate.x === 'number'
                    && typeof candidate.y === 'number'
                    && typeof candidate.label === 'string'
                    && typeof candidate.value === 'string';
            });
        }
    }
    catch {
        points = [];
    }
    if (chartSvg !== null
        && hoverGuide !== null
        && tooltip !== null
        && tooltipValue !== null
        && tooltipLabel !== null
        && points.length > 0) {
        const hideIncomeTrendTooltip = () => {
            tooltip.hidden = true;
            tooltip.classList.remove('is-below');
            incomeTrendChartShell.classList.remove('is-interacting');
        };
        const updateIncomeTrendTooltip = (event) => {
            const svgRect = chartSvg.getBoundingClientRect();
            const shellRect = incomeTrendChartShell.getBoundingClientRect();
            const viewBox = chartSvg.viewBox.baseVal;
            if (svgRect.width <= 0 || svgRect.height <= 0 || viewBox.width <= 0 || viewBox.height <= 0) {
                hideIncomeTrendTooltip();
                return;
            }
            const relativeX = ((event.clientX - svgRect.left) / svgRect.width) * viewBox.width;
            const nearestPoint = points.reduce((closest, point) => {
                return Math.abs(point.x - relativeX) < Math.abs(closest.x - relativeX) ? point : closest;
            }, points[0]);
            const tooltipX = ((nearestPoint.x / viewBox.width) * svgRect.width) + (svgRect.left - shellRect.left);
            const tooltipY = ((nearestPoint.y / viewBox.height) * svgRect.height) + (svgRect.top - shellRect.top);
            const clampedX = Math.min(Math.max(tooltipX, 88), Math.max(shellRect.width - 88, 88));
            hoverGuide.setAttribute('x1', nearestPoint.x.toFixed(2));
            hoverGuide.setAttribute('x2', nearestPoint.x.toFixed(2));
            hoverGuide.setAttribute('y1', chartTop.toFixed(2));
            hoverGuide.setAttribute('y2', chartBottom.toFixed(2));
            tooltipValue.textContent = nearestPoint.value;
            tooltipLabel.textContent = nearestPoint.label;
            tooltip.style.left = `${clampedX}px`;
            tooltip.style.top = `${tooltipY}px`;
            tooltip.classList.toggle('is-below', tooltipY < 76);
            tooltip.hidden = false;
            incomeTrendChartShell.classList.add('is-interacting');
        };
        chartSvg.addEventListener('mousemove', updateIncomeTrendTooltip);
        chartSvg.addEventListener('mouseenter', updateIncomeTrendTooltip);
        chartSvg.addEventListener('mouseleave', hideIncomeTrendTooltip);
        chartSvg.addEventListener('blur', hideIncomeTrendTooltip);
    }
}
const rowLinks = document.querySelectorAll('[data-row-link][data-row-link-href]');
rowLinks.forEach((rowLink) => {
    const href = rowLink.dataset.rowLinkHref?.trim();
    if (!href) {
        return;
    }
    const navigate = () => {
        window.location.href = href;
    };
    rowLink.addEventListener('click', (event) => {
        const target = event.target;
        if (target instanceof HTMLElement
            && target.closest('a, button, input, select, textarea, summary, [role="button"]') !== null) {
            return;
        }
        navigate();
    });
    rowLink.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            navigate();
        }
    });
});
//# sourceMappingURL=app.js.map