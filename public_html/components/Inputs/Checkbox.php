<?php
namespace Components\Inputs;

use LDLib\Utils\Utils;

class Checkbox {
    public static function getComponentJSClass(bool $withDefine=false) {
        $root = Utils::getRootLink();

        $js = <<<JAVASCRIPT
        class Checkbox extends BaseElement {
            static formAssociated = true;
            #initialized = false;

            constructor() {
                super();
                this.internals = this.attachInternals();

                const shadowRoot = this.attachShadow({ mode: "open" });
                shadowRoot.replaceChildren(...stringToNodes(`
                <link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    :host([disabled]) {
                        opacity: 0.5;
                    }
                    :host([disabled]) #checkbox, :host([disabled]) #label {
                        cursor: not-allowed;
                    }
                    #mainContainer {
                        display:flex;
                        align-items: center;
                    }
                    #checkbox {
                        position: relative;
                        border: 0.13rem solid grey;
                        margin: 0px 0.4em;
                        background: #251f1f;
                        padding: 0.5em;
                        cursor: pointer;
                    }
                    #invisibleCheckbox {
                        display:none;
                    }
                    .checkbox_bar {
                        width: 90%;
                        transform: translate(-50%,-50%) rotate(45deg);
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        background: white;
                        height: 20%;
                    }
                    #checkbox:not(.checked) .checkbox_bar {
                        display: none;
                    }
                    #checkbox.checked .checkbox_bar {
                        display: unset;
                    }
                    #label {
                        user-select: none;
                        cursor: pointer;
                    }
                    #checkbox_bar_1 {
                        transform: translate(-50%,-50%) rotate(45deg);
                    }
                    #checkbox_bar_2 {
                        transform: translate(-50%,-50%) rotate(135deg);
                    }
                </style>
                <div id="mainContainer">
                    <div id="checkbox"/>
                        <div id="checkbox_bar_1" class="checkbox_bar"></div>
                        <div id="checkbox_bar_2" class="checkbox_bar"></div>
                    </div>
                    <label for="invisibleCheckbox" id="label"></label><input id="invisibleCheckbox" type="checkbox" />
                </div>
                `));

                this.eLabel = shadowRoot.querySelector('#label');
                this.eCheckbox = shadowRoot.querySelector('#checkbox');
                this.eInvisCheckbox = shadowRoot.querySelector('#invisibleCheckbox');

                this.eCheckbox.addEventListener('click',(ev) => {
                    if (this.disabled) return;
                    this.#setInvisCheckbox(!this.eInvisCheckbox.checked);
                });
                this.addEventListener('keydown',(ev) => {
                    if (this.disabled) return;
                    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); this.#setInvisCheckbox(!this.eInvisCheckbox.checked); }
                });
                this.eInvisCheckbox.addEventListener('change',(ev) => {
                    this.checked = this.eInvisCheckbox.checked;
                    this.dispatchEvent(new Event("change"));
                });
            }

            connectedCallback() {
                if (this.initialized) return;
                this.initialized = true;
                this.tabIndex = 0;
                this.form = this.internals.form;
                this.eLabel.innerText = this.getAttribute('text');
            }

            formDisabledCallback(b) { this.eInvisCheckbox.disabled = b; }
            formResetCallback() { this.#setInvisCheckbox(false); }

            get disabled() { return this.getAttribute('disabled') !== null; }
            set disabled(val) {
                const b = val != null && val != false;
                const disabled = this.getAttribute('disabled') !== null;
                if (b && !disabled) this.setAttribute('disabled','true');
                else if (!b && disabled) this.removeAttribute('disabled');
            }
            get checked() { return this.hasAttribute('checked'); }
            set checked(val) {
                if (typeof val !== "boolean" || this.checked == val) return;
                if (val) {
                    this.eCheckbox.classList.add('checked');
                    this.setAttribute('checked','');
                    this.internals.setFormValue(this.eInvisCheckbox.value??'on','checked');
                } else {
                    this.eCheckbox.classList.remove('checked');
                    this.removeAttribute('checked');
                    this.internals.setFormValue(null,null);
                }
                this.#setInvisCheckbox(val,false);
                this.dispatchEvent(new Event("change"));
            }
            get value() { return this.eInvisCheckbox.value; }
            set value(val) { this.eInvisCheckbox.value = val; }

            #setInvisCheckbox(b,dispatchEvent=true) {
                this.eInvisCheckbox.checked = b;
                if (dispatchEvent) this.eInvisCheckbox.dispatchEvent(new Event("change"));
            }
        }

        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-input-checkbox", Checkbox);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>