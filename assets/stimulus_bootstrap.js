import { startStimulusApp } from '@symfony/stimulus-bundle';
import CatalogMediaPickerController from './controllers/catalog_media_picker_controller.js';
import CatalogMediaUploadController from './controllers/catalog_media_upload_controller.js';

const app = startStimulusApp();
app.register('catalog-media-picker', CatalogMediaPickerController);
app.register('catalog-media-upload', CatalogMediaUploadController);
