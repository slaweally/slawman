/**
 * CKEditor 5 editör — içerik formu.
 * Tom Select CDN üzerinden (form.html.twig <script> etiketleriyle) yüklenir;
 * CKEditor 5 npm paketinden bu dosyaya bundle edilir (self-hosted, GPL, API key gerekmez).
 */
import '../styles/admin-post-form.scss';
import 'ckeditor5/ckeditor5.css';
import Modal from 'bootstrap/js/dist/modal';
import {
    ClassicEditor,
    Essentials,
    Paragraph,
    Heading,
    Bold,
    Italic,
    Underline,
    Strikethrough,
    Link,
    List,
    ListProperties,
    BlockQuote,
    Table,
    TableToolbar,
    TableColumnResize,
    Image,
    ImageToolbar,
    ImageCaption,
    ImageStyle,
    ImageResize,
    ImageUpload,
    SimpleUploadAdapter,
    CodeBlock,
    SourceEditing,
    Alignment,
    HorizontalLine,
    Indent,
    IndentBlock,
    ButtonView,
} from 'ckeditor5';

/**
 * WordPress "Medya Ekle" tarzı toolbar butonu: mevcut medya galerisi modalını açar.
 * `onOpen` çağrısı formdaki openMedia('editor')'a bağlanır; seçim yapılınca pick(url)
 * editorInstance.model.change ile resmi imleç konumuna ekler (admin-post-form.js altında).
 */
function GalleryImagePlugin(onOpen) {
    return function GalleryImageEditorPlugin(editor) {
        editor.ui.componentFactory.add('insertImageFromGallery', (locale) => {
            const button = new ButtonView(locale);
            button.set({
                label: 'Galeriden Görsel Ekle',
                icon: '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M2 4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4Zm2 1v9.586l3.293-3.293a1 1 0 0 1 1.414 0L11 13.586l2.293-2.293a1 1 0 0 1 1.414 0L16 12.586V5H4Zm3 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/></svg>',
                tooltip: true,
            });
            button.on('execute', () => onOpen());

            return button;
        });
    };
}

const FULL_EDITOR_TOOLBAR = [
    'undo', 'redo', '|',
    'heading', '|',
    'bold', 'italic', 'underline', 'strikethrough', '|',
    'link', 'blockQuote', 'insertTable', 'uploadImage', 'insertImageFromGallery', 'horizontalLine', '|',
    'bulletedList', 'numberedList', 'outdent', 'indent', 'alignment', '|',
    'codeBlock', 'sourceEditing',
];

const BASIC_EDITOR_PLUGINS = [Essentials, Paragraph, Bold, Italic, Underline, Link, List];
const BASIC_EDITOR_TOOLBAR = ['undo', 'redo', '|', 'bold', 'italic', 'underline', '|', 'link', 'bulletedList', 'numberedList'];

const EDITOR_ID = 'post_content';
const SNIPPET_NOTES_EDITOR_ID = 'snippet-notes-editor';

/** @type {Record<string, string[]>} */
const DEFAULT_BLOCKS = {
    title: ['article', 'page', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
    'snippet-lang': ['snippet'],
    'snippet-notes': ['snippet'],
    howto: ['howto'],
    troubleshoot: ['troubleshoot'],
    comparison: ['comparison'],
    content: ['note', 'article', 'page', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
    excerpt: ['article', 'page', 'project', 'software', 'howto', 'troubleshoot', 'comparison'],
    project: ['project'],
    software: ['software'],
    seo: ['article', 'page', 'project', 'software', 'howto', 'troubleshoot', 'comparison'],
    featured: ['article', 'page', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
    taxonomy: ['article', 'project', 'software', 'snippet', 'howto', 'troubleshoot', 'comparison'],
    'publish-flags': ['article', 'project', 'software', 'snippet', 'note', 'howto', 'troubleshoot', 'comparison'],
};

/** @returns {Record<string, string[]>} */
function loadFormBlocks() {
    try {
        const el = document.getElementById('post-form-blocks');
        if (el?.textContent) {
            const parsed = JSON.parse(el.textContent);
            if (parsed && typeof parsed === 'object') {
                return parsed;
            }
        }
    } catch (e) {
        console.error('post-form-blocks:', e);
    }

    return DEFAULT_BLOCKS;
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('post-editor-form');
    if (!form) return;

    let typeConfig = {};
    try {
        const cfgEl = document.getElementById('post-type-config');
        if (cfgEl?.textContent) {
            typeConfig = JSON.parse(cfgEl.textContent);
        }
    } catch (e) {
        console.error('typeConfig:', e);
    }

    const tinymceUploadUrl = form.dataset.tinymceUploadUrl || form.dataset.mediaUploadUrl || '';
    const mediaUploadUrl = form.dataset.mediaUploadUrl || '';
    const softwareUploadUrl = form.dataset.softwareUploadUrl || mediaUploadUrl;
    const uploadToken = form.dataset.uploadToken || '';
    const mediaListUrl = form.dataset.mediaListUrl || '';
    const tagSearchUrl = form.dataset.tagSearchUrl || '';
    const tagCreateUrl = form.dataset.tagCreateUrl || '';
    const tagCreateToken = form.dataset.tagCreateToken || '';

    const BLOCKS = loadFormBlocks();
    const typeSelect = document.getElementById('post-type-select')
        || form.querySelector('select[name="post[type]"]')
        || form.querySelector('[name$="[type]"]');
    const contentField = document.getElementById(EDITOR_ID) || form.querySelector('textarea[name$="[content]"]');
    const snippetNotesField = document.getElementById(SNIPPET_NOTES_EDITOR_ID)
        || form.querySelector('textarea[name$="[snippetNotes]"]');
    const featuredInput = document.getElementById('featured-image-input') || form.querySelector('input[name$="[featuredImage]"]');
    const ogInput = document.getElementById('og-image-input') || form.querySelector('input[name$="[ogImage]"]');
    const tagsSelect = document.getElementById('post-tags')
        || form.querySelector('select[name$="[tags][]"]')
        || form.querySelector('select[name$="[tags]"]');

    if (contentField && !contentField.id) contentField.id = EDITOR_ID;

    let mediaTarget = null;
    let editorInstance = null;
    let snippetNotesEditorInstance = null;
    let editorStartPromise = null;
    let snippetNotesEditorStartPromise = null;
    let editorWanted = false;
    let snippetNotesEditorWanted = false;

    const mediaModal = document.getElementById('mediaPickerModal')
        ? Modal.getOrCreateInstance(document.getElementById('mediaPickerModal'))
        : null;

    const getType = () => {
        if (!typeSelect) {
            return 'article';
        }
        if (typeSelect instanceof HTMLSelectElement) {
            return typeSelect.value || 'article';
        }
        if (typeSelect instanceof HTMLInputElement && typeSelect.type === 'radio' && typeSelect.checked) {
            return typeSelect.value || 'article';
        }

        return 'article';
    };
    const getCfg = (t) => typeConfig[t] || typeConfig.article || {};

    function syncEditor(needsRich) {
        editorWanted = needsRich;
        if (needsRich) {
            startEditor();
        } else {
            stopEditor();
        }
    }

    function syncSnippetNotesEditor(needsNotes) {
        snippetNotesEditorWanted = needsNotes;
        if (needsNotes) {
            startSnippetNotesEditor();
        } else {
            stopSnippetNotesEditor();
        }
    }

    /**
     * CKEditor 5 SimpleUploadAdapter için sunucu, { url } alanı olan bir JSON bekler.
     * Backend zaten { location } veya { url } dönebiliyor — ikisini de destekliyoruz.
     */
    class CkeditorUploadAdapter {
        constructor(loader) {
            this.loader = loader;
        }

        upload() {
            return this.loader.file.then((file) => new Promise((resolve, reject) => {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('_token', uploadToken);

                fetch(tinymceUploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': uploadToken },
                    body: fd,
                    credentials: 'same-origin',
                })
                    .then((response) => response.json())
                    .then((data) => {
                        const url = data.location || data.url;
                        if (!url) {
                            reject(data.error || 'Yükleme başarısız: sunucudan görsel adresi alınamadı.');
                            return;
                        }
                        resolve({ default: url });
                    })
                    .catch(() => reject('Yükleme sırasında bağlantı hatası oluştu.'));
            }));
        }

        abort() {}
    }

    function withUploadAdapter(editor) {
        editor.plugins.get('FileRepository').createUploadAdapter = (loader) => new CkeditorUploadAdapter(loader);
    }

    function startEditor() {
        if (!contentField || editorInstance || editorStartPromise) return;

        const plugins = [
            Essentials, Paragraph, Heading, Bold, Italic, Underline, Strikethrough,
            Link, List, ListProperties, BlockQuote, Table, TableToolbar, TableColumnResize,
            Image, ImageToolbar, ImageCaption, ImageStyle, ImageResize, ImageUpload,
            SimpleUploadAdapter, CodeBlock, SourceEditing, Alignment, HorizontalLine,
            Indent, IndentBlock, GalleryImagePlugin(() => openMedia('editor')),
        ];

        editorStartPromise = ClassicEditor.create(contentField, {
            licenseKey: 'GPL',
            plugins,
            toolbar: FULL_EDITOR_TOOLBAR,
            language: 'tr',
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
            },
            image: {
                toolbar: ['imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'toggleImageCaption', 'imageTextAlternative', 'resizeImage'],
            },
        }).then((editor) => {
            editorStartPromise = null;
            if (!editorWanted) {
                editor.destroy();
                return;
            }
            withUploadAdapter(editor);
            editor.ui.view.editable.element.parentElement?.classList.add('editor-min-h');
            editor.model.document.on('change:data', () => {
                contentField.value = editor.getData();
            });
            editorInstance = editor;
        });
    }

    function startSnippetNotesEditor() {
        if (!snippetNotesField || snippetNotesEditorInstance || snippetNotesEditorStartPromise) return;

        snippetNotesEditorStartPromise = ClassicEditor.create(snippetNotesField, {
            licenseKey: 'GPL',
            plugins: BASIC_EDITOR_PLUGINS,
            toolbar: BASIC_EDITOR_TOOLBAR,
            language: 'tr',
        }).then((editor) => {
            snippetNotesEditorStartPromise = null;
            if (!snippetNotesEditorWanted) {
                editor.destroy();
                return;
            }
            editor.ui.view.editable.element.parentElement?.classList.add('editor-min-h--sm');
            snippetNotesEditorInstance = editor;
        });
    }

    function stopSnippetNotesEditor() {
        if (snippetNotesEditorInstance) {
            snippetNotesField.value = snippetNotesEditorInstance.getData();
            snippetNotesEditorInstance.destroy();
            snippetNotesEditorInstance = null;
        }
    }

    function stopEditor() {
        if (editorInstance) {
            contentField.value = editorInstance.getData();
            editorInstance.destroy();
            editorInstance = null;
        }
    }

    function toggleBlocks(type) {
        Object.entries(BLOCKS).forEach(([name, types]) => {
            document.querySelectorAll(`[data-post-block="${name}"]`).forEach((el) => {
                el.classList.toggle('d-none', !types.includes(type));
            });
        });

        const cfg = getCfg(type);
        const titleEl = document.getElementById('content-panel-title');
        const hintEl = document.getElementById('content-panel-hint');
        const badge = document.getElementById('post-type-badge');
        if (titleEl) titleEl.textContent = cfg.contentPanelTitle || 'İçerik';
        if (hintEl) hintEl.textContent = cfg.contentHint || '';
        if (badge) badge.textContent = cfg.label || type;

        if (contentField) {
            const plain = cfg.codeEditor || cfg.note;
            contentField.classList.toggle('font-monospace', plain);
            contentField.rows = cfg.note ? 4 : plain ? 16 : 12;
            if (cfg.note) contentField.setAttribute('maxlength', '250');
            else contentField.removeAttribute('maxlength');
        }

        syncEditor(!!cfg.richEditor);
        syncSnippetNotesEditor(!!cfg.snippetNotesEditor);
    }

    function openMedia(target) {
        mediaTarget = target;
        loadGallery();
        mediaModal?.show();
    }

    function preview(id, url) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = url ? `<img src="${url}" alt="">` : '';
    }

    function pick(url) {
        if (!url || !mediaTarget) return;

        if (mediaTarget === 'featured' && featuredInput) {
            featuredInput.value = url;
            preview('featured-image-preview', url);
        } else if (mediaTarget === 'og' && ogInput) {
            ogInput.value = url;
            preview('og-image-preview', url);
        } else if (mediaTarget === 'gallery') {
            const g = document.getElementById('post-gallery-field') || form.querySelector('[name$="[gallery]"]');
            if (g) g.value = g.value.trim() ? `${g.value.trim()}\n${url}` : url;
        } else if (mediaTarget === 'editor' && editorInstance) {
            editorInstance.model.change((writer) => {
                const imageElement = writer.createElement('imageBlock', { src: url });
                editorInstance.model.insertContent(imageElement, editorInstance.model.document.selection);
            });
        }

        document.querySelectorAll('[data-media-clear]').forEach((b) => {
            const t = b.dataset.mediaClear;
            b.hidden = !(t === 'featured' ? featuredInput?.value : t === 'og' ? ogInput?.value : false);
        });
        mediaModal?.hide();
        mediaTarget = null;
    }

    async function loadGallery() {
        const grid = document.getElementById('media-picker-grid');
        if (!grid) return;
        grid.innerHTML = '<p class="text-center py-3">Yükleniyor…</p>';
        try {
            const r = await fetch(mediaListUrl, { credentials: 'same-origin' });
            const d = await r.json();
            const items = d.items || [];
            if (!items.length) {
                grid.innerHTML = '<p class="text-muted text-center py-3">Görsel yok</p>';
                return;
            }
            grid.innerHTML = items.map((i) =>
                `<button type="button" class="media-pick" data-url="${i.url}"><img src="${i.thumb || i.url}" alt=""><span>${i.name}</span></button>`
            ).join('');
            grid.querySelectorAll('.media-pick').forEach((b) => b.addEventListener('click', () => pick(b.dataset.url)));
        } catch {
            grid.innerHTML = '<p class="text-danger text-center py-3">Galeri yüklenemedi</p>';
        }
    }

    async function upload(file, cb) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_token', uploadToken);
        const r = await fetch(mediaUploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': uploadToken },
            body: fd,
            credentials: 'same-origin',
        });
        const d = await r.json();
        if (d.url) cb(d.url);
        else alert(d.error || 'Yükleme hatası');
    }

    document.querySelectorAll('[data-media-target]').forEach((b) => b.addEventListener('click', () => openMedia(b.dataset.mediaTarget)));
    document.querySelectorAll('[data-media-upload]').forEach((b) => b.addEventListener('click', () => {
        mediaTarget = b.dataset.mediaUpload;
        document.getElementById('media-direct-upload')?.click();
    }));
    document.getElementById('media-direct-upload')?.addEventListener('change', (e) => {
        const f = e.target.files?.[0];
        if (f) upload(f, pick);
        e.target.value = '';
    });
    document.getElementById('media-picker-upload')?.addEventListener('change', (e) => {
        const f = e.target.files?.[0];
        if (f) upload(f, () => loadGallery());
        e.target.value = '';
    });
    document.querySelectorAll('[data-media-clear]').forEach((b) => b.addEventListener('click', () => {
        if (b.dataset.mediaClear === 'featured' && featuredInput) {
            featuredInput.value = '';
            preview('featured-image-preview', '');
        }
        if (b.dataset.mediaClear === 'og' && ogInput) {
            ogInput.value = '';
            preview('og-image-preview', '');
        }
        b.hidden = true;
    }));

    /**
     * WordPress tarzı AJAX etiket sistemi: Tom Select ile canlı arama (load) +
     * listede yoksa yeni etiket yazıp enter'lama (create). Sunucu gerçek tag ID'sini
     * dönene kadar geçici value (yazılan metin) kullanılır; cevap gelince option'ın
     * value'su gerçek ID'ye çevrilir (updateOption) — böylece submit'te backend'e
     * her zaman sayısal Tag ID'leri gider, isimler değil.
     */
    async function createTagAndSync(tomSelect, tempValue, name) {
        if (!tagCreateUrl || !tagCreateToken) return;

        const fd = new FormData();
        fd.append('name', name);
        fd.append('_token', tagCreateToken);

        try {
            const response = await fetch(tagCreateUrl, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!data.id) return;

            const realValue = String(data.id);
            const wasSelected = tomSelect.items.includes(tempValue);
            tomSelect.removeOption(tempValue);
            tomSelect.addOption({ value: realValue, text: data.name || name });
            if (wasSelected) {
                tomSelect.addItem(realValue, true);
            }
        } catch (err) {
            console.error('Tag create failed:', err);
        }
    }

    if (tagsSelect && typeof window.TomSelect !== 'undefined') {
        const tomSelectInstance = new window.TomSelect('#post-tags', {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            load: function (query, callback) {
                const url = tagSearchUrl + '?q=' + encodeURIComponent(query);
                fetch(url)
                    .then((response) => response.json())
                    .then((json) => callback(json))
                    .catch(() => callback());
            },
            create: true,
            onOptionAdd: function (value, data) {
                createTagAndSync(tomSelectInstance, value, data.text || value);
            },
        });
    }

    const downloadFileInput = document.getElementById('software-download-file');
    const downloadUrlInput = document.getElementById('software-download-url');
    const downloadStatus = document.getElementById('software-download-status');

    downloadFileInput?.addEventListener('change', async () => {
        const file = downloadFileInput.files?.[0];
        if (!file || !downloadUrlInput) {
            return;
        }

        if (!softwareUploadUrl || !uploadToken) {
            if (downloadStatus) {
                downloadStatus.textContent = 'Dosya kayıt sırasında yüklenecek.';
                downloadStatus.className = 'form-text text-muted';
            }
            return;
        }

        if (downloadStatus) {
            downloadStatus.textContent = 'Dosya yükleniyor…';
            downloadStatus.className = 'form-text text-muted';
        }

        const body = new FormData();
        body.append('file', file);
        body.append('_token', uploadToken);

        try {
            const response = await fetch(softwareUploadUrl, {
                method: 'POST',
                body,
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!response.ok || !data.url) {
                throw new Error(data.error || 'Yükleme başarısız.');
            }

            downloadUrlInput.value = data.url;
            downloadFileInput.value = '';
            if (downloadStatus) {
                downloadStatus.textContent = `${file.name} yüklendi — indirme URL’si güncellendi.`;
                downloadStatus.className = 'form-text text-success';
            }
        } catch (error) {
            console.error('Software download upload failed:', error);
            if (downloadStatus) {
                downloadStatus.textContent = error instanceof Error ? error.message : 'Dosya yüklenemedi.';
                downloadStatus.className = 'form-text text-danger';
            }
        }
    });

    /**
     * Backend PRE_SUBMIT listener'ıyla aynı mantığın anlık (kaydetmeden önce) UI
     * yansıması: tarih değiştikçe "Durum" select'i otomatik Zamanla/Yayınla olur.
     * Taslak elle seçiliyse dokunulmaz — kullanıcı isteyerek taslakta bırakmış olabilir.
     */
    const statusSelect = document.getElementById('post-status-select');
    const publishedAtInput = document.getElementById('post-published-at');

    function syncStatusWithDate() {
        if (!statusSelect || !publishedAtInput) return;
        if (statusSelect.value === 'draft') return;

        const raw = publishedAtInput.value;
        const chosen = raw ? new Date(raw) : null;
        const now = new Date();

        statusSelect.value = (chosen && chosen > now) ? 'scheduled' : 'published';
    }

    publishedAtInput?.addEventListener('change', syncStatusWithDate);
    publishedAtInput?.addEventListener('input', syncStatusWithDate);

    const onTypeChange = () => toggleBlocks(getType());

    toggleBlocks(getType());

    if (typeSelect instanceof HTMLSelectElement) {
        typeSelect.addEventListener('change', onTypeChange);
        typeSelect.addEventListener('input', onTypeChange);
    } else if (typeSelect) {
        typeSelect.addEventListener('change', onTypeChange);
    } else {
        form.querySelectorAll('input[name="post[type]"]').forEach((input) => {
            input.addEventListener('change', onTypeChange);
        });
    }

    /**
     * CKEditor 5 orijinal <textarea>'yı gizler; tarayıcı "required" alana submit
     * anında native validasyonla odaklanmaya çalışınca (gizli olduğu için)
     * "geçersiz form elemanına odaklanılamaz" hatasıyla submit'i tamamen engelliyordu.
     * Çözüm: submit'i yakalayıp veriyi textarea'ya yazdıktan SONRA native validasyonu
     * atlayarak (requestSubmit değil, gerçek submit) formu kendimiz gönderiyoruz.
     */
    form.addEventListener('submit', (event) => {
        if (editorInstance) contentField.value = editorInstance.getData();
        if (snippetNotesEditorInstance) snippetNotesField.value = snippetNotesEditorInstance.getData();

        if (contentField && contentField.hasAttribute('required') && !form.dataset.ckSubmitting) {
            event.preventDefault();
            form.dataset.ckSubmitting = '1';
            contentField.removeAttribute('required');
            form.submit();
        }
    });
});
