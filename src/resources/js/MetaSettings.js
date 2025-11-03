/* MetaSettings.js — legacy-compatible, no build step required
   ------------------------------------------------------------ */

// --- Safe namespace bootstrap (must be first) -------------------------------
window.Craft = window.Craft || {};
Craft.MetaSettingsField = Craft.MetaSettingsField || {};

/* ============================================================================
 * FieldController — one controller per metasettings field
 * ========================================================================== */
class FieldController {
  constructor(fieldEl, namespace) {
    this.fieldEl = fieldEl?.closest?.('.metasettings-field') || null;
    if (!this.fieldEl) return;

    this.namespace = namespace;
    this.fieldEl.dataset.namespace = namespace;

    this.elements = {
      gear: this.fieldEl.querySelector('.btn-gear'),
      hiddenInput: this.fieldEl.querySelector('input[type="hidden"][name$="[json]"]'),
      tooltips: this.fieldEl.querySelector('.tooltips'),
      select: this.fieldEl.querySelector('.selectized'),
    };

    this.button = new Craft.MetaSettingsField.Button(this);
    this.bindEvents();
    this.refresh();
  }

  // --- Public ----------------------------------------------------------------
  get handle() {
    return this.fieldEl.dataset.field;
  }

  get value() {
    return this.elements.select?.value ?? this.fieldEl.value ?? null;
  }

  get json() {
    try {
      return JSON.parse(this.elements.hiddenInput?.value ?? '{}');
    } catch {
      return {};
    }
  }

  refresh() {
    const hasTemplate = !!this.template(this.tagfor('virtuals'));
    this.toggleDisabled(this.elements.gear, !hasTemplate);

    const tipTpl = this.template(this.tagfor('inline')) ?? null;
    const tooltipTarget = this.elements.tooltips;

    if (tooltipTarget) {
      tooltipTarget.innerHTML = tipTpl
        ? tipTpl.content.querySelector('span')?.outerHTML ?? ''
        : '';
    }
  }

  optchange() {
    this.refresh();
  }

  save(formEl) {
    if (!formEl) return;
    const fields = formEl.querySelectorAll('input, select, textarea');
    const data = this.serialize(fields);
    this.update(data);
    this.saveIcons(formEl);
  }

  // --- Events ----------------------------------------------------------------
  bindEvents() {
    const { gear } = this.elements;
    if (gear) {
      gear.addEventListener('click', () => this.button.inputmodal());
      gear.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') this.button.inputmodal();
      });
    }
  }

  toggleDisabled(el, state) {
    el?.classList.toggle('disabled', !!state);
  }

  // --- Templates -------------------------------------------------------------
  template(name) {
    return document.querySelector(
      `template[data-name="${name}"][data-namespace="${this.namespace}"]`
    );
  }

  tagfor(type = null) {
    const handle = `${this.handle}_${this.value}_${type}`;
    return handle
      .replace(/([a-z])([A-Z])/g, '$1-$2')   // split camelCase with dash
      .replace(/[^A-Za-z0-9]+/g, '-')        // turn non-alphanum groups into single dash
      .replace(/^-+|-+$/g, '')               // trim leading/trailing dashes
      .toLowerCase();
  }

  // --- Hidden JSON sync ------------------------------------------------------
  update(obj) {
    const json = JSON.stringify(obj);
    if (this.elements.hiddenInput && this.elements.hiddenInput.value !== json) {
      this.elements.hiddenInput.value = json;
    }
  }

  // Keep parity with original serialize() including money/date/time wrappers
  serialize(fields) {
    let values = {};
    if (!fields || !fields.length) return values;

    fields.forEach((input) => {
      if (!input?.name) return;

      const parent = input.parentNode;
      const fieldWrap = parent?.closest?.('.field[data-attribute]') || null;
      const nameMatch = input.name.match(/\[([^[\]]+)\](\[\])?$/);
      if (!nameMatch) return;

      const key = nameMatch[1];

      // Lightswitch container
      if (parent?.classList?.contains('lightswitch')) {
        values[key] = parent.classList.contains('on') ? input.value : '';
        return;
      }

      // Money wrapper (Craft money input)
      if (parent?.classList?.contains('money-container')) {
        if (fieldWrap?.dataset?.name) {
          values[fieldWrap.dataset.name] = input.value;
        } else {
          values[key] = input.value;
        }
        return;
      }

      // Date wrapper (ignore locale/timezone controls)
      if (parent?.classList?.contains('datewrapper')) {
        if (!input.name.endsWith('][locale]') && !input.name.endsWith('][timezone]')) {
          if (fieldWrap?.dataset?.name) {
            values[fieldWrap.dataset.name] = input.value;
          } else {
            values[key] = input.value;
          }
        }
        return;
      }

      // Time wrapper (ignore locale/timezone controls)
      if (parent?.classList?.contains('timewrapper')) {
        if (!input.name.endsWith('][locale]') && !input.name.endsWith('][timezone]')) {
          if (fieldWrap?.dataset?.name) {
            values[fieldWrap.dataset.name] = input.value;
          } else {
            values[key] = input.value;
          }
        }
        return;
      }

      // Select (including multiselect)
      if (input.tagName === 'SELECT') {
        if (input.multiple) {
          values[key] = Array.from(input.selectedOptions).map((opt) => opt.value);
        } else {
          // also merge selected option's data-* attrs, like original
          const selected = input.options[input.selectedIndex];
          const optionData = selected ? { ...selected.dataset } : {};
          values[key] = input.value;
          values = Object.assign(values, optionData);
        }
        return;
      }

      // Generic input (ignore locale/timezone)
      if (!input.name.endsWith('][locale]') && !input.name.endsWith('][timezone]')) {
        values[key] = input.value;
      }
    });

    return values;
  }

  saveIcons(formEl) {
    const icons = formEl.querySelectorAll('.icon-picker--icon');
    for (const icon of icons) {
      const title = icon.getAttribute('title');
      if (title) {
        Craft.MetaSettingsField.icons[title] = icon.innerHTML;
      }
    }
  }

  // --- Modal triggers --------------------------------------------------------
  inputModal() {
    const tpl = this.template(this.tagfor('virtuals'));
    if (!tpl) return;

    new Craft.MetaSettingsField.ModalFields(this, {
      title: tpl.dataset.title || 'Settings',
      triggerElement: this.elements.gear,
    });
  }

  // --- Value hydration helpers (reused by ModalFields) -----------------------
  // NOTE: these mirror your original setvalues()/refreshicons()

  refreshicons(domref) {
    if (!domref) return domref;

    const currvals = this.json || {};
    const inputs = domref.querySelectorAll('[data-attribute^="iconpicker"]');
    if (!inputs?.length) return domref;

    for (let i = 0; i < inputs.length; i++) {
      const fieldname = inputs[i].dataset.name ?? null;
      const holder = inputs[i].querySelector('.icon-picker') ?? null;
      if (!holder || !fieldname) continue;

      const removeBtn = holder.querySelector('button.icon-picker--remove-btn');
      const hidden = holder.querySelector('input[type=hidden]');
      const iconEl = holder.querySelector('.icon-picker--icon');

      if (currvals[fieldname]) {
        removeBtn?.classList?.remove('hidden');
        if (hidden) hidden.value = currvals[fieldname];
        if (Craft.MetaSettingsField.icons[currvals[fieldname]]) {
          iconEl.innerHTML = Craft.MetaSettingsField.icons[currvals[fieldname]];
        }
      } else {
        removeBtn?.classList?.add('hidden');
        if (iconEl) iconEl.innerHTML = '';
        if (hidden) hidden.value = '';
      }
    }
    return domref;
  }

  setvalues(domref) {
    if (!domref) return domref;

    const currvals = this.json || {};
    for (const key in currvals) {
      const input =
        domref.querySelector(`[name$="[${key}][]"]`) ||
        domref.querySelector(`[name$="[${key}]"]`);

      if (!input) continue;

      const parent = input.parentNode;

      // Multi-select
      if (input.tagName === 'SELECT' && input.multiple) {
        const values = (Array.isArray(currvals[key]) ? currvals[key] : [currvals[key]]).map(String);
        for (const option of input.options) {
          option.selected = values.includes(option.value);
        }
        continue;
      }

      // Single select
      if (input.tagName === 'SELECT') {
        const exists = Array.from(input.options).some((opt) => opt.value === currvals[key]);
        if (exists) input.value = currvals[key];
        continue;
      }

      // Radio
      if (input.tagName === 'INPUT' && input.type === 'radio') {
        input.checked = input.value === currvals[key];
        continue;
      }

      // Lightswitch
      if (parent?.classList?.contains('lightswitch')) {
        if (currvals[key]) {
          parent.classList.add('on');
          parent.setAttribute('aria-checked', 'true');
        } else {
          parent.classList.remove('on');
          parent.setAttribute('aria-checked', 'false');
        }
        continue;
      }

      // Generic
      input.value = currvals[key];
    }

    return domref;
  }
}

/* ============================================================================
 * ModalFields — builds a styled Craft CP modal & injects template content
 * ========================================================================== */
class ModalFields {
  constructor(controller, settings = {}) {
    this.controller = controller;
    this.settings = Object.assign(
      {
        title: 'Settings',
        triggerElement: null,
      },
      settings
    );

    this.render();
    this.createShade();
    this.bindEvents();
    this.show();
    this.initFields();
  }

  render() {
    const ns = this.controller.namespace;
    const tplName = this.controller.tagfor('virtuals');
    const selector = `template[data-namespace="${ns}"][data-name="${tplName}"]`;
    const tpl = document.querySelector(selector);

    if (!tpl) {
      console.error(`[MetaSettings] Could not find template ${selector}`);
      return;
    }

    // Modal container + skeleton
    this.modalEl = document.createElement('form');
    this.modalEl.className = 'modal fitted metasettings-modal metasettings-virtuals';
    this.modalEl.id = `${ns}-virtuals-modal`;
    this.modalEl.setAttribute('accept-charset', 'UTF-8');

    const header = document.createElement('div');
    header.className = 'header';
    header.innerHTML = `<h1>${this.settings.title || tpl.dataset.title || 'Settings'}</h1>`;

    const body = document.createElement('div');
    body.className = 'body fields';

    const footer = document.createElement('div');
    footer.className = 'footer';
    footer.innerHTML = `
      <span>${Craft.t('app', 'Changes saved automatically.')}</span>
      <div class="buttons right">
        <input type="button" class="btn btn-close" value="${Craft.t('app', 'Close')}"/>
      </div>
    `;

    // Inject cloned template fragment into body
    const clone = tpl.content ? tpl.content.cloneNode(true) : null;
    if (clone) body.appendChild(clone);

    this.modalEl.appendChild(header);
    this.modalEl.appendChild(body);
    this.modalEl.appendChild(footer);
    document.body.appendChild(this.modalEl);

    // Initialize Craft UI widgets within modal
    Craft.initUiElements(this.modalEl);

    // Rehydrate from current hidden JSON (parity with original behavior)
    this.controller.refreshicons(this.modalEl);
    this.controller.setvalues(this.modalEl);
  }

  createShade() {
    this.shadeEl = document.createElement('div');
    this.shadeEl.className = 'modal-shade';
    document.body.appendChild(this.shadeEl);
  }

  bindEvents() {
    // --- Click / Esc handlers ---
    this.shadeEl.addEventListener('click', () => this.close());
    this.modalEl
      ?.querySelectorAll('.btn-close')
      .forEach((btn) => btn.addEventListener('click', () => this.close()));

    document.addEventListener('keydown', this._keydownHandler = (e) => {
      // ESC key → close modal
      if (e.key === 'Escape') {
        e.preventDefault();
        this.close();
        return;
      }

      // Ctrl+S or Cmd+S → save modal only if modal is active
      const isMac = navigator.platform.toUpperCase().includes('MAC');
      const saveCombo = (isMac && e.metaKey && e.key === 's') || (!isMac && e.ctrlKey && e.key === 's');

      if (saveCombo && this.isActive()) {
        e.preventDefault();   // stop Craft's global save
        e.stopPropagation();
        this.close();         // your modal's save+close
      }
    });
  }

  isActive() {
    // True if modal is currently visible
    return this.modalEl && this.modalEl.style.display === 'block';
  }

  show() {
    if (!this.modalEl) return;
    this.modalEl.classList.remove('hidden');
    this.modalEl.style.display = 'block';
    this.modalEl.style.zIndex = 120;
    this.shadeEl.style.display = 'block';
    this.modalEl.focus();
  }

  close() {
    if (!this.modalEl) return;

    this.controller.save(this.modalEl);
    this.modalEl.style.display = 'none';
    this.modalEl.classList.add('hidden');
    this.shadeEl.remove();

    // Remove key handler (so Craft regains Ctrl+S)
    if (this._keydownHandler) {
      document.removeEventListener('keydown', this._keydownHandler);
      this._keydownHandler = null;
    }

    this.settings.triggerElement?.focus();
  }

  // Initialize inner Craft UI (icon picker, color input, money, date, time)
  initFields() {
    if (!this.modalEl) return;

    const fields = this.modalEl.querySelectorAll('div.field[data-attribute]');
    for (const field of fields) {
      const attr = field.dataset.attribute ?? '';

      if (attr.startsWith('iconpicker')) {
        const id = field.id.replace(/-field$/, '');
        new Craft.IconPicker(`#${id}`);
      }

      if (attr.startsWith('color')) {
        const id = field.id.replace(/-field$/, '-container');
        new Craft.ColorInput(`#${id}`, { presets: [] });
      }

      if (attr.startsWith('money')) {
        new Craft.Money(`fields-${attr}`);
      }

      if (attr.startsWith('time')) {
        $(`#fields-${attr}-time`).timepicker({ ...Craft.timepickerOptions });
      }

      if (attr.startsWith('date')) {
        $(`#fields-${attr}-date`).datepicker({ ...Craft.datepickerOptions });
      }
    }
  }
}

/* ============================================================================
 * Utilities
 * ========================================================================== */
function waitFor(selector, root = document) {
  return new Promise((resolve) => {
    const el = root.querySelector(selector);
    if (el) return resolve(el);

    const observer = new MutationObserver(() => {
      const found = root.querySelector(selector);
      if (found) {
        observer.disconnect();
        resolve(found);
      }
    });
    observer.observe(root.body || root, { childList: true, subtree: true });
  });
}


/* ============================================================================
 * Public Namespace API
 * ========================================================================== */
Object.assign(Craft.MetaSettingsField, {
  icons: {},
  controllers: new Map(),

  setup(field, namespace, options = {}) {
    const controller = new FieldController(field, namespace);
    const selectEl = controller.elements.select;

    const id = selectEl?.id ?? field.id;
    if (!id) return;

    this.controllers.set(id, controller);

    controller.options = Object.assign({ autoSaveOnChange: false }, options);

    if (selectEl?.tagName === 'SELECT') {
      new MutationObserver(() => this.changed(selectEl)).observe(selectEl, {
        childList: true,
      });
    }

    this.blurFix(selectEl);
  },

  changed(selectEl) {
    const id = selectEl?.id;
    const controller = this.controllers.get(id);
    if (controller && controller.value) controller.optchange();
  },

  blurFix(el) {
    if (el?.selectize) setTimeout(() => el.selectize.blur(), 25);
  },

  waitFor,
  waitfor: waitFor,
});

Craft.MetaSettingsField.ModalFields = ModalFields;

/* ============================================================================
 * Button helper (kept minimal; used by FieldController)
 * ========================================================================== */
Craft.MetaSettingsField.Button = class {
  constructor(control) {
    this.control = control;
  }
  inputmodal() {
    this.control.inputModal();
  }
};


/* ============================================================================
 * ConfigToggle — optional JSON/Twig/File mode switcher
 * (only needed if your field's UI still uses it)
 * ========================================================================== */
Craft.MetaSettingsField.ConfigToggle = class {
  constructor(namespace, mode = 'json') {
    this.namespace = namespace;
    this.mode = mode;
    this.setup();
  }

  setup() {
    const ns = `#${this.namespace}`;
    const $json = document.querySelector(`${ns}-json-container`);
    const $twig = document.querySelector(`${ns}-twig-container`);
    const $file = document.querySelector(`${ns}-file-container`);
    const $modeInput = document.querySelector(`${ns}-mode`);
    const group = document.querySelector(`${ns} .btngroup`);
    if (!group) return;

    new Craft.Listbox($(group), {
      onChange: (e) => {
        const newMode = e.data('mode');
        this.mode = newMode;

        [$json, $twig, $file].forEach((el) => el?.classList.add('hidden'));
        if (newMode === 'json') $json?.classList.remove('hidden');
        if (newMode === 'file') $file?.classList.remove('hidden');
        if (newMode === 'twig') $twig?.classList.remove('hidden');

        if ($modeInput) $modeInput.value = newMode;
      },
    });
  }
};
