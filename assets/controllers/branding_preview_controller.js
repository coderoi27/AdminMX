import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'appNameInput',
        'baseTitleInput',
        'baseDescriptionInput',
        'ogTitleInput',
        'ogDescriptionInput',
        'twitterTitleInput',
        'twitterDescriptionInput',
        'facebookTitleInput',
        'facebookDescriptionInput',
        'threadsTitleInput',
        'threadsDescriptionInput',
        'googleTitleInput',
        'googleDescriptionInput',
        'baseTitlePreview',
        'baseDescriptionPreview',
        'facebookTitlePreview',
        'facebookDescriptionPreview',
        'twitterTitlePreview',
        'twitterDescriptionPreview',
        'threadsTitlePreview',
        'threadsDescriptionPreview',
        'googleTitlePreview',
        'googleDescriptionPreview',
        'baseImagePreview',
        'facebookImagePreview',
        'twitterImagePreview',
        'threadsImagePreview',
        'googleImagePreview',
    ];

    connect() {
        this.objectUrls = [];
        this.update();
    }

    disconnect() {
        this.objectUrls.forEach((url) => URL.revokeObjectURL(url));
    }

    update() {
        const appName = this.value(this.appNameInputTarget, 'Mi Monchis');
        const baseTitle = this.value(this.baseTitleInputTarget, appName);
        const baseDescription = this.value(this.baseDescriptionInputTarget, 'Explora locales cerca de ti.');
        const ogTitle = this.value(this.ogTitleInputTarget, baseTitle);
        const ogDescription = this.value(this.ogDescriptionInputTarget, baseDescription);

        this.baseTitlePreviewTarget.textContent = ogTitle;
        this.baseDescriptionPreviewTarget.textContent = ogDescription;
        this.facebookTitlePreviewTarget.textContent = this.value(this.facebookTitleInputTarget, ogTitle);
        this.facebookDescriptionPreviewTarget.textContent = this.value(this.facebookDescriptionInputTarget, ogDescription);
        this.twitterTitlePreviewTarget.textContent = this.value(this.twitterTitleInputTarget, ogTitle);
        this.twitterDescriptionPreviewTarget.textContent = this.value(this.twitterDescriptionInputTarget, ogDescription);
        this.threadsTitlePreviewTarget.textContent = this.value(this.threadsTitleInputTarget, ogTitle);
        this.threadsDescriptionPreviewTarget.textContent = this.value(this.threadsDescriptionInputTarget, ogDescription);
        this.googleTitlePreviewTarget.textContent = this.value(this.googleTitleInputTarget, baseTitle);
        this.googleDescriptionPreviewTarget.textContent = this.value(this.googleDescriptionInputTarget, baseDescription);
    }

    previewFile(event) {
        const file = event.currentTarget.files?.[0];
        const targetName = event.currentTarget.dataset.imagePreviewTarget;
        if (!file || !targetName) {
            return;
        }

        const imageTarget = this.targets.find(targetName);
        if (!imageTarget) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        this.objectUrls.push(objectUrl);
        imageTarget.src = objectUrl;
        imageTarget.hidden = false;
    }

    value(input, fallback) {
        const value = String(input?.value ?? '').trim();
        return value || fallback;
    }
}
