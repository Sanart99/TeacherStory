<?php
namespace Components\Buttons;

use LDLib\Utils\Utils;

class Select {
    public static function getComponentJSClass(bool $withDefine=false) {
        $root = Utils::getRootLink();
        $js = <<<JAVASCRIPT
        class Select extends BaseElement {
            static formAssociated = true;
            static observedAttributes = ["disabled","class"];
            #initialized = false;
            #docListener = () => this.classList.remove('showContent');

            constructor() {
                super();
                this.internals = this.attachInternals();
                this.tabIndex = 0;

                const shadowRoot = this.attachShadow({ mode: "open" });
                shadowRoot.replaceChildren(...stringToNodes(`
                <link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    :host {
                        display: block;
                        width: 25ch;
                        height: 1.5em;
                    }
                    :host([disabled]) {
                        opacity: 0.5;
                        cursor: unset;
                    }
                    #selectedCont {
                        width: 100%;
                        height: 100%;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        padding: 0px 0px 0px 1em;
                        border: 1px solid grey;
                        background: #251f1f;
                    }
                    #content {
                        user-select: none;
                    }
                    .item {
                        border-bottom: 1px dotted white;
                        padding: 0.2em 0px 0.2em 1em;
                        cursor: pointer;
                    }
                    .item:hover {
                        background: white;
                        color: black;
                    }
                    c-div {
                        background: black;
                        border-bottom: 0px solid transparent;
                        max-height: 0px;
                        transition: all 0.25s;
                    }
                    :host(.showContent) c-div {
                        max-height: 15em;
                        border-bottom: 1px solid white;
                    }
                </style>
                <div id="selectedCont" part="selectedCont"><p id="selectedValue" part="selected"><- SELECT -></p></div>
                <c-div><div id="list" part="list" slot="content"></div></c-div>
                `));

                this.eSelectedValue = shadowRoot.querySelector('#selectedValue');
                this.eList = shadowRoot.querySelector('#list');

                shadowRoot.querySelector('#selectedCont').addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    this.classList.toggle('showContent');
                });
            }

            connectedCallback() {
                if (this.initialized) return;
                this.initialized = true;

                this.disabled = this.getAttribute('disabled') !== null;
                this.eSelectedValue.innerText = this.getAttribute('default-text')??'<- SELECT ->';
            }

            get disabled() { return this.getAttribute('disabled') !== null; }
            set disabled(val) {
                const b = val != null && val != false;
                const disabled = this.getAttribute('disabled') !== null;
                if (b && !disabled) this.setAttribute('disabled','true');
                else if (!b && disabled) this.removeAttribute('disabled');
            }

            get value() { return this.eSelectedValue.getAttribute('value'); }
            set value(val) {
                const items = this.shadowRoot.querySelectorAll('#list > .item');
                let itemFound = null;
                for (const item of items) if (item.getAttribute('value') === val) { itemFound = item; break; }

                if (!itemFound) return;

                this.eSelectedValue.innerText = itemFound.innerText;
                this.eSelectedValue.setAttribute('value',val);
                this.internals.setFormValue(val);
            }

            attributeChangedCallback(name,oldValue,newValue) {
                if (!this.initialized) return;
                switch (name) {
                    case 'disabled': this.disabled = this.getAttribute('disabled') !== null; break;
                    case 'class':
                        if (this.classList.contains('showContent')) {
                            document.addEventListener('click', this.#docListener, {once:true});
                        } else {
                            document.removeEventListener('click', this.#docListener);
                        }
                    default: break;
                }
            }

            addSelection(text,value) {
                value ??= text;

                const n = stringToNodes(`<p class="item" part="item"></p>`)[0];
                n.innerText = text;
                n.setAttribute('value',value);

                this.eList.insertAdjacentElement('beforeend',n);
                n.addEventListener('click',() => {
                    this.eSelectedValue.innerText = text;
                    this.eSelectedValue.setAttribute('value',n.getAttribute('value'));
                    this.classList.remove('showContent');
                });

                return n;
            }

            showContent(b=true) {
                if (b) this.classList.add('showContent');
                else this.classList.remove('showContent');
            }
        }

        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-select", Select);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>