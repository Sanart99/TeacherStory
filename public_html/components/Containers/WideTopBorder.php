<?php
namespace Components\Containers;

use LDLib\Utils\Utils;

class WideTopBorder {
    public static function getComponentJSClass(bool $withDefine=false) {
        $root = Utils::getRootLink();

        $js = <<<JAVASCRIPT
        class WideTopBorder extends BaseElement {
            static observedAttributes = ["title-content"];
            #initialized = false;

            constructor() { super(); }

            connectedCallback() {
                if (this.initialized) return;
                this.initialized = true;

                const shadowRoot = this.attachShadow({ mode: "open" });
                shadowRoot.replaceChildren(...stringToNodes(`
                <link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    :host {
                        display: flex;
                        flex-direction: column;
                        border: 0.2rem solid rgb(255 255 255 / 20%);
                        border-radius: 1rem;
                        overflow: auto;
                    }
                    #topBar {
                        width: 100%;
                        height: 60px;
                        background-color: rgb(255 255 255 / 20%);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        pointer-events: none;
                        user-select: none;
                    }
                    #contentDiv {
                        overflow: auto;
                    }
                </style>
                <div id="topBar" part="topBar"><p id="titleContent">TITLE</p></div>
                <div id="contentDiv" part="contentDiv">
                    <slot name="content"></slot>
                </div>
                `));
                this.eTitle = shadowRoot.querySelector('#titleContent');
                this.eTitle.innerText = this.getAttribute('title-content')??'[TITLE]';
            }

            attributeChangedCallback(name, oldValue, newValue) {
                if (!this.initialized) return;
                switch (name) {
                    case 'title-content': this.eTitle.innerText = newValue; break;
                    default: break;
                }
            }
        }

        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-cont-wide-top-border", WideTopBorder);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>