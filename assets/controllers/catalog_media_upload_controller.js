import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dropzone', 'fileInput', 'queue', 'summary'];
    static values = {
        prepareUrl: String,
        completeUrlTemplate: String,
        csrfToken: String,
        defaultSourceType: { type: String, default: 'own' },
        categorySlug: { type: String, default: '' },
        usageSlot: { type: String, default: '' },
        reloadOnComplete: { type: Boolean, default: false }
    };

    connect() {
        this.items = new Map();
        this.sequence = 0;
        this.updateSummary();
    }

    openPicker(event) {
        event.preventDefault();
        this.fileInputTarget.click();
    }

    selectFiles(event) {
        this.enqueueFiles(Array.from(event.target.files || []));
        this.fileInputTarget.value = '';
    }

    dragOver(event) {
        event.preventDefault();
        this.dropzoneTarget.style.borderColor = '#111827';
        this.dropzoneTarget.style.background = '#eef2ff';
    }

    dragLeave(event) {
        event.preventDefault();
        this.resetDropzone();
    }

    drop(event) {
        event.preventDefault();
        this.resetDropzone();
        this.enqueueFiles(Array.from(event.dataTransfer.files || []));
    }

    retryFailed(event) {
        event.preventDefault();
        for (const item of this.items.values()) {
            if (item.state === 'failed') {
                this.startItem(item);
            }
        }
    }

    cancel(event) {
        const item = this.items.get(event.currentTarget.dataset.uploadId);
        if (!item) {
            return;
        }

        if (item.xhr) {
            item.xhr.abort();
        }
        item.file = null;
        item.state = 'cancelled';
        this.renderItem(item, 'Cancelado', 0);
        this.updateSummary();
    }

    retry(event) {
        const item = this.items.get(event.currentTarget.dataset.uploadId);
        if (!item || item.state === 'uploading' || item.state === 'preparing' || item.state === 'confirming') {
            return;
        }

        this.startItem(item);
    }

    enqueueFiles(files) {
        for (const file of files) {
            const id = `upload-${++this.sequence}`;
            const item = {
                id,
                file,
                state: 'queued',
                assetUuid: null,
                uploadUrl: null,
                uploadHeaders: {},
                xhr: null,
                row: this.createRow(id, file)
            };
            this.items.set(id, item);
            this.queueTarget.prepend(item.row);
            this.renderItem(item, 'En cola', 0);
            this.startItem(item);
        }
        this.updateSummary();
    }

    async startItem(item) {
        if (!item.file) {
            return;
        }

        item.state = 'preparing';
        item.xhr = null;
        this.renderItem(item, 'Preparando...', 0);
        this.updateSummary();

        let prepareData;
        try {
            const response = await fetch(this.prepareUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue
                },
                body: JSON.stringify({
                    original_filename: item.file.name,
                    mime_type: this.normalizeMimeType(item.file.type),
                    file_size: item.file.size,
                    media_type: this.mediaTypeFor(item.file),
                    source_type: this.defaultSourceTypeValue,
                    category_slug: this.categorySlugValue || null,
                    usage_slot: this.usageSlotValue || null
                })
            });

            prepareData = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(this.errorMessage(prepareData, 'No se pudo preparar el upload.'));
            }
        } catch (error) {
            item.state = 'failed';
            this.renderItem(item, error.message, 0);
            this.updateSummary();
            return;
        }

        item.assetUuid = prepareData.asset_uuid;
        item.uploadUrl = prepareData.upload_url;
        item.uploadHeaders = prepareData.upload_headers || {};
        if (!item.assetUuid || !item.uploadUrl) {
            item.state = 'failed';
            this.renderItem(item, 'El servidor no devolvió datos de upload.', 0);
            this.updateSummary();
            return;
        }

        this.uploadItem(item);
    }

    uploadItem(item) {
        item.state = 'uploading';
        this.renderItem(item, 'Subiendo...', 3);
        this.updateSummary();

        const xhr = new XMLHttpRequest();
        item.xhr = xhr;
        xhr.open('PUT', item.uploadUrl);
        xhr.withCredentials = false;

        const headers = Object.keys(item.uploadHeaders).length > 0
            ? item.uploadHeaders
            : { 'Content-Type': this.normalizeMimeType(item.file.type) };
        for (const [name, value] of Object.entries(headers)) {
            xhr.setRequestHeader(name, String(value));
        }

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                const progress = Math.max(1, Math.min(99, Math.round((event.loaded / event.total) * 100)));
                this.renderItem(item, `Subiendo ${progress}%`, progress);
            }
        });

        xhr.onload = () => {
            if (xhr.status < 200 || xhr.status >= 300) {
                item.state = 'failed';
                this.renderItem(item, 'Carga fallida. Reintenta el archivo.', 0);
                this.updateSummary();
                return;
            }

            const etag = this.normalizeEtag(xhr.getResponseHeader('ETag') || '');
            if (!etag) {
                item.state = 'failed';
                this.renderItem(item, 'R2 no expuso ETag. Revisa CORS del bucket.', 0);
                this.updateSummary();
                return;
            }

            this.completeItem(item, etag);
        };

        xhr.onerror = () => {
            item.state = 'failed';
            this.renderItem(item, 'No se pudo subir el archivo.', 0);
            this.updateSummary();
        };

        xhr.onabort = () => {
            item.state = 'cancelled';
            this.renderItem(item, 'Cancelado', 0);
            this.updateSummary();
        };

        xhr.send(item.file);
    }

    async completeItem(item, etag) {
        item.state = 'confirming';
        this.renderItem(item, 'Confirmando almacenamiento...', 100);
        this.updateSummary();

        try {
            const response = await fetch(this.completeUrlTemplateValue.replace('__ASSET_UUID__', encodeURIComponent(item.assetUuid)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue
                },
                body: JSON.stringify({
                    etag,
                    uploaded_size: item.file.size,
                    uploaded_mime_type: this.normalizeMimeType(item.file.type),
                    client_finished_at: new Date().toISOString()
                })
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(this.errorMessage(payload, 'No se pudo confirmar el archivo.'));
            }

            item.state = 'completed';
            item.file = null;
            this.renderItem(item, 'Completado. Edita detalles para activar.', 100, payload.edit_url);
            this.dispatch('completed', { detail: payload, prefix: 'catalog-media-upload' });
            if (this.reloadOnCompleteValue && this.isIdle()) {
                window.location.reload();
            }
        } catch (error) {
            item.state = 'failed';
            this.renderItem(item, error.message, 100);
        } finally {
            this.updateSummary();
        }
    }

    createRow(id, file) {
        const row = document.createElement('article');
        row.dataset.uploadId = id;
        row.style.cssText = 'display:grid; grid-template-columns:72px minmax(0,1fr) auto; gap:0.85rem; align-items:center; border:1px solid #e5e7eb; border-radius:14px; padding:0.8rem; background:#fff;';

        const preview = document.createElement('div');
        preview.style.cssText = 'width:72px; height:54px; border-radius:10px; overflow:hidden; background:#f1f5f9; display:grid; place-items:center; color:#64748b; font-size:0.72rem; font-weight:800;';
        if (file.type.startsWith('image/')) {
            const image = document.createElement('img');
            image.alt = '';
            image.src = URL.createObjectURL(file);
            image.onload = () => URL.revokeObjectURL(image.src);
            image.style.cssText = 'width:100%; height:100%; object-fit:cover;';
            preview.append(image);
        } else if (file.type.startsWith('video/')) {
            const video = document.createElement('video');
            video.muted = true;
            video.preload = 'metadata';
            video.src = URL.createObjectURL(file);
            video.onloadedmetadata = () => URL.revokeObjectURL(video.src);
            video.style.cssText = 'width:100%; height:100%; object-fit:cover;';
            preview.append(video);
        } else {
            preview.textContent = 'file';
        }

        const body = document.createElement('div');
        body.innerHTML = `
            <strong style="display:block; color:#111827; overflow-wrap:anywhere;"></strong>
            <span data-role="status" style="display:block; margin-top:0.2rem; color:#64748b; font-size:0.86rem;"></span>
            <progress data-role="progress" value="0" max="100" style="width:100%; margin-top:0.45rem;"></progress>
        `;
        body.querySelector('strong').textContent = file.name;

        const actions = document.createElement('div');
        actions.style.cssText = 'display:flex; gap:0.45rem; flex-wrap:wrap; justify-content:flex-end;';
        actions.innerHTML = `
            <button type="button" data-action="click->catalog-media-upload#retry" data-upload-id="${id}" style="border:1px solid #d1d5db; border-radius:999px; padding:0.45rem 0.75rem; background:#fff; color:#374151; font-weight:800; cursor:pointer;">Reintentar</button>
            <button type="button" data-action="click->catalog-media-upload#cancel" data-upload-id="${id}" style="border:1px solid #d1d5db; border-radius:999px; padding:0.45rem 0.75rem; background:#fff; color:#374151; font-weight:800; cursor:pointer;">Cancelar</button>
        `;

        row.append(preview, body, actions);
        return row;
    }

    renderItem(item, statusText, progress, editUrl = null) {
        const status = item.row.querySelector('[data-role="status"]');
        const progressElement = item.row.querySelector('[data-role="progress"]');
        status.textContent = statusText;
        progressElement.value = progress;

        const retry = item.row.querySelector('[data-action="click->catalog-media-upload#retry"]');
        const cancel = item.row.querySelector('[data-action="click->catalog-media-upload#cancel"]');
        retry.hidden = item.state !== 'failed' && item.state !== 'cancelled';
        cancel.hidden = item.state === 'completed' || item.state === 'failed' || item.state === 'cancelled';

        const existingEdit = item.row.querySelector('[data-role="edit-link"]');
        if (existingEdit) {
            existingEdit.remove();
        }
        if (editUrl) {
            const link = document.createElement('a');
            link.dataset.role = 'edit-link';
            link.href = editUrl;
            link.textContent = 'Editar detalles';
            link.style.cssText = 'border:1px solid #d1d5db; border-radius:999px; padding:0.45rem 0.75rem; color:#111827; text-decoration:none; font-weight:800;';
            item.row.lastElementChild.append(link);
        }
    }

    updateSummary() {
        const counts = { queued: 0, preparing: 0, uploading: 0, confirming: 0, completed: 0, failed: 0, cancelled: 0 };
        for (const item of this.items.values()) {
            counts[item.state] = (counts[item.state] || 0) + 1;
        }

        this.summaryTarget.textContent = `Completados: ${counts.completed} · Fallidos: ${counts.failed} · Pendientes: ${counts.queued + counts.preparing + counts.uploading + counts.confirming}`;
    }

    isIdle() {
        for (const item of this.items.values()) {
            if (['queued', 'preparing', 'uploading', 'confirming'].includes(item.state)) {
                return false;
            }
        }

        return true;
    }

    mediaTypeFor(file) {
        if (file.type.startsWith('video/')) {
            return 'video';
        }

        return 'image';
    }

    normalizeMimeType(mimeType) {
        return String(mimeType || '').split(';')[0].trim().toLowerCase();
    }

    normalizeEtag(value) {
        return String(value || '').trim().replace(/^"+|"+$/g, '');
    }

    errorMessage(payload, fallback) {
        if (payload && Array.isArray(payload.errors) && payload.errors.length > 0) {
            return payload.errors.join(' ');
        }

        return payload?.error || fallback;
    }

    resetDropzone() {
        this.dropzoneTarget.style.borderColor = '#cbd5e1';
        this.dropzoneTarget.style.background = '#f8fafc';
    }
}
