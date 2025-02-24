<?php
namespace Components\Containers;

use LDLib\Utils\Utils;

class Router {
    public static function getComponentJSClass($subdomain='www', bool $withDefine=false) {
        $root = Utils::getRootLink();
        $rootForRegex = str_replace('/','\/',str_replace(['.'],'\.',$root));

        if ($subdomain === 'subdomain') {
            $content = <<<JAVASCRIPT
            switch (url) {
                default: break;
            }

            JAVASCRIPT;
        } else {
            $content = <<<JAVASCRIPT
            switch (url) {
                case '/':
                    this.eRouter.innerHTML = '<p>[PAGE]</p>';
                    break;
                default: break;
            }

            JAVASCRIPT;
        }

        $js = <<<JAVASCRIPT
        class Router extends BaseElement {
            #initialized = false;
            #urlFormatter = null;
            static mainRouter = null;
            currentURL = '';

            get urlFormatter() { return this.#urlFormatter; }

            constructor() { super(); Router.mainRouter ??= this; }

            connectedCallback() {
                Events.addEventListener(['page_refresh'],this,(eo) => {
                    if (eo.name != 'page_refresh') return;
                    this.loadContent(this.currentURL,StateAction.None);
                });

                if (this.initialized) return;
                this.initialized = true;

                const shadowRoot = this.attachShadow({ mode: "open" });
                shadowRoot.replaceChildren(...stringToNodes(`
                <link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    :host { display: block; }
                    #router { width:100%; height:100%; }
                </style>
                <div id="router"></div>
                `));
                this.eRouter = shadowRoot.querySelector('#router');

                this.setDefaultUrlFormatter();
            }

            disconnectedCallback() {
                Events.removeEventListener(['page_refresh'],this);
            }

            async loadPage(url, stateAction=-1, options=null) {
                if (url == '') return;

                const urlFormatter = options?.urlFormatter ?? this.#urlFormatter;
                const nonOkResponseHandler = options?.nonOkResponseHandler;

                for (const o of LinkInterceptor.preProcesses) {
                    url = o.f(url,stateAction);
                    if (url === false) return;
                }

                let test = false;
                return fetch(url).then((response) => {
                    if (!response.ok) {
                        if (nonOkResponseHandler == null) throw `Failed to load '\${url}'.`;
                        else nonOkResponseHandler(url, stateAction);
                    }
                    if (response.headers.get('X-TEST') != null) test = true;
                    return response.text();
                }).then((text) => {
                    if (__debug) console.log("loading page at: "+url);

                    const displayedURL = urlFormatter(url);
                    for (const o of LinkInterceptor.midProcesses) if (o.f(url,displayedURL,stateAction) == true) return;

                    historyEdit(displayedURL,stateAction,url);

                    this.eRouter.innerHTML = "";

                    const template = document.createElement("template");
                    template.innerHTML = text.trim();
                    template.content.childNodes.forEach(cNode => {
                        if (cNode.tagName == undefined) {
                            if (__debug && cNode.nodeName != "#comment") console.warn("Undefined tag: " + cNode.nodeName);
                            return;
                        }

                        if (cNode.tagName == "SCRIPT") {
                            var scrE = document.createElement("script");
                            scrE.innerHTML = cNode.innerHTML;
                            if (cNode.type != '') scrE.type = cNode.type;
                            scrE.async = cNode.async == true;
                            this.eRouter.appendChild(scrE);
                        } else this.eRouter.appendChild(cNode);
                    });

                    return url;
                });
            }

            loadContent(url,stateAction=StateAction.PushState) {
                $content

                if (Router.mainRouter === this) historyEdit(url,stateAction);
                this.currentURL = url;
            }

            setDefaultUrlFormatter(urlFormatter) {
                this.#urlFormatter = urlFormatter != null ? urlFormatter : function(url) {
                    var res = /^(?:$rootForRegex)?\/pages\/([^?]*).*?(?:(?:\?|&)urlEnd=(.+))?$/.exec(url);
                    if (res == null) {
                        if (__debug) console.log('urlFormatter regex failed');
                        return url;
                    }

                    r1 = res[1].replace(/\.h\w+$/g,'');
                    const afterRoot = r1.endsWith('.php') ? r1.substr(0,r1.length-4) : r1;
                    var displayedURL = `$root/\${afterRoot}`;
                    if (res[2] != undefined) displayedURL += res[2].endsWith('.php') ? res[2].substr(0,res[2].length-4) : res[2];
                    if (__debug) console.log(`urlFormatter: \${url} -> \${displayedURL}`);
                    return displayedURL;
                };
            }
        }

        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-router", Router);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>