<?php
namespace Components\Inputs;

use LDLib\Utils\Utils;

class TextField {
    public static function getComponentJSClass(bool $withDefine=false) {
        $root = Utils::getRootLink();

        $js = <<<JAVASCRIPT
        class TextField extends BaseElement {
            static formAssociated = true;
            #initialized = false;

            constructor() {
                super();
                this.internals = this.attachInternals();
            }

            connectedCallback() {
                if (this.initialized) return;
                this.initialized = true;
                this.form = this.internals.form;

                const shadowRoot = this.attachShadow({ mode: "open" });
                shadowRoot.replaceChildren(...stringToNodes(`
                <link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    #textInput {
                        background: #251f1f;
                        color: grey;
                        border: 0.13rem solid grey;
                        padding: 0.5rem;
                        font-size: 1em;
                        width: 100%;
                        transition: all 0.25s;
                    }
                    #textInput:focus {
                        border-color: white;
                    }
                    #placeholder {
                        position: absolute;
                        font-size: 1.2em;
                        font-weight: bold;
                        color: #ffffff47;
                        top: 50%;
                        left: 50%;
                        text-align: center;
                        transform: translate(-50%, -50%);
                        width: 100%;
                        pointer-events: none;
                        user-select: none;
                        transition: all 0.25s;
                    }
                    #placeholder.ontop {
                        pointer-events: unset;
                        top: -36%;
                        font-size: 1em;
                    }
                </style>
                <label for="textInput" id="placeholder"></label>
                <input id="textInput" type="text" />
                `));
                this.ePlaceholder = shadowRoot.querySelector('#placeholder');
                this.eInput = shadowRoot.querySelector('input');
            }

            formAssociatedCallback() {
                this.ePlaceholder.innerText = this.getAttribute('placeholder');

                if (this.getAttribute('type') == 'password') this.eInput.type = 'password';
                this.eInput.addEventListener('input', (ev) => { this.value = ev.target.value; });
                this.eInput.addEventListener('focus', () => this.ePlaceholder.classList.add('ontop'));
                this.eInput.addEventListener('focusout', () => { if (this.value.trim().length == 0) this.ePlaceholder.classList.remove('ontop'); });
            }

            formDisabledCallback(b) { this.eInput.disabled = b; }
            formResetCallback() { this.value = ''; }

            get value() { return this.eInput.value; }
            set value(val) { this.eInput.value = val; this.internals.setFormValue(val);}
        }
        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-input-text-field", TextField);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>