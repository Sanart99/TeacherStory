<?php
namespace Components\Containers;

use LDLib\Utils\Utils;

class Modal {
    public static function getComponentJSClass(bool $withDefine=false) {
        $root = Utils::getRootLink();

        $js = <<<JAVASCRIPT
        class Modal extends BaseElement {
            constructor() {
                super();

                const shadowRoot = this.attachShadow({ mode:"open" });
                shadowRoot.replaceChildren(...stringToNodes(
                `<link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    :host {
                        position: absolute;
                        width: 100%;
                        height: 100%;
                        top: 0px;
                        left: 0px;
                        background: #00000055;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    #content {
                        position: relative;
                    }
                </style>
                <div id="content"></div>`
                ));

                this.eContent = shadowRoot.querySelector('#content');

                this.eContent.addEventListener('click',(ev) => {
                    ev.stopPropagation();
                });
                this.addEventListener('click',(ev) => {
                    this.remove();
                });
            }

            setContent(element) {
                this.eContent.replaceChildren(element);
            }
        }

        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-modal", Modal);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>