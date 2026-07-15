import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog', 'grid', 'search', 'slotTitle', 'upload'];
    static values = {
        assets: Array,
        categorySlug: String
    };

    connect() {
        this.currentSlot = null;
        this.currentMultiple = false;
        this.assetsByUuid = new Map();
        for (const asset of this.assetsValue || []) {
            this.assetsByUuid.set(asset.uuid, asset);
        }
        this.renderSelections();
    }

    open(event) {
        event.preventDefault();
        this.openForButton(event.currentTarget, false);
    }

    openUpload(event) {
        event.preventDefault();
        this.openForButton(event.currentTarget, true);
    }

    openForButton(button, focusUpload) {
        this.currentSlot = button.dataset.slot;
        this.currentMultiple = button.dataset.multiple === '1';
        this.slotTitleTarget.textContent = button.dataset.label || this.currentSlot;
        if (this.hasUploadTarget) {
            const slugInput = this.element.querySelector('input[name="slug"]');
            this.uploadTarget.dataset.catalogMediaUploadCategorySlugValue = slugInput?.value || this.categorySlugValue || '';
            this.uploadTarget.dataset.catalogMediaUploadUsageSlotValue = this.currentSlot || '';
        }
        this.dialogTarget.hidden = false;
        this.searchTarget.value = '';
        this.renderGrid();
        if (focusUpload && this.hasUploadTarget) {
            this.uploadTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            this.searchTarget.focus();
        }
    }

    close(event) {
        event.preventDefault();
        this.dialogTarget.hidden = true;
    }

    filter() {
        this.renderGrid();
    }

    select(event) {
        event.preventDefault();
        const uuid = event.currentTarget.dataset.uuid;
        const asset = this.assetsByUuid.get(uuid);
        if (!asset || !this.currentSlot || !this.isSelectable(asset, this.currentSlot)) {
            return;
        }

        const input = this.selectionInput(this.currentSlot, this.currentMultiple);
        if (!input) {
            return;
        }

        if (this.currentMultiple) {
            const selected = this.uuidsFromValue(input.value);
            if (!selected.includes(uuid)) {
                selected.push(uuid);
            }
            input.value = selected.join(',');
        } else {
            input.value = uuid;
            this.dialogTarget.hidden = true;
        }

        this.markTouched(this.currentSlot, this.currentMultiple);
        this.renderSelections();
        this.renderGrid();
    }

    clear(event) {
        event.preventDefault();
        const slot = event.currentTarget.dataset.slot;
        const multiple = event.currentTarget.dataset.multiple === '1';
        const input = this.selectionInput(slot, multiple);
        if (input) {
            input.value = '';
        }
        this.markTouched(slot, multiple);
        this.renderSelections();
    }

    remove(event) {
        event.preventDefault();
        const slot = event.currentTarget.dataset.slot;
        const multiple = event.currentTarget.dataset.multiple === '1';
        const uuid = event.currentTarget.dataset.uuid;
        const input = this.selectionInput(slot, multiple);
        if (!input) {
            return;
        }

        input.value = this.uuidsFromValue(input.value).filter((selectedUuid) => selectedUuid !== uuid).join(',');
        this.markTouched(slot, multiple);
        this.renderSelections();
        this.renderGrid();
    }

    uploadCompleted(event) {
        const detail = event.detail || {};
        if (!detail.asset_uuid) {
            return;
        }

        const asset = {
            uuid: detail.asset_uuid,
            title: detail.title || 'Upload pendiente',
            media_type: detail.media_type || 'image',
            mime_type: detail.mime_type || '',
            public_url: detail.public_url || detail.preview_url || null,
            status: detail.status || 'uploading',
            moderation_status: 'pending',
            rights_verified: false,
            issues_by_slot: {}
        };
        this.assetsByUuid.set(asset.uuid, asset);
        if (this.currentSlot) {
            this.renderGrid();
        }
    }

    renderGrid() {
        const query = this.searchTarget.value.trim().toLowerCase();
        const assets = Array.from(this.assetsByUuid.values())
            .filter((asset) => !query || String(asset.title || '').toLowerCase().includes(query))
            .slice(0, 80);

        this.gridTarget.innerHTML = '';
        for (const asset of assets) {
            const issues = this.issuesFor(asset, this.currentSlot);
            const selected = this.currentSlot ? this.selectedUuids(this.currentSlot, this.currentMultiple).includes(asset.uuid) : false;
            const card = document.createElement('article');
            card.style.cssText = 'border:1px solid #e5e7eb; border-radius:12px; padding:0.7rem; display:grid; gap:0.55rem; background:#fff;';

            const preview = document.createElement('div');
            preview.style.cssText = 'height:96px; border-radius:10px; overflow:hidden; background:#f1f5f9; display:grid; place-items:center; color:#64748b; font-weight:800; font-size:0.8rem;';
            if (asset.public_url && asset.media_type !== 'video') {
                const image = document.createElement('img');
                image.src = asset.public_url;
                image.alt = '';
                image.loading = 'lazy';
                image.style.cssText = 'width:100%; height:100%; object-fit:cover;';
                preview.append(image);
            } else {
                preview.textContent = asset.media_type || 'asset';
            }

            const title = document.createElement('strong');
            title.textContent = asset.title || 'Sin título';
            title.style.cssText = 'color:#111827; overflow-wrap:anywhere;';

            const meta = document.createElement('span');
            meta.textContent = `${asset.media_type || 'asset'} · ${asset.status || 'sin estado'}`;
            meta.style.cssText = 'color:#64748b; font-size:0.82rem;';

            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.action = 'click->catalog-media-picker#select';
            button.dataset.uuid = asset.uuid;
            button.disabled = issues.length > 0;
            button.textContent = selected ? 'Seleccionado' : 'Seleccionar';
            button.style.cssText = button.disabled
                ? 'border:1px solid #e5e7eb; border-radius:999px; padding:0.5rem 0.7rem; background:#f8fafc; color:#94a3b8; font-weight:800; cursor:not-allowed;'
                : 'border:none; border-radius:999px; padding:0.5rem 0.7rem; background:#111827; color:#fff; font-weight:800; cursor:pointer;';

            card.append(preview, title, meta, button);
            if (issues.length > 0) {
                const reason = document.createElement('small');
                reason.textContent = issues.join(' ');
                reason.style.cssText = 'color:#be123c; line-height:1.4;';
                card.append(reason);
            }
            this.gridTarget.append(card);
        }
    }

    renderSelections() {
        for (const container of this.element.querySelectorAll('[data-role="catalog-media-selection"]')) {
            const slot = container.dataset.slot;
            const multiple = container.dataset.multiple === '1';
            const uuids = this.selectedUuids(slot, multiple);
            container.innerHTML = '';

            if (uuids.length === 0) {
                const empty = document.createElement('span');
                empty.textContent = 'Sin medio seleccionado.';
                empty.style.cssText = 'color:#64748b;';
                container.append(empty);
                continue;
            }

            for (const uuid of uuids) {
                const asset = this.assetsByUuid.get(uuid);
                if (!asset) {
                    continue;
                }

                const item = document.createElement('article');
                item.style.cssText = 'display:grid; grid-template-columns:64px minmax(0,1fr) auto; gap:0.75rem; align-items:center; border:1px solid #e5e7eb; border-radius:12px; padding:0.65rem; background:#fff;';
                item.innerHTML = `
                    <div data-role="preview" style="width:64px; height:48px; border-radius:9px; overflow:hidden; background:#f1f5f9; display:grid; place-items:center; color:#64748b; font-size:0.72rem; font-weight:800;"></div>
                    <div>
                        <strong style="display:block; color:#111827; overflow-wrap:anywhere;"></strong>
                        <span style="display:block; color:#64748b; font-size:0.82rem;"></span>
                    </div>
                    <button type="button" data-action="click->catalog-media-picker#remove" style="border:1px solid #d1d5db; border-radius:999px; padding:0.45rem 0.7rem; background:#fff; color:#374151; font-weight:800; cursor:pointer;">Quitar</button>
                `;
                const preview = item.querySelector('[data-role="preview"]');
                if (asset.public_url && asset.media_type !== 'video') {
                    const image = document.createElement('img');
                    image.src = asset.public_url;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.style.cssText = 'width:100%; height:100%; object-fit:cover;';
                    preview.append(image);
                } else {
                    preview.textContent = asset.media_type || 'asset';
                }
                item.querySelector('strong').textContent = asset.title || 'Sin título';
                item.querySelector('span').textContent = `${asset.media_type || 'asset'} · ${asset.public_url || 'sin URL pública'}`;
                const removeButton = item.querySelector('button');
                removeButton.dataset.slot = slot;
                removeButton.dataset.multiple = multiple ? '1' : '0';
                removeButton.dataset.uuid = uuid;
                container.append(item);
            }
        }
    }

    isSelectable(asset, slot) {
        return this.issuesFor(asset, slot).length === 0;
    }

    issuesFor(asset, slot) {
        if (!slot) {
            return ['Selecciona un slot.'];
        }

        return asset.issues_by_slot?.[slot] || ['El asset necesita metadata vigente antes de seleccionarse.'];
    }

    selectedUuids(slot, multiple) {
        const input = this.selectionInput(slot, multiple);

        return input ? this.uuidsFromValue(input.value) : [];
    }

    selectionInput(slot, multiple) {
        const selector = multiple
            ? `input[data-pool-slot="${slot}"]`
            : `input[data-singular-slot="${slot}"]`;

        return this.element.querySelector(selector);
    }

    markTouched(slot, multiple) {
        const selector = multiple
            ? `input[data-pool-touched-slot="${slot}"]`
            : `input[data-singular-touched-slot="${slot}"]`;
        const touched = this.element.querySelector(selector);
        if (touched) {
            touched.value = '1';
        }
    }

    uuidsFromValue(value) {
        return String(value || '').split(',').map((uuid) => uuid.trim()).filter(Boolean);
    }
}
