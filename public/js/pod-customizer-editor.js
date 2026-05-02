/**
 * POD Aggregator — Product Customizer Editor (Fabric.js)
 *
 * @package POD_Aggregator\Public
 */

const PODCustomizerEditor = (function ($) {
    'use strict';

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    /** @type {fabric.Canvas} */
    let canvas = null;

    /** @type {string} Current tool: 'select' | 'text' | 'image' | 'shape' */
    let currentTool = 'select';

    /** @type {string} Current shape type when tool='shape' */
    let currentShapeType = 'rect';

    /** @type {string|null} Current design UUID (null = unsaved) */
    let designUUID = null;

    /** @type {number} WC Product ID */
    let productId = 0;

    /** @type {string} Print area */
    let printArea = 'front';

    /** @type {number} Canvas width in pixels */
    let canvasW = 0;

    /** @type {number} Canvas height in pixels */
    let canvasH = 0;

    /** @type {number} Canvas scale: pixels per mm */
    let canvasScale = 3;

    /** @type {Object} Undo/Redo stacks */
    let history = { past: [], future: [] };

    /** @type {boolean} Whether we are currently loading a design (suppress history) */
    let isLoading = false;

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /**
     * Initialize the editor on a .pod-customizer container.
     * Called by the shortcode render output.
     */
    function init() {
        const $container = $('.pod-customizer').first();
        if (!$container.length || typeof fabric === 'undefined') {
            return;
        }

        productId  = parseInt($container.data('product-id'), 10) || 0;
        printArea  = $container.data('area') || 'front';
        designUUID = $container.data('design-uuid') || null;
        canvasW    = parseInt($container.data('canvas-w'), 10) || 900;
        canvasH    = parseInt($container.data('canvas-h'), 10) || 1200;

        setupCanvas($container);
        setupToolbar();
        setupProperties();
        setupImageUpload();
        setupSaveButton();

        // Load existing design if UUID provided.
        if (designUUID) {
            loadDesign(designUUID);
        }

        // Initialize history.
        saveHistory();
    }

    // -------------------------------------------------------------------------
    // Canvas setup
    // -------------------------------------------------------------------------

    function setupCanvas($container) {
        canvas = new fabric.Canvas('pod-canvas', {
            width: canvasW,
            height: canvasH,
            backgroundColor: '#ffffff',
            preserveObjectStacking: true,
            selection: true,
        });

        // Update UI on selection events.
        canvas.on('selection:created',  onSelectionChange);
        canvas.on('selection:updated',  onSelectionChange);
        canvas.on('selection:cleared',  onSelectionCleared);
        canvas.on('object:modified',    onObjectModified);
        canvas.on('object:added',      onObjectAdded);

        // Double-click text to edit.
        canvas.on('mouse:dblclick', onCanvasDoubleClick);

        // Canvas click — add element based on current tool.
        canvas.on('mouse:down', function (opt) {
            const pointer = canvas.getPointer(opt.e);
            if (currentTool === 'text' && opt.e.button === 0) {
                addTextElement(pointer.x, pointer.y);
                setTool('select');
            } else if (currentTool === 'shape' && opt.e.button === 0) {
                addShapeElement(pointer.x, pointer.y);
                setTool('select');
            } else if (currentTool === 'image' && opt.e.button === 0) {
                $('#pod-image-upload').trigger('click');
                // Store click position for placing the image after upload.
                canvas.imageDropPos = pointer;
                setTool('select');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Toolbar & tools
    // -------------------------------------------------------------------------

    function setupToolbar() {
        // Tool selection.
        $(document).on('click', '.pod-tool-btn[data-tool]', function () {
            const tool = $(this).data('tool');
            setTool(tool);
        });

        // Action buttons.
        $(document).on('click', '.pod-tool-btn[data-action]', function () {
            const action = $(this).data('action');
            handleAction(action);
        });

        // Shape sub-tool click.
        $(document).on('click', '.pod-tool-btn[data-shape]', function () {
            currentShapeType = $(this).data('shape');
            setTool('shape');
        });
    }

    /**
     * Switch active tool.
     * @param {string} tool
     */
    function setTool(tool) {
        currentTool = tool;
        $('.pod-tool-btn[data-tool]').removeClass('pod-tool-btn--active');
        $(`.pod-tool-btn[data-tool="${tool}"]`).addClass('pod-tool-btn--active');

        if (tool === 'select') {
            canvas.selection = true;
            canvas.defaultCursor = 'default';
        } else {
            canvas.selection = false;
            canvas.defaultCursor = 'crosshair';
        }
    }

    /**
     * Handle toolbar action buttons.
     * @param {string} action
     */
    function handleAction(action) {
        switch (action) {
            case 'undo':
                undo();
                break;
            case 'redo':
                redo();
                break;
            case 'delete':
                deleteSelected();
                break;
            case 'clear':
                if (confirm(PODCustomizer.i18n.confirmClear)) {
                    canvas.clear();
                    canvas.backgroundColor = '#ffffff';
                    canvas.renderAll();
                    updateLayersPanel();
                    saveHistory();
                }
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Add elements
    // -------------------------------------------------------------------------

    /**
     * Add a new text element at the given canvas coordinates.
     * Enters inline editing immediately.
     */
    function addTextElement(x, y) {
        const text = new fabric.IText(PODCustomizer.i18n.enterText, {
            left: x,
            top: y,
            fontFamily: PODCustomizer.availableFonts[0] || 'Arial',
            fontSize: 32,
            fill: '#000000',
            originX: 'left',
            originY: 'top',
        });
        canvas.add(text);
        canvas.setActiveObject(text);
        text.enterEditing();
        text.selectAll();
        canvas.renderAll();
        updateLayersPanel();
        saveHistory();
    }

    /**
     * Add a shape element at the given position.
     */
    function addShapeElement(x, y) {
        let shape;
        const w = 120;
        const h = 80;

        const common = {
            left: x,
            top: y,
            originX: 'left',
            originY: 'top',
            fill: 'transparent',
            stroke: '#000000',
            strokeWidth: 2,
        };

        if (currentShapeType === 'rect') {
            shape = new fabric.Rect(Object.assign({}, common, { width: w, height: h }));
        } else if (currentShapeType === 'circle') {
            shape = new fabric.Ellipse(Object.assign({}, common, { rx: w / 2, ry: h / 2, width: w, height: h }));
        } else if (currentShapeType === 'line') {
            shape = new fabric.Line([x, y, x + w, y + 20], {
                stroke: '#000000',
                strokeWidth: 2,
                originX: 'left',
                originY: 'top',
            });
        } else {
            return;
        }

        // Tag shape with its POD type for serialization.
        shape.podType = 'shape';
        shape.podShapeType = currentShapeType;

        canvas.add(shape);
        canvas.setActiveObject(shape);
        canvas.renderAll();
        updateLayersPanel();
        saveHistory();
    }

    /**
     * Add an image element from a URL or File object.
     * @param {string|File} source  URL string or File object.
     * @param {Object}  [pos]     Optional {x, y} canvas position (defaults to center).
     */
    function addImageElement(source, pos) {
        const imgOpts = {
            originX: 'center',
            originY: 'center',
            left: pos ? pos.x : canvasW / 2,
            top:  pos ? pos.y : canvasH / 2,
        };

        if (typeof source === 'string') {
            // URL string — load from URL.
            fabric.Image.fromURL(source, function (img) {
                if (!img) return;
                // Scale to fit within a reasonable max size.
                const maxDim = 300;
                const scale = Math.min(maxDim / img.width, maxDim / img.height, 1);
                img.set(Object.assign({
                    scaleX: scale,
                    scaleY: scale,
                }, imgOpts));
                img.podType = 'image';
                canvas.add(img);
                canvas.setActiveObject(img);
                canvas.renderAll();
                updateLayersPanel();
                saveHistory();
            }, { crossOrigin: 'anonymous' });
        } else {
            // File object — read as data URL.
            const reader = new FileReader();
            reader.onload = function (e) {
                fabric.Image.fromURL(e.target.result, function (img) {
                    if (!img) return;
                    const maxDim = 300;
                    const scale = Math.min(maxDim / img.width, maxDim / img.height, 1);
                    img.set(Object.assign({
                        scaleX: scale,
                        scaleY: scale,
                    }, imgOpts));
                    img.podType = 'image';
                    canvas.add(img);
                    canvas.setActiveObject(img);
                    canvas.renderAll();
                    updateLayersPanel();
                    saveHistory();
                });
            };
            reader.readAsDataURL(source);
        }
    }

    // -------------------------------------------------------------------------
    // Selection & object events
    // -------------------------------------------------------------------------

    function onSelectionChange(e) {
        const obj = e.selected && e.selected[0];
        if (!obj) return;
        refreshPropertiesPanel(obj);
        updateActionButtons(true);
    }

    function onSelectionCleared() {
        $('#pod-props-content').hide();
        $('#pod-no-selection').show();
        updateActionButtons(false);
    }

    function onObjectModified(e) {
        if (!isLoading) {
            saveHistory();
        }
        updateLayersPanel();
    }

    function onObjectAdded(e) {
        // Assign a layer index if not set.
        if (e.target && e.target.z_index === undefined) {
            e.target.z_index = canvas.getObjects().length - 1;
        }
    }

    function onCanvasDoubleClick(e) {
        if (e.target && e.target.type === 'i-text') {
            e.target.enterEditing();
            e.target.selectAll();
        }
    }

    // -------------------------------------------------------------------------
    // Properties panel
    // -------------------------------------------------------------------------

    function setupProperties() {
        // Text content change.
        $('#pod-prop-text-content').on('input', function () {
            const obj = canvas.getActiveObject();
            if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
                obj.set('text', $(this).val());
                obj.setCoords();
                canvas.renderAll();
            }
        });

        // Font family.
        $('#pod-prop-font-family').on('change', function () {
            const obj = canvas.getActiveObject();
            if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
                obj.set('fontFamily', $(this).val());
                canvas.renderAll();
                saveHistory();
            }
        });

        // Font size.
        $('#pod-prop-font-size').on('input', function () {
            const obj = canvas.getActiveObject();
            if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
                obj.set('fontSize', parseInt($(this).val(), 10) || 24);
                canvas.renderAll();
                saveHistory();
            }
        });

        // Text color.
        $('#pod-prop-text-color').on('input', function () {
            const obj = canvas.getActiveObject();
            if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
                obj.set('fill', $(this).val());
                canvas.renderAll();
                saveHistory();
            }
        });

        // Text style toggles (bold/italic/underline).
        $(document).on('click', '.pod-prop-toggle[data-style]', function () {
            const style = $(this).data('style');
            const obj = canvas.getActiveObject();
            if (!obj || (obj.type !== 'i-text' && obj.type !== 'text')) return;

            const $btn = $(this);
            let newVal;
            if (style === 'bold') {
                newVal = !obj.fontWeight || obj.fontWeight === 'normal';
                obj.set('fontWeight', newVal ? 'bold' : 'normal');
                $btn.toggleClass('active', newVal);
            } else if (style === 'italic') {
                newVal = !obj.fontStyle || obj.fontStyle === 'normal';
                obj.set('fontStyle', newVal ? 'italic' : 'normal');
                $btn.toggleClass('active', newVal);
            } else if (style === 'underline') {
                newVal = !obj.underline;
                obj.set('underline', newVal);
                $btn.toggleClass('active', newVal);
            }
            canvas.renderAll();
            saveHistory();
        });

        // Text alignment.
        $(document).on('click', '.pod-prop-toggle[data-align]', function () {
            const align = $(this).data('align');
            const obj = canvas.getActiveObject();
            if (!obj || (obj.type !== 'i-text' && obj.type !== 'text')) return;
            obj.set('textAlign', align);
            canvas.renderAll();
            saveHistory();
        });

        // Fill color (shape).
        $('#pod-prop-fill-color').on('input', function () {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            const val = $(this).val();
            obj.set('fill', val === 'transparent' ? 'transparent' : val);
            canvas.renderAll();
            saveHistory();
        });

        // Stroke color.
        $('#pod-prop-stroke-color').on('input', function () {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            obj.set('stroke', $(this).val());
            canvas.renderAll();
            saveHistory();
        });

        // Stroke width.
        $('#pod-prop-stroke-width').on('input', function () {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            obj.set('strokeWidth', parseInt($(this).val(), 10) || 0);
            canvas.renderAll();
            saveHistory();
        });

        // Position (X, Y).
        ['#pod-prop-x', '#pod-prop-y'].forEach(function (sel) {
            $(sel).on('change', function () {
                const obj = canvas.getActiveObject();
                if (!obj) return;
                if (sel === '#pod-prop-x') {
                    obj.set('left', parseInt($(this).val(), 10) || 0);
                } else {
                    obj.set('top', parseInt($(this).val(), 10) || 0);
                }
                obj.setCoords();
                canvas.renderAll();
                saveHistory();
            });
        });

        // Size (width, height).
        ['#pod-prop-width', '#pod-prop-height'].forEach(function (sel) {
            $(sel).on('change', function () {
                const obj = canvas.getActiveObject();
                if (!obj) return;
                const dim = parseInt($(this).val(), 10) || 1;
                if (sel === '#pod-prop-width') {
                    obj.set('width', dim);
                    obj.set('scaleX', 1);
                } else {
                    obj.set('height', dim);
                    obj.set('scaleY', 1);
                }
                obj.setCoords();
                canvas.renderAll();
                saveHistory();
            });
        });

        // Rotation.
        $('#pod-prop-rotation').on('change', function () {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            obj.set('angle', parseInt($(this).val(), 10) || 0);
            obj.setCoords();
            canvas.renderAll();
            saveHistory();
        });

        // Layer order buttons.
        $(document).on('click', '.pod-prop-action[data-layer]', function () {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            const action = $(this).data('layer');
            switch (action) {
                case 'front':
                    canvas.bringToFront(obj);
                    break;
                case 'forward':
                    canvas.bringForward(obj);
                    break;
                case 'backward':
                    canvas.sendBackwards(obj);
                    break;
                case 'back':
                    canvas.sendToBack(obj);
                    break;
            }
            canvas.renderAll();
            updateLayersPanel();
            saveHistory();
        });

        // Lock/unlock.
        $('#pod-btn-lock').on('click', function () {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            const locked = !obj.podLocked;
            obj.podLocked = locked;
            obj.set({
                selectable: !locked,
                evented: !locked,
                lockMovementX: locked,
                lockMovementY: locked,
                lockScalingX: locked,
                lockScalingY: locked,
                lockRotation: locked,
            });
            canvas.renderAll();
            updateLayersPanel();
        });

        // Duplicate.
        $('#pod-btn-duplicate').on('click', function () {
            const obj = canvas.getActiveObject();
            if (!obj) return;
            obj.clone(function (clone) {
                clone.set({ left: (clone.left || 0) + 20, top: (clone.top || 0) + 20 });
                clone.podLocked = false;
                canvas.add(clone);
                canvas.setActiveObject(clone);
                canvas.renderAll();
                updateLayersPanel();
                saveHistory();
            });
        });

        // Replace image button.
        $('#pod-btn-replace-image').on('click', function () {
            $('#pod-image-upload').trigger('click');
        });
    }

    /**
     * Populate the properties panel from the selected object.
     */
    function refreshPropertiesPanel(obj) {
        $('#pod-no-selection').hide();
        $('#pod-props-content').show();

        const type = obj.type;

        // Reset all.
        $('#pod-prop-text, #pod-prop-font, #pod-prop-size, #pod-prop-text-style, #pod-prop-align, #pod-prop-shape, #pod-prop-stroke, #pod-prop-image').hide();

        // Position & size (all objects).
        $('#pod-prop-x').val(Math.round(obj.left || 0));
        $('#pod-prop-y').val(Math.round(obj.top || 0));
        $('#pod-prop-width').val(Math.round((obj.getScaledWidth ? obj.getScaledWidth() : obj.width) || 0));
        $('#pod-prop-height').val(Math.round((obj.getScaledHeight ? obj.getScaledHeight() : obj.height) || 0));
        $('#pod-prop-rotation').val(Math.round(obj.angle || 0));

        // Lock state.
        const locked = !!obj.podLocked;
        $('#pod-btn-lock .dashicons').attr('class', locked ? 'dashicons dashicons-unlock' : 'dashicons dashicons-lock');
        $('#pod-lock-label').text(locked ? PODCustomizer.i18n.unlock : PODCustomizer.i18n.lock);

        if (type === 'i-text' || type === 'text') {
            // Text element.
            $('#pod-prop-text, #pod-prop-font, #pod-prop-size, #pod-prop-text-style, #pod-prop-align').show();
            $('#pod-prop-text-content').val(obj.text || '');
            $('#pod-prop-font-family').val(obj.fontFamily || 'Arial').trigger('change.select2');
            $('#pod-prop-font-size').val(Math.round(obj.fontSize || 24));
            $('#pod-prop-text-color').val(obj.fill || '#000000');

            // Style toggles.
            $('[data-style="bold"]').toggleClass('active', obj.fontWeight === 'bold');
            $('[data-style="italic"]').toggleClass('active', obj.fontStyle === 'italic');
            $('[data-style="underline"]').toggleClass('active', !!obj.underline);
            $('[data-align]').removeClass('active');
            $(`[data-align="${obj.textAlign || 'center'}"]`).addClass('active');
        } else if (type === 'image') {
            // Image element.
            $('#pod-prop-image').show();
        } else if (type === 'rect' || type === 'ellipse' || type === 'line' || type === 'circle') {
            // Shape element.
            $('#pod-prop-shape, #pod-prop-stroke').show();
            $('#pod-prop-fill-color').val(obj.fill || 'transparent');
            $('#pod-prop-stroke-color').val(obj.stroke || '#000000');
            $('#pod-prop-stroke-width').val(Math.round(obj.strokeWidth || 0));
        }
    }

    // -------------------------------------------------------------------------
    // Layers panel
    // -------------------------------------------------------------------------

    /**
     * Rebuild the layers list from current canvas objects.
     */
    function updateLayersPanel() {
        const $list = $('#pod-layers-list');
        const objects = canvas.getObjects();

        if (!objects.length) {
            $list.html(`<li class="pod-layers-empty">${PODCustomizer.i18n.noElements}</li>`);
            return;
        }

        // Render in reverse order (top layer first in list).
        const html = objects.slice().reverse().map(function (obj, revIdx) {
            const idx = objects.length - 1 - revIdx;
            const typeLabel = getTypeLabel(obj);
            const name = getObjectName(obj);
            const locked = obj.podLocked ? '🔒 ' : '';
            const active = (canvas.getActiveObject() === obj) ? 'active' : '';
            const visible = obj.visible === false ? 'visibility: hidden;' : '';

            return `
                <li class="pod-layer-item ${active}" data-idx="${idx}" style="${visible}">
                    <span class="pod-layer-icon">${getTypeIcon(obj.type)}</span>
                    <span class="pod-layer-name">${locked}${name}</span>
                    <button type="button" class="pod-layer-visibility" data-idx="${idx}" title="${obj.visible !== false ? 'Hide' : 'Show'}">
                        <span class="dashicons dashicons-${obj.visible !== false ? 'eye' : 'hidden'}"></span>
                    </button>
                </li>
            `;
        }).join('');

        $list.html(html);

        // Click layer item to select.
        $list.find('.pod-layer-item:not(.pod-layer-visibility)').on('click', function (e) {
            if ($(e.target).closest('.pod-layer-visibility').length) return;
            const idx = parseInt($(this).data('idx'), 10);
            const obj = canvas.getObjects()[idx];
            if (obj) {
                canvas.setActiveObject(obj);
                canvas.renderAll();
                updateLayersPanel();
            }
        });

        // Toggle visibility.
        $list.find('.pod-layer-visibility').on('click', function (e) {
            e.stopPropagation();
            const idx = parseInt($(this).data('idx'), 10);
            const obj = canvas.getObjects()[idx];
            if (obj) {
                obj.set('visible', !obj.visible);
                canvas.renderAll();
                updateLayersPanel();
            }
        });
    }

    function getTypeLabel(obj) {
        if (obj.podType === 'image') return 'Image';
        if (obj.podType === 'shape') return obj.podShapeType || 'Shape';
        if (obj.type === 'i-text' || obj.type === 'text') return 'Text';
        if (obj.type === 'rect') return 'Rectangle';
        if (obj.type === 'ellipse' || obj.type === 'circle') return 'Circle';
        if (obj.type === 'line') return 'Line';
        return 'Element';
    }

    function getObjectName(obj) {
        if (obj.type === 'i-text' || obj.type === 'text') {
            const t = (obj.text || '').substring(0, 20);
            return t || PODCustomizer.i18n.addText;
        }
        return getTypeLabel(obj);
    }

    function getTypeIcon(type) {
        const icons = {
            'i-text': '<span class="dashicons dashicons-text"></span>',
            'text': '<span class="dashicons dashicons-text"></span>',
            'image': '<span class="dashicons dashicons-format-image"></span>',
            'rect': '<span class="dashicons dashicons-admin-generic"></span>',
            'ellipse': '<span class="dashicons dashicons-dashboard"></span>',
            'circle': '<span class="dashicons dashicons-dashboard"></span>',
            'line': '<span class="dashicons dashicons-minus"></span>',
        };
        return icons[type] || '<span class="dashicons dashicons-admin-generic"></span>';
    }

    // -------------------------------------------------------------------------
    // Image upload
    // -------------------------------------------------------------------------

    function setupImageUpload() {
        $('#pod-image-upload').on('change', function (e) {
            const file = (e.target.files || [])[0];
            if (!file) return;
            const pos = canvas.imageDropPos || { x: canvasW / 2, y: canvasH / 2 };
            addImageElement(file, pos);
            $(this).val(''); // Reset so same file can be re-uploaded.
        });
    }

    // -------------------------------------------------------------------------
    // Undo / Redo
    // -------------------------------------------------------------------------

    function saveHistory() {
        if (isLoading) return;
        const json = JSON.stringify(canvas.toJSON(['podType', 'podShapeType', 'podLocked', 'z_index']));
        history.past.push(json);
        history.future = [];
        // Limit history size.
        if (history.past.length > 50) history.past.shift();
        updateUndoRedoButtons();
    }

    function undo() {
        if (!history.past.length) return;
        const current = JSON.stringify(canvas.toJSON(['podType', 'podShapeType', 'podLocked', 'z_index']));
        history.future.push(current);
        const prev = history.past.pop();
        loadCanvasJSON(prev);
        updateUndoRedoButtons();
    }

    function redo() {
        if (!history.future.length) return;
        const current = JSON.stringify(canvas.toJSON(['podType', 'podShapeType', 'podLocked', 'z_index']));
        history.past.push(current);
        const next = history.future.pop();
        loadCanvasJSON(next);
        updateUndoRedoButtons();
    }

    function loadCanvasJSON(json) {
        isLoading = true;
        canvas.loadFromJSON(json, function () {
            canvas.renderAll();
            updateLayersPanel();
            isLoading = false;
        });
    }

    function updateUndoRedoButtons() {
        const $undoBtn = $('.pod-tool-btn[data-action="undo"]');
        const $redoBtn = $('.pod-tool-btn[data-action="redo"]');
        $undoBtn.prop('disabled', history.past.length <= 1);
        $redoBtn.prop('disabled', history.future.length === 0);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    function deleteSelected() {
        const active = canvas.getActiveObjects();
        if (!active.length) return;
        active.forEach(function (obj) { canvas.remove(obj); });
        canvas.discardActiveObject();
        canvas.renderAll();
        updateLayersPanel();
        saveHistory();
    }

    function updateActionButtons(enabled) {
        $('.pod-tool-btn[data-action="delete"]').prop('disabled', !enabled);
    }

    // -------------------------------------------------------------------------
    // Save / Load via REST API
    // -------------------------------------------------------------------------

    function setupSaveButton() {
        $('.pod-tool-btn[data-action="save"]').on('click', function () {
            saveDesign();
        });
    }

    /**
     * Serialize the canvas to our element format and save via REST API.
     */
    function saveDesign() {
        const $btn = $('.pod-tool-btn[data-action="save"]');
        const originalLabel = $btn.find('.pod-tool-btn__label').text();
        $btn.find('.pod-tool-btn__label').text(PODCustomizer.i18n.saving);
        $btn.prop('disabled', true);

        const elements = serializeCanvasElements();
        const payload = {
            name:               `Design for Product ${productId}`,
            area:               printArea,
            product_id:         productId,
            provider:           'printful',
            provider_product_id: '',
            dpi:                300,
            elements:           elements,
        };

        const isUpdate = !!designUUID;
        const method = isUpdate ? 'PUT' : 'POST';
        const url = isUpdate
            ? `${PODCustomizer.restBase}/${designUUID}`
            : `${PODCustomizer.restBase}`;

        $.ajax({
            url:  url,
            method: method,
            data:   JSON.stringify(payload),
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': PODCustomizer.restNonce,
            },
        }).done(function (response) {
            if (!isUpdate && response.uuid) {
                designUUID = response.uuid;
                // Update URL bar without reload (optional).
                if (window.history && window.history.replaceState) {
                    const newUrl = `${window.location.pathname}?design_uuid=${designUUID}`;
                    window.history.replaceState({}, '', newUrl);
                }
            }
            $btn.find('.pod-tool-btn__label').text(PODCustomizer.i18n.saved);
            setTimeout(function () {
                $btn.find('.pod-tool-btn__label').text(originalLabel);
                $btn.prop('disabled', false);
            }, 2000);
        }).fail(function (jqXHR) {
            alert('Save failed: ' + (jqXHR.responseJSON && jqXHR.responseJSON.message || 'Unknown error'));
            $btn.find('.pod-tool-btn__label').text(originalLabel);
            $btn.prop('disabled', false);
        });
    }

    /**
     * Load a design by UUID from the REST API.
     */
    function loadDesign(uuid) {
        $.ajax({
            url: `${PODCustomizer.restBase}/${uuid}`,
            method: 'GET',
            headers: { 'X-WP-Nonce': PODCustomizer.restNonce },
        }).done(function (response) {
            if (!response.design) return;
            const d = response.design;
            designUUID = d.id || uuid;
            productId  = d.product_id || productId;
            printArea  = d.area || printArea;
            loadCanvasFromDesign(d);
        }).fail(function () {
            console.error('Failed to load design:', uuid);
        });
    }

    /**
     * Populate the canvas from a Design JSON object.
     */
    function loadCanvasFromDesign(design) {
        isLoading = true;

        canvas.clear();
        canvas.backgroundColor = '#ffffff';

        const elements = design.elements || [];
        const fabricObjects = [];

        const loadPromises = elements.map(function (el, i) {
            return new Promise(function (resolve) {
                if (el.type === 'text') {
                    const text = new fabric.IText(el.text || '', {
                        left: el.x,
                        top:  el.y,
                        fontFamily: el.font || 'Arial',
                        fontSize:   el.fontSize || 24,
                        fill:       el.color || '#000000',
                        angle:      el.rotation || 0,
                        originX:    'left',
                        originY:    'top',
                        textAlign:  el.align || 'center',
                        fontWeight:  el.bold ? 'bold' : 'normal',
                        fontStyle:   el.italic ? 'italic' : 'normal',
                        underline:  !!el.underline,
                    });
                    text.podType = 'text';
                    text.z_index = el.z_index !== undefined ? el.z_index : i;
                    if (el.locked) {
                        text.podLocked = true;
                        text.set({
                            selectable: false,
                            evented: false,
                            lockMovementX: true,
                            lockMovementY: true,
                        });
                    }
                    fabricObjects.push(text);
                    resolve();

                } else if (el.type === 'image') {
                    fabric.Image.fromURL(el.src || '', function (img) {
                        if (!img) { resolve(); return; }
                        img.set({
                            left:     el.x,
                            top:      el.y,
                            scaleX:   (el.width || img.width) / img.width,
                            scaleY:   (el.height || img.height) / img.height,
                            angle:    el.rotation || 0,
                            originX:  'left',
                            originY:  'top',
                        });
                        img.podType = 'image';
                        img.z_index = el.z_index !== undefined ? el.z_index : i;
                        if (el.locked) {
                            img.podLocked = true;
                            img.set({
                                selectable: false,
                                evented: false,
                            });
                        }
                        fabricObjects.push(img);
                        resolve();
                    }, { crossOrigin: 'anonymous' });

                } else if (el.type === 'shape') {
                    let shape;
                    const common = {
                        left:         el.x,
                        top:          el.y,
                        fill:         el.fill === 'transparent' ? 'transparent' : (el.fill || 'transparent'),
                        stroke:       el.stroke || '#000000',
                        strokeWidth:  el.strokeWidth || 0,
                        angle:        el.rotation || 0,
                        originX:      'left',
                        originY:      'top',
                    };
                    if (el.shape === 'circle') {
                        shape = new fabric.Ellipse(Object.assign({}, common, {
                            rx: el.width / 2, ry: el.height / 2,
                        }));
                    } else if (el.shape === 'line') {
                        shape = new fabric.Line([el.x, el.y, el.x + (el.width || 100), el.y], common);
                    } else {
                        shape = new fabric.Rect(Object.assign({}, common, {
                            width: el.width || 100, height: el.height || 80,
                        }));
                    }
                    shape.podType = 'shape';
                    shape.podShapeType = el.shape || 'rect';
                    shape.z_index = el.z_index !== undefined ? el.z_index : i;
                    if (el.locked) {
                        shape.podLocked = true;
                        shape.set({ selectable: false, evented: false });
                    }
                    fabricObjects.push(shape);
                    resolve();
                } else {
                    resolve();
                }
            });
        });

        Promise.all(loadPromises).then(function () {
            // Sort by z_index and add to canvas.
            fabricObjects.sort((a, b) => (a.z_index || 0) - (b.z_index || 0));
            fabricObjects.forEach(function (obj) { canvas.add(obj); });
            canvas.renderAll();
            updateLayersPanel();
            isLoading = false;
            saveHistory();
        });
    }

    /**
     * Serialize canvas objects to our element format for REST API.
     */
    function serializeCanvasElements() {
        return canvas.getObjects().map(function (obj, i) {
            const base = {
                type:     getPodType(obj),
                x:        Math.round(obj.left || 0),
                y:        Math.round(obj.top || 0),
                width:    Math.round(obj.getScaledWidth ? obj.getScaledWidth() : (obj.width || 0)),
                height:   Math.round(obj.getScaledHeight ? obj.getScaledHeight() : (obj.height || 0)),
                rotation: Math.round(obj.angle || 0),
                z_index:  i,
                locked:   !!obj.podLocked,
            };

            if (obj.type === 'i-text' || obj.type === 'text') {
                return Object.assign({}, base, {
                    type:      'text',
                    text:      obj.text || '',
                    font:      obj.fontFamily || 'Arial',
                    fontSize:  Math.round(obj.fontSize || 24),
                    color:     obj.fill || '#000000',
                    align:     obj.textAlign || 'center',
                    bold:      obj.fontWeight === 'bold',
                    italic:    obj.fontStyle === 'italic',
                    underline: !!obj.underline,
                });
            }

            if (obj.type === 'image') {
                return Object.assign({}, base, {
                    type: 'image',
                    src:  obj.getSrc ? obj.getSrc() : '',
                });
            }

            if (obj.podType === 'shape' || ['rect', 'ellipse', 'circle', 'line'].includes(obj.type)) {
                return Object.assign({}, base, {
                    type:        'shape',
                    shape:       obj.podShapeType || 'rect',
                    fill:        obj.fill === 'transparent' ? 'transparent' : (obj.fill || 'transparent'),
                    stroke:      obj.stroke || '#000000',
                    strokeWidth: Math.round(obj.strokeWidth || 0),
                });
            }

            return base;
        });
    }

    /**
     * Get our POD element type from a Fabric.js object type.
     */
    function getPodType(obj) {
        if (obj.type === 'i-text' || obj.type === 'text') return 'text';
        if (obj.type === 'image') return 'image';
        if (obj.podType === 'shape') return 'shape';
        return 'shape';
    }

    // -------------------------------------------------------------------------
    // Public API (for shortcode to call)
    // -------------------------------------------------------------------------

    return {
        init:              init,
        addImageElement:   addImageElement,
        loadDesign:        loadDesign,
        saveDesign:        saveDesign,
        getCanvasJSON:     function () { return canvas.toJSON(['podType', 'podShapeType', 'podLocked', 'z_index']); },
        getDesignUUID:     function () { return designUUID; },
    };

})(jQuery);

// Auto-init when DOM is ready.
jQuery(function () {
    if (typeof fabric !== 'undefined') {
        PODCustomizerEditor.init();
    } else {
        // Fabric.js loads async; wait for it.
        var attempts = 0;
        var interval = setInterval(function () {
            attempts++;
            if (typeof fabric !== 'undefined') {
                clearInterval(interval);
                PODCustomizerEditor.init();
            } else if (attempts > 20) {
                clearInterval(interval);
                console.error('Fabric.js failed to load after 10 seconds.');
            }
        }, 500);
    }
});
