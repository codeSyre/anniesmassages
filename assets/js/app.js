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
const searchableSelectInputs = document.querySelectorAll('[data-searchable-select-input]');
searchableSelectInputs.forEach((searchInput) => {
    if (!(searchInput instanceof HTMLInputElement)) {
        return;
    }
    const listId = searchInput.getAttribute('list')?.trim() ?? '';
    const targetId = searchInput.dataset.searchableSelectTarget?.trim() ?? '';
    if (listId === '' || targetId === '') {
        return;
    }
    const dataList = document.getElementById(listId);
    const hiddenInput = document.getElementById(targetId);
    if (!(dataList instanceof HTMLDataListElement) || !(hiddenInput instanceof HTMLInputElement)) {
        return;
    }
    const options = Array.from(dataList.options)
        .map((option) => {
        const id = option.dataset.searchableSelectId?.trim() ?? '';
        const label = option.value.trim();
        return id !== '' && label !== '' ? { id, label } : null;
    })
        .filter((option) => option !== null);
    const emptyMessage = searchInput.dataset.searchableSelectEmptyMessage?.trim() || 'Select an option from the list.';
    const syncSearchableSelect = () => {
        const typedValue = searchInput.value.trim();
        const matchedOption = options.find((option) => option.label === typedValue);
        hiddenInput.value = matchedOption?.id ?? '';
        searchInput.setCustomValidity(matchedOption !== undefined ? '' : emptyMessage);
        return matchedOption !== undefined;
    };
    syncSearchableSelect();
    searchInput.addEventListener('input', () => {
        syncSearchableSelect();
    });
    searchInput.addEventListener('change', () => {
        const matched = syncSearchableSelect();
        if (matched && searchInput.dataset.searchableSelectSubmit === 'true') {
            searchInput.form?.requestSubmit();
        }
    });
    searchInput.form?.addEventListener('submit', () => {
        syncSearchableSelect();
    });
});
const tagEditors = document.querySelectorAll('[data-tag-editor]');
tagEditors.forEach((tagEditor) => {
    if (!(tagEditor instanceof HTMLElement)) {
        return;
    }
    const field = tagEditor.closest('.field');
    const hiddenInput = field?.querySelector('[data-tag-editor-value]') ?? null;
    const textInput = tagEditor.querySelector('[data-tag-editor-input]');
    const chipList = tagEditor.querySelector('[data-tag-editor-list]');
    if (!(hiddenInput instanceof HTMLInputElement)
        || !(textInput instanceof HTMLInputElement)
        || !(chipList instanceof HTMLElement)) {
        return;
    }
    const allowCustom = tagEditor.dataset.tagEditorAllowCustom !== 'false';
    const dataListId = textInput.getAttribute('list')?.trim() ?? '';
    const dataList = dataListId !== '' ? document.getElementById(dataListId) : null;
    const allowedOptions = dataList instanceof HTMLDataListElement
        ? Array.from(dataList.options)
            .map((option) => option.value.trim())
            .filter((option) => option !== '')
        : [];
    const invalidMessage = textInput.dataset.tagEditorEmptyMessage?.trim() || 'Select an existing option from the list.';
    const normalizeTag = (value) => value.replace(/\s+/g, ' ').trim();
    const currentTags = () => Array.from(chipList.querySelectorAll('[data-tag-editor-chip]'))
        .map((chip) => chip.dataset.tagValue ?? '')
        .map(normalizeTag)
        .filter((tag) => tag !== '');
    const syncHiddenInput = () => {
        hiddenInput.value = currentTags().join(', ');
    };
    const createChip = (tag) => {
        const chip = document.createElement('span');
        chip.className = 'tag-editor-chip';
        chip.dataset.tagEditorChip = '';
        chip.dataset.tagValue = tag;
        const label = document.createElement('span');
        label.textContent = tag;
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'tag-editor-chip-remove';
        removeButton.dataset.tagEditorRemove = '';
        removeButton.setAttribute('aria-label', `Remove ${tag}`);
        removeButton.textContent = '×';
        chip.append(label, removeButton);
        chipList.appendChild(chip);
    };
    const addTag = (rawValue) => {
        const tag = normalizeTag(rawValue);
        if (tag === '') {
            textInput.setCustomValidity('');
            return;
        }
        if (!allowCustom) {
            const matchedOption = allowedOptions.find((option) => option.toLowerCase() === tag.toLowerCase());
            if (matchedOption === undefined) {
                textInput.setCustomValidity(invalidMessage);
                textInput.reportValidity();
                return;
            }
            textInput.setCustomValidity('');
        }
        const existing = currentTags();
        if (existing.some((existingTag) => existingTag.toLowerCase() === tag.toLowerCase())) {
            textInput.value = '';
            textInput.setCustomValidity('');
            return;
        }
        createChip(allowCustom ? tag : (allowedOptions.find((option) => option.toLowerCase() === tag.toLowerCase()) ?? tag));
        textInput.value = '';
        textInput.setCustomValidity('');
        syncHiddenInput();
    };
    chipList.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const removeButton = target.closest('[data-tag-editor-remove]');
        if (!(removeButton instanceof HTMLElement)) {
            return;
        }
        removeButton.closest('[data-tag-editor-chip]')?.remove();
        syncHiddenInput();
        textInput.focus();
    });
    textInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addTag(textInput.value);
            return;
        }
        if (event.key === 'Backspace' && textInput.value.trim() === '') {
            const chips = chipList.querySelectorAll('[data-tag-editor-chip]');
            const lastChip = chips.item(chips.length - 1);
            if (lastChip instanceof HTMLElement) {
                lastChip.remove();
                syncHiddenInput();
            }
        }
    });
    textInput.addEventListener('change', () => {
        addTag(textInput.value);
    });
    textInput.addEventListener('blur', () => {
        addTag(textInput.value);
    });
    textInput.addEventListener('input', () => {
        textInput.setCustomValidity('');
    });
    syncHiddenInput();
});
//# sourceMappingURL=app.js.map
