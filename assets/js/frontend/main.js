/**
 * Ultra Light Options - Frontend JavaScript
 * Vanilla ES6+ - No jQuery dependency
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

'use strict';

/**
 * Main ULO Frontend Class
 */
class ULOFrontend {
    /**
     * Constructor
     */
    constructor() {
        this.container = null;
        this.productId = 0;
        this.variationId = 0;
        this.basePrice = 0;
        this.debounceTimer = null;
        this.debounceDelay = 300;
        this.isCalculating = false;

        // Bind methods
        this.init = this.init.bind(this);
        this.handleFieldChange = this.handleFieldChange.bind(this);
        this.handleVariationChange = this.handleVariationChange.bind(this);
        this.calculatePrice = this.calculatePrice.bind(this);
        this.evaluateConditions = this.evaluateConditions.bind(this);
    }

    /**
     * Initialize the frontend
     */
    init() {
        this.container = document.querySelector('.ulo-product-options');

        if (!this.container) {
            return;
        }

        this.productId = parseInt(this.container.dataset.productId, 10) || 0;
        this.variationId = parseInt(this.container.dataset.variationId, 10) || 0;
        this.basePrice = parseFloat(this.container.dataset.basePrice) || 0;

        this.bindEvents();
        this.evaluateConditions();
        this.initFileUploads();
        this.initHiddenInput();

        // Initial price calculation if we have fields
        if (this.container.querySelector('.ulo-field')) {
            this.calculatePrice();
        }
    }

    /**
     * Initialize hidden input for Modern Cart compatibility
     */
    initHiddenInput() {
        const cartForm = document.querySelector('form.cart');
        if (!cartForm) {
            return;
        }

        let input = cartForm.querySelector('input[name="ulo_serialized"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ulo_serialized';
            cartForm.appendChild(input);
        }

        this.updateHiddenInput();
    }

    /**
     * Update hidden input with current values
     */
    updateHiddenInput() {
        const cartForm = document.querySelector('form.cart');
        const input = cartForm ? cartForm.querySelector('input[name="ulo_serialized"]') : null;

        if (input) {
            const values = this.collectFieldValues();
            input.value = JSON.stringify(values);
        }
    }

    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Field change events
        this.container.addEventListener('change', this.handleFieldChange);
        this.container.addEventListener('input', this.handleFieldChange);

        // Variation change event (WooCommerce)
        // Variation change event (WooCommerce uses jQuery)
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('found_variation', (e, variation) => this.handleVariationChange(e, variation));
            jQuery(document).on('reset_data', () => this.handleVariationReset());
        } else {
            // Fallback for custom implementations (unlikely to work with standard WC)
            document.addEventListener('found_variation', this.handleVariationChange);
            document.addEventListener('reset_data', () => this.handleVariationReset());
        }

        // Quantity change
        const quantityInput = document.querySelector('.quantity input[type="number"]');
        if (quantityInput) {
            quantityInput.addEventListener('change', () => this.calculatePrice());
            quantityInput.addEventListener('input', () => this.debouncedCalculatePrice());
        }
    }

    /**
     * Handle field value changes
     * @param {Event} event - Change/input event
     */
    handleFieldChange(event) {
        const target = event.target;
        const field = target.closest('.ulo-field');

        if (!field) {
            return;
        }

        // Skip for file inputs (handled separately)
        if (target.type === 'file') {
            return;
        }

        // Re-evaluate conditions
        this.evaluateConditions();

        // Debounced price calculation
        this.debouncedCalculatePrice();
        this.updateHiddenInput();
    }

    /**
     * Handle WooCommerce variation selection
     * @param {Event} event - Custom event with variation data
     */
    handleVariationChange(event, variationData) {
        const variation = variationData || event.detail || event.originalEvent?.detail;

        if (!variation) {
            return;
        }

        this.variationId = variation.variation_id || 0;
        this.basePrice = parseFloat(variation.display_price) || this.basePrice;

        // Update container data
        this.container.dataset.variationId = this.variationId;
        this.container.dataset.basePrice = this.basePrice;

        // Load variation-specific fields
        this.loadVariationFields();
    }

    /**
     * Handle variation reset
     */
    handleVariationReset() {
        this.variationId = 0;
        this.basePrice = parseFloat(this.container.dataset.basePrice) || 0;
        this.container.dataset.variationId = '0';

        // Clear fields container
        const fieldsContainer = this.container.querySelector('.ulo-fields-container');
        if (fieldsContainer) {
            fieldsContainer.innerHTML = '';
        }

        // Hide price summary
        this.hidePriceSummary();
    }

    /**
     * Load fields for a specific variation via AJAX
     */
    async loadVariationFields() {
        if (!this.variationId) {
            return;
        }

        this.setLoading(true);

        try {
            const formData = new FormData();
            formData.append('action', 'ulo_get_variation_fields');
            formData.append('nonce', uloFrontend.nonce);
            formData.append('product_id', this.productId);
            formData.append('variation_id', this.variationId);

            const response = await fetch(uloFrontend.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                // Insert fields HTML
                let fieldsContainer = this.container.querySelector('.ulo-fields-container');
                if (!fieldsContainer) {
                    fieldsContainer = document.createElement('div');
                    fieldsContainer.className = 'ulo-fields-container';
                    this.container.insertBefore(fieldsContainer, this.container.querySelector('.ulo-price-summary'));
                }

                fieldsContainer.innerHTML = data.data.html;

                // Update base price
                if (data.data.base_price) {
                    this.basePrice = parseFloat(data.data.base_price);
                }

                // Re-init
                this.evaluateConditions();
                this.initFileUploads();
                this.calculatePrice();
                this.updateHiddenInput();
            }
        } catch (error) {
            console.error('ULO: Error loading variation fields', error);
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Debounced price calculation
     */
    debouncedCalculatePrice() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.debounceTimer = setTimeout(() => {
            this.calculatePrice();
        }, this.debounceDelay);
    }

    /**
     * Calculate price via AJAX
     */
    async calculatePrice() {
        if (this.isCalculating) {
            return;
        }

        this.isCalculating = true;

        const options = this.collectFieldValues();
        const quantity = this.getQuantity();

        try {
            const formData = new FormData();
            formData.append('action', 'ulo_calculate_price');
            formData.append('nonce', uloFrontend.nonce);
            formData.append('product_id', this.productId);
            formData.append('variation_id', this.variationId);
            formData.append('quantity', quantity);

            // Append options
            Object.keys(options).forEach(key => {
                if (Array.isArray(options[key])) {
                    options[key].forEach(val => {
                        formData.append(`options[${key}][]`, val);
                    });
                } else {
                    formData.append(`options[${key}]`, options[key]);
                }
            });

            const response = await fetch(uloFrontend.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.updatePriceDisplay(data.data);
            }
        } catch (error) {
            console.error('ULO: Error calculating price', error);
        } finally {
            this.isCalculating = false;
        }
    }

    /**
     * Collect all field values
     * @returns {Object} Field values keyed by field name
     */
    collectFieldValues() {
        const values = {};
        const fields = this.container.querySelectorAll('.ulo-field[data-visible="true"], .ulo-field:not([data-visible])');

        fields.forEach(field => {
            const fieldName = field.dataset.fieldName || field.dataset.fieldId;
            if (!fieldName) return;

            const inputs = field.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                if (input.type === 'radio') {
                    if (input.checked) {
                        values[fieldName] = input.value;
                    }
                } else if (input.type === 'checkbox') {
                    if (!values[fieldName]) {
                        values[fieldName] = [];
                    }
                    if (input.checked) {
                        if (Array.isArray(values[fieldName])) {
                            values[fieldName].push(input.value);
                        } else {
                            values[fieldName] = input.value;
                        }
                    }
                } else if (input.type === 'file') {
                    // Files handled separately via file upload
                    const fileData = field.dataset.uploadedFile;
                    if (fileData) {
                        values[fieldName] = fileData;
                    }
                } else {
                    values[fieldName] = input.value;
                }
            });
        });

        return values;
    }

    /**
     * Get current quantity
     * @returns {number} Quantity value
     */
    getQuantity() {
        const quantityInput = document.querySelector('.quantity input[type="number"]');
        return quantityInput ? parseInt(quantityInput.value, 10) || 1 : 1;
    }

    /**
     * Update price display
     * @param {Object} priceData - Price data from server
     */
    updatePriceDisplay(priceData) {
        const summary = this.container.querySelector('.ulo-price-summary');

        if (!summary) {
            return;
        }

        // Update base price
        const baseEl = summary.querySelector('[data-base-price]');
        if (baseEl) {
            baseEl.innerHTML = priceData.base_price_formatted;
        }

        // Update options price
        const optionsEl = summary.querySelector('[data-options-price]');
        if (optionsEl) {
            optionsEl.innerHTML = priceData.options_price_formatted;
        }

        // Update final price
        const finalEl = summary.querySelector('[data-final-price]');
        if (finalEl) {
            finalEl.innerHTML = priceData.final_price_formatted;
        }

        // Show/hide options row based on whether there are option prices
        const optionsRow = summary.querySelector('.ulo-price-options');
        if (optionsRow) {
            optionsRow.style.display = priceData.options_price > 0 ? 'flex' : 'none';
        }

        // Update breakdown if provided
        if (priceData.breakdown && priceData.breakdown.length > 0) {
            this.updatePriceBreakdown(priceData.breakdown);
        }

        // Show price summary if we have options
        if (priceData.options_price > 0) {
            summary.style.display = 'block';
        }

        // Update main WooCommerce price if possible
        if (priceData.final_price_formatted) {
            this.updateMainPrice(priceData.final_price_formatted);
        }
    }

    /**
     * Update the main WooCommerce product price
     * @param {string} formattedPrice - The formatted price string to display
     */
    updateMainPrice(formattedPrice) {
        // Common selectors for WooCommerce product price
        const selectors = [
            '.product .summary .price',
            '.product .price',
            '.woocommerce-Price-amount',
            '.summary .price > .amount'
        ];

        // Try to find the price element closest to our container first
        let priceEl = null;

        // Look for price inside the product form or container siblings
        const productContainer = this.container.closest('.product') || this.container.closest('.type-product');
        if (productContainer) {
            for (const selector of selectors) {
                const el = productContainer.querySelector(selector);
                if (el) {
                    priceEl = el;
                    break;
                }
            }
        }

        // Fallback to document search if not found in product container
        if (!priceEl) {
            for (const selector of selectors) {
                const el = document.querySelector(selector);
                if (el) {
                    priceEl = el;
                    break;
                }
            }
        }

        if (priceEl) {
            // If it's a range (e.g. variable product), we might want to be careful
            // But usually for calculated price we just replace the content
            priceEl.innerHTML = formattedPrice;
        }
    }

    /**
     * Update price breakdown section
     * @param {Array} breakdown - Breakdown items
     */
    updatePriceBreakdown(breakdown) {
        const summary = this.container.querySelector('.ulo-price-summary');
        let breakdownEl = summary.querySelector('.ulo-price-breakdown');

        if (!breakdownEl) {
            breakdownEl = document.createElement('div');
            breakdownEl.className = 'ulo-price-breakdown';
            const optionsRow = summary.querySelector('.ulo-price-options');
            if (optionsRow) {
                optionsRow.after(breakdownEl);
            }
        }

        // Build breakdown HTML
        let html = '';
        breakdown.forEach(item => {
            html += `
                <div class="ulo-price-breakdown-item">
                    <span class="ulo-breakdown-label">${this.escapeHtml(item.label)}</span>
                    <span class="ulo-breakdown-value">${item.formatted_price}</span>
                </div>
            `;
        });

        breakdownEl.innerHTML = html;
    }

    /**
     * Hide price summary
     */
    hidePriceSummary() {
        const summary = this.container.querySelector('.ulo-price-summary');
        if (summary) {
            summary.style.display = 'none';
        }
    }

    /**
     * Evaluate conditional logic for all fields
     */
    evaluateConditions() {
        const fields = this.container.querySelectorAll('.ulo-field[data-ulo-condition]');

        fields.forEach(field => {
            const conditionsData = field.dataset.uloCondition;

            if (!conditionsData) {
                return;
            }

            try {
                const conditions = JSON.parse(conditionsData);
                const shouldBeVisible = this.evaluateFieldConditions(conditions);

                field.dataset.visible = shouldBeVisible ? 'true' : 'false';

                // Enable/disable inputs based on visibility
                const inputs = field.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.disabled = !shouldBeVisible;
                });
            } catch (e) {
                console.error('ULO: Error parsing conditions', e);
            }
        });
    }

    /**
     * Evaluate conditions for a single field
     * @param {Object} conditions - Conditions configuration
     * @returns {boolean} Whether field should be visible
     */
    evaluateFieldConditions(conditions) {
        const action = conditions.action || 'show';
        const rules = conditions.rules || [];

        if (rules.length === 0) {
            return true;
        }

        // All rules must match (AND logic)
        const allMatch = rules.every(rule => this.evaluateRule(rule));

        // If action is 'show', visible when all rules match
        // If action is 'hide', visible when NOT all rules match
        return action === 'show' ? allMatch : !allMatch;
    }

    /**
     * Evaluate a single condition rule
     * @param {Object} rule - Condition rule
     * @returns {boolean} Whether rule matches
     */
    evaluateRule(rule) {
        const { field: targetField, operator, value: expectedValue } = rule;

        // Find target field value - use data-field-id which is set by PHP
        const targetFieldEl = this.container.querySelector(`.ulo-field[data-field-id="${targetField}"]`);

        if (!targetFieldEl) {
            return false;
        }

        const currentValue = this.getFieldValue(targetFieldEl);

        // Evaluate based on operator
        switch (operator) {
            case 'equals':
                return this.compareEquals(currentValue, expectedValue);

            case 'not_equals':
                return !this.compareEquals(currentValue, expectedValue);

            case 'contains':
                return this.compareContains(currentValue, expectedValue);

            case 'not_contains':
                return !this.compareContains(currentValue, expectedValue);

            case 'empty':
                return this.isEmpty(currentValue);

            case 'not_empty':
                return !this.isEmpty(currentValue);

            case 'greater_than':
                return parseFloat(currentValue) > parseFloat(expectedValue);

            case 'less_than':
                return parseFloat(currentValue) < parseFloat(expectedValue);

            default:
                return false;
        }
    }

    /**
     * Get value from a field element
     * @param {HTMLElement} fieldEl - Field element
     * @returns {*} Field value
     */
    getFieldValue(fieldEl) {
        const radios = fieldEl.querySelectorAll('input[type="radio"]');
        if (radios.length > 0) {
            const checked = fieldEl.querySelector('input[type="radio"]:checked');
            return checked ? checked.value : '';
        }

        const checkboxes = fieldEl.querySelectorAll('input[type="checkbox"]');
        if (checkboxes.length > 0) {
            // Single checkbox (not a group)
            if (checkboxes.length === 1) {
                return checkboxes[0].checked ? '1' : '';
            }

            // Checkbox group - return array of checked values
            const values = [];
            checkboxes.forEach(cb => {
                if (cb.checked) values.push(cb.value);
            });
            return values;
        }

        const select = fieldEl.querySelector('select');
        if (select) {
            return select.value;
        }

        const input = fieldEl.querySelector('input, textarea');
        if (input) {
            return input.value;
        }

        return '';
    }

    /**
     * Compare equality (handles arrays)
     * @param {*} current - Current value
     * @param {*} expected - Expected value
     * @returns {boolean} Whether equal
     */
    compareEquals(current, expected) {
        if (Array.isArray(current)) {
            return current.includes(expected);
        }
        return String(current) === String(expected);
    }

    /**
     * Compare contains (handles arrays)
     * @param {*} current - Current value
     * @param {*} expected - Expected value
     * @returns {boolean} Whether contains
     */
    compareContains(current, expected) {
        if (Array.isArray(current)) {
            return current.some(v => String(v).includes(expected));
        }
        return String(current).includes(expected);
    }

    /**
     * Check if value is empty
     * @param {*} value - Value to check
     * @returns {boolean} Whether empty
     */
    isEmpty(value) {
        if (Array.isArray(value)) {
            return value.length === 0;
        }
        return value === '' || value === null || value === undefined;
    }

    /**
     * Initialize file upload handlers
     */
    initFileUploads() {
        const fileFields = this.container.querySelectorAll('.ulo-field-file');

        fileFields.forEach(field => {
            const dropzone = field.querySelector('.ulo-file-dropzone');
            const fileInput = field.querySelector('input[type="file"]');
            const preview = field.querySelector('.ulo-file-preview');

            if (!dropzone || !fileInput) return;

            // Drag and drop events
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('ulo-dragover');
            });

            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('ulo-dragover');
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('ulo-dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFileUpload(field, files[0]);
                }
            });

            // File input change
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    this.handleFileUpload(field, fileInput.files[0]);
                }
            });

            // Remove button
            const removeBtn = preview?.querySelector('.ulo-file-preview-remove');
            if (removeBtn) {
                removeBtn.addEventListener('click', () => {
                    this.removeFile(field);
                });
            }
        });
    }

    /**
     * Handle file upload
     * @param {HTMLElement} field - Field element
     * @param {File} file - File to upload
     */
    async handleFileUpload(field, file) {
        const progress = field.querySelector('.ulo-file-progress');
        const progressFill = progress?.querySelector('.ulo-file-progress-fill');
        const progressText = progress?.querySelector('.ulo-file-progress-text');
        const preview = field.querySelector('.ulo-file-preview');
        const fieldName = field.dataset.fieldName;

        // Show progress
        if (progress) {
            progress.classList.add('ulo-uploading');
        }

        try {
            const formData = new FormData();
            formData.append('action', 'ulo_upload_file');
            formData.append('nonce', uloFrontend.nonce);
            formData.append('file', file);
            formData.append('field_name', fieldName);
            formData.append('product_id', this.productId);

            const xhr = new XMLHttpRequest();

            // Progress tracking
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable && progressFill) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = percent + '%';
                    if (progressText) {
                        progressText.textContent = percent + '%';
                    }
                }
            });

            // Complete handler
            xhr.addEventListener('load', () => {
                if (progress) {
                    progress.classList.remove('ulo-uploading');
                }

                try {
                    const response = JSON.parse(xhr.responseText);

                    if (response.success) {
                        this.showFilePreview(field, file, response.data);
                        field.dataset.uploadedFile = response.data.file_id;
                        this.calculatePrice();
                    } else {
                        this.showFileError(field, response.data.message);
                    }
                } catch (e) {
                    this.showFileError(field, 'Upload failed');
                }
            });

            xhr.addEventListener('error', () => {
                if (progress) {
                    progress.classList.remove('ulo-uploading');
                }
                this.showFileError(field, 'Upload failed');
            });

            xhr.open('POST', uloFrontend.ajaxUrl);
            xhr.send(formData);
        } catch (error) {
            console.error('ULO: File upload error', error);
            if (progress) {
                progress.classList.remove('ulo-uploading');
            }
            this.showFileError(field, 'Upload failed');
        }
    }

    /**
     * Show file preview
     * @param {HTMLElement} field - Field element
     * @param {File} file - Uploaded file
     * @param {Object} data - Server response data
     */
    showFilePreview(field, file, data) {
        const preview = field.querySelector('.ulo-file-preview');
        const dropzone = field.querySelector('.ulo-file-dropzone');

        if (!preview) return;

        // Update preview content
        const nameEl = preview.querySelector('.ulo-file-preview-name');
        const sizeEl = preview.querySelector('.ulo-file-preview-size');
        const imageEl = preview.querySelector('.ulo-file-preview-image');

        if (nameEl) nameEl.textContent = file.name;
        if (sizeEl) sizeEl.textContent = this.formatFileSize(file.size);

        // Show image preview if applicable
        if (imageEl && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imageEl.src = e.target.result;
                imageEl.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        preview.classList.add('ulo-has-file');
        if (dropzone) dropzone.style.display = 'none';
    }

    /**
     * Show file upload error
     * @param {HTMLElement} field - Field element
     * @param {string} message - Error message
     */
    showFileError(field, message) {
        let errorEl = field.querySelector('.ulo-field-error');

        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'ulo-field-error';
            field.appendChild(errorEl);
        }

        errorEl.textContent = message;
        field.classList.add('ulo-has-error');

        // Auto-clear after 5 seconds
        setTimeout(() => {
            field.classList.remove('ulo-has-error');
        }, 5000);
    }

    /**
     * Remove uploaded file
     * @param {HTMLElement} field - Field element
     */
    removeFile(field) {
        const preview = field.querySelector('.ulo-file-preview');
        const dropzone = field.querySelector('.ulo-file-dropzone');
        const fileInput = field.querySelector('input[type="file"]');

        if (preview) {
            preview.classList.remove('ulo-has-file');
        }
        if (dropzone) {
            dropzone.style.display = 'flex';
        }
        if (fileInput) {
            fileInput.value = '';
        }

        delete field.dataset.uploadedFile;
        this.calculatePrice();
        this.updateHiddenInput();
    }

    /**
     * Format file size
     * @param {number} bytes - File size in bytes
     * @returns {string} Formatted size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Set loading state
     * @param {boolean} loading - Whether loading
     */
    setLoading(loading) {
        if (loading) {
            this.container.classList.add('ulo-loading');
        } else {
            this.container.classList.remove('ulo-loading');
        }
    }

    /**
     * Escape HTML
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

/**
 * Form Validation Handler
 */
class ULOValidation {
    /**
     * Constructor
     * @param {HTMLElement} container - Options container
     */
    constructor(container) {
        this.container = container;
        this.bindFormSubmit();
    }

    /**
     * Bind form submit validation
     */
    bindFormSubmit() {
        const form = this.container.closest('form.cart');

        if (!form) return;

        form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
                e.stopPropagation();

                // Scroll to first error
                const firstError = this.container.querySelector('.ulo-has-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }

    /**
     * Validate all visible required fields
     * @returns {boolean} Whether valid
     */
    validate() {
        let isValid = true;

        // Clear previous errors
        this.container.querySelectorAll('.ulo-has-error').forEach(el => {
            el.classList.remove('ulo-has-error');
        });

        // Check visible required fields
        const visibleFields = this.container.querySelectorAll('.ulo-field[data-visible="true"], .ulo-field:not([data-visible])');

        visibleFields.forEach(field => {
            const isRequired = field.dataset.required === 'true';

            if (!isRequired) return;

            const value = this.getFieldValue(field);

            if (this.isEmpty(value)) {
                isValid = false;
                field.classList.add('ulo-has-error');

                // Set error message
                let errorEl = field.querySelector('.ulo-field-error');
                if (!errorEl) {
                    errorEl = document.createElement('div');
                    errorEl.className = 'ulo-field-error';
                    field.appendChild(errorEl);
                }
                errorEl.textContent = uloFrontend.i18n?.requiredField || 'This field is required.';
            }
        });

        return isValid;
    }

    /**
     * Get field value
     * @param {HTMLElement} field - Field element
     * @returns {*} Field value
     */
    getFieldValue(field) {
        const radios = field.querySelectorAll('input[type="radio"]');
        if (radios.length > 0) {
            const checked = field.querySelector('input[type="radio"]:checked');
            return checked ? checked.value : '';
        }

        const checkboxes = field.querySelectorAll('input[type="checkbox"]');
        if (checkboxes.length > 0) {
            const values = [];
            checkboxes.forEach(cb => {
                if (cb.checked) values.push(cb.value);
            });
            return values;
        }

        const select = field.querySelector('select');
        if (select) {
            return select.value;
        }

        const input = field.querySelector('input, textarea');
        if (input) {
            return input.value;
        }

        // Check for uploaded file
        if (field.dataset.uploadedFile) {
            return field.dataset.uploadedFile;
        }

        return '';
    }

    /**
     * Check if value is empty
     * @param {*} value - Value to check
     * @returns {boolean} Whether empty
     */
    isEmpty(value) {
        if (Array.isArray(value)) {
            return value.length === 0;
        }
        return value === '' || value === null || value === undefined;
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const frontend = new ULOFrontend();
    frontend.init();

    // Initialize validation
    const container = document.querySelector('.ulo-product-options');
    if (container) {
        new ULOValidation(container);
    }
});

// Export for external use
window.ULOFrontend = ULOFrontend;
window.ULOValidation = ULOValidation;
