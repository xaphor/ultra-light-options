/**
 * Ultra Light Options - Admin Field Builder
 * Vanilla ES6+ - No jQuery dependency
 *
 * @package UltraLightOptions
 * @since 2.0.0
 */

'use strict';

/**
 * Main Admin Builder Class
 */
class ULOBuilder {
    /**
     * Constructor
     */
    constructor() {
        this.currentGroup = null;
        this.currentFieldIndex = -1;
        this.groups = [];
        this.isDirty = false;

        // DOM elements
        this.panel = null;
        this.overlay = null;
        this.groupsList = null;

        // Bind methods
        this.init = this.init.bind(this);
        this.openPanel = this.openPanel.bind(this);
        this.closePanel = this.closePanel.bind(this);
        this.saveGroup = this.saveGroup.bind(this);
    }

    /**
     * Initialize the builder
     */
    init() {
        this.panel = document.querySelector('.ulo-builder-panel');
        this.overlay = document.querySelector('.ulo-builder-overlay');
        this.groupsList = document.querySelector('.ulo-field-groups-list');

        if (!this.panel) {
            this.createPanelHTML();
        }

        this.bindEvents();
        this.loadGroups();
    }

    /**
     * Create panel HTML dynamically
     */
    createPanelHTML() {
        // Create overlay
        this.overlay = document.createElement('div');
        this.overlay.className = 'ulo-builder-overlay';
        document.body.appendChild(this.overlay);

        // Create panel
        this.panel = document.createElement('div');
        this.panel.className = 'ulo-builder-panel';
        this.panel.innerHTML = this.getPanelTemplate();
        document.body.appendChild(this.panel);
    }

    /**
     * Get panel template HTML
     * @returns {string} Panel HTML
     */
    getPanelTemplate() {
        return `
            <div class="ulo-builder-header">
                <h2 class="ulo-builder-title">${uloAdmin.i18n.newFieldGroup}</h2>
                <button type="button" class="ulo-builder-close">&times;</button>
            </div>
            <div class="ulo-builder-body">
                <div class="ulo-builder-tabs">
                    <button type="button" class="ulo-builder-tab ulo-active" data-tab="fields">${uloAdmin.i18n.fields}</button>
                    <button type="button" class="ulo-builder-tab" data-tab="rules">${uloAdmin.i18n.assignmentRules}</button>
                    <button type="button" class="ulo-builder-tab" data-tab="settings">${uloAdmin.i18n.settings}</button>
                </div>

                <!-- Fields Tab -->
                <div class="ulo-builder-tab-content ulo-active" data-tab-content="fields">
                    ${this.getFieldsTabTemplate()}
                </div>

                <!-- Rules Tab -->
                <div class="ulo-builder-tab-content" data-tab-content="rules">
                    ${this.getRulesTabTemplate()}
                </div>

                <!-- Settings Tab -->
                <div class="ulo-builder-tab-content" data-tab-content="settings">
                    ${this.getSettingsTabTemplate()}
                </div>
            </div>
            <div class="ulo-builder-footer">
                <button type="button" class="button ulo-builder-cancel">${uloAdmin.i18n.cancel}</button>
                <button type="button" class="button button-primary ulo-builder-save">${uloAdmin.i18n.saveGroup}</button>
            </div>
        `;
    }

    /**
     * Get fields tab template
     * @returns {string} HTML
     */
    getFieldsTabTemplate() {
        return `
            <div class="ulo-builder-section">
                <label class="ulo-builder-label">${uloAdmin.i18n.groupTitle} <span class="required">*</span></label>
                <input type="text" class="ulo-builder-input" id="ulo-group-title" required>
            </div>

            <div class="ulo-builder-section">
                <h3 class="ulo-builder-section-title">${uloAdmin.i18n.fields}</h3>
                <div class="ulo-fields-list">
                    <div class="ulo-fields-list-empty">${uloAdmin.i18n.noFields}</div>
                </div>
                <button type="button" class="ulo-add-field-btn">+ ${uloAdmin.i18n.addField}</button>
            </div>

            <!-- Field Editor (initially hidden) -->
            <div class="ulo-field-editor" style="display: none;">
                ${this.getFieldEditorTemplate()}
            </div>
        `;
    }

    /**
     * Get field editor template
     * @returns {string} HTML
     */
    getFieldEditorTemplate() {
        return `
            <div class="ulo-builder-section">
                <h3 class="ulo-builder-section-title">${uloAdmin.i18n.editField}</h3>

                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.fieldType}</label>
                    <div class="ulo-field-type-grid">
                        ${this.getFieldTypeOptions()}
                    </div>
                </div>

                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.fieldLabel} <span class="required">*</span></label>
                    <input type="text" class="ulo-builder-input" id="ulo-field-label" required>
                </div>

                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.fieldName}</label>
                    <input type="text" class="ulo-builder-input" id="ulo-field-name" placeholder="${uloAdmin.i18n.autoGenerated}">
                    <p class="ulo-builder-description">${uloAdmin.i18n.fieldNameDesc}</p>
                </div>

                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.placeholder}</label>
                    <input type="text" class="ulo-builder-input" id="ulo-field-placeholder">
                </div>

                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.description}</label>
                    <textarea class="ulo-builder-textarea" id="ulo-field-description"></textarea>
                </div>

                <div class="ulo-builder-row ulo-badges-config">
                    <div class="ulo-builder-col">
                        <label class="ulo-builder-label">${uloAdmin.i18n.badgeText}</label>
                        <input type="text" class="ulo-builder-input" id="ulo-field-badge" placeholder="e.g. Save 20%">
                    </div>
                    <div class="ulo-builder-col">
                        <label class="ulo-builder-label">${uloAdmin.i18n.badgeColor}</label>
                        <input type="color" class="ulo-builder-input" id="ulo-field-badge-color" value="#ef4444">
                    </div>
                    <div class="ulo-builder-col ulo-builder-toggle" style="margin-top: 25px;">
                        <input type="checkbox" id="ulo-field-badge-pulse">
                        <label for="ulo-field-badge-pulse">${uloAdmin.i18n.pulseAnimation}</label>
                    </div>
                </div>

                <div class="ulo-builder-row ulo-builder-toggle">
                    <input type="checkbox" id="ulo-field-required">
                    <label for="ulo-field-required">${uloAdmin.i18n.required}</label>
                </div>

                <!-- Options section (for radio, checkbox, select) -->
                <div class="ulo-field-options-section" style="display: none;">
                    <div class="ulo-builder-row">
                        <label class="ulo-builder-label">${uloAdmin.i18n.options}</label>
                        <div class="ulo-options-builder">
                            <div class="ulo-options-list"></div>
                            <button type="button" class="ulo-add-option-btn">+ ${uloAdmin.i18n.addOption}</button>
                        </div>
                    </div>
                </div>

                <!-- Pricing section -->
                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.pricing}</label>
                    <div class="ulo-pricing-config">
                        <div class="ulo-pricing-type-row">
                            <label class="ulo-pricing-type-option">
                                <input type="radio" name="ulo-pricing-type" value="none" checked>
                                <span class="ulo-pricing-type-label">${uloAdmin.i18n.noPricing}</span>
                            </label>
                            <label class="ulo-pricing-type-option">
                                <input type="radio" name="ulo-pricing-type" value="flat">
                                <span class="ulo-pricing-type-label">${uloAdmin.i18n.flatFee}</span>
                            </label>
                            <label class="ulo-pricing-type-option">
                                <input type="radio" name="ulo-pricing-type" value="quantity_flat">
                                <span class="ulo-pricing-type-label">${uloAdmin.i18n.quantityFlat}</span>
                            </label>
                            <label class="ulo-pricing-type-option">
                                <input type="radio" name="ulo-pricing-type" value="formula">
                                <span class="ulo-pricing-type-label">${uloAdmin.i18n.formula}</span>
                            </label>
                            <label class="ulo-pricing-type-option">
                                <input type="radio" name="ulo-pricing-type" value="field_value">
                                <span class="ulo-pricing-type-label">${uloAdmin.i18n.fieldValue}</span>
                            </label>
                            <label class="ulo-pricing-type-option">
                                <input type="radio" name="ulo-pricing-type" value="tiered">
                                <span class="ulo-pricing-type-label">Tiered Pricing</span>
                            </label>
                        </div>

                        <!-- Flat fee fields -->
                        <div class="ulo-pricing-fields" data-pricing-type="flat">
                            <label class="ulo-builder-label">${uloAdmin.i18n.price}</label>
                            <input type="number" step="0.01" class="ulo-builder-input" id="ulo-field-price">
                        </div>

                        <!-- Quantity flat fields -->
                        <div class="ulo-pricing-fields" data-pricing-type="quantity_flat">
                            <label class="ulo-builder-label">${uloAdmin.i18n.pricePerUnit}</label>
                            <input type="number" step="0.01" class="ulo-builder-input" id="ulo-field-price-per-unit">
                        </div>

                        <!-- Formula fields -->
                        <div class="ulo-pricing-fields" data-pricing-type="formula">
                            <label class="ulo-builder-label">${uloAdmin.i18n.formula}</label>
                            <input type="text" class="ulo-builder-input ulo-formula-input" id="ulo-field-formula" placeholder="{quantity} * 5 + {width} * 2">
                            <div class="ulo-formula-help">
                                <strong>${uloAdmin.i18n.availableVariables}:</strong>
                                <code>{quantity}</code>, <code>{base_price}</code>, <code>{product.weight}</code>, <code>{product.length}</code>, <code>{product.width}</code>, <code>{product.height}</code>
                                <br><strong>${uloAdmin.i18n.examples}:</strong>
                                <code>{quantity} * 10</code> - $10 per unit
                            </div>
                            <button type="button" class="button ulo-test-formula-btn">${uloAdmin.i18n.testFormula}</button>
                        </div>

                        <!-- Field value fields -->
                        <div class="ulo-pricing-fields" data-pricing-type="field_value">
                            <label class="ulo-builder-label">${uloAdmin.i18n.multiplier}</label>
                            <input type="number" step="0.01" class="ulo-builder-input" id="ulo-field-multiplier" value="1" placeholder="1">
                            <p class="ulo-builder-description">${uloAdmin.i18n.multiplierDesc}</p>
                        </div>

                        <!-- Tiered pricing fields -->
                        <div class="ulo-pricing-fields" data-pricing-type="tiered">
                            <div class="ulo-tiered-pricing-config">
                                <div class="ulo-builder-row">
                                    <label class="ulo-builder-label">Minimum Price (floor)</label>
                                    <input type="number" step="0.01" class="ulo-builder-input" id="ulo-tiered-base-price" value="0" placeholder="0">
                                    <p class="ulo-builder-description">Final price = MAX(Minimum Price, Price/Unit × Qty)</p>
                                </div>

                                <div class="ulo-builder-row">
                                    <label class="ulo-builder-label">Price Tiers</label>
                                    <table class="ulo-tiers-table">
                                        <thead>
                                            <tr>
                                                <th>Qty From</th>
                                                <th>Qty To</th>
                                                <th>Price/Unit</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="ulo-tiers-list"></tbody>
                                    </table>
                                    <button type="button" class="button ulo-add-tier-btn">+ Add Tier</button>
                                </div>
                                <div class="ulo-tiered-preview">
                                    <label class="ulo-builder-label">Preview</label>
                                    <div id="ulo-tiered-preview-content" class="ulo-preview-box"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conditional Logic -->
                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.conditionalLogic}</label>
                    <div class="ulo-builder-toggle">
                        <input type="checkbox" id="ulo-field-has-conditions">
                        <label for="ulo-field-has-conditions">${uloAdmin.i18n.enableConditions}</label>
                    </div>

                    <div class="ulo-conditions-builder" style="display: none;">
                        <div class="ulo-builder-row">
                            <select class="ulo-builder-select" id="ulo-condition-action">
                                <option value="show">${uloAdmin.i18n.showField}</option>
                                <option value="hide">${uloAdmin.i18n.hideField}</option>
                            </select>
                            <span>${uloAdmin.i18n.ifAllMatch}</span>
                        </div>
                        <div class="ulo-conditions-list"></div>
                        <button type="button" class="ulo-add-condition-btn">+ ${uloAdmin.i18n.addCondition}</button>
                    </div>
                </div>

                <div class="ulo-field-editor-actions">
                    <button type="button" class="button ulo-field-cancel">${uloAdmin.i18n.cancel}</button>
                    <button type="button" class="button button-primary ulo-field-save">${uloAdmin.i18n.saveField}</button>
                </div>
            </div>
        `;
    }

    /**
     * Get field type options HTML
     * @returns {string} HTML
     */
    getFieldTypeOptions() {
        const types = [
            { value: 'text', label: uloAdmin.i18n.text, icon: 'dashicons-editor-textcolor' },
            { value: 'textarea', label: uloAdmin.i18n.textarea, icon: 'dashicons-editor-paragraph' },
            { value: 'number', label: uloAdmin.i18n.number, icon: 'dashicons-calculator' },
            { value: 'radio', label: uloAdmin.i18n.radio, icon: 'dashicons-marker' },
            { value: 'checkbox', label: uloAdmin.i18n.checkbox, icon: 'dashicons-yes-alt' },
            { value: 'select', label: uloAdmin.i18n.select, icon: 'dashicons-arrow-down-alt2' },
            { value: 'date', label: uloAdmin.i18n.date, icon: 'dashicons-calendar-alt' },
            { value: 'time', label: uloAdmin.i18n.time, icon: 'dashicons-clock' },
            { value: 'file', label: uloAdmin.i18n.file, icon: 'dashicons-media-default' },
            { value: 'html', label: uloAdmin.i18n.html, icon: 'dashicons-editor-code' },
        ];

        return types.map(type => `
            <label class="ulo-field-type-option" data-type="${type.value}">
                <input type="radio" name="ulo-field-type" value="${type.value}">
                <span class="dashicons ${type.icon} ulo-field-type-icon"></span>
                <span class="ulo-field-type-label">${type.label}</span>
            </label>
        `).join('');
    }

    /**
     * Get rules tab template
     * @returns {string} HTML
     */
    getRulesTabTemplate() {
        return `
            <div class="ulo-rules-builder">
                <div class="ulo-rule-option">
                    <input type="radio" name="ulo-rule-type" value="all" id="ulo-rule-all">
                    <label class="ulo-rule-option-label" for="ulo-rule-all">
                        <span class="ulo-rule-option-title">${uloAdmin.i18n.allProducts}</span>
                        <span class="ulo-rule-option-desc">${uloAdmin.i18n.allProductsDesc}</span>
                    </label>
                </div>

                <div class="ulo-rule-option">
                    <input type="radio" name="ulo-rule-type" value="products" id="ulo-rule-products">
                    <label class="ulo-rule-option-label" for="ulo-rule-products">
                        <span class="ulo-rule-option-title">${uloAdmin.i18n.specificProducts}</span>
                        <span class="ulo-rule-option-desc">${uloAdmin.i18n.specificProductsDesc}</span>
                    </label>
                </div>

                <div class="ulo-rule-products-search">
                    <div class="ulo-search-input-wrap">
                        <span class="dashicons dashicons-search ulo-search-icon"></span>
                        <input type="text" class="ulo-search-input" id="ulo-product-search-input" placeholder="${uloAdmin.i18n.searchProducts}">
                        <div id="ulo-product-search-results" class="ulo-search-results"></div>
                    </div>
                    <div id="ulo-selected-products" class="ulo-selected-products"></div>
                </div>

                <div class="ulo-rule-option">
                    <input type="radio" name="ulo-rule-type" value="variations" id="ulo-rule-variations">
                    <label class="ulo-rule-option-label" for="ulo-rule-variations">
                        <span class="ulo-rule-option-title">${uloAdmin.i18n.specificVariations}</span>
                        <span class="ulo-rule-option-desc">${uloAdmin.i18n.specificVariationsDesc}</span>
                    </label>
                </div>

                <div class="ulo-rule-variations-search" style="display: none;">
                    <div class="ulo-search-input-wrap">
                        <span class="dashicons dashicons-search ulo-search-icon"></span>
                        <input type="text" class="ulo-search-input" id="ulo-variation-search-input" placeholder="${uloAdmin.i18n.searchProducts}">
                        <div id="ulo-variation-search-results" class="ulo-search-results"></div>
                    </div>
                    <div id="ulo-selected-variations" class="ulo-selected-variations"></div>
                </div>
            </div>
        `;
    }

    /**
     * Get settings tab template
     * @returns {string} HTML
     */
    getSettingsTabTemplate() {
        return `
            <div class="ulo-builder-section">
                <div class="ulo-builder-row ulo-builder-toggle">
                    <input type="checkbox" id="ulo-group-active" checked>
                    <label for="ulo-group-active">${uloAdmin.i18n.groupActive}</label>
                </div>

                <div class="ulo-builder-row">
                    <label class="ulo-builder-label">${uloAdmin.i18n.priority}</label>
                    <input type="number" class="ulo-builder-input" id="ulo-group-priority" value="10" min="0" max="100">
                    <p class="ulo-builder-description">${uloAdmin.i18n.priorityDesc}</p>
                </div>
            </div>
        `;
    }

    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Panel close
        this.overlay?.addEventListener('click', this.closePanel);
        this.panel?.querySelector('.ulo-builder-close')?.addEventListener('click', this.closePanel);
        this.panel?.querySelector('.ulo-builder-cancel')?.addEventListener('click', this.closePanel);

        // Save group
        this.panel?.querySelector('.ulo-builder-save')?.addEventListener('click', this.saveGroup);

        // Tab switching
        this.panel?.querySelectorAll('.ulo-builder-tab').forEach(tab => {
            tab.addEventListener('click', (e) => this.switchTab(e.target.dataset.tab));
        });

        // Add new group button
        document.querySelector('.ulo-add-group-btn')?.addEventListener('click', () => this.openPanel());

        // Add field button
        this.panel?.querySelector('.ulo-add-field-btn')?.addEventListener('click', () => this.showFieldEditor());

        // Field type selection
        this.panel?.querySelectorAll('.ulo-field-type-option').forEach(option => {
            option.addEventListener('click', () => this.selectFieldType(option.dataset.type));
        });

        // Pricing type toggle
        this.panel?.querySelectorAll('input[name="ulo-pricing-type"]').forEach(radio => {
            radio.addEventListener('change', (e) => this.togglePricingFields(e.target.value));
        });

        // Conditional logic toggle
        this.panel?.querySelector('#ulo-field-has-conditions')?.addEventListener('change', (e) => {
            this.toggleConditionsBuilder(e.target.checked);
        });

        // Add option button
        this.panel?.querySelector('.ulo-add-option-btn')?.addEventListener('click', () => this.addOption());

        // Add condition button
        this.panel?.querySelector('.ulo-add-condition-btn')?.addEventListener('click', () => this.addCondition());

        // Field editor actions
        this.panel?.querySelector('.ulo-field-cancel')?.addEventListener('click', () => this.hideFieldEditor());
        this.panel?.querySelector('.ulo-field-save')?.addEventListener('click', () => this.saveField());

        // Rule type toggle
        this.panel?.querySelectorAll('input[name="ulo-rule-type"]').forEach(radio => {
            radio.addEventListener('change', (e) => this.toggleRuleSearch(e.target.value));
        });

        // Product search
        this.panel?.querySelector('#ulo-product-search-input')?.addEventListener('input',
            this.debounce((e) => this.searchProducts(e.target.value), 300));

        // Variation search
        this.panel?.querySelector('#ulo-variation-search-input')?.addEventListener('input',
            this.debounce((e) => this.searchVariations(e.target.value), 300));

        // Test formula button
        this.panel?.querySelector('.ulo-test-formula-btn')?.addEventListener('click', () => this.testFormula());

        // Add tier button for tiered pricing
        this.panel?.querySelector('.ulo-add-tier-btn')?.addEventListener('click', () => this.addTier());

        // Tier table event delegation for remove and input changes
        this.panel?.querySelector('#ulo-tiers-list')?.addEventListener('click', (e) => {
            if (e.target.classList.contains('ulo-remove-tier-btn')) {
                e.target.closest('tr').remove();
                this.updateTieredPreview();
            }
        });
        this.panel?.querySelector('#ulo-tiers-list')?.addEventListener('input', () => this.updateTieredPreview());
        this.panel?.querySelector('#ulo-tiered-base-price')?.addEventListener('input', () => this.updateTieredPreview());

        // Field label auto-generate name
        this.panel?.querySelector('#ulo-field-label')?.addEventListener('input', (e) => {
            const nameField = this.panel.querySelector('#ulo-field-name');
            if (!nameField.dataset.manual) {
                nameField.value = this.generateFieldName(e.target.value);
            }
        });

        this.panel?.querySelector('#ulo-field-name')?.addEventListener('input', (e) => {
            e.target.dataset.manual = 'true';
        });

        // Group list actions (edit, delete, duplicate)
        this.groupsList?.addEventListener('click', (e) => {
            const action = e.target.dataset.action;
            const groupId = e.target.closest('[data-group-id]')?.dataset.groupId;

            if (action && groupId) {
                this.handleGroupAction(action, groupId);
            }
        });
    }

    /**
     * Load all field groups
     */
    async loadGroups() {
        try {
            const formData = new FormData();
            formData.append('action', 'ulo_get_all_field_groups');
            formData.append('nonce', uloAdmin.nonce);

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                // Convert object to array if needed (PHP returns associative array)
                const groupsData = data.data.groups || {};
                if (Array.isArray(groupsData)) {
                    this.groups = groupsData;
                } else {
                    // Convert object to array with id property
                    this.groups = Object.entries(groupsData).map(([id, group]) => ({
                        id,
                        ...group
                    }));
                }
                this.renderGroupsList();
            }
        } catch (error) {
            console.error('ULO: Error loading groups', error);
        }
    }

    /**
     * Render groups list
     */
    renderGroupsList() {
        if (!this.groupsList) return;

        if (this.groups.length === 0) {
            this.groupsList.innerHTML = `
                <div class="ulo-empty-state">
                    <div class="ulo-empty-state-icon">📋</div>
                    <div class="ulo-empty-state-title">${uloAdmin.i18n.noGroups}</div>
                    <div class="ulo-empty-state-text">${uloAdmin.i18n.noGroupsDesc}</div>
                    <button type="button" class="button button-primary ulo-add-group-btn">
                        ${uloAdmin.i18n.createFirstGroup}
                    </button>
                </div>
            `;
            return;
        }

        let html = '';
        this.groups.forEach(group => {
            const fieldCount = group.fields?.length || 0;
            const isActive = group.active !== false;
            const groupTitle = group.title || group.name || 'Untitled Group';

            html += `
                <div class="ulo-field-group-item" data-group-id="${this.escapeHtml(group.id)}">
                    <span class="ulo-field-group-handle dashicons dashicons-menu"></span>
                    <div class="ulo-field-group-info">
                        <h4 class="ulo-field-group-title">${this.escapeHtml(groupTitle)}</h4>
                        <div class="ulo-field-group-meta">
                            <span class="ulo-field-group-status ${isActive ? 'ulo-active' : 'ulo-inactive'}">
                                ${isActive ? uloAdmin.i18n.active : uloAdmin.i18n.inactive}
                            </span>
                            <span>${fieldCount} ${fieldCount === 1 ? uloAdmin.i18n.field : uloAdmin.i18n.fields}</span>
                        </div>
                    </div>
                    <div class="ulo-field-group-actions">
                        <button type="button" class="button" data-action="edit">${uloAdmin.i18n.edit}</button>
                        <button type="button" class="button" data-action="duplicate">${uloAdmin.i18n.duplicate}</button>
                        <button type="button" class="button" data-action="delete">${uloAdmin.i18n.delete}</button>
                    </div>
                </div>
            `;
        });

        this.groupsList.innerHTML = html;
    }

    /**
     * Handle group action (edit, delete, duplicate)
     * @param {string} action - Action type
     * @param {string} groupId - Group ID
     */
    async handleGroupAction(action, groupId) {
        switch (action) {
            case 'edit':
                await this.editGroup(groupId);
                break;

            case 'delete':
                if (confirm(uloAdmin.i18n.confirmDelete)) {
                    await this.deleteGroup(groupId);
                }
                break;

            case 'duplicate':
                await this.duplicateGroup(groupId);
                break;
        }
    }

    /**
     * Open panel for editing
     * @param {Object} group - Group data to edit (optional)
     */
    openPanel(group = null) {
        this.currentGroup = group || {
            id: '',
            title: '',
            fields: [],
            rules: { all_products: true },
            active: true,
            priority: 10
        };

        this.populateForm();
        this.panel?.classList.add('ulo-open');
        this.overlay?.classList.add('ulo-open');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close panel
     */
    closePanel() {
        if (this.isDirty && !confirm(uloAdmin.i18n.unsavedChanges)) {
            return;
        }

        this.panel?.classList.remove('ulo-open');
        this.overlay?.classList.remove('ulo-open');
        document.body.style.overflow = '';
        this.isDirty = false;
        this.currentGroup = null;
        this.currentFieldIndex = -1;
    }

    /**
     * Populate form with group data
     */
    populateForm() {
        if (!this.currentGroup) return;

        // Title (handle both 'title' and 'name' properties)
        const titleInput = this.panel?.querySelector('#ulo-group-title');
        if (titleInput) {
            titleInput.value = this.currentGroup.title || this.currentGroup.name || '';
        }

        // Active status
        const activeCheckbox = this.panel?.querySelector('#ulo-group-active');
        if (activeCheckbox) {
            activeCheckbox.checked = this.currentGroup.active !== false;
        }

        // Priority
        const priorityInput = this.panel?.querySelector('#ulo-group-priority');
        if (priorityInput) {
            priorityInput.value = this.currentGroup.priority || 10;
        }

        // Render fields list
        this.renderFieldsList();

        // Set rules
        this.populateRules();

        // Update panel title
        const panelTitle = this.panel?.querySelector('.ulo-builder-title');
        if (panelTitle) {
            panelTitle.textContent = this.currentGroup.id
                ? uloAdmin.i18n.editFieldGroup
                : uloAdmin.i18n.newFieldGroup;
        }
    }

    /**
     * Populate assignment rules
     */
    populateRules() {
        const rules = this.currentGroup?.rules || {};

        // Set rule type
        let ruleType = 'all';
        if (rules.product_ids?.length > 0) {
            ruleType = 'products';
        } else if (rules.variation_ids?.length > 0) {
            ruleType = 'variations';
        }

        const ruleRadio = this.panel?.querySelector(`input[name="ulo-rule-type"][value="${ruleType}"]`);
        if (ruleRadio) {
            ruleRadio.checked = true;
            setTimeout(() => this.toggleRuleSearch(ruleType), 10);
        }

        // Populate selected products
        if (rules.product_ids?.length > 0) {
            // Would need to fetch product names - simplified for now
            const container = this.panel?.querySelector('.ulo-selected-products');
            if (container) {
                container.innerHTML = rules.product_ids.map(id => `
                    <span class="ulo-selected-product" data-id="${id}">
                        Product #${id}
                        <button type="button" class="ulo-selected-product-remove" data-id="${id}">&times;</button>
                    </span>
                `).join('');

                container.querySelectorAll('.ulo-selected-product-remove').forEach(btn => {
                    btn.addEventListener('click', (e) => e.target.closest('.ulo-selected-product').remove());
                });
            }
        }

        // Populate selected variations
        if (rules.variation_ids?.length > 0) {
            const container = this.panel?.querySelector('.ulo-selected-variations');
            if (container) {
                container.innerHTML = rules.variation_ids.map(id => `
                    <span class="ulo-selected-product" data-id="${id}">
                        Variation #${id}
                        <button type="button" class="ulo-selected-product-remove" data-id="${id}">&times;</button>
                    </span>
                `).join('');

                container.querySelectorAll('.ulo-selected-product-remove').forEach(btn => {
                    btn.addEventListener('click', (e) => e.target.closest('.ulo-selected-product').remove());
                });
            }
        }
    }

    /**
     * Render fields list
     */
    renderFieldsList() {
        const container = this.panel?.querySelector('.ulo-fields-list');
        if (!container) return;

        const fields = this.currentGroup?.fields || [];

        if (fields.length === 0) {
            container.innerHTML = `<div class="ulo-fields-list-empty">${uloAdmin.i18n.noFields}</div>`;
            return;
        }

        let html = '';
        fields.forEach((field, index) => {
            html += `
                <div class="ulo-field-item" data-field-index="${index}">
                    <span class="ulo-field-item-handle dashicons dashicons-menu"></span>
                    <span class="dashicons dashicons-${this.getFieldTypeIcon(field.type)} ulo-field-item-icon"></span>
                    <div class="ulo-field-item-info">
                        <span class="ulo-field-item-label">${this.escapeHtml(field.label || 'Untitled')}</span>
                        <span class="ulo-field-item-type">${field.type}</span>
                    </div>
                    <div class="ulo-field-item-actions">
                        <button type="button" class="ulo-field-item-action" data-action="edit" title="${uloAdmin.i18n.edit}">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button type="button" class="ulo-field-item-action ulo-delete" data-action="delete" title="${uloAdmin.i18n.delete}">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        // Bind field item actions
        container.querySelectorAll('.ulo-field-item').forEach((item, index) => {
            item.addEventListener('click', (e) => {
                const action = e.target.closest('[data-action]')?.dataset.action;
                if (action === 'edit' || (!action && !e.target.closest('.ulo-field-item-handle'))) {
                    this.editField(index);
                } else if (action === 'delete') {
                    this.deleteField(index);
                }
            });
        });
    }

    /**
     * Get icon for field type
     * @param {string} type - Field type
     * @returns {string} Dashicon class
     */
    getFieldTypeIcon(type) {
        const icons = {
            text: 'editor-textcolor',
            textarea: 'editor-paragraph',
            number: 'calculator',
            radio: 'marker',
            checkbox: 'yes-alt',
            select: 'arrow-down-alt2',
            date: 'calendar-alt',
            time: 'clock',
            file: 'media-default',
            html: 'editor-code'
        };
        return icons[type] || 'admin-generic';
    }

    /**
     * Show field editor
     * @param {Object} field - Field data (optional for new field)
     */
    showFieldEditor(field = null) {
        const editor = this.panel?.querySelector('.ulo-field-editor');
        const fieldsList = this.panel?.querySelector('.ulo-fields-list');
        const addBtn = this.panel?.querySelector('.ulo-add-field-btn');

        if (editor) editor.style.display = 'block';
        if (fieldsList) fieldsList.style.display = 'none';
        if (addBtn) addBtn.style.display = 'none';

        // Reset form
        this.resetFieldForm();

        // Populate if editing
        if (field) {
            this.populateFieldForm(field);
        }
    }

    /**
     * Hide field editor
     */
    hideFieldEditor() {
        const editor = this.panel?.querySelector('.ulo-field-editor');
        const fieldsList = this.panel?.querySelector('.ulo-fields-list');
        const addBtn = this.panel?.querySelector('.ulo-add-field-btn');

        if (editor) editor.style.display = 'none';
        if (fieldsList) fieldsList.style.display = 'block';
        if (addBtn) addBtn.style.display = 'block';

        this.currentFieldIndex = -1;
    }

    /**
     * Reset field form
     */
    resetFieldForm() {
        // Reset field type selection
        this.panel?.querySelectorAll('.ulo-field-type-option').forEach(option => {
            option.classList.remove('ulo-selected');
            const radio = option.querySelector('input[type="radio"]');
            if (radio) radio.checked = false;
        });

        // Reset inputs
        const inputs = ['#ulo-field-label', '#ulo-field-name', '#ulo-field-placeholder', '#ulo-field-description', '#ulo-field-badge'];
        inputs.forEach(selector => {
            const input = this.panel?.querySelector(selector);
            if (input) {
                input.value = '';
                delete input.dataset.manual;
            }
        });

        const badgeColorInput = this.panel?.querySelector('#ulo-field-badge-color');
        if (badgeColorInput) badgeColorInput.value = '#ef4444';

        const badgePulseInput = this.panel?.querySelector('#ulo-field-badge-pulse');
        if (badgePulseInput) badgePulseInput.checked = false;

        // Reset checkboxes
        const checkboxes = ['#ulo-field-required', '#ulo-field-has-conditions'];
        checkboxes.forEach(selector => {
            const checkbox = this.panel?.querySelector(selector);
            if (checkbox) checkbox.checked = false;
        });

        // Reset pricing
        const noPricingRadio = this.panel?.querySelector('input[name="ulo-pricing-type"][value="none"]');
        if (noPricingRadio) {
            noPricingRadio.checked = true;
            this.togglePricingFields('none');
        }

        // Hide options section
        const optionsSection = this.panel?.querySelector('.ulo-field-options-section');
        if (optionsSection) optionsSection.style.display = 'none';

        // Clear options list
        const optionsList = this.panel?.querySelector('.ulo-options-list');
        if (optionsList) optionsList.innerHTML = '';

        // Hide conditions
        this.toggleConditionsBuilder(false);
    }

    /**
     * Populate field form with data
     * @param {Object} field - Field data
     */
    populateFieldForm(field) {
        // Select field type
        this.selectFieldType(field.type);

        // Fill inputs
        const labelInput = this.panel?.querySelector('#ulo-field-label');
        if (labelInput) labelInput.value = field.label || '';

        const nameInput = this.panel?.querySelector('#ulo-field-name');
        if (nameInput) {
            nameInput.value = field.name || '';
            nameInput.dataset.manual = 'true';
        }

        const placeholderInput = this.panel?.querySelector('#ulo-field-placeholder');
        if (placeholderInput) placeholderInput.value = field.placeholder || '';

        const descInput = this.panel?.querySelector('#ulo-field-description');
        if (descInput) descInput.value = field.description || '';

        const requiredCheckbox = this.panel?.querySelector('#ulo-field-required');
        if (requiredCheckbox) requiredCheckbox.checked = field.required === true;

        const badgeInput = this.panel?.querySelector('#ulo-field-badge');
        if (badgeInput) badgeInput.value = field.badge || '';

        const badgeColorInput = this.panel?.querySelector('#ulo-field-badge-color');
        if (badgeColorInput) badgeColorInput.value = field.badge_color || '#ef4444';

        const badgePulseInput = this.panel?.querySelector('#ulo-field-badge-pulse');
        if (badgePulseInput) badgePulseInput.checked = field.badge_pulse === true;

        // Populate options if applicable
        if (['radio', 'checkbox', 'select'].includes(field.type) && field.options) {
            this.populateOptions(field.options);
        }

        // Populate pricing
        if (field.pricing?.type) {
            const pricingRadio = this.panel?.querySelector(`input[name="ulo-pricing-type"][value="${field.pricing.type}"]`);
            if (pricingRadio) {
                pricingRadio.checked = true;
                this.togglePricingFields(field.pricing.type);
            }

            // Set pricing values
            if (field.pricing.price) {
                const priceInput = this.panel?.querySelector('#ulo-field-price');
                if (priceInput) priceInput.value = field.pricing.price;
            }
            if (field.pricing.formula) {
                const formulaInput = this.panel?.querySelector('#ulo-field-formula');
                if (formulaInput) formulaInput.value = field.pricing.formula;
            }
            if (field.pricing.multiplier) {
                const multiplierInput = this.panel?.querySelector('#ulo-field-multiplier');
                if (multiplierInput) multiplierInput.value = field.pricing.multiplier;
            }
            // Tiered pricing restoration
            if (field.pricing.type === 'tiered') {
                const basePriceInput = this.panel?.querySelector('#ulo-tiered-base-price');
                if (basePriceInput) basePriceInput.value = field.pricing.base_price || 0;
                this.populateTiers(field.pricing.tiers);
            }
        }

        // Populate conditions
        if (field.condition?.rules?.length > 0) {
            const hasConditionsCheckbox = this.panel?.querySelector('#ulo-field-has-conditions');
            if (hasConditionsCheckbox) {
                hasConditionsCheckbox.checked = true;
                this.toggleConditionsBuilder(true);
                this.populateConditions(field.condition);
            }
        }
    }

    /**
     * Select field type
     * @param {string} type - Field type
     */
    selectFieldType(type) {
        this.panel?.querySelectorAll('.ulo-field-type-option').forEach(option => {
            const isSelected = option.dataset.type === type;
            option.classList.toggle('ulo-selected', isSelected);
            const radio = option.querySelector('input[type="radio"]');
            if (radio) radio.checked = isSelected;
        });

        // Show/hide options section
        const optionsSection = this.panel?.querySelector('.ulo-field-options-section');
        if (optionsSection) {
            optionsSection.style.display = ['radio', 'checkbox_group', 'select'].includes(type) ? 'block' : 'none';
        }
    }

    /**
     * Toggle pricing fields visibility
     * @param {string} type - Pricing type
     */
    togglePricingFields(type) {
        this.panel?.querySelectorAll('.ulo-pricing-fields').forEach(section => {
            section.classList.toggle('ulo-active', section.dataset.pricingType === type);
        });

        this.panel?.querySelectorAll('.ulo-pricing-type-option').forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            option.classList.toggle('ulo-selected', radio?.checked);
        });
    }

    /**
     * Toggle conditions builder
     * @param {boolean} show - Whether to show
     */
    toggleConditionsBuilder(show) {
        const builder = this.panel?.querySelector('.ulo-conditions-builder');
        if (builder) {
            builder.style.display = show ? 'block' : 'none';
        }
    }

    /**
     * Toggle rule search based on type
     * @param {string} type - Rule type
     */
    toggleRuleSearch(type) {
        const productsSearch = this.panel?.querySelector('.ulo-rule-products-search');
        const variationsSearch = this.panel?.querySelector('.ulo-rule-variations-search');

        if (productsSearch) {
            const isActive = type === 'products';
            productsSearch.style.display = isActive ? 'block' : 'none';
            productsSearch.classList.toggle('ulo-active', isActive);
        }
        if (variationsSearch) {
            const isActive = type === 'variations';
            variationsSearch.style.display = isActive ? 'block' : 'none';
            variationsSearch.classList.toggle('ulo-active', isActive);
        }
    }

    /**
     * Add option to options list
     * @param {Object} option - Option data (optional)
     */
    addOption(option = null) {
        const container = this.panel?.querySelector('.ulo-options-list');
        if (!container) return;

        const index = container.querySelectorAll('.ulo-option-item').length;
        const div = document.createElement('div');
        div.className = 'ulo-option-item';
        div.innerHTML = `
            <span class="ulo-option-handle dashicons dashicons-menu"></span>
            <div class="ulo-option-inputs">
                <input type="text" class="ulo-option-label-input" placeholder="${uloAdmin.i18n.label}" value="${this.escapeHtml(option?.label || '')}">
                <input type="text" class="ulo-option-value-input" placeholder="${uloAdmin.i18n.value}" value="${this.escapeHtml(option?.value || '')}">
                <input type="number" step="0.01" class="ulo-option-price-input" placeholder="${uloAdmin.i18n.price}" value="${option?.price || ''}">
                <div class="ulo-option-badge-inputs">
                    <input type="text" class="ulo-option-badge-input" placeholder="${uloAdmin.i18n.badgeText}" value="${this.escapeHtml(option?.badge || '')}">
                    <input type="color" class="ulo-option-badge-color-input" value="${option?.badge_color || '#ef4444'}" title="${uloAdmin.i18n.badgeColor}">
                    <label class="ulo-option-pulse-toggle" title="${uloAdmin.i18n.pulseAnimation}">
                        <input type="checkbox" class="ulo-option-badge-pulse-input" ${option?.badge_pulse ? 'checked' : ''}>
                        <span class="dashicons dashicons-marker"></span>
                    </label>
                </div>
            </div>
            <button type="button" class="ulo-option-remove">&times;</button>
        `;

        // Remove button handler
        div.querySelector('.ulo-option-remove')?.addEventListener('click', () => div.remove());

        container.appendChild(div);
    }

    /**
     * Populate options
     * @param {Array} options - Options array
     */
    populateOptions(options) {
        const container = this.panel?.querySelector('.ulo-options-list');
        if (container) container.innerHTML = '';

        options.forEach(option => this.addOption(option));

        const optionsSection = this.panel?.querySelector('.ulo-field-options-section');
        if (optionsSection) optionsSection.style.display = 'block';
    }

    /**
     * Add condition row
     * @param {Object} condition - Condition data (optional)
     */
    addCondition(condition = null) {
        const container = this.panel?.querySelector('.ulo-conditions-list');
        if (!container) return;

        const fields = this.currentGroup?.fields || [];
        const fieldOptions = fields.map(f => {
            const val = f.name || f.id || '';
            const label = f.label || val || 'Unnamed Field';
            return `<option value="${this.escapeHtml(val)}">${this.escapeHtml(label)}</option>`;
        }).join('');

        const div = document.createElement('div');
        div.className = 'ulo-condition-row';
        div.innerHTML = `
            <select class="ulo-condition-select ulo-condition-field">
                <option value="">${uloAdmin.i18n.selectField}</option>
                ${fieldOptions}
            </select>
            <select class="ulo-condition-select ulo-condition-operator">
                <option value="equals">${uloAdmin.i18n.equals}</option>
                <option value="not_equals">${uloAdmin.i18n.notEquals}</option>
                <option value="contains">${uloAdmin.i18n.contains}</option>
                <option value="not_contains">${uloAdmin.i18n.notContains}</option>
                <option value="empty">${uloAdmin.i18n.isEmpty}</option>
                <option value="not_empty">${uloAdmin.i18n.isNotEmpty}</option>
                <option value="greater_than">${uloAdmin.i18n.greaterThan}</option>
                <option value="less_than">${uloAdmin.i18n.lessThan}</option>
            </select>
            <input type="text" class="ulo-condition-value" placeholder="${uloAdmin.i18n.value}">
            <button type="button" class="ulo-condition-remove">&times;</button>
        `;

        // Populate values if editing
        if (condition) {
            const fieldSelect = div.querySelector('.ulo-condition-field');
            const operatorSelect = div.querySelector('.ulo-condition-operator');
            const valueInput = div.querySelector('.ulo-condition-value');

            if (fieldSelect) fieldSelect.value = condition.field || '';
            if (operatorSelect) operatorSelect.value = condition.operator || 'equals';
            if (valueInput) valueInput.value = condition.value || '';
        }

        // Remove button handler
        div.querySelector('.ulo-condition-remove')?.addEventListener('click', () => div.remove());

        container.appendChild(div);
    }

    /**
     * Populate conditions
     * @param {Object} conditions - Conditions object
     */
    populateConditions(conditions) {
        const container = this.panel?.querySelector('.ulo-conditions-list');
        if (container) container.innerHTML = '';

        // Set action
        const actionSelect = this.panel?.querySelector('#ulo-condition-action');
        if (actionSelect) {
            actionSelect.value = conditions.action || 'show';
        }

        // Add condition rows
        (conditions.rules || []).forEach(rule => this.addCondition(rule));
    }

    /**
     * Save field from editor
     */
    saveField() {
        const type = this.panel?.querySelector('input[name="ulo-field-type"]:checked')?.value;
        const label = this.panel?.querySelector('#ulo-field-label')?.value?.trim();
        const name = this.panel?.querySelector('#ulo-field-name')?.value?.trim() || this.generateFieldName(label);

        if (!type) {
            alert(uloAdmin.i18n.selectFieldType);
            return;
        }

        if (!label) {
            alert(uloAdmin.i18n.labelRequired);
            return;
        }

        // Get existing field ID when editing, or generate new one
        let fieldId;
        if (this.currentFieldIndex >= 0 && this.currentGroup?.fields?.[this.currentFieldIndex]?.id) {
            // Preserve existing ID when editing
            fieldId = this.currentGroup.fields[this.currentFieldIndex].id;
        } else {
            // Generate new ID from name or use unique ID
            fieldId = name ? name.toLowerCase().replace(/[^a-z0-9_]/g, '_') : 'field_' + Date.now();
        }

        const field = {
            id: fieldId,
            type,
            label,
            name,
            placeholder: this.panel?.querySelector('#ulo-field-placeholder')?.value || '',
            description: this.panel?.querySelector('#ulo-field-description')?.value || '',
            required: this.panel?.querySelector('#ulo-field-required')?.checked || false,
            badge: this.panel?.querySelector('#ulo-field-badge')?.value || '',
            badge_color: this.panel?.querySelector('#ulo-field-badge-color')?.value || '#ef4444',
            badge_pulse: this.panel?.querySelector('#ulo-field-badge-pulse')?.checked || false
        };

        // Collect options if applicable
        if (['radio', 'checkbox_group', 'select'].includes(type)) {
            field.options = this.collectOptions();
        }

        // Collect pricing
        const pricingType = this.panel?.querySelector('input[name="ulo-pricing-type"]:checked')?.value;
        if (pricingType && pricingType !== 'none') {
            field.pricing = { type: pricingType };

            switch (pricingType) {
                case 'flat':
                case 'quantity_flat':
                    field.pricing.price = parseFloat(this.panel?.querySelector('#ulo-field-price')?.value) || 0;
                    break;
                case 'formula':
                    field.pricing.formula = this.panel?.querySelector('#ulo-field-formula')?.value || '';
                    break;
                case 'field_value':
                    field.pricing.multiplier = parseFloat(this.panel?.querySelector('#ulo-field-multiplier')?.value) || 1;
                    break;
                case 'tiered':
                    field.pricing.base_price = parseFloat(this.panel?.querySelector('#ulo-tiered-base-price')?.value) || 0;
                    field.pricing.tiers = this.collectTiers();
                    break;
            }
        }

        // Collect conditions
        if (this.panel?.querySelector('#ulo-field-has-conditions')?.checked) {
            field.condition = this.collectConditions();
        }

        // Add or update field
        if (!this.currentGroup.fields) {
            this.currentGroup.fields = [];
        }

        if (this.currentFieldIndex >= 0) {
            this.currentGroup.fields[this.currentFieldIndex] = field;
        } else {
            this.currentGroup.fields.push(field);
        }

        this.isDirty = true;
        this.hideFieldEditor();
        this.renderFieldsList();
    }

    /**
     * Collect options from form
     * @returns {Array} Options array
     */
    collectOptions() {
        const options = [];
        this.panel?.querySelectorAll('.ulo-option-item').forEach(item => {
            const label = item.querySelector('.ulo-option-label-input')?.value?.trim();
            const value = item.querySelector('.ulo-option-value-input')?.value?.trim() || label;
            const price = parseFloat(item.querySelector('.ulo-option-price-input')?.value) || 0;
            const badge = item.querySelector('.ulo-option-badge-input')?.value?.trim() || '';
            const badge_color = item.querySelector('.ulo-option-badge-color-input')?.value || '#ef4444';
            const badge_pulse = item.querySelector('.ulo-option-badge-pulse-input')?.checked || false;

            if (label) {
                options.push({ label, value, price, badge, badge_color, badge_pulse });
            }
        });
        return options;
    }

    /**
     * Collect conditions from form
     * @returns {Object} Conditions object
     */
    collectConditions() {
        const conditions = {
            action: this.panel?.querySelector('#ulo-condition-action')?.value || 'show',
            rules: []
        };

        this.panel?.querySelectorAll('.ulo-condition-row').forEach(row => {
            const field = row.querySelector('.ulo-condition-field')?.value;
            const operator = row.querySelector('.ulo-condition-operator')?.value;
            const value = row.querySelector('.ulo-condition-value')?.value;

            if (field && operator) {
                conditions.rules.push({ field, operator, value });
            }
        });

        return conditions;
    }

    /**
     * Add a tier row to the tiered pricing table
     * @param {Object} tier - Tier data (optional)
     */
    addTier(tier = null) {
        const tiersList = this.panel?.querySelector('#ulo-tiers-list');
        if (!tiersList) return;

        const existingRows = tiersList.querySelectorAll('tr').length;
        const qtyFrom = tier?.qty_from ?? (existingRows === 0 ? 1 : '');
        const qtyTo = tier?.qty_to ?? '';
        const pricePerUnit = tier?.price_per_unit ?? '';

        const row = document.createElement('tr');
        row.className = 'ulo-tier-row';
        row.innerHTML = `
            <td><input type="number" class="ulo-tier-qty-from" value="${qtyFrom}" min="1" placeholder="1"></td>
            <td><input type="number" class="ulo-tier-qty-to" value="${qtyTo}" min="1" placeholder="∞ (leave empty)"></td>
            <td><input type="number" step="0.01" class="ulo-tier-price" value="${pricePerUnit}" placeholder="0.00"></td>
            <td><button type="button" class="button-link ulo-remove-tier-btn" title="Remove">✕</button></td>
        `;

        tiersList.appendChild(row);
        this.updateTieredPreview();
    }

    /**
     * Collect tiers data from the table
     * @returns {Array} Tiers array
     */
    collectTiers() {
        const tiers = [];
        this.panel?.querySelectorAll('.ulo-tier-row').forEach(row => {
            const qtyFrom = parseInt(row.querySelector('.ulo-tier-qty-from')?.value) || 1;
            const qtyToValue = row.querySelector('.ulo-tier-qty-to')?.value;
            const qtyTo = qtyToValue ? parseInt(qtyToValue) : null;
            const pricePerUnit = parseFloat(row.querySelector('.ulo-tier-price')?.value) || 0;

            tiers.push({
                qty_from: qtyFrom,
                qty_to: qtyTo,
                price_per_unit: pricePerUnit
            });
        });

        // Sort by qty_from ascending
        tiers.sort((a, b) => a.qty_from - b.qty_from);
        return tiers;
    }

    /**
     * Populate tiers table from saved data
     * @param {Array} tiers - Tiers array
     */
    populateTiers(tiers) {
        const tiersList = this.panel?.querySelector('#ulo-tiers-list');
        if (!tiersList) return;

        tiersList.innerHTML = '';
        if (Array.isArray(tiers) && tiers.length > 0) {
            tiers.forEach(tier => this.addTier(tier));
        } else {
            // Add default first tier
            this.addTier({ qty_from: 1, qty_to: null, price_per_unit: 0 });
        }
    }

    /**
     * Update tiered pricing preview
     */
    updateTieredPreview() {
        const previewEl = this.panel?.querySelector('#ulo-tiered-preview-content');
        if (!previewEl) return;

        const basePrice = parseFloat(this.panel?.querySelector('#ulo-tiered-base-price')?.value) || 0;
        const tiers = this.collectTiers();

        if (tiers.length === 0) {
            previewEl.innerHTML = '<em>Add tiers to see preview</em>';
            return;
        }

        const currency = uloAdmin.currency || '';
        let previewHtml = '<table class="ulo-preview-table"><thead><tr><th>Qty</th><th>Tier Price/Unit</th><th>Option Addon</th></tr></thead><tbody>';

        // Show sample calculations for different quantities
        const sampleQtys = [1, 2, 5, 10, 25, 50, 100];
        sampleQtys.forEach(qty => {
            const tier = this.getTierForQuantity(tiers, qty);
            if (tier) {
                const unitPrice = tier.price_per_unit;
                const tierTotal = unitPrice * qty;
                // Base price is a FLOOR (minimum), not additive
                const addonTotal = Math.max(basePrice, tierTotal);
                previewHtml += `<tr><td>${qty}</td><td>${currency}${unitPrice.toFixed(2)}</td><td>${currency}${addonTotal.toFixed(2)}</td></tr>`;
            }
        });

        previewHtml += '</tbody></table>';
        previewHtml += '<p class="ulo-preview-note"><small><em>Option Addon = MAX(Min Price, Tier×Qty). Added to product price.</em></small></p>';
        previewEl.innerHTML = previewHtml;
    }



    /**
     * Get applicable tier for a given quantity
     * @param {Array} tiers - Tiers array
     * @param {number} qty - Quantity
     * @returns {Object|null} Matching tier
     */
    getTierForQuantity(tiers, qty) {
        for (const tier of tiers) {
            const from = tier.qty_from || 1;
            const to = tier.qty_to || Infinity;
            if (qty >= from && qty <= to) {
                return tier;
            }
        }
        // Fall back to last tier if quantity exceeds all
        return tiers[tiers.length - 1] || null;
    }

    /**
     * Edit field at index
     * @param {number} index - Field index
     */
    editField(index) {
        this.currentFieldIndex = index;
        const field = this.currentGroup?.fields?.[index];
        if (field) {
            this.showFieldEditor(field);
        }
    }

    /**
     * Delete field at index
     * @param {number} index - Field index
     */
    deleteField(index) {
        if (!confirm(uloAdmin.i18n.confirmDeleteField)) {
            return;
        }

        this.currentGroup?.fields?.splice(index, 1);
        this.isDirty = true;
        this.renderFieldsList();
    }

    /**
     * Save group
     */
    async saveGroup() {
        const title = this.panel?.querySelector('#ulo-group-title')?.value?.trim();

        if (!title) {
            alert(uloAdmin.i18n.titleRequired);
            return;
        }

        // Use 'name' to match PHP Data_Handler expectations
        this.currentGroup.name = title;
        this.currentGroup.title = title; // Keep both for JS compatibility
        this.currentGroup.active = this.panel?.querySelector('#ulo-group-active')?.checked !== false;
        this.currentGroup.priority = parseInt(this.panel?.querySelector('#ulo-group-priority')?.value) || 10;

        // Set group_id for PHP (it expects 'group_id' not 'id')
        if (this.currentGroup.id && !this.currentGroup.group_id) {
            this.currentGroup.group_id = this.currentGroup.id;
        }

        // Collect rules
        const ruleType = this.panel?.querySelector('input[name="ulo-rule-type"]:checked')?.value || 'all';
        this.currentGroup.rules = { all_products: ruleType === 'all' };

        if (ruleType === 'products') {
            this.currentGroup.rules.product_ids = this.collectSelectedProducts();
        } else if (ruleType === 'variations') {
            this.currentGroup.rules.variation_ids = this.collectSelectedVariations();
        }

        try {
            const formData = new FormData();
            formData.append('action', 'ulo_save_field_group');
            formData.append('nonce', uloAdmin.nonce);
            formData.append('group', JSON.stringify(this.currentGroup));

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.isDirty = false;
                this.showNotice('success', uloAdmin.i18n.groupSaved);
                this.closePanel();
                this.loadGroups();
            } else {
                this.showNotice('error', data.data.message || uloAdmin.i18n.saveFailed);
            }
        } catch (error) {
            console.error('ULO: Error saving group', error);
            this.showNotice('error', uloAdmin.i18n.saveFailed);
        }
    }

    /**
     * Edit existing group
     * @param {string} groupId - Group ID
     */
    async editGroup(groupId) {
        try {
            const formData = new FormData();
            formData.append('action', 'ulo_get_field_group');
            formData.append('nonce', uloAdmin.nonce);
            formData.append('group_id', groupId);

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data.group) {
                const group = data.data.group;
                // Ensure the group has an id
                group.id = group.id || groupId;
                this.openPanel(group);
            }
        } catch (error) {
            console.error('ULO: Error loading group', error);
        }
    }

    /**
     * Delete group
     * @param {string} groupId - Group ID
     */
    async deleteGroup(groupId) {
        try {
            const formData = new FormData();
            formData.append('action', 'ulo_delete_field_group');
            formData.append('nonce', uloAdmin.nonce);
            formData.append('group_id', groupId);

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.showNotice('success', uloAdmin.i18n.groupDeleted);
                this.loadGroups();
            } else {
                this.showNotice('error', data.data.message || uloAdmin.i18n.deleteFailed);
            }
        } catch (error) {
            console.error('ULO: Error deleting group', error);
        }
    }

    /**
     * Duplicate group
     * @param {string} groupId - Group ID
     */
    async duplicateGroup(groupId) {
        try {
            const formData = new FormData();
            formData.append('action', 'ulo_duplicate_field_group');
            formData.append('nonce', uloAdmin.nonce);
            formData.append('group_id', groupId);

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.showNotice('success', uloAdmin.i18n.groupDuplicated);
                this.loadGroups();
            }
        } catch (error) {
            console.error('ULO: Error duplicating group', error);
        }
    }

    /**
     * Search products
     * @param {string} query - Search query
     */
    async searchProducts(query) {
        const results = this.panel?.querySelector('#ulo-product-search-results');
        if (query.length < 2) {
            if (results) {
                results.classList.remove('ulo-has-results');
                results.innerHTML = '';
            }
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'ulo_search_products');
            formData.append('nonce', uloAdmin.nonce);
            formData.append('search', query);

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.displayProductResults(data.data.products);
            }
        } catch (error) {
            console.error('ULO: Error searching products', error);
        }
    }

    /**
     * Display product search results
     * @param {Array} products - Products array
     */
    displayProductResults(products) {
        const container = this.panel?.querySelector('#ulo-product-search-results');
        if (!container) return;

        if (products.length === 0) {
            container.classList.remove('ulo-has-results');
            container.innerHTML = '';
            return;
        }

        container.classList.add('ulo-has-results');
        container.innerHTML = products.map(p => `
            <div class="ulo-search-result-item" data-id="${p.id}" data-title="${this.escapeHtml(p.title)}">
                ${this.escapeHtml(p.title)}
            </div>
        `).join('');

        // Click handlers
        container.querySelectorAll('.ulo-search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectProduct(item.dataset.id, item.dataset.title);
                container.classList.remove('ulo-has-results');
                container.innerHTML = '';
            });
        });
    }

    /**
     * Select a product
     * @param {string} id - Product ID
     * @param {string} title - Product title
     */
    selectProduct(id, title) {
        const container = this.panel?.querySelector('.ulo-selected-products');
        if (!container) return;

        // Check if already selected
        if (container.querySelector(`[data-id="${id}"]`)) {
            return;
        }

        const tag = document.createElement('span');
        tag.className = 'ulo-selected-product';
        tag.dataset.id = id;
        tag.innerHTML = `
            ${this.escapeHtml(title)}
            <button type="button" class="ulo-selected-product-remove">&times;</button>
        `;

        tag.querySelector('.ulo-selected-product-remove')?.addEventListener('click', () => tag.remove());

        container.appendChild(tag);

        // Clear search
        const searchInput = this.panel?.querySelector('#ulo-product-search-input');
        if (searchInput) searchInput.value = '';

        this.isDirty = true;
    }

    /**
     * Search variations
     * @param {string} search - Search term
     */
    async searchVariations(search) {
        const results = this.panel?.querySelector('#ulo-variation-search-results');
        if (search.length < 2) {
            if (results) {
                results.classList.remove('ulo-has-results');
                results.innerHTML = '';
            }
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'ulo_search_variations');
            formData.append('nonce', uloAdmin.nonce);
            formData.append('search', search);

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                this.displayVariationResults(data.data.variations || []);
            }
        } catch (error) {
            console.error('ULO: Error searching variations', error);
        }
    }

    /**
     * Display variation search results
     * @param {Array} variations - Variations array
     */
    displayVariationResults(variations) {
        const container = this.panel?.querySelector('#ulo-variation-search-results');
        if (!container) return;

        if (variations.length === 0) {
            container.classList.remove('ulo-has-results');
            container.innerHTML = '';
            return;
        }

        container.classList.add('ulo-has-results');
        container.innerHTML = variations.map(v => `
            <div class="ulo-search-result-item" data-id="${v.id}" data-title="${this.escapeHtml(v.title)}">
                ${this.escapeHtml(v.title)}
            </div>
        `).join('');

        // Click handlers
        container.querySelectorAll('.ulo-search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectVariation(item.dataset.id, item.dataset.title);
                container.classList.remove('ulo-has-results');
                container.innerHTML = '';
            });
        });
    }

    /**
     * Select a variation
     * @param {string} id - Variation ID
     * @param {string} title - Variation title
     */
    selectVariation(id, title) {
        const container = this.panel?.querySelector('.ulo-selected-variations');
        if (!container) return;

        // Check if already selected
        if (container.querySelector(`[data-id="${id}"]`)) {
            return;
        }

        const tag = document.createElement('span');
        tag.className = 'ulo-selected-product';
        tag.dataset.id = id;
        tag.innerHTML = `
            ${this.escapeHtml(title)}
            <button type="button" class="ulo-selected-product-remove">&times;</button>
        `;

        tag.querySelector('.ulo-selected-product-remove')?.addEventListener('click', () => tag.remove());

        container.appendChild(tag);

        // Clear search
        const searchInput = this.panel?.querySelector('#ulo-variation-search-input');
        if (searchInput) searchInput.value = '';

        this.isDirty = true;
    }

    /**
     * Collect selected product IDs
     * @returns {Array} Product IDs
     */
    collectSelectedProducts() {
        const ids = [];
        this.panel?.querySelectorAll('.ulo-selected-products .ulo-selected-product').forEach(el => {
            if (el.dataset.id) {
                ids.push(parseInt(el.dataset.id, 10));
            }
        });
        return ids;
    }

    /**
     * Collect selected variation IDs
     * @returns {Array} Variation IDs
     */
    collectSelectedVariations() {
        const ids = [];
        this.panel?.querySelectorAll('.ulo-selected-variations .ulo-selected-product').forEach(el => {
            if (el.dataset.id) {
                ids.push(parseInt(el.dataset.id, 10));
            }
        });
        return ids;
    }

    /**
     * Test formula
     */
    async testFormula() {
        const formula = this.panel?.querySelector('#ulo-field-formula')?.value;

        if (!formula) {
            alert(uloAdmin.i18n.enterFormula);
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'ulo_test_formula');
            formData.append('nonce', uloAdmin.nonce);
            formData.append('formula', formula);
            formData.append('variables[quantity]', '2');
            formData.append('variables[base_price]', '100');

            const response = await fetch(uloAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success) {
                alert(data.data.message);
            } else {
                alert(uloAdmin.i18n.invalidFormula + ': ' + data.data.message);
            }
        } catch (error) {
            console.error('ULO: Error testing formula', error);
        }
    }

    /**
     * Switch builder tab
     * @param {string} tabId - Tab ID
     */
    switchTab(tabId) {
        this.panel?.querySelectorAll('.ulo-builder-tab').forEach(tab => {
            tab.classList.toggle('ulo-active', tab.dataset.tab === tabId);
        });

        this.panel?.querySelectorAll('.ulo-builder-tab-content').forEach(content => {
            content.classList.toggle('ulo-active', content.dataset.tabContent === tabId);
        });
    }

    /**
     * Show notice
     * @param {string} type - Notice type (success, error, warning)
     * @param {string} message - Notice message
     */
    showNotice(type, message) {
        const existing = document.querySelector('.ulo-notice');
        if (existing) existing.remove();

        const notice = document.createElement('div');
        notice.className = `ulo-notice ulo-notice-${type} ulo-fade-in`;
        notice.innerHTML = `
            <span class="dashicons dashicons-${type === 'success' ? 'yes-alt' : type === 'error' ? 'warning' : 'info'}"></span>
            <span>${this.escapeHtml(message)}</span>
            <button type="button" class="ulo-notice-dismiss">&times;</button>
        `;

        notice.querySelector('.ulo-notice-dismiss')?.addEventListener('click', () => notice.remove());

        const wrap = document.querySelector('.ulo-admin-wrap') || document.querySelector('.wrap');
        wrap?.insertBefore(notice, wrap.firstChild);

        // Auto-remove after 5 seconds
        setTimeout(() => notice.remove(), 5000);
    }

    /**
     * Generate field name from label
     * @param {string} label - Field label
     * @returns {string} Generated name
     */
    generateFieldName(label) {
        return (label || '')
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, '')
            .replace(/\s+/g, '_')
            .substring(0, 50);
    }

    /**
     * Escape HTML
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    /**
     * Debounce function
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in ms
     * @returns {Function} Debounced function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Only init on ULO admin page
    if (document.querySelector('.ulo-admin-wrap') || document.getElementById('ulo_product_options')) {
        window.uloBuilder = new ULOBuilder();
        window.uloBuilder.init();
    }
});

// Export
window.ULOBuilder = ULOBuilder;
