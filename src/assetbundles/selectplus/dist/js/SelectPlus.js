Craft.SelectPlusField = typeof Craft.SelectPlusField === 'undefined' ? {} : Craft.SelectPlusField;
document.addEventListener('DOMContentLoaded', () => {
    Craft.SelectPlusField.Fields.init();
});



/**
 * SelectPlusField - Documentation Modal
 */
Craft.SelectPlusField.DocumentationModal = Garnish.Modal.extend({

    init( modalContent = {} ) {

        Object.keys(modalContent).forEach( k => {
            if ( modalContent[k] === null || modalContent[k] == "" )
                delete modalContent[k]
        });

        const content = Object.assign({}, {
            title: 'Documentation',
            more : 'More',
            url  : null,
            html : null,
        }, modalContent )

        this.modal.$form = $('<form class="modal fitted selectplus" method="post" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);

        $('<div class="header"><h1>' + content.title + '</h1></header>').appendTo(this.modal.$form);

        if( content.html ) {
            this.$body = $('<div class="body">' + content.html + '</div>').appendTo(this.modal.$form);
        }

        const $footer = $('<div class="footer"/>').appendTo(this.modal.$form);

        const $mainBtnGroup = $('<div class="buttons right"/>').appendTo($footer);

        if( content.url ) {
            this.$moreBtn = $('<a href="' + content.url + '" target="_blank" class="btn submit">' + Craft.t('selectplus', content.more) + '</a>').appendTo($mainBtnGroup);
            this.addListener(this.$moreBtn, 'click', 'onFadeOut');
        }

        this.$cancelBtn   = $('<input type="button" class="btn" value="' + Craft.t('app', 'Close') + '"/>').appendTo($mainBtnGroup);
        this.addListener(this.$cancelBtn, 'click', 'onFadeOut');

        Craft.initUiElements(this.modal.$form);

        this.base(this.modal.$form);
    },

    onFadeOut() {
        this.modal.$form.remove();
        this.$shade.remove();
    }
});


/**
 * SelectPlusField - Settings Modal
 */
Craft.SelectPlusField.InputModal = Garnish.Modal.extend({

    field: null,

    init( settings = {} ) {

        content = Object.assign({}, {
            title : 'Field Settings',
            html  : null,
        }, settings )

        this.field = settings.field ?? null

        // the modal element is actually the <form> tag
        this.$form = $('<form class="modal fitted selectplus fields" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);

        // modal header
        // todo: add a close button here
        $('<div class="header"><h1>' + content.title + '</h1></header>').appendTo(this.$form);

        // modal body (input fields)
        const $body = $( '<div class="body"></div>').appendTo(this.$form);
        $(content.html).appendTo($body);

        // bottom close "message/button"
        const $footerclose = $('<input type="button" class="close" value="' + Craft.t('app', 'Changes automatically saved on close.') + '"/>')
        this.addListener( $footerclose, 'click', 'onFadeOut');

        // modal footer
        const $footer = $('<div class="footer"/>').appendTo(this.$form);
        $footerclose.appendTo($footer);

        // set starting values
        const values = settings.values ?? {};
        for (const key in values) {
            const input = this.$form[0].querySelector(`[name$="[${key}]"]`);
            if (input) {
                if( input.tagName === 'SELECT' ) {
                    const optionExists = Array.from(input.options).some(option => option.value === values[key]);
                    if (optionExists) {
                        input.value = values[key];
                    }
                } else {
                    input.value = values[key];
                }
            }
        }

        // open the modal
        Craft.initUiElements(this.$form);
        this.base(this.$form);
    },


    onFadeOut() {
        Craft.SelectPlusField.Fields.saveVirtuals( this.$form, this.field )
        this.$form.remove()
        this.$shade.remove()
    }
});



/**
 * SelectPlusField - Primary JS
 */
Craft.SelectPlusField.Fields = {
    init() {
        this.monitorSelects()
        this.monitorButtons()
    },

    monitorSelects() {
        // setup selectize fields that existed on the page when it loaded
        // i.e. editing an entry type directly that uses a selectplus field
        const selectizeFields = document.querySelectorAll('.selectplus select.selectized')
        selectizeFields.forEach((field) => {
            Craft.SelectPlusField.Fields.setupSelect( field )
        });


        // listener for new selectize fields added to the page dynamically, i.e. via
        // matrix fields or elements loaded via slideout / modal
        new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if( mutation.addedNodes.length > 0 ) {
                    mutation.addedNodes.forEach(function(field) {
                        if( field.classList
                         && field.classList.contains('selectized')
                         && field.tagName === 'SELECT'
                         && field.parentNode.classList.contains('selectize')
                        ) {
                            Craft.SelectPlusField.Fields.setupSelect( field )
                        }
                    })
                }
            })
        }).observe(
            document.body, { childList: true, subtree: true }
        )
    },


    // setup a selectplus field the first time we see one
    setupSelect( field ) {
        const selectplus = field ? field.closest('.selectplus') : null
        if( selectplus ) {

            // toggle any tooltips + modal buttons
            this.toggleButtons( selectplus, field.value )

            // watch for changes
            new MutationObserver(function(mutations) {
                if( mutations[0].target ) Craft.SelectPlusField.Fields.changeSelect( mutations[0].target )
            }).observe( field, { childList: true } )

            // setup our onload defaults
            const json = selectplus.querySelector('input[type="hidden"][name$="[json]"]')
            const gear = selectplus.querySelector('.gears .btn-gear[data-value="'+field.value+'"]')
            if( json && gear ) {
                const template = document.querySelector( 'template[data-modal="'+gear.dataset.modal+'"]' )
                if( template ) {
                    const values = Object.assign({},
                        this.virtualDefaults( template.content.cloneNode(true) ),
                        JSON.parse( json.value )
                    )
                    json.value = JSON.stringify( values )
                }
            }

            // this fixes a weird UI glitch where .. the first selectize in a slide out panel would start open.
            // as best as i can tell, this was only happening if the `Title` field is disabled for the entry
            // type being edited. I *see* the issue on a fresh install of Craft 5, but it was self correcting
            // by the time the panel fully openned. This is my attempt to replicate the autocorrect, but it's
            // also not the end of the world if it doesn't work. It doesn't break anything, it's just annoying
            const $select = selectplus.querySelector( 'select.selectized' );
            if( $select ) {
                setTimeout(() => { $select.selectize.blur(); }, 25 );
            }
        }
    },


    // when the value of the selectize field changes
    changeSelect( field ) {
        const selectplus = field ? field.closest('.selectplus') : null
        if( selectplus ) {
            this.toggleButtons( field.closest(".selectplus"), field.value  )
        }
    },



    monitorButtons() {
        // jQuery is <fart> but it since selectize is already based on it, this does
        // make setting click listeners a little easier.
        (function($) {
            $(document).on('keypress click', '.selectplus .btn-gear', function(e) {
                e.preventDefault();
                Craft.SelectPlusField.Fields.buttonSettings( e.target )
            });

            $(document).on('keypress click', '.selectplus .btn-help', function(e) {
                e.preventDefault();
                Craft.SelectPlusField.Fields.buttonTooltip( e.target )
            });
        })(jQuery);
    },


    toggleButtons( selectplus, value ) {
        // toggle visibility of inline tooltips & modal buttons for each select <option>
        const gears = selectplus.querySelectorAll( '.gears' );
        if( gears && gears[0] && gears[0].children ) {
            Array.from(gears[0].children).forEach((btn) => {
                btn.style.display = ( btn.dataset.value == value ) ? 'flex' : 'none';
            });
        }

        const tooltips = selectplus.querySelectorAll( '.tooltips' );
        if( tooltips && tooltips[0] && tooltips[0].children ) {
            Array.from(tooltips[0].children).forEach((tip) => {
                tip.style.display = ( tip.dataset.value == value ) ? 'flex' : 'none';
            });
        }
    },


    buttonTooltip( link ) {

        const parent  = link.closest('div.note')

        const modalSettings = {
            title: parent.dataset.title ?? null,
            more:  parent.dataset.more  ?? null,
            url:   parent.dataset.url   ?? null,
            html:  parent.dataset.html  ?? null,
        }

        new Craft.SelectPlusField.DocumentationModal( modalSettings )
    },


    buttonSettings( link )
    {
        const selectplus = link ? link.closest('.selectplus') :  null
        if( selectplus ) {
            const modal    = link.dataset.modal ?? null
            const title    = link.dataset.title ?? 'Settings'
            const template = document.querySelector( 'template[data-modal="'+modal+'"]' )

            if( template ) {
                let json = selectplus.querySelector('input[type="hidden"][name$="[json]"]')
                new Craft.SelectPlusField.InputModal({
                    field : selectplus,
                    title : title,
                    values: JSON.parse( json.value ?? '' ),
                    html  : template.content.cloneNode(true),
                })
            }
        }
    },

    virtualDefaults( fields ) {
        $form = $('<form class="modal fitted selectplus fields" accept-charset="UTF-8"/>');
        $(fields).appendTo($form);
        const $inputs = $form[0] ? $form[0].querySelectorAll('input, select, textarea') : null
        return this.serialize( $inputs )
    },

    saveVirtuals( $form, $field )
    {
        const $inputs = $form[0] ? $form[0].querySelectorAll('input, select, textarea') : null
        let values = this.serialize( $inputs )

        const $json = $field ? $field.querySelector('input[type="hidden"][name$="[json]"]') : null
        if( $json ) {
            $json.value = JSON.stringify( values )
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
    }
}
