<?php
namespace Components\Buttons;

use LDLib\Utils\Utils;

class Button {
    public static function getComponentJSClass(bool $withDefine=false) {
        $root = Utils::getRootLink();
        $js = <<<JAVASCRIPT
        class Button extends BaseElement {
            static formAssociated = true;
            static observedAttributes = ["disabled"];
            #initialized = false;

            constructor() {
                super();
                this.internals = this.attachInternals();
            }

            connectedCallback() {
                if (this.initialized) return;
                this.initialized = true;
                this.tabIndex = 0;

                const shadowRoot = this.attachShadow({ mode: "open" });
                shadowRoot.replaceChildren(...stringToNodes(`
                <link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    :host {
                        display: block;
                        background: #181818;
                        border: 2px solid rgb(255 255 255 / 45%);
                        border-radius: 1rem;
                        padding: 0px 1rem;
                        cursor: pointer;
                        width: fit-content;
                    }
                    #content {
                        pointer-events: none;
                        user-select: none;
                    }
                    :host([disabled]) {
                        opacity: 0.5;
                        cursor: unset;
                    }
                </style>
                <p id="content" ><slot name="content"></slot></p>
                `));

                this.disabled = this.getAttribute('disabled') !== null;
                if (this.getAttribute("type") == 'submit') {                    
                    this.addEventListener('click',() => this.#requestSubmit());
                    this.addEventListener('keydown',(ev) => {
                        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); this.#requestSubmit(); }
                    });
                }
            }

            get disabled() { return this.getAttribute('disabled') !== null; }
            set disabled(val) {
                const b = val != null && val != false;
                const disabled = this.getAttribute('disabled') !== null;
                if (b && !disabled) this.setAttribute('disabled','true');
                else if (!b && disabled) this.removeAttribute('disabled');
            }

            attributeChangedCallback(name,oldValue,newValue) {
                if (!this.initialized) return;
                switch (name) {
                    case 'disabled': this.disabled = this.getAttribute('disabled') !== null; break;
                    default: break;
                }
            }

            #requestSubmit()  {
                if (this.getAttribute("disabled") == true) return;
                this.internals.form.requestSubmit();
            }
        }

        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-button", Button);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>