/** SelectPlusField - Core Class
/---------------------------------------------------------------------------------------/
    These functions communicate with any/all of the SelectPlus/Selectize instances that
    exist on the page at any give time.

    When a SelectPlus field is added to the UI, the `registerjs` macro adds a {% js %}
    call to `waitfor` Selectize to finish its thing before running out `setup()`

    It also monitors each SelectPlus field for <option> changes and triggers the
    `optchange()` function on the appropriate controller.
/-------------------------------------------------------------------------------------**/
Craft.SelectPlusField = typeof Craft.SelectPlusField === 'undefined' ? {} : Craft.SelectPlusField;
Craft.SelectPlusField = {

    icons: {},
    $controllers: {},

    setup(selectize) {
        const $controller = new Craft.SelectPlusField.Controller(selectize)
        const $selectize  = $controller.selectize()

        if( $selectize.tagName == 'SELECT' ) {
            new MutationObserver(function(mutations) {
                if( mutations[0].target ) Craft.SelectPlusField.changed( mutations[0].target )
            }).observe( $selectize, { childList: true } )

            this.blurfix($selectize)
        }

        const fieldid = $selectize.id ?? null
        this.$controllers[fieldid] = $controller
    },

    changed(selectize) {
        const fieldid = selectize.id ?? null
        if( this.$controllers[fieldid] ) {
            if( this.$controllers[fieldid].value() ) {
                this.$controllers[fieldid].optchange()
            }
        }
    },

    waitfor(selector) {
        return new Promise(resolve => {
            if( document.querySelector(selector) ) {
                return resolve(document.querySelector(selector));
            }

            const observer = new MutationObserver(mutations => {
                if( document.querySelector(selector) ) {
                    observer.disconnect();
                    resolve(document.querySelector(selector));
                }
            })

            // If you get "parameter 1 is not of type 'Node'" error,
            // see: https://stackoverflow.com/a/77855838/492336
            observer.observe(document.body, {
                childList: true,
                subtree: true
            })
        })
    },

    // Fixes a weird UI glitch where .. the first selectize in a slide out panel would
    // start open. as best as i can tell, this was only happening if the `Title` field
    // is disabled for the entry type being edited. I *see* the issue on a fresh install
    // of Craft 5, but it was self correcting by the time the panel fully opened.
    //
    // This is my attempt to replicate the autocorrect, but it's also not the end of the
    // world if it doesn't work. It doesn't break anything, it's just annoying
    blurfix($field) {
        setTimeout(() => { $field.selectize.blur(); }, 25 );
    }
};



/** Individual Field Controller
/---------------------------------------------------------------------------------------/

/-------------------------------------------------------------------------------------**/
Craft.SelectPlusField.Controller = Garnish.Base.extend({

    $spf: null,
    $button: null,

    init(elem) {
        this.$spf = elem ? elem.closest('.selectplus-field') : null
        if( this.$spf ) {

            const $button = new Craft.SelectPlusField.Button(this)

            this.btngear().onclick    = function() { $button.inputmodal() }
            this.btngear().onkeypress = function(e) {
                if (e.key === 'Enter') { $button.inputmodal() }
            }

            this.btnhelp().onclick    = function() { $button.helpmodal()  }
            this.btnhelp().onkeypress = function(e) {
                if (e.key === 'Enter') { $button.helpmodal() }
            }

            this.refresh()

            this.$button = $button
        }
    },

    handle() {
        return this.$spf.dataset.field
    },

    selectize() {
        return this.$spf.querySelector('.selectized')
    },

    value() {
        return this.selectize()?.value ?? null
    },

    btngear() {
        return this.$spf.querySelector('a.btn-gear') ?? null
    },

    btnhelp() {
        return this.$spf.querySelector('a.btn-help') ?? null
    },

    tooltips() {
        return this.$spf.querySelector('.tooltips') ?? null
    },

    hidden() {
        return this.$spf.querySelector('input[type="hidden"][name$="[json]"]')
    },

    json() {
        return JSON.parse( this.hidden()?.value )
    },

    update(obj) {
        if( !this.samesame(obj) ) {
            const field = this.hidden()
            field.value = JSON.stringify(obj)
        }
    },

    samesame(updated) {
        const current = this.json()
        const keys1   = Object.keys(current);
        const keys2   = Object.keys(updated);
        if( keys1.length !== keys2.length ) { return false; }
        for( let key of keys1 ) {
            if( !keys2.includes(key) || current[key] !== updated[key] ) { return false; }
        }
        return true;
    },

    template(name) {
        return document.querySelector('template[data-name=' + name + ']' ) ?? null
    },

    tagfor(type=null) {
        const handle = this.handle() + '_' + this.value() + '_' + type
        return handle
            .replace(/\W/g, '')
            .replace(/([a-z])([A-Z])/g, '$1_$2')
            .toLowerCase()
    },

    inputhtml() {
        let content = this.template( this.tagfor('virtuals') )?.content.cloneNode(true)

        if( content ) {
            content = this.refreshicons( content )
            content = this.setvalues( content )
        }

        return content
    },

    inputmodal() {
        const template = this.template( this.tagfor('virtuals') )
        if( template ) {
            new Craft.SelectPlusField.VirtualInputs( this, {
                html : this.inputhtml(),
                title: template.dataset.title,
                help: template.dataset.help,
                triggerElement: this.btngear(),
            })
        }
    },

    helphtml() {
        return this.template( this.tagfor('help') )?.content.cloneNode(true)
    },

    helpmodal() {
        const template = this.template( this.tagfor('help') )
        if( template ) {
            new Craft.SelectPlusField.HelpModal( this, {
                html    : this.helphtml(),
                title   : template.dataset.title,
                helpurl : template.dataset.helpurl,
                virtuals: template.dataset.virtuals,
                triggerElement: this.btnhelp(),
            })
        }
    },

    refreshicons(domref) {

        const currvals = this.json()
        const $iconinputs = domref.querySelectorAll('[data-attribute^="iconpicker"]')

        for( var i = 0; i < $iconinputs.length; i++ ) {

            const fieldname = $iconinputs[i].dataset.name ?? null;
            const $iconhome = $iconinputs[i].querySelector('.icon-picker') ?? null;
            if( ! $iconhome ) { break; }

            // have an existing icon value
            if( currvals[fieldname] ) {
                $iconhome.querySelector('button.icon-picker--remove-btn').classList.remove('hidden')
                $iconhome.querySelector('input[type=hidden]').value = currvals[fieldname]

                if( Craft.SelectPlusField.icons[currvals[fieldname]] ?? null ) {
                    $iconhome.querySelector('.icon-picker--icon').innerHTML = Craft.SelectPlusField.icons[currvals[fieldname]]
                }

            // no icon selected
            } else {
                $iconhome.querySelector('button.icon-picker--remove-btn').classList.add('hidden')
                $iconhome.querySelector('.icon-picker--icon').innerHTML = ''
                $iconhome.querySelector('input[type=hidden]').value = ''
            }
        }
        return domref
    },

    setvalues(domref) {
        const currvals = this.json()
        for( const key in currvals ) {
            const input = domref.querySelector(`[name$="[${key}]"]`)
            if( input ) {
                const parent = input.parentNode

                // don't set select value unless an option for it exists
                if( input.tagName === 'SELECT' ) {
                    const optionExists = Array.from(input.options).some(option => option.value === currvals[key]);
                    if( optionExists ) {
                        input.value = currvals[key];
                    }

                // don't reset the value for lightswitch, it should already be set, right?
                } else if( parent.classList.contains('lightswitch') ) {
                    if( currvals[key] ) {
                        parent.classList.add('on');
                        parent.setAttribute('aria-checked', 'true');
                    } else {
                        parent.classList.remove('on');
                        parent.setAttribute('aria-checked', 'false');
                    }

                // all the other field types
                } else { input.value = currvals[key]; }

            }
        }

        return domref
    },

    inputs() {
        $form = $('<form/>')
        $(this.inputhtml()).appendTo($form);
        return $form[0] ? $form[0].querySelectorAll('input, select, textarea') : null
    },

    fieldnames() {
        return Array.from( this.inputs())
            .map( elem => {
                return elem.getAttribute('name'); })
            .map( str => {
                const matches = str.match(/\[([^[]+)\]$/);
                return matches ? matches[1] : null; })
            .filter( str => str != null && str != "" )
    },

    inputdata( fieldname, value ) {
        const input = this.inputhtml().querySelector(`[name$="[${fieldname}]"]`);
        if( input ) {
            let data = {}

            if( input.tagName === 'SELECT' ) {
                const opt = Array.from(input.options).some(option => option.value === value);
                if( opt ) {
                    data = opt.dataset ?? {}
                    data[fieldname] = opt.value
                }
            } else {
                data[fieldname] = input.value
            }

            return data
        }
    },

    optchange() {
        const modalname= 'virtuals'
        const currjson = this.json()
        const $inputs  = this.inputs(modalname)
        const $fields  = this.fieldnames(modalname)
        const defaults = this.serialize($inputs)

        // which fields from our current json exist in the modal $fields?
        const portable = {};
        Object.keys(currjson).forEach( name => {
            if( $fields.includes(name) ) { portable[name] = currjson[name] }
        })

        // are the existing portable values (a) acceptable for each of their
        // respectively names fields, and (b) do any of those fields have extra
        // `settings` data associated with its newly ported value?
        let transfer = {};
        for( const key in portable ) {
            transfer = Object.assign(transfer,
                this.inputdata(key, portable[key])
            )
        }

        // combine the valid transferable values with the new defaults
        // and update the field json
        this.update( Object.assign( defaults, transfer ) )

        this.refresh()
    },


    refresh() {
        if( this.template( this.tagfor('virtuals') ) ) {
            this.btngear()?.classList.remove( 'disabled' );
        } else {
            this.btngear()?.classList.add( 'disabled' );
        }

        if( this.template( this.tagfor('help') ) ) {
            this.btnhelp()?.classList.remove( 'disabled' );
        } else {
            this.btnhelp()?.classList.add( 'disabled' );
        }

        const tip = this.template( this.tagfor('inline') )
        this.tooltips().innerHTML = ( tip )
            ? tip.content.querySelector('span').outerHTML
            : ''
    },


    save( $form ) {
        if( $form[0] ?? null ) {
            this.update( this.serialize( $form[0].querySelectorAll('input, select, textarea') ) )
            this.saveicons( $form[0] )
        }
    },


    // save each icon fields end state so we can re-populate them if/when the
    // modal is re-opened before the page is saved/reloaded
    saveicons( $form ) {
        const $icons = $form.querySelectorAll( '.icon-picker--icon' ) ?? null;
        if( $icons ) {
            Array.from($icons).forEach((icon) => {
                Craft.SelectPlusField.icons[icon.getAttribute('title')] = icon.innerHTML
            });
        }
    },


    serialize( fields ) {
        let values = {};
        if( !fields || !fields.length ) { return values; }

        fields.forEach( (input) => {
            if( input.name ) {
                const match = input.name.match(/\[([^[\]]+)\]$/)
                if( match ) {
                    if( input.type === 'checkbox' || input.type === 'radiogroup' ) {
                        values[match[1]] = input.checked ? input.value : values[match[1]];
                    } else if( input.tagName === 'SELECT' ) {
                        const option = Object.assign({}, input.options[input.selectedIndex].dataset ?? null )
                        values[match[1]] = input.value;
                        values = Object.assign({}, values, option);
                    } else {
                        values[match[1]] = input.value;
                    }
                }
            }
        } );

        return values;
    },
});



/** Button Objects
/---------------------------------------------------------------------------------------/
/-------------------------------------------------------------------------------------**/
Craft.SelectPlusField.Button = Garnish.Base.extend({
    $control: null,
    init($control) { this.$control = $control },
    helpmodal()  { this.$control.helpmodal() },
    inputmodal() { this.$control.inputmodal() }
})



/** Help Modal
/---------------------------------------------------------------------------------------/
/-------------------------------------------------------------------------------------**/
Craft.SelectPlusField.HelpModal = Garnish.Modal.extend({

    $control: null,

    init( $control, settings = {} ) {

        this.$control = $control

        const content = Object.assign({}, {
            title   : 'Help',
            helpurl : null,
            virtuals: false,
            html    : '',
        }, settings )

        this.setSettings({ draggable: true }, Garnish.Modal.defaults);

        this.$form = $('<form class="modal fitted selectplus-modal selectplus-help" />').appendTo(Garnish.$bod);

        const $header = $('<div class="header" />').appendTo(this.$form);
        $('<h1>' + content.title + '</h1>').appendTo($header);
        if( content.virtuals == 'true' ) {
            const $gearbtn = $('<a class="btn btn-gear" tabindex="0"></a>').appendTo($header);
            this.addListener($gearbtn, 'click', 'inputmodal');
            this.addListener($gearbtn, 'keypress', function (e) {
                if (e.key === 'Enter') { this.inputmodal() }
            });
        }


        const $body = $( '<div class="body"></div>').appendTo(this.$form);
        $(content.html).appendTo($body);

        const $footer = $('<div class="footer"/>').appendTo(this.$form);
        const $mainBtnGroup = $('<div class="buttons right"/>').appendTo($footer);

        if( content.helpurl ) {
            $moreBtn = $('<a href="' + content.helpurl + '" target="_blank" class="btn submit">' + Craft.t('selectplus', 'More') + '</a>').appendTo($mainBtnGroup);
            this.addListener($moreBtn, 'click', 'closing');
            this.addListener($moreBtn, 'keypress', function (e) {
                if (e.key === 'Enter') { this.closing() }
            });
        }

        $cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('app', 'Close') + '"/>').appendTo($mainBtnGroup);
        this.addListener($cancelBtn, 'click', 'closing');
        this.addListener($cancelBtn, 'keypress', function (e) {
            if (e.key === 'Enter') { this.closing() }
        });

        this.$shade = $('<div class="' + this.settings.shadeClass + '"/>');
        this.$shade.appendTo(Garnish.$bod);

        this.setContainer(this.$form);
        Garnish.addModalAttributes(this.$form);
        this.show();

        if( this.settings.triggerElement ) {
            this.$triggerElement = this.settings.triggerElement;
        } else {
            this.$triggerElement = Garnish.getFocusedElement();
        }

        Garnish.Modal.instances.push(this);
    },

    inputmodal() {
        this.closing()
        this.$control.inputmodal()
    },

    closing() {
        this.$form.remove();
        this.$shade.remove();
    }
});



/** Virtual Inputs Modal
/---------------------------------------------------------------------------------------/
/-------------------------------------------------------------------------------------**/
Craft.SelectPlusField.VirtualInputs = Garnish.Modal.extend({

    $control: null,

    init( $control, settings = {} ) {

        this.$control = $control

        content = Object.assign({}, {
            title : 'Settings',
            help  : false,
            html  : '',
        }, settings )

        this.setSettings({
            draggable: true,
            hideOnEsc: false,
            hideOnShadeClick: false,
        }, Garnish.Modal.defaults);

        this.$form = $('<form class="modal fitted selectplus-modal selectplus-virtuals" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);

        // modal header
        // todo: add a sidebar showing other selectplus fields in the same entry?
        const $header = $('<div class="header" />').appendTo(this.$form);
        $('<h1>' + content.title + '</h1>').appendTo($header);
        if( content.help == 'true' ) {
            const $helpbtn = $('<a class="btn btn-help" tabindex="0" role="button"></a>').appendTo($header);
            this.addListener($helpbtn, 'click', 'helpmodal');
            this.addListener($helpbtn, 'keypress', function (e) {
                if (e.key === 'Enter') { this.helpmodal() }
            });
        }

        // modal body (input fields)
        const $body = $( '<div class="body fields"></div>').appendTo(this.$form);
        $(content.html).appendTo($body);

        // modal footer
        const $footer = $('<div class="footer"/>').appendTo(this.$form);

        // bottom close "message/button"
        $('<span>' + Craft.t('app', 'Changes saved automatically.') + '</span>').appendTo($footer);
        const $mainBtnGroup = $('<div class="buttons right"/>').appendTo($footer);

        $cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('app', 'Close') + '"/>').appendTo($mainBtnGroup);
        this.addListener($cancelBtn, 'click', 'closing');
        this.addListener($cancelBtn, 'keypress', function (e) {
            if (e.key === 'Enter') { this.closing() }
        });


        // Create the shade with a trigger to run our closing function
        this.$shade = $('<div class="' + this.settings.shadeClass + '"/>');
        this.addListener(this.$shade, 'click', 'closing');
        this.$shade.appendTo(Garnish.$bod);

        this.setContainer(this.$form);
        Garnish.addModalAttributes(this.$form);
        this.show();

        // this sets up things like lightswitches, but not things like iconpickers
        Craft.initUiElements(this.$form);

        // Register CTRL+S to save the modal
        Garnish.uiLayerManager.registerShortcut({
            keyCode: Garnish.S_KEY,
            ctrl: true,
        },() => { this.closing() });

        // Register ESC to close the modal
        Garnish.uiLayerManager.registerShortcut({
            keyCode: Garnish.ESC_KEY
        },() => { this.closing() });

        if( this.settings.triggerElement ) {
          this.$triggerElement = this.settings.triggerElement;
        } else {
          this.$triggerElement = Garnish.getFocusedElement();
        }

        Garnish.Modal.instances.push(this);

        // trigger the js the setting up icon / color and other fields
        // that require a javascript initialization
        setTimeout(() => { this.triggerJS(); }, 25 );
    },


    // trigger any JS that needs to be run for individual fields after the modal is open.
    // some fields (like lightbox) are triggered just fine by Craft.initUiElements(),
    // but others (like iconpicker) need a little extra {% js %} to get started.
    triggerJS()
    {
        // loop through each of the fields in the form
        const $virtuals = this.$form[0].querySelectorAll( 'div.body > div.field[data-attribute]' );
        for( var i = 0; i < $virtuals.length; i++ ) {

            const attribute = $virtuals[i].dataset.attribute ?? null;

            // icon picker fields
            // -> craftcms/vendor/craftcms/cms/src/templates/_includes/forms/iconPicker.twig
            if( attribute.startsWith('iconpicker') ) {
                const iconfield = $virtuals[i].id.replace(/-field$/, '');
                new Craft.IconPicker( '#' + iconfield )
            }

            // color picker fields
            // -> craftcms/vendor/simplicateca/selectplus/src/templates/forms/color.twig
            if( attribute.startsWith('color') ) {
                const colorfield = $virtuals[i].id.replace(/-field$/, '-container');
                new Craft.ColorInput('#' + colorfield, { presets: [] });
            }

            // money fields
            // -> craftcms/vendor/craftcms/cms/src/templates/_includes/forms/money.twig
            if( attribute.startsWith('money') ) {
                new Craft.Money( 'fields-' + attribute );
            }

            // time fields
            // -> craftcms/vendor/craftcms/cms/src/templates/_includes/forms/time.twig
            if( attribute.startsWith('time') ) {
                $('#fields-' + attribute + '-time').timepicker($.extend({
                }, Craft.timepickerOptions ));
            }

            // datepick fields
            // -> craftcms/vendor/craftcms/cms/src/templates/_includes/forms/date.twig
            if( attribute.startsWith('date') ) {
                $('#fields-' + attribute + '-date').datepicker($.extend({
                }, Craft.datepickerOptions ));
            }
        }
    },

    helpmodal() {
        this.closing()
        this.$control.helpmodal()
    },

    closing() {
        this.$control.save( this.$form )
        this.$form.remove()
        this.$shade.remove()
    }
});
