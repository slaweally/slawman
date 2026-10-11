import '../styles/app.css';
import Alpine from '@alpinejs/csp';
import 'prismjs/themes/prism-tomorrow.min.css';
import Prism from 'prismjs';
import 'prismjs/components/prism-markup';
import 'prismjs/components/prism-css';
import 'prismjs/components/prism-javascript';
import 'prismjs/components/prism-typescript';
import 'prismjs/components/prism-php';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-sql';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-yaml';
import 'prismjs/components/prism-python';
import 'prismjs/components/prism-go';
import 'prismjs/components/prism-docker';
import 'prismjs/components/prism-markdown';

window.Prism = Prism;
window.Alpine = Alpine;

Alpine.data('footerTags', () => ({
    tags: [],
    visible: [],
    extra: 0,
    more: 0,
    moreText: '',
    moreLabel: '',
    _ro: null,
    init() {
        const raw = this.$el.dataset.tags ?? '[]';
        this.extra = Number.parseInt(this.$el.dataset.extra ?? '0', 10) || 0;
        try {
            this.tags = JSON.parse(raw).slice(0, 5);
        } catch {
            this.tags = [];
        }
        this.visible = [...this.tags];
        this.$nextTick(() => this.reflow());
        this._ro = new ResizeObserver(() => this.reflow());
        if (this.$refs.row) {
            this._ro.observe(this.$refs.row);
        }
    },
    reflow() {
        const row = this.$refs.row;
        if (!row || this.tags.length === 0) {
            this.more = this.extra;
            this.updateMore();
            return;
        }

        let count = this.tags.length;
        const tryFit = () => {
            this.visible = this.tags.slice(0, count);
            const hiddenInRow = this.tags.length - count;
            this.more = hiddenInRow + this.extra;
            this.$nextTick(() => {
                if (row.scrollWidth > row.clientWidth && count > 0) {
                    count -= 1;
                    tryFit();
                } else {
                    this.updateMore();
                }
            });
        };
        tryFit();
    },
    updateMore() {
        const hiddenInRow = this.tags.length - this.visible.length;
        this.more = hiddenInRow + this.extra;
        if (this.more <= 0) {
            this.moreText = '';
            this.moreLabel = '';
            return;
        }
        this.moreText = this.more === 1 ? '…' : `… +${this.more}`;
        this.moreLabel = `${this.more} etiket daha`;
    },
    destroy() {
        this._ro?.disconnect();
    },
}));

Alpine.data('trackerPanel', () => ({
    open: false,
}));

Alpine.data('stickyNotes', () => ({
    modalOpen: false,
    active: null,
    openNote(event) {
        const el = event.currentTarget;
        el.classList.add('is-unpinning');
        window.setTimeout(() => {
            this.active = {
                content: el.dataset.content ?? '',
                date: el.dataset.date ?? '',
                url: el.dataset.url ?? '#',
            };
            this.modalOpen = true;
            document.body.classList.add('overflow-hidden');
            el.classList.remove('is-unpinning');
        }, 280);
    },
    closeModal() {
        this.modalOpen = false;
        this.active = null;
        document.body.classList.remove('overflow-hidden');
    },
}));

Alpine.start();

function initCodeBlocks() {
    document.querySelectorAll('[data-copy-code]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', async () => {
            const block = button.closest('[data-code-block]');
            const code = block?.querySelector('code');
            if (!code) return;
            try {
                await navigator.clipboard.writeText(code.textContent ?? '');
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Kopyalandı';
                button.classList.add('is-copied');
                window.setTimeout(() => {
                    button.innerHTML = original;
                    button.classList.remove('is-copied');
                }, 2000);
            } catch {
                button.textContent = 'Kopyalanamadı';
            }
        });
    });
    if (window.Prism) Prism.highlightAll();
}

function initTurnstile() {
    if (!window.turnstile) return;

    document.querySelectorAll('[data-turnstile]').forEach((container) => {
        if (container.dataset.rendered === '1') return;
        const sitekey = container.dataset.sitekey;
        if (!sitekey) return;

        const form = container.closest('form');
        const tokenInput = form?.querySelector('[name$="[cf_turnstile_response]"]');
        if (!tokenInput) return;

        container.dataset.rendered = '1';
        window.turnstile.render(container, {
            sitekey,
            callback: (token) => { tokenInput.value = token; },
            'expired-callback': () => { tokenInput.value = ''; },
        });
    });
}

window.onTurnstileLoad = initTurnstile;

function initScrollTop() {
    const btn = document.getElementById('scroll-top');
    const footer = document.querySelector('.site-footer');
    if (!btn || !footer) return;

    const update = () => {
        const footerTop = footer.getBoundingClientRect().top;
        const nearFooter = footerTop < window.innerHeight + 120;
        const scrolled = window.scrollY > 480;
        const show = scrolled && (nearFooter || window.scrollY > 900);
        btn.hidden = !show;
        btn.classList.toggle('is-visible', show);
    };

    window.addEventListener('scroll', update, { passive: true });
    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    update();
}

function initMobileNav() {
    const toggle = document.getElementById('mobile-nav-toggle');
    const nav = document.getElementById('site-nav');
    const header = document.querySelector('.site-header');
    if (!toggle || !nav) {
        return;
    }

    const icon = toggle.querySelector('.bi');
    let open = false;

    const setOpen = (value) => {
        open = value;
        nav.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (icon) {
            icon.classList.toggle('bi-list', !open);
            icon.classList.toggle('bi-x-lg', open);
        }
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        setOpen(!open);
    });

    nav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('click', (event) => {
        if (!open || !event.target) {
            return;
        }
        if (header?.contains(event.target)) {
            return;
        }
        setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function initTrackerFeed() {
    const btn = document.getElementById('tracker-load-more');
    const list = document.getElementById('tracker-log-list');
    if (!btn || !list) return;

    btn.addEventListener('click', async () => {
        const offset = parseInt(btn.dataset.offset || '0', 10);
        btn.disabled = true;
        try {
            const response = await fetch(`/tracker-feed?offset=${offset}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            const items = data.items || [];
            items.forEach((item) => {
                const li = document.createElement('li');
                li.className = item.type === 'alert' ? 'tracker-panel__entry tracker-panel__entry--alert' : 'tracker-panel__entry';
                li.innerHTML = `<span class="tracker-panel__prompt">&gt;</span><span class="tracker-panel__time">${escapeHtml(item.time)}</span><span class="tracker-panel__msg">${item.messageHtml}</span>`;
                list.appendChild(li);
            });
            btn.dataset.offset = String(offset + items.length);
            if (!data.hasMore) {
                btn.remove();
                return;
            }
        } catch {
            // sessiz geç, buton tekrar denenebilir
        }
        btn.disabled = false;
    });
}

const ARCHIVE_TYPE_LABELS = {
    article: 'Makale', note: 'Not', project: 'Proje', software: 'Yazılım',
    snippet: 'Kod Parçası', page: 'Sayfa', howto: 'Rehber',
    troubleshoot: 'Sorun Giderme', comparison: 'Karşılaştırma',
};

function formatArchiveDate(iso) {
    const d = new Date(String(iso).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function loadArchiveMonth(monthLi) {
    const year = monthLi.dataset.year;
    const month = monthLi.dataset.month;
    const list = monthLi.querySelector(`[data-items-for="${year}-${month}"]`);
    if (!list || list.dataset.loaded === '1') return;

    list.innerHTML = `<li class="archive-tree__loading">$ fetch --month=${month} --year=${year} ...</li>`;

    fetch(`/arsiv/${year}/${month}`, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            list.dataset.loaded = '1';
            const items = data.items || [];
            if (items.length === 0) {
                list.innerHTML = '<li class="archive-tree__empty">-- boş --</li>';
                return;
            }
            list.innerHTML = items.map((item) => `
                <li class="archive-tree__item">
                    <span class="archive-tree__item-prompt">-</span>
                    <a href="${item.url}" class="archive-tree__item-link">${escapeHtml(item.title)}</a>
                    <span class="archive-tree__item-type">${escapeHtml(ARCHIVE_TYPE_LABELS[item.type] || item.type)}</span>
                    <time class="archive-tree__item-date">${formatArchiveDate(item.publishedAt)}</time>
                </li>
            `).join('');
        })
        .catch(() => {
            list.innerHTML = '<li class="archive-tree__empty text-danger">İstek başarısız oldu.</li>';
        });
}

/**
 * CSP script-src politikası inline <script> çalıştırmaya izin vermediği için
 * (ve AJAX ile sonradan DOM'a eklenen linklere ayrı ayrı listener bağlamamak
 * için) tek bir delege edilmiş click listener kullanılır — kapsayıcı üstünde
 * dinlenir, tıklanan öğe closest() ile bulunur.
 */
function initArchiveTree() {
    const tree = document.getElementById('archive-tree');
    if (!tree) return;

    tree.addEventListener('click', (event) => {
        const yearToggle = event.target.closest('.archive-tree__year-toggle');
        if (yearToggle) {
            yearToggle.closest('.archive-tree__year').classList.toggle('is-open');
            return;
        }

        const monthToggle = event.target.closest('.archive-tree__month-toggle');
        if (monthToggle) {
            const monthLi = monthToggle.closest('.archive-tree__month');
            const isOpen = monthLi.classList.toggle('is-open');
            if (isOpen) loadArchiveMonth(monthLi);
        }
    });
}

function initNavDropdowns() {
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('.site-nav__dropdown-toggle');
        const openDropdown = document.querySelector('.site-nav__dropdown.is-open');

        if (toggle) {
            const dropdown = toggle.closest('.site-nav__dropdown');
            const wasOpen = dropdown.classList.contains('is-open');
            if (openDropdown && openDropdown !== dropdown) openDropdown.classList.remove('is-open');
            dropdown.classList.toggle('is-open', !wasOpen);
            toggle.setAttribute('aria-expanded', String(!wasOpen));
            return;
        }

        if (openDropdown && !event.target.closest('.site-nav__dropdown')) {
            openDropdown.classList.remove('is-open');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCodeBlocks();
    initTurnstile();
    initScrollTop();
    initMobileNav();
    initTrackerFeed();
    initArchiveTree();
    initNavDropdowns();
});
