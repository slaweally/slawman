import '@tabler/core/dist/js/tabler.min.js';
import Modal from 'bootstrap/js/dist/modal';
import Chart from 'chart.js/auto';
import '../styles/admin.scss';

/* ── Helpers ──────────────────────────────────────────────────────── */

function onReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function parseJsonData(value, fallback = []) {
    if (!value) return fallback;
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : fallback;
    } catch {
        return fallback;
    }
}

/* ── Quick Note ───────────────────────────────────────────────────── */

function initQuickNote() {
    const form = document.getElementById('quick-note-form');
    if (!form) return;

    const textarea   = form.querySelector('[name="content"]');
    const submitBtn  = form.querySelector('[type="submit"]');
    const feedback   = form.querySelector('.adm-quick-note__feedback');
    const counter    = form.querySelector('.adm-char-count span');
    const action     = form.getAttribute('action') || form.dataset.action;
    const noteLabel  = form.dataset.noteLabel ?? 'Not';

    if (!textarea || !submitBtn || !action) return;

    const updateCounter = () => {
        if (counter) counter.textContent = textarea.value.length;
    };
    updateCounter();
    textarea.addEventListener('input', updateCounter);

    const setFeedback = (message, type = 'muted') => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.className = 'adm-quick-note__feedback small';
        if (type === 'success') feedback.classList.add('text-success');
        else if (type === 'error') feedback.classList.add('text-danger');
        else feedback.style.color = 'var(--adm-muted)';
    };

    const setLoading = (loading) => {
        submitBtn.disabled = loading;
        submitBtn.classList.toggle('disabled', loading);
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const content = textarea.value.trim();
        if (!content) {
            setFeedback('İçerik boş olamaz.', 'error');
            textarea.focus();
            return;
        }

        setLoading(true);
        setFeedback('Yayınlanıyor…');

        try {
            const response = await fetch(action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            let data = null;
            try { data = await response.json(); } catch { throw new Error('Sunucu yanıtı okunamadı.'); }

            if (!response.ok || !data?.success) throw new Error(data?.error ?? 'Not yayınlanamadı.');

            textarea.value = '';
            updateCounter();
            setFeedback(data.message ?? 'Not yayınlandı.', 'success');

            const noteCount = document.querySelector('[data-dash-note-count]');
            if (noteCount) noteCount.textContent = String((parseInt(noteCount.textContent, 10) || 0) + 1);

            const list = document.getElementById('dash-recent-posts');
            if (list && data.editUrl) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="adm-table__primary"><a href="${data.editUrl}">${escapeHtml(data.preview ?? 'Yeni not')}</a></div>
                        <div class="adm-table__sub">${escapeHtml(noteLabel)}</div>
                    </td>
                    <td class="adm-table__meta text-end">${escapeHtml(data.createdAt ?? '')}</td>
                `;
                list.prepend(row);
                while (list.children.length > 6) list.lastElementChild?.remove();
            }
        } catch (error) {
            setFeedback(error instanceof Error ? error.message : 'Beklenmeyen hata.', 'error');
        } finally {
            setLoading(false);
        }
    });
}

/* ── Media Upload ─────────────────────────────────────────────────── */

function initMediaUpload() {
    const form = document.getElementById('upload-form');
    if (!form) return;

    const trigger = form.querySelector('[data-media-upload-trigger]');
    if (!trigger) return;

    trigger.addEventListener('click', async () => {
        const endpoint = form.getAttribute('action');
        if (!endpoint) return;

        trigger.disabled = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok || !data?.url) throw new Error(data?.error ?? 'Yükleme başarısız.');
            window.location.reload();
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'Beklenmeyen hata.');
        } finally {
            trigger.disabled = false;
        }
    });
}

/* ── Settings: logo / favicon / media assets ─────────────────────── */

function initSettingsMediaAssets() {
    const form = document.querySelector('form[data-media-upload-url]');
    if (!form) return;

    const uploadUrl = form.dataset.mediaUploadUrl || '';
    const listUrl = form.dataset.mediaListUrl || '';
    const token = form.dataset.uploadToken || '';
    const modalEl = document.getElementById('mediaPickerModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let activeInputId = null;

    const setPreview = (inputId, url) => {
        const input = document.getElementById(inputId);
        if (input) input.value = url;
        const field = document.querySelector(`[data-target-input="${inputId}"]`);
        const preview = field?.querySelector('.media-asset-field__preview');
        if (preview) {
            preview.innerHTML = url ? `<img src="${escapeHtml(url)}" alt="">` : '';
        }
        document.querySelectorAll(`[data-media-clear="${inputId}"]`).forEach((btn) => {
            btn.hidden = !url;
        });
    };

    const pick = (url) => {
        if (activeInputId) setPreview(activeInputId, url);
        modal?.hide();
        activeInputId = null;
    };

    async function loadGallery() {
        const grid = document.getElementById('media-picker-grid');
        if (!grid) return;
        grid.innerHTML = '<p class="text-center py-3">Yükleniyor…</p>';
        try {
            const response = await fetch(listUrl, { credentials: 'same-origin' });
            const data = await response.json();
            const items = data.items || [];
            if (!items.length) {
                grid.innerHTML = '<p class="text-muted text-center py-3">Görsel yok</p>';
                return;
            }
            grid.innerHTML = items.map((item) =>
                `<button type="button" class="media-pick" data-url="${escapeHtml(item.url)}"><img src="${escapeHtml(item.thumb || item.url)}" alt=""><span>${escapeHtml(item.name)}</span></button>`
            ).join('');
            grid.querySelectorAll('.media-pick').forEach((btn) => {
                btn.addEventListener('click', () => pick(btn.dataset.url || ''));
            });
        } catch {
            grid.innerHTML = '<p class="text-danger text-center py-3">Galeri yüklenemedi</p>';
        }
    }

    document.querySelectorAll('[data-media-pick]').forEach((btn) => {
        btn.addEventListener('click', () => {
            activeInputId = btn.getAttribute('data-media-pick');
            loadGallery();
            modal?.show();
        });
    });

    document.querySelectorAll('[data-media-clear]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const inputId = btn.getAttribute('data-media-clear');
            if (inputId) setPreview(inputId, '');
        });
    });

    document.querySelectorAll('[data-media-upload]').forEach((input) => {
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            const inputId = input.getAttribute('data-media-upload');
            if (!file || !inputId || !uploadUrl) return;

            const body = new FormData();
            body.append('file', file);
            body.append('_token', token);

            try {
                const response = await fetch(uploadUrl, { method: 'POST', body, credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.url) throw new Error(data.error || 'Yükleme başarısız');
                setPreview(inputId, data.url);
                input.value = '';
            } catch (error) {
                window.alert(error instanceof Error ? error.message : 'Dosya yüklenemedi');
            }
        });
    });

    document.getElementById('media-picker-upload')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file || !uploadUrl) return;

        const body = new FormData();
        body.append('file', file);
        body.append('_token', token);

        try {
            const response = await fetch(uploadUrl, { method: 'POST', body, credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok || !data.url) throw new Error(data.error || 'Yükleme başarısız');
            if (activeInputId) setPreview(activeInputId, data.url);
            event.target.value = '';
            modal?.hide();
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'Yükleme başarısız');
        }
    });
}

/* ── Sidebar Widget: dinamik koleksiyon (sosyal linkler) ────────────── */

function initWidgetCollections() {
    document.querySelectorAll('[data-collection-holder]').forEach((holder) => {
        let index = parseInt(holder.dataset.index || '0', 10);

        const bindRemove = (item) => {
            item.querySelector('[data-collection-remove]')?.addEventListener('click', () => {
                item.remove();
            });
        };

        holder.querySelectorAll('[data-collection-item]').forEach(bindRemove);

        const addBtn = holder.parentElement?.querySelector('[data-collection-add]');
        addBtn?.addEventListener('click', () => {
            const iconHtml = (holder.dataset.prototypeIcon || '').replace(/__name__/g, String(index));
            const urlHtml = (holder.dataset.prototypeUrl || '').replace(/__name__/g, String(index));
            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
                <div class="row g-2 align-items-start mb-2" data-collection-item>
                    <div class="col-md-5">${iconHtml}</div>
                    <div class="col-md-6">${urlHtml}</div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-collection-remove>
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>`;
            const item = wrapper.firstElementChild;
            if (!item) return;

            holder.appendChild(item);
            bindRemove(item);
            index += 1;
        });
    });

    const typeSelect = document.querySelector('[data-widget-type-select]');
    const socialSection = document.querySelector('[data-widget-social-links]');
    const archiveSection = document.querySelector('[data-widget-archive-icon]');
    if (typeSelect) {
        const toggle = () => {
            if (socialSection) socialSection.hidden = typeSelect.value !== 'profile';
            if (archiveSection) archiveSection.hidden = typeSelect.value !== 'archive';
        };
        typeSelect.addEventListener('change', toggle);
        toggle();
    }
}

/* ── Clipboard ────────────────────────────────────────────────────── */

function initClipboard() {
    document.querySelectorAll('[data-copy-target]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const input = document.querySelector(btn.getAttribute('data-copy-target'));
            if (!(input instanceof HTMLInputElement)) return;
            try {
                await navigator.clipboard.writeText(input.value);
                btn.classList.add('adm-btn--primary');
                setTimeout(() => btn.classList.remove('adm-btn--primary'), 900);
            } catch {
                window.alert('Kopyalama başarısız.');
            }
        });
    });
}

/* ── Confirm Actions ──────────────────────────────────────────────── */

function initConfirm() {
    document.querySelectorAll('[data-confirm-message]').forEach((el) => {
        el.addEventListener('click', (e) => {
            const msg = el.getAttribute('data-confirm-message');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
}

/* ── Stat Counter Animation ───────────────────────────────────────── */

function animateCounters() {
    const els = document.querySelectorAll('[data-count-to]');
    if (!els.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);

            const el     = entry.target;
            const target = parseInt(el.dataset.countTo, 10);
            const dur    = 700;
            const start  = performance.now();

            const tick = (now) => {
                const elapsed = now - start;
                const progress = Math.min(elapsed / dur, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(eased * target);
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });
    }, { threshold: 0.3 });

    els.forEach((el) => observer.observe(el));
}

/* ── Dashboard Charts ─────────────────────────────────────────────── */

const CHART_DEFAULTS = {
    font: { family: 'Inter, sans-serif', size: 12 },
    color: '#7b8aab',
};

function initDashboardCharts() {
    if (typeof Chart === 'undefined') return;

    Chart.defaults.font = CHART_DEFAULTS.font;
    Chart.defaults.color = CHART_DEFAULTS.color;

    const safeInit = (name, fn) => {
        try { fn(); } catch (e) { console.error(`Chart init failed (${name})`, e); }
    };

    /* Traffic line chart */
    const trafficCanvas = document.getElementById('dashboard-traffic-chart');
    if (trafficCanvas) {
        safeInit('traffic', () => {
            const labels   = parseJsonData(trafficCanvas.dataset.labels);
            const visitors = parseJsonData(trafficCanvas.dataset.visitors);
            const views    = parseJsonData(trafficCanvas.dataset.views);

            new Chart(trafficCanvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Ziyaretçi',
                            data: visitors,
                            borderColor: '#4361EE',
                            backgroundColor: 'rgba(67,97,238,.08)',
                            fill: true,
                            borderWidth: 2,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#4361EE',
                        },
                        {
                            label: 'Görüntülenme',
                            data: views,
                            borderColor: '#2fb344',
                            backgroundColor: 'rgba(47,179,68,.08)',
                            fill: true,
                            borderWidth: 2,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#2fb344',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 16 },
                        },
                        tooltip: {
                            backgroundColor: '#1a2036',
                            titleColor: '#fff',
                            bodyColor: '#8899b4',
                            padding: 12,
                            cornerRadius: 8,
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#f0f2f7' }, border: { display: false } },
                    },
                },
            });
        });
    }

    /* Engagement doughnut */
    const engagementCanvas = document.getElementById('dashboard-engagement-chart');
    if (engagementCanvas) {
        safeInit('engagement', () => {
            const labels = parseJsonData(engagementCanvas.dataset.labels);
            const values = parseJsonData(engagementCanvas.dataset.values);

            new Chart(engagementCanvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#f59f00', '#e03131', '#4361EE', '#2fb344'],
                        borderWidth: 0,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 },
                        },
                        tooltip: {
                            backgroundColor: '#1a2036',
                            padding: 10,
                            cornerRadius: 8,
                        },
                    },
                },
            });
        });
    }

    /* Storage bar chart */
    const storageCanvas = document.getElementById('dashboard-storage-chart');
    if (storageCanvas) {
        safeInit('storage', () => {
            const labels   = parseJsonData(storageCanvas.dataset.labels);
            const values   = parseJsonData(storageCanvas.dataset.values);
            const valuesMb = values.map((v) => Math.round((Number(v) / (1024 * 1024)) * 10) / 10);

            new Chart(storageCanvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'MB',
                        data: valuesMb,
                        backgroundColor: ['#4361EE', '#f59f00', '#e03131', '#845ef7'],
                        borderRadius: 6,
                        borderSkipped: false,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: '#1a2036', padding: 10, cornerRadius: 8 },
                    },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#f0f2f7' }, border: { display: false } },
                    },
                },
            });
        });
    }

    /* System health pie (DB / Cache / PHP bellek dağılımı) */
    const healthPieCanvas = document.getElementById('dashboard-health-pie');
    if (healthPieCanvas) {
        safeInit('health-pie', () => {
            const labels = parseJsonData(healthPieCanvas.dataset.labels);
            const values = parseJsonData(healthPieCanvas.dataset.values).map((v) => Math.max(0, Number(v) || 0));
            const hasData = values.some((v) => v > 0);

            new Chart(healthPieCanvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: hasData ? values : [1, 1, 1],
                        backgroundColor: ['#4361EE', '#f59f00', '#2fb344'],
                        borderWidth: 0,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 14 },
                        },
                        tooltip: {
                            backgroundColor: '#1a2036',
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label(context) {
                                    if (!hasData) return context.label;
                                    const bytes = context.raw || 0;
                                    const mb = Math.round((bytes / 1048576) * 10) / 10;
                                    return `${context.label}: ${mb} MB`;
                                },
                            },
                        },
                    },
                },
            });
        });
    }
}

/* ── Sidebar: accordion toggles ──────────────────────────────────── */

function initSidebarAccordion() {
    document.querySelectorAll('[data-adm-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-adm-toggle');
            const sub = document.getElementById(targetId);
            if (!sub) return;

            const isOpen = sub.classList.contains('is-open');
            sub.classList.toggle('is-open', !isOpen);
            btn.setAttribute('aria-expanded', String(!isOpen));
        });
    });
}

/* ── Sidebar: mobile open/close ──────────────────────────────────── */

function initMobileSidebar() {
    const sidebar  = document.getElementById('adm-sidebar');
    const backdrop = document.getElementById('adm-backdrop');
    const menuBtn  = document.getElementById('adm-menu-btn');
    if (!sidebar || !backdrop || !menuBtn) return;

    const open  = () => { sidebar.classList.add('is-open');  backdrop.classList.add('is-visible'); };
    const close = () => { sidebar.classList.remove('is-open'); backdrop.classList.remove('is-visible'); };

    menuBtn.addEventListener('click', open);
    backdrop.addEventListener('click', close);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) close();
    });
}

/* ── Menü sürükle-bırak (Nestable/SortableJS mantığı, native HTML5 D&D) ── */

function serializeNestableList(listEl) {
    const tree = [];
    listEl.querySelectorAll(':scope > .menu-nestable__item').forEach((li) => {
        const id = parseInt(li.dataset.id, 10);
        const nestedList = li.querySelector(':scope > .menu-nestable__list--nested');
        const children = nestedList ? serializeNestableList(nestedList) : [];
        tree.push(children.length ? { id, children } : { id });
    });
    return tree;
}

function initMenuNestable() {
    document.querySelectorAll('.menu-nestable').forEach((root) => {
        if (root.dataset.nestableInitialized === '1') return;
        root.dataset.nestableInitialized = '1';

        const location = root.dataset.menuLocation;
        const saveUrl = root.dataset.saveUrl;
        const csrfToken = root.dataset.csrfToken;
        const statusEl = document.querySelector(`[data-save-status="${location}"]`);
        let draggedItem = null;
        let saveTimer = null;

        root.querySelectorAll('.menu-nestable__item').forEach((item) => {
            item.addEventListener('dragstart', (event) => {
                draggedItem = item;
                event.dataTransfer.effectAllowed = 'move';
                item.classList.add('is-dragging');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('is-dragging');
                root.querySelectorAll('.is-drop-target, .is-drop-before, .is-drop-after').forEach((el) => {
                    el.classList.remove('is-drop-target', 'is-drop-before', 'is-drop-after');
                });
            });

            item.addEventListener('dragover', (event) => {
                if (!draggedItem || draggedItem === item || item.contains(draggedItem)) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';

                const rect = item.getBoundingClientRect();
                const offsetX = event.clientX - rect.left;
                const offsetY = event.clientY - rect.top;

                item.classList.remove('is-drop-target', 'is-drop-before', 'is-drop-after');

                // Sağ tarafa (indent bölgesi) veya satırın orta-alt kısmına bırakılırsa: child yap.
                if (offsetX > 40 && offsetY > rect.height * 0.3) {
                    item.classList.add('is-drop-target');
                } else if (offsetY < rect.height / 2) {
                    item.classList.add('is-drop-before');
                } else {
                    item.classList.add('is-drop-after');
                }
            });

            item.addEventListener('drop', (event) => {
                if (!draggedItem || draggedItem === item) return;
                event.preventDefault();

                if (item.classList.contains('is-drop-target')) {
                    let nestedList = item.querySelector(':scope > .menu-nestable__list--nested');
                    if (!nestedList) {
                        nestedList = document.createElement('ol');
                        nestedList.className = 'menu-nestable__list menu-nestable__list--nested';
                        nestedList.dataset.nestedFor = item.dataset.id;
                        item.appendChild(nestedList);
                    }
                    nestedList.appendChild(draggedItem);
                } else if (item.classList.contains('is-drop-before')) {
                    item.parentElement.insertBefore(draggedItem, item);
                } else {
                    item.parentElement.insertBefore(draggedItem, item.nextSibling);
                }

                item.classList.remove('is-drop-target', 'is-drop-before', 'is-drop-after');
                scheduleSave();
            });
        });

        function scheduleSave() {
            if (statusEl) statusEl.textContent = 'Kaydediliyor…';
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                const topList = root.querySelector(':scope > .menu-nestable__list');
                const tree = serializeNestableList(topList);

                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: JSON.stringify({ location, tree }),
                })
                    .then((r) => r.json())
                    .then((res) => {
                        if (statusEl) statusEl.textContent = res.ok ? 'Kaydedildi ✓' : 'Hata: kaydedilemedi';
                    })
                    .catch(() => {
                        if (statusEl) statusEl.textContent = 'Hata: bağlantı başarısız';
                    });
            }, 400);
        }
    });
}

/* ── Bootstrap ────────────────────────────────────────────────────── */

onReady(initSidebarAccordion);
onReady(initMobileSidebar);
onReady(initQuickNote);
onReady(initMediaUpload);
onReady(initSettingsMediaAssets);
onReady(initWidgetCollections);
onReady(initClipboard);
onReady(initConfirm);
onReady(animateCounters);
onReady(initDashboardCharts);
window.initMenuNestable = initMenuNestable;
onReady(initMenuNestable);