/**
 * POD Aggregator — Product Customizer JavaScript
 *
 * Frontend canvas designer powered by Fabric.js.
 * Handles: text, images, shapes, layers, undo/redo, undo stack, serialization.
 *
 * @package POD_Aggregator\Public
 */

(function ($, window, document) {
  'use strict';

  // ---------------------------------------------------------------------------
  // Fabric.js must be loaded before this script.
  // Load via: wp_enqueue_script('fabric', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js', [], '5.3.1', true);
  // ---------------------------------------------------------------------------

  const POD = window.POD || {};

  // ---------------------------------------------------------------------------
  // Undo/Redo Stack
  // ---------------------------------------------------------------------------

  /**
   * Simple undo/redo manager.
   * Keeps a history of JSON snapshots; max depth 30.
   */
  class HistoryManager {
    constructor(maxHistory = 30) {
      this.history = [];
      this.index = -1;
      this.maxHistory = maxHistory;
      this._isApplying = false;
    }

    /**
     * Push a new canvas JSON state.
     * @param {object} state Fabric canvas JSON object.
     */
    push(state) {
      if (this._isApplying) return;

      // Discard any redo states.
      this.history = this.history.slice(0, this.index + 1);

      // Deep-clone the state.
      this.history.push(JSON.parse(JSON.stringify(state)));
      this.index = this.history.length - 1;

      if (this.history.length > this.maxHistory) {
        this.history.shift();
        this.index--;
      }

      this._updateButtons();
    }

    undo() {
      if (!this.canUndo() || this._isApplying) return null;
      this.index--;
      this._isApplying = true;
      const state = JSON.parse(JSON.stringify(this.history[this.index]));
      this._isApplying = false;
      this._updateButtons();
      return state;
    }

    redo() {
      if (!this.canRedo() || this._isApplying) return null;
      this.index++;
      this._isApplying = true;
      const state = JSON.parse(JSON.stringify(this.history[this.index]));
      this._isApplying = false;
      this._updateButtons();
      return state;
    }

    canUndo() {
      return this.index > 0;
    }

    canRedo() {
      return this.index < this.history.length - 1;
    }

    clear() {
      this.history = [];
      this.index = -1;
      this._updateButtons();
    }

    _updateButtons() {
      const $btns = $('.pod-customizer-history button');
      $btns.filter('.undo').prop('disabled', !this.canUndo());
      $btns.filter('.redo').prop('disabled', !this.canRedo());
    }
  }

  // ---------------------------------------------------------------------------
  // Template Presets
  // ---------------------------------------------------------------------------

  const TEMPLATE_PRESETS = {
    blank: {
      label: 'Blank Canvas',
      elements: [],
    },
    monogram: {
      label: 'Monogram Center',
      elements: [
        {
          type: 'text',
          text: 'ABC',
          x: 150,
          y: 175,
          width: 200,
          height: 50,
          font: 'Arial',
          fontSize: 48,
          color: '#000000',
          align: 'center',
          bold: true,
          italic: false,
          underline: false,
          z_index: 1,
          locked: false,
        },
      ],
    },
    text_vintage: {
      label: 'Vintage Text',
      elements: [
        {
          type: 'text',
          text: 'EST. 2024',
          x: 150,
          y: 160,
          width: 200,
          height: 30,
          font: 'Georgia',
          fontSize: 18,
          color: '#555555',
          align: 'center',
          bold: false,
          italic: true,
          underline: false,
          z_index: 1,
          locked: false,
        },
        {
          type: 'text',
          text: 'HANDCRAFTED',
          x: 150,
          y: 200,
          width: 200,
          height: 40,
          font: 'Georgia',
          fontSize: 28,
          color: '#222222',
          align: 'center',
          bold: true,
          italic: false,
          underline: false,
          z_index: 2,
          locked: false,
        },
        {
          type: 'shape',
          shape: 'line',
          x: 100,
          y: 240,
          width: 200,
          height: 0,
          fill: 'transparent',
          stroke: '#888888',
          strokeWidth: 1,
          z_index: 3,
          locked: false,
        },
      ],
    },
    graphic_center: {
      label: 'Graphic + Text',
      elements: [
        {
          type: 'shape',
          shape: 'rect',
          x: 130,
          y: 120,
          width: 140,
          height: 140,
          fill: 'transparent',
          stroke: '#000000',
          strokeWidth: 3,
          z_index: 1,
          locked: true,
        },
        {
          type: 'text',
          text: 'LOGO',
          x: 200,
          y: 175,
          width: 140,
          height: 30,
          font: 'Arial',
          fontSize: 24,
          color: '#000000',
          align: 'center',
          bold: true,
          italic: false,
          underline: false,
          z_index: 2,
          locked: false,
        },
      ],
    },
  };

  // ---------------------------------------------------------------------------
  // Font List
  // ---------------------------------------------------------------------------

  const GOOGLE_FONTS = [
    'Arial',
    'Georgia',
    'Times New Roman',
    'Courier New',
    'Verdana',
    'Trebuchet MS',
    'Impact',
    'Comic Sans MS',
    'Lucida Console',
    'Palatino Linotype',
    'Bookman Old Style',
    'Garamond',
    'Tahoma',
    'Century Gothic',
    'Franklin Gothic Medium',
  ];

  // ---------------------------------------------------------------------------
  // Main POD Customizer Class
  // ---------------------------------------------------------------------------

  /**
   * PODCustomizer — Canvas-based product designer.
   *
   * @param {string|Element} container Selector or DOM element.
   * @param {object}         options   Configuration.
   *
   * Options:
   *   canvasWidth    {number} Canvas width in pixels. Default: 400.
   *   canvasHeight   {number} Canvas height in pixels. Default: 500.
   *   productId      {number} WC product ID.
   *   provider       {string} Provider slug.
   *   area           {string} Print area (front|back|left_sleeve|right_sleeve).
   *   apiBase       {string} REST API base URL.
   *   nonce         {string} WP nonce for AJAX/REST auth.
   *   designUuid    {string} Existing design UUID to load.
   *   onDesignSave  {function} Callback after design is saved.
   *   onAddToCart   {function} Callback after Add to Cart.
   */
  function PODCustomizer(container, options) {
    this.$container = $(container);
    this.options = $.extend(
      {
        canvasWidth: 400,
        canvasHeight: 500,
        productId: 0,
        provider: 'printful',
        area: 'front',
        apiBase: '',
        nonce: '',
        designUuid: '',
        onDesignSave: null,
        onAddToCart: null,
      },
      options || {}
    );

    this.canvas = null;
    this.history = new HistoryManager();
    this.isLoading = false;
    this.activeObject = null;
    this._dragState = null;
    this.designUuid = this.options.designUuid || '';
    this.currentFile = null; // pending print file result

    this._init();
  }

  PODCustomizer.prototype = {
    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------

    _init: function () {
      this._renderUI();
      this._initCanvas();
      this._bindEvents();
      this._bindKeyboard();

      // Load existing design if UUID provided.
      if (this.designUuid) {
        this._loadDesign(this.designUuid);
      } else {
        // Push initial blank state to history.
        this._pushHistory();
      }

      this._updateToolsPanel();
    },

    /**
     * Render the customizer DOM structure into the container.
     */
    _renderUI: function () {
      const html = `
      <div class="pod-customizer" data-product-id="${this.options.productId}" data-area="${this.options.area}">
        <div class="pod-customizer-header">
          <h3 class="pod-customizer-title">${podCustomizerL10n.title || 'Design Your Product'}</h3>
          <div class="pod-customizer-history">
            <button type="button" class="undo" title="${podCustomizerL10n.undo || 'Undo'}" disabled>↩</button>
            <button type="button" class="redo" title="${podCustomizerL10n.redo || 'Redo'}" disabled>↪</button>
          </div>
        </div>

        <div class="pod-customizer-body">
          <!-- Left: Tools -->
          <div class="pod-customizer-tools">
            <div class="pod-tool-section">
              <label class="pod-tool-label">${podCustomizerL10n.tools || 'Tools'}</label>
              <div class="pod-tool-buttons">
                <button type="button" class="pod-tool-btn active" data-tool="select" title="${podCustomizerL10n.select || 'Select/Move'}">
                  <span class="dashicons dashicons-arthron"></span>
                </button>
                <button type="button" class="pod-tool-btn" data-tool="text" title="${podCustomizerL10n.addText || 'Add Text'}">
                  <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="pod-tool-btn" data-tool="image" title="${podCustomizerL10n.addImage || 'Add Image'}">
                  <span class="dashicons dashicons-format-image"></span>
                </button>
                <button type="button" class="pod-tool-btn" data-tool="shape" title="${podCustomizerL10n.addShape || 'Add Shape'}">
                  <span class="dashicons dashicons-shapes"></span>
                </button>
              </div>
            </div>

            <div class="pod-tool-divider"></div>

            <!-- Shape picker (shown when shape tool active) -->
            <div class="pod-tool-section pod-shape-picker" style="display:none">
              <label class="pod-tool-label">${podCustomizerL10n.shape || 'Shape'}</label>
              <div class="pod-tool-buttons">
                <button type="button" class="pod-tool-btn active" data-shape="rect" title="Rectangle">▢</button>
                <button type="button" class="pod-tool-btn" data-shape="circle" title="Circle">○</button>
                <button type="button" class="pod-tool-btn" data-shape="line" title="Line">╱</button>
              </div>
            </div>

            <!-- Layers -->
            <div class="pod-tool-section">
              <label class="pod-tool-label">${podCustomizerL10n.layers || 'Layers'}</label>
              <div class="pod-layers-list"></div>
            </div>

            <div class="pod-tool-divider"></div>

            <!-- Presets -->
            <div class="pod-tool-section">
              <label class="pod-tool-label">${podCustomizerL10n.templates || 'Templates'}</label>
              <select class="pod-template-select" style="width:100%">
                <option value="">— ${podCustomizerL10n.chooseTemplate || 'Choose a template'} —</option>
                <option value="blank">${TEMPLATE_PRESETS.blank.label}</option>
                <option value="monogram">${TEMPLATE_PRESETS.monogram.label}</option>
                <option value="text_vintage">${TEMPLATE_PRESETS.text_vintage.label}</option>
                <option value="graphic_center">${TEMPLATE_PRESETS.graphic_center.label}</option>
              </select>
            </div>
          </div>

          <!-- Center: Canvas area -->
          <div class="pod-customizer-canvas-wrap">
            <div class="pod-canvas-frame">
              <div class="pod-canvas-area-label">${this.options.area.toUpperCase()}</div>
              <canvas id="pod-canvas-main"></canvas>
            </div>
            <div class="pod-canvas-dims">
              ${this.options.canvasWidth} × ${this.options.canvasHeight}px
            </div>
          </div>

          <!-- Right: Properties panel -->
          <div class="pod-customizer-props">
            <div class="pod-prop-group pod-prop-position">
              <label class="pod-prop-label">${podCustomizerL10n.position || 'Position'}</label>
              <div class="pod-prop-row">
                <label>X</label>
                <input type="number" class="pod-prop-x" min="0" step="1">
                <label>Y</label>
                <input type="number" class="pod-prop-y" min="0" step="1">
              </div>
              <div class="pod-prop-row">
                <label>W</label>
                <input type="number" class="pod-prop-w" min="1" step="1">
                <label>H</label>
                <input type="number" class="pod-prop-h" min="1" step="1">
              </div>
              <div class="pod-prop-row">
                <label>°</label>
                <input type="number" class="pod-prop-rotation" min="-180" max="180" step="1" style="flex:1">
                <label><input type="checkbox" class="pod-prop-lock"> 🔒</label>
              </div>
            </div>

            <div class="pod-prop-group pod-prop-text" style="display:none">
              <label class="pod-prop-label">${podCustomizerL10n.textProps || 'Text'}</label>
              <textarea class="pod-prop-text-content" rows="2" style="width:100%;margin-bottom:4px"></textarea>
              <select class="pod-prop-font" style="width:100%;margin-bottom:4px">
                ${GOOGLE_FONTS.map((f) => `<option value="${f}">${f}</option>`).join('')}
              </select>
              <div class="pod-prop-row">
                <input type="number" class="pod-prop-font-size" min="6" max="200" step="1" value="24" style="width:60px">
                <input type="color" class="pod-prop-text-color" value="#000000" style="width:40px;height:26px;border:none;padding:0">
                <label><input type="checkbox" class="pod-prop-bold"> <b>B</b></label>
                <label><input type="checkbox" class="pod-prop-italic"> <i>I</i></label>
                <label><input type="checkbox" class="pod-prop-underline"> <u>U</u></label>
              </div>
              <div class="pod-prop-row" style="margin-top:4px">
                <label style="font-size:11px">Align:</label>
                <button type="button" class="pod-tool-btn pod-prop-align" data-align="left" title="Left">◢</button>
                <button type="button" class="pod-tool-btn pod-prop-align" data-align="center" title="Center">◣</button>
                <button type="button" class="pod-tool-btn pod-prop-align active" data-align="right" title="Right">◥</button>
              </div>
            </div>

            <div class="pod-prop-group pod-prop-image" style="display:none">
              <label class="pod-prop-label">${podCustomizerL10n.imageProps || 'Image'}</label>
              <div class="pod-image-preview" style="width:100%;height:80px;background:#f0f0f0;border:1px dashed #ccc;text-align:center;line-height:80px;color:#999;font-size:11px;margin-bottom:4px">No image</div>
              <button type="button" class="button pod-upload-image-btn" style="width:100%">${podCustomizerL10n.uploadImage || 'Upload Image'}</button>
              <input type="file" class="pod-image-file-input" accept="image/*" style="display:none">
            </div>

            <div class="pod-prop-group pod-prop-shape" style="display:none">
              <label class="pod-prop-label">${podCustomizerL10n.shapeProps || 'Shape'}</label>
              <div class="pod-prop-row">
                <label>Fill</label>
                <input type="color" class="pod-prop-fill-color" value="#cccccc" style="width:40px;height:26px;border:none;padding:0">
                <label style="margin-left:8px">Stroke</label>
                <input type="color" class="pod-prop-stroke-color" value="#000000" style="width:40px;height:26px;border:none;padding:0">
              </div>
              <div class="pod-prop-row" style="margin-top:4px">
                <label>Width</label>
                <input type="number" class="pod-prop-stroke-width" min="0" max="20" step="1" value="0" style="width:60px">
              </div>
            </div>

            <div class="pod-prop-actions">
              <button type="button" class="button button-primary pod-save-design-btn" style="width:100%">
                ${podCustomizerL10n.saveDesign || 'Save Design'}
              </button>
              <button type="button" class="button pod-add-to-cart-btn" style="width:100%;margin-top:6px;display:none">
                ${podCustomizerL10n.addToCart || 'Add to Cart'}
              </button>
            </div>

            <div class="pod-customizer-msg" style="display:none;margin-top:8px;font-size:12px"></div>
          </div>
        </div>
      </div>
      `;

      this.$container.html(html);
      this.$customizer = this.$container.find('.pod-customizer');
    },

    // -----------------------------------------------------------------------
    // Canvas setup
    // -----------------------------------------------------------------------

    _initCanvas: function () {
      const canvasEl = this.$customizer.find('#pod-canvas-main')[0];
      this.canvas = new fabric.Canvas(canvasEl, {
        width: this.options.canvasWidth,
        height: this.options.canvasHeight,
        backgroundColor: '#ffffff',
        preserveObjectStacking: true,
        selection: true,
      });

      // Sync selection → properties panel.
      this.canvas.on('selection:created', (e) => this._onSelectionChange(e));
      this.canvas.on('selection:updated', (e) => this._onSelectionChange(e));
      this.canvas.on('selection:cleared', () => this._onSelectionCleared());

      // Track object changes.
      this.canvas.on('object:modified', () => this._pushHistory());
      this.canvas.on('object:added', () => this._pushHistory());
      this.canvas.on('object:removed', () => this._pushHistory());

      // Double-click text → enter edit mode.
      this.canvas.on('mouse:dblclick', (e) => {
        if (e.target && e.target.type === 'i-text') {
          e.target.enterEditing();
        }
      });

      // Deselect on canvas click (not object).
      this.canvas.on('mouse:down', (e) => {
        if (!e.target) {
          this.canvas.discardActiveObject();
          this.canvas.renderAll();
        }
      });
    },

    // -----------------------------------------------------------------------
    // Event bindings
    // -----------------------------------------------------------------------

    _bindEvents: function () {
      const self = this;

      // Tool buttons.
      this.$customizer.on('click', '.pod-tool-btn[data-tool]', function () {
        self.$customizer.find('.pod-tool-btn[data-tool]').removeClass('active');
        $(this).addClass('active');
        self._setTool($(this).data('tool'));
      });

      // Shape picker buttons.
      this.$customizer.on('click', '.pod-tool-btn[data-shape]', function () {
        self.$customizer.find('.pod-tool-btn[data-shape]').removeClass('active');
        $(this).addClass('active');
        self._setShape($(this).data('shape'));
      });

      // Undo / Redo.
      this.$customizer.on('click', '.pod-customizer-history button.undo', () => {
        const state = this.history.undo();
        if (state) this._applyHistoryState(state);
      });

      this.$customizer.on('click', '.pod-customizer-history button.redo', () => {
        const state = this.history.redo();
        if (state) this._applyHistoryState(state);
      });

      // Template select.
      this.$customizer.on('change', '.pod-template-select', function () {
        const key = $(this).val();
        if (key && TEMPLATE_PRESETS[key]) {
          self._loadTemplate(TEMPLATE_PRESETS[key]);
        }
        $(this).val(''); // Reset select.
      });

      // Properties panel — position inputs.
      this.$customizer.on('input change', '.pod-prop-x', function () {
        self._updateSelected('left', parseInt($(this).val(), 10) || 0);
      });
      this.$customizer.on('input change', '.pod-prop-y', function () {
        self._updateSelected('top', parseInt($(this).val(), 10) || 0);
      });
      this.$customizer.on('input change', '.pod-prop-w', function () {
        self._updateSelected('width', parseInt($(this).val(), 10) || 1);
      });
      this.$customizer.on('input change', '.pod-prop-h', function () {
        self._updateSelected('height', parseInt($(this).val(), 10) || 1);
      });
      this.$customizer.on('input change', '.pod-prop-rotation', function () {
        self._updateSelected('angle', parseInt($(this).val(), 10) || 0);
      });
      this.$customizer.on('change', '.pod-prop-lock', function () {
        const obj = self.canvas.getActiveObject();
        if (obj) {
          obj.set('selectable', !this.checked);
          obj.set('evented', !this.checked);
          if (this.checked) self.canvas.discardActiveObject();
          self.canvas.renderAll();
        }
      });

      // Text properties.
      this.$customizer.on('input change', '.pod-prop-text-content', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('text', $(this).val());
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('change', '.pod-prop-font', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('fontFamily', $(this).val());
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('input change', '.pod-prop-font-size', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('fontSize', parseInt($(this).val(), 10) || 24);
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('input change', '.pod-prop-text-color', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('fill', $(this).val());
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('change', '.pod-prop-bold', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('fontWeight', this.checked ? 'bold' : 'normal');
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('change', '.pod-prop-italic', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('fontStyle', this.checked ? 'italic' : 'normal');
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('change', '.pod-prop-underline', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('underline', this.checked);
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('click', '.pod-prop-align', function () {
        const obj = self.canvas.getActiveObject();
        const align = $(this).data('align');
        if (obj && (obj.type === 'i-text' || obj.type === 'text')) {
          obj.set('textAlign', align);
          self.canvas.renderAll();
          self._pushHistory();
        }
        self.$customizer.find('.pod-prop-align').removeClass('active');
        $(this).addClass('active');
      });

      // Image properties.
      this.$customizer.on('click', '.pod-upload-image-btn', function () {
        self.$customizer.find('.pod-image-file-input').trigger('click');
      });
      this.$customizer.on('change', '.pod-image-file-input', function (e) {
        const file = e.target.files[0];
        if (file) self._handleImageUpload(file);
      });

      // Shape properties.
      this.$customizer.on('input change', '.pod-prop-fill-color', function () {
        const obj = self.canvas.getActiveObject();
        if (obj && obj.type !== 'line') {
          obj.set('fill', $(this).val());
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('input change', '.pod-prop-stroke-color', function () {
        const obj = self.canvas.getActiveObject();
        if (obj) {
          obj.set('stroke', $(this).val());
          self.canvas.renderAll();
          self._pushHistory();
        }
      });
      this.$customizer.on('input change', '.pod-prop-stroke-width', function () {
        const obj = self.canvas.getActiveObject();
        if (obj) {
          obj.set('strokeWidth', parseInt($(this).val(), 10) || 0);
          self.canvas.renderAll();
          self._pushHistory();
        }
      });

      // Delete selected object.
      this.$customizer.on('click', '.pod-delete-selected-btn', function () {
        self._deleteSelected();
      });

      // Save design.
      this.$customizer.on('click', '.pod-save-design-btn', function () {
        self._saveDesign();
      });

      // Add to cart.
      this.$customizer.on('click', '.pod-add-to-cart-btn', function () {
        self._addToCart();
      });

      // Canvas click for adding objects (when a tool is active).
      this.canvas.on('mouse:down', function (opt) {
        if (!self.currentTool || self.currentTool === 'select') return;
        if (opt.target) return;
        const pointer = self.canvas.getPointer(opt.e);
        self._addObjectAtPointer(self.currentTool, pointer);
      });
    },

    /**
     * Bind keyboard shortcuts.
     */
    _bindKeyboard: function () {
      const self = this;
      $(document).on('keydown', function (e) {
        // Don't intercept when typing in inputs/textareas.
        const tag = e.target.tagName.toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

        // Delete selected.
        if (e.key === 'Delete' || e.key === 'Backspace') {
          self._deleteSelected();
          e.preventDefault();
        }

        // Undo: Ctrl/Cmd+Z.
        if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
          const state = self.history.undo();
          if (state) self._applyHistoryState(state);
          e.preventDefault();
        }

        // Redo: Ctrl/Cmd+Shift+Z or Ctrl/Cmd+Y.
        if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
          const state = self.history.redo();
          if (state) self._applyHistoryState(state);
          e.preventDefault();
        }

        // Duplicate: Ctrl/Cmd+D.
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
          self._duplicateSelected();
          e.preventDefault();
        }
      });
    },

    // -----------------------------------------------------------------------
    // Tool management
    // -----------------------------------------------------------------------

    _setTool: function (tool) {
      this.currentTool = tool;
      this.canvas.defaultCursor = tool === 'select' ? 'default' : 'crosshair';
      this.canvas.selection = tool === 'select';

      // Show/hide property panels.
      this.$customizer.find('.pod-prop-text').toggle(tool === 'text');
      this.$customizer.find('.pod-prop-image').toggle(tool === 'image');
      this.$customizer.find('.pod-prop-shape').toggle(tool === 'shape');
      this.$customizer.find('.pod-shape-picker').toggle(tool === 'shape');

      // Shape picker starts with rect.
      if (tool === 'shape') {
        this.currentShape = 'rect';
        this.$customizer.find('.pod-tool-btn[data-shape="rect"]').addClass('active').siblings().removeClass('active');
      }

      this._updateToolsPanel();
    },

    _setShape: function (shape) {
      this.currentShape = shape;
    },

    /**
     * Update tools panel (delete button, layers).
     */
    _updateToolsPanel: function () {
      const self = this;

      // Update layers list.
      const $list = this.$customizer.find('.pod-layers-list');
      $list.empty();

      const objects = this.canvas.getObjects().slice().reverse(); // Top layer first.
      if (objects.length === 0) {
        $list.append('<div style="font-size:11px;color:#999;padding:4px">No layers</div>');
        return;
      }

      objects.forEach(function (obj, i) {
        const label = self._getObjectLabel(obj);
        const isActive = obj === self.canvas.getActiveObject();
        const $item = $(`
          <div class="pod-layer-item ${isActive ? 'active' : ''}" data-index="${self.canvas.getObjects().indexOf(obj)}" style="display:flex;align-items:center;gap:4px;padding:3px 4px;cursor:pointer;font-size:12px;border-bottom:1px solid #eee">
            <span class="pod-layer-visibility" style="cursor:pointer;opacity:${obj.visible === false ? 0.3 : 1}">${obj.visible === false ? '◎' : '◉'}</span>
            <span class="pod-layer-label" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${label}</span>
          </div>
        `);

        $item.on('click', '.pod-layer-visibility', function (e) {
          e.stopPropagation();
          obj.visible = !obj.visible;
          self.canvas.renderAll();
          self._updateToolsPanel();
        });

        $item.on('click', function () {
          self.canvas.setActiveObject(obj);
          self.canvas.renderAll();
          self._updateToolsPanel();
        });

        $list.append($item);
      });
    },

    _getObjectLabel: function (obj) {
      if (obj.type === 'i-text' || obj.type === 'text') {
        const t = obj.text || 'Text';
        return t.substring(0, 20) + (t.length > 20 ? '…' : '');
      }
      if (obj.type === 'image') return '🖼 Image';
      if (obj.type === 'rect') return '▢ Rectangle';
      if (obj.type === 'circle') return '○ Circle';
      if (obj.type === 'ellipse') return '○ Ellipse';
      if (obj.type === 'line') return '╱ Line';
      return obj.type || 'Object';
    },

    // -----------------------------------------------------------------------
    // Add objects
    // -----------------------------------------------------------------------

    /**
     * Add a new object at the given canvas pointer coordinates.
     *
     * @param {string} tool    'text' | 'image' | 'shape'
     * @param {object} pointer {x, y} in canvas pixels.
     */
    _addObjectAtPointer: function (tool, pointer) {
      const cx = this.options.canvasWidth;
      const cy = this.options.canvasHeight;

      if (tool === 'text') {
        const text = new fabric.IText(podCustomizerL10n.doubleClickEdit || 'Double-click to edit', {
          left: Math.min(pointer.x, cx - 200),
          top: Math.min(pointer.y, cy - 40),
          fontFamily: 'Arial',
          fontSize: 32,
          fill: '#000000',
          textAlign: 'center',
        });
        this.canvas.add(text);
        this.canvas.setActiveObject(text);
        this.canvas.renderAll();
        this._setTool('select');
      } else if (tool === 'shape') {
        this._addShape(pointer.x, pointer.y);
      }
    },

    /**
     * Add a shape to the center of the canvas.
     */
    _addShape: function (x, y) {
      const cx = x !== undefined ? x : this.options.canvasWidth / 2 - 50;
      const cy = y !== undefined ? y : this.options.canvasHeight / 2 - 50;

      let shape;
      switch (this.currentShape) {
        case 'circle':
          shape = new fabric.Circle({
            left: cx,
            top: cy,
            radius: 50,
            fill: '#cccccc',
            stroke: '#000000',
            strokeWidth: 0,
          });
          break;

        case 'line':
          shape = new fabric.Line([cx, cy, cx + 150, cy], {
            stroke: '#000000',
            strokeWidth: 2,
            fill: 'transparent',
          });
          break;

        case 'rect':
        default:
          shape = new fabric.Rect({
            left: cx,
            top: cy,
            width: 150,
            height: 100,
            fill: '#cccccc',
            stroke: '#000000',
            strokeWidth: 0,
          });
          break;
      }

      this.canvas.add(shape);
      this.canvas.setActiveObject(shape);
      this.canvas.renderAll();
      this._setTool('select');
    },

    /**
     * Handle image upload from local file input.
     */
    _handleImageUpload: function (file) {
      if (!file.type.match(/^image\//)) {
        this._showMsg(podCustomizerL10n.invalidImageType || 'Please select an image file.', 'error');
        return;
      }

      if (file.size > 5 * 1024 * 1024) {
        this._showMsg(podCustomizerL10n.imageTooLarge || 'Image must be under 5 MB.', 'error');
        return;
      }

      const reader = new FileReader();
      const self = this;

      reader.onload = function (e) {
        fabric.Image.fromURL(e.target.result, function (img) {
          // Scale image to fit within 200×200.
          const maxDim = 200;
          const scale = Math.min(maxDim / img.width, maxDim / img.height, 1);
          img.set({
            left: (self.options.canvasWidth - img.width * scale) / 2,
            top: (self.options.canvasHeight - img.height * scale) / 2,
            scaleX: scale,
            scaleY: scale,
          });

          self.canvas.add(img);
          self.canvas.setActiveObject(img);
          self.canvas.renderAll();
          self._setTool('select');
          self._updateImagePropPanel(e.target.result);
        });
      };

      reader.readAsDataURL(file);
    },

    // -----------------------------------------------------------------------
    // Selection → properties panel sync
    // -----------------------------------------------------------------------

    _onSelectionChange: function (e) {
      const obj = e.selected ? e.selected[0] : null;
      if (!obj) return;
      this.activeObject = obj;

      // Position / size.
      this.$customizer.find('.pod-prop-x').val(Math.round(obj.left || 0));
      this.$customizer.find('.pod-prop-y').val(Math.round(obj.top || 0));
      this.$customizer.find('.pod-prop-w').val(Math.round((obj.width || 1) * (obj.scaleX || 1)));
      this.$customizer.find('.pod-prop-h').val(Math.round((obj.height || 1) * (obj.scaleY || 1)));
      this.$customizer.find('.pod-prop-rotation').val(Math.round(obj.angle || 0));
      this.$customizer.find('.pod-prop-lock').prop('checked', !obj.selectable);

      // Show appropriate property group.
      const type = obj.type;
      this.$customizer.find('.pod-prop-text').toggle(type === 'i-text' || type === 'text');
      this.$customizer.find('.pod-prop-image').toggle(type === 'image');
      this.$customizer.find('.pod-prop-shape').toggle(['rect', 'circle', 'ellipse', 'line'].includes(type));

      // Text props.
      if (type === 'i-text' || type === 'text') {
        this.$customizer.find('.pod-prop-text-content').val(obj.text || '');
        this.$customizer.find('.pod-prop-font').val(obj.fontFamily || 'Arial');
        this.$customizer.find('.pod-prop-font-size').val(Math.round(obj.fontSize || 24));
        this.$customizer.find('.pod-prop-text-color').val(obj.fill || '#000000');
        this.$customizer.find('.pod-prop-bold').prop('checked', obj.fontWeight === 'bold');
        this.$customizer.find('.pod-prop-italic').prop('checked', obj.fontStyle === 'italic');
        this.$customizer.find('.pod-prop-underline').prop('checked', obj.underline || false);
        const align = obj.textAlign || 'center';
        this.$customizer.find('.pod-prop-align').removeClass('active');
        this.$customizer.find('.pod-prop-align[data-align="' + align + '"]').addClass('active');
      }

      // Image props.
      if (type === 'image') {
        this._updateImagePropPanel(null, obj);
      }

      // Shape props.
      if (['rect', 'circle', 'ellipse', 'line'].includes(type)) {
        this.$customizer.find('.pod-prop-fill-color').val(obj.fill && obj.fill !== 'transparent' ? obj.fill : '#cccccc');
        this.$customizer.find('.pod-prop-stroke-color').val(obj.stroke || '#000000');
        this.$customizer.find('.pod-prop-stroke-width').val(obj.strokeWidth || 0);
      }

      this._updateToolsPanel();
    },

    _updateImagePropPanel: function (src, obj) {
      const $preview = this.$customizer.find('.pod-image-preview');
      if (src) {
        $preview.css('background-image', `url(${src})`);
        $preview.css('background-size', 'contain');
        $preview.css('background-repeat', 'no-repeat');
        $preview.css('background-position', 'center');
        $preview.text('');
      } else if (obj) {
        $preview.text('🖼 ' + (obj._element ? obj._element.src || 'Image' : 'Image'));
      }
    },

    _onSelectionCleared: function () {
      this.activeObject = null;
      this.$customizer.find('.pod-prop-text, .pod-prop-image, .pod-prop-shape').hide();
      this._updateToolsPanel();
    },

    /**
     * Update a property on the active object.
     */
    _updateSelected: function (prop, value) {
      const obj = this.canvas.getActiveObject();
      if (!obj) return;

      switch (prop) {
        case 'left':
        case 'top':
          obj.set(prop, value);
          break;
        case 'width':
          obj.set('scaleX', value / (obj.width || 1));
          break;
        case 'height':
          obj.set('scaleY', value / (obj.height || 1));
          break;
        case 'angle':
          obj.set('angle', value);
          break;
      }

      obj.setCoords();
      this.canvas.renderAll();
      this._pushHistory();
    },

    // -----------------------------------------------------------------------
    // History (undo/redo)
    // -----------------------------------------------------------------------

    _pushHistory: function () {
      if (this.isLoading) return;
      const json = this.canvas.toJSON(['selectable', 'evented']);
      this.history.push(json);
    },

    _applyHistoryState: function (state) {
      this.isLoading = true;
      const self = this;
      this.canvas.loadFromJSON(state, function () {
        self.canvas.renderAll();
        self.isLoading = false;
        self._updateToolsPanel();
      });
    },

    // -----------------------------------------------------------------------
    // Template loading
    // -----------------------------------------------------------------------

    _loadTemplate: function (template) {
      this.isLoading = true;
      this.canvas.clear();
      this.canvas.backgroundColor = '#ffffff';

      if (!template.elements || template.elements.length === 0) {
        this.canvas.renderAll();
        this.isLoading = false;
        this._pushHistory();
        this._updateToolsPanel();
        return;
      }

      let loaded = 0;
      const self = this;

      template.elements.forEach(function (elData) {
        self._addElementFromData(elData, function (obj) {
          loaded++;
          if (loaded === template.elements.length) {
            self.canvas.renderAll();
            self.isLoading = false;
            self._pushHistory();
            self._updateToolsPanel();
          }
        });
      });
    },

    /**
     * Add a Fabric.js object from element data.
     *
     * @param {object}   data
     * @param {function} cb   Callback(obj) when added.
     */
    _addElementFromData: function (data, cb) {
      const self = this;
      const type = data.type;

      if (type === 'text') {
        const obj = new fabric.IText(data.text || 'Text', {
          left: data.x || 0,
          top: data.y || 0,
          width: data.width || 200,
          fontFamily: data.font || 'Arial',
          fontSize: data.fontSize || 24,
          fill: data.color || '#000000',
          textAlign: data.align || 'center',
          fontWeight: data.bold ? 'bold' : 'normal',
          fontStyle: data.italic ? 'italic' : 'normal',
          underline: data.underline || false,
        });
        this.canvas.add(obj);
        cb(obj);

      } else if (type === 'image') {
        if (data.src) {
          fabric.Image.fromURL(data.src, function (img) {
            img.set({
              left: data.x || 0,
              top: data.y || 0,
              scaleX: (data.scale || 1) * (data.width || 200) / img.width,
              scaleY: (data.scale || 1) * (data.height || 200) / img.height,
            });
            self.canvas.add(img);
            cb(img);
          });
        } else {
          cb(null);
        }

      } else if (type === 'shape') {
        let obj;
        switch (data.shape) {
          case 'circle':
            obj = new fabric.Circle({
              left: data.x || 0,
              top: data.y || 0,
              radius: Math.min(data.width || 50, data.height || 50) / 2,
              fill: data.fill || 'transparent',
              stroke: data.stroke || '#000000',
              strokeWidth: data.strokeWidth || 0,
            });
            break;
          case 'line':
            obj = new fabric.Line([data.x || 0, data.y || 0, (data.x || 0) + (data.width || 100), (data.y || 0)], {
              stroke: data.stroke || '#000000',
              strokeWidth: data.strokeWidth || 1,
              fill: 'transparent',
            });
            break;
          default: // rect
            obj = new fabric.Rect({
              left: data.x || 0,
              top: data.y || 0,
              width: data.width || 150,
              height: data.height || 100,
              fill: data.fill || 'transparent',
              stroke: data.stroke || '#000000',
              strokeWidth: data.strokeWidth || 0,
            });
        }
        if (obj) {
          if (data.locked) {
            obj.set({ selectable: false, evented: false });
          }
          this.canvas.add(obj);
          cb(obj);
        } else {
          cb(null);
        }
      } else {
        cb(null);
      }
    },

    // -----------------------------------------------------------------------
    // Delete / Duplicate
    // -----------------------------------------------------------------------

    _deleteSelected: function () {
      const active = this.canvas.getActiveObjects();
      if (!active.length) return;
      active.forEach((obj) => this.canvas.remove(obj));
      this.canvas.discardActiveObject();
      this.canvas.renderAll();
      this._pushHistory();
      this._updateToolsPanel();
    },

    _duplicateSelected: function () {
      const active = this.canvas.getActiveObject();
      if (!active) return;
      active.clone(function (cloned) {
        cloned.set({ left: (cloned.left || 0) + 20, top: (cloned.top || 0) + 20 });
        this.canvas.add(cloned);
        this.canvas.setActiveObject(cloned);
        this.canvas.renderAll();
        this._pushHistory();
        this._updateToolsPanel();
      }.bind(this));
    },

    // -----------------------------------------------------------------------
    // Design save / load via REST API
    // -----------------------------------------------------------------------

    /**
     * Serialize the canvas and save to the REST API.
     *
     * @param {function} done   Callback(error, result).
     */
    _saveDesign: function (done) {
      const self = this;
      this._showMsg(podCustomizerL10n.saving || 'Saving…', 'info');

      const elements = this._canvasToElements();
      const payload = {
        name: `Design — ${this.options.area} — ${Date.now()}`,
        area: this.options.area,
        product_id: this.options.productId,
        provider: this.options.provider,
        dpi: 300,
        elements: elements,
      };

      const isUpdate = !!this.designUuid;
      const url = isUpdate
        ? `${this.options.apiBase}/designs/${this.designUuid}`
        : `${this.options.apiBase}/designs`;
      const method = isUpdate ? 'PUT' : 'POST';

      fetch(url, {
        method: method,
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': this.options.nonce,
        },
        body: JSON.stringify(payload),
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.code) {
            throw new Error(data.message || 'Save failed');
          }
          if (!isUpdate && data.uuid) {
            self.designUuid = data.uuid;
          }
          self._showMsg(podCustomizerL10n.designSaved || 'Design saved!', 'success');
          self.$customizer.find('.pod-add-to-cart-btn').show();
          if (typeof done === 'function') done(null, data);
          if (self.options.onDesignSave) self.options.onDesignSave(null, data);
        })
        .catch(function (err) {
          self._showMsg(err.message || podCustomizerL10n.saveFailed || 'Save failed', 'error');
          if (typeof done === 'function') done(err);
          if (self.options.onDesignSave) self.options.onDesignSave(err);
        });
    },

    /**
     * Load a design from the REST API by UUID.
     */
    _loadDesign: function (uuid) {
      const self = this;
      this._showMsg(podCustomizerL10n.loading || 'Loading…', 'info');

      fetch(`${this.options.apiBase}/designs/${uuid}`, {
        method: 'GET',
        headers: { 'X-WP-Nonce': this.options.nonce },
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.code) {
            throw new Error(data.message || 'Load failed');
          }
          const design = data.design || data;
          self._loadDesignData(design);
          self._showMsg('', 'none');
        })
        .catch(function (err) {
          self._showMsg(err.message || podCustomizerL10n.loadFailed || 'Load failed', 'error');
        });
    },

    /**
     * Load design data into the canvas.
     */
    _loadDesignData: function (design) {
      this.isLoading = true;
      this.designUuid = design.uuid || design.id || '';
      this.canvas.clear();
      this.canvas.backgroundColor = '#ffffff';

      const elements = design.elements || [];
      let loaded = 0;
      const self = this;

      if (elements.length === 0) {
        this.canvas.renderAll();
        this.isLoading = false;
        this._pushHistory();
        return;
      }

      elements.forEach(function (elData) {
        self._addElementFromData(elData, function () {
          loaded++;
          if (loaded === elements.length) {
            self.canvas.renderAll();
            self.isLoading = false;
            self._pushHistory();
            self._updateToolsPanel();
          }
        });
      });
    },

    // -----------------------------------------------------------------------
    // Cart integration
    // -----------------------------------------------------------------------

    /**
     * Save design first, then trigger WooCommerce Add to Cart with design data.
     */
    _addToCart: function () {
      const self = this;

      // If design has not been saved yet, save it first.
      if (!this.designUuid) {
        this._saveDesign(function (err) {
          if (!err) self._doAddToCart();
        });
      } else {
        this._doAddToCart();
      }
    },

    /**
     * Actually trigger the add_to_cart AJAX call with design metadata.
     */
    _doAddToCart: function () {
      const elements = this._canvasToElements();

      $.ajax({
        url: podCustomizerL10n.ajaxUrl,
        type: 'POST',
        data: {
          action: 'pod_add_to_cart',
          nonce: this.options.nonce,
          product_id: this.options.productId,
          design_uuid: this.designUuid,
          design_data: JSON.stringify(elements),
          area: this.options.area,
          provider: this.options.provider,
        },
        success: function (res) {
          if (res.success) {
            // Trigger WooCommerce mini-cart refresh.
            $(document.body).trigger('added_to_cart', [res.data || [], true]);
            this._showMsg(podCustomizerL10n.addedToCart || 'Added to cart!', 'success');
          } else {
            this._showMsg(res.data || podCustomizerL10n.addToCartFailed || 'Add to cart failed', 'error');
          }
        }.bind(this),
        error: function () {
          this._showMsg(podCustomizerL10n.addToCartFailed || 'Add to cart failed', 'error');
        }.bind(this),
      });
    },

    // -----------------------------------------------------------------------
    // Canvas → Elements serialization
    // -----------------------------------------------------------------------

    /**
     * Serialize the Fabric canvas to a list of element data objects.
     * Returns an array matching the DesignElement schema.
     */
    _canvasToElements: function () {
      const self = this;
      return this.canvas.getObjects().map(function (obj, index) {
        const base = {
          x: Math.round(obj.left || 0),
          y: Math.round(obj.top || 0),
          rotation: Math.round(obj.angle || 0),
          z_index: index,
          locked: !obj.selectable,
        };

        if (obj.type === 'i-text' || obj.type === 'text') {
          return Object.assign(base, {
            type: 'text',
            text: obj.text || '',
            font: obj.fontFamily || 'Arial',
            fontSize: Math.round(obj.fontSize || 24),
            color: obj.fill || '#000000',
            align: obj.textAlign || 'center',
            bold: obj.fontWeight === 'bold',
            italic: obj.fontStyle === 'italic',
            underline: obj.underline || false,
            width: Math.round((obj.width || 200) * (obj.scaleX || 1)),
            height: Math.round((obj.height || 50) * (obj.scaleY || 1)),
          });
        }

        if (obj.type === 'image') {
          return Object.assign(base, {
            type: 'image',
            src: (obj._element && obj._element.src) ? obj._element.src : '',
            width: Math.round((obj.width || 200) * (obj.scaleX || 1)),
            height: Math.round((obj.height || 200) * (obj.scaleY || 1)),
            scale: obj.scaleX || 1,
          });
        }

        if (obj.type === 'rect') {
          return Object.assign(base, {
            type: 'shape',
            shape: 'rect',
            width: Math.round((obj.width || 150) * (obj.scaleX || 1)),
            height: Math.round((obj.height || 100) * (obj.scaleY || 1)),
            fill: obj.fill && obj.fill !== 'transparent' ? obj.fill : 'transparent',
            stroke: obj.stroke || '#000000',
            strokeWidth: obj.strokeWidth || 0,
          });
        }

        if (obj.type === 'circle') {
          return Object.assign(base, {
            type: 'shape',
            shape: 'circle',
            width: Math.round((obj.width || 100) * (obj.scaleX || 1)),
            height: Math.round((obj.height || 100) * (obj.scaleY || 1)),
            fill: obj.fill && obj.fill !== 'transparent' ? obj.fill : 'transparent',
            stroke: obj.stroke || '#000000',
            strokeWidth: obj.strokeWidth || 0,
          });
        }

        if (obj.type === 'line') {
          return Object.assign(base, {
            type: 'shape',
            shape: 'line',
            width: Math.round(obj.x2 - obj.x1),
            height: 0,
            fill: 'transparent',
            stroke: obj.stroke || '#000000',
            strokeWidth: obj.strokeWidth || 1,
          });
        }

        return Object.assign(base, { type: 'shape', shape: 'rect', width: 100, height: 100 });
      });
    },

    // -----------------------------------------------------------------------
    // Messaging
    // -----------------------------------------------------------------------

    _showMsg: function (msg, type) {
      const $msg = this.$customizer.find('.pod-customizer-msg');
      $msg.text(msg).removeClass('notice-success notice-error notice-info').addClass(
        type === 'error' ? 'notice-error' : type === 'success' ? 'notice-success' : 'notice-info'
      ).toggle(!!msg);
    },
  };

  // ---------------------------------------------------------------------------
  // jQuery Plugin
  // ---------------------------------------------------------------------------

  $.fn.podCustomizer = function (options) {
    return this.each(function () {
      const $el = $(this);
      if (!$el.data('podCustomizer')) {
        const instance = new PODCustomizer(this, options);
        $el.data('podCustomizer', instance);
      }
    });
  };

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  POD.Customizer = PODCustomizer;

  window.POD = POD;

})(jQuery, window, document);
