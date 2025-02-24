<?php
namespace Components\Containers;

use LDLib\Utils\Utils;

class Div {
    public static function getComponentJSClass(bool $withDefine=false) {
        $root = Utils::getRootLink();

        $js = <<<JAVASCRIPT
        class Div extends BaseElement {
            #trueVVal = 0;
            #trueHVal = 0;
            slideDuration = 0.2;

            constructor() {
                super();

                const shadowRoot = this.attachShadow({ mode: "open" });
                shadowRoot.replaceChildren(...stringToNodes(`
                <link rel="stylesheet" href="{$root}/styleReset.css" type="text/css">
                <style>
                    :host {
                        display: block;
                        overflow: hidden;
                        position: relative;
                    }
                    #scrollbarVCont {
                        top: 0px;
                        right: 0px;
                        height: calc(100% - 4px);
                        width: 4px;
                    }
                    #scrollbarHCont {
                        bottom: 0px;
                        left: 0px;
                        width: calc(100% - 4px);
                        height: 4px;
                    }
                    .scrollbarCont {
                        position: absolute;
                        background: #667c9788;
                    }
                    .scrollbar {
                        width: 100%;
                        height: 100%;
                        position: relative;
                        background: burlywood;
                        cursor: grab;
                        user-select: none;
                    }
                    .scrollbar:active {
                        cursor: grabbing;
                    }
                    ::slotted([slot="content"]) {
                        position: relative;
                        touch-action: pinch-zoom;
                    }
                </style>
                <slot name="content"></slot>
                <div id="scrollbarVCont" class="scrollbarCont" part="scrollbarVCont"><div id="scrollbarV" class="scrollbar" part="scrollbarV"></div></div>
                <div id="scrollbarHCont" class="scrollbarCont" part="scrollbarHCont"><div id="scrollbarH" class="scrollbar" part="scrollbarH"></div></div>
                `));

                this.eScrollbarVCont = shadowRoot.querySelector('#scrollbarVCont');
                this.eScrollbarV = shadowRoot.querySelector('#scrollbarV');
                this.eScrollbarHCont = shadowRoot.querySelector('#scrollbarHCont');
                this.eScrollbarH = shadowRoot.querySelector('#scrollbarH');
                this.eContent = shadowRoot.querySelector('slot[name="content"]').assignedNodes()[0];

                this.eScrollbarV.addEventListener('mousedown',(ev) => {
                    ev.stopPropagation();

                    let startY = ev.clientY;
                    const elMov = (ev) => {
                        ev.stopPropagation();
                        const deltaY = ev.clientY - startY;
                        startY = ev.clientY;
                        this.#deltaMov(0,deltaY);
                    };
                    document.addEventListener('mousemove',elMov);
                    document.addEventListener('mouseup',(ev) => { ev.stopPropagation(); document.removeEventListener('mousemove',elMov); }, {once:true});
                });
                this.eScrollbarH.addEventListener('mousedown',(ev) => {
                    ev.stopPropagation();

                    let startX = ev.clientX;
                    const elMov = (ev) => {
                        ev.stopPropagation();

                        const deltaX = ev.clientX - startX;
                        startX = ev.clientX;
                        this.#deltaMov(deltaX,0);
                    };
                    document.addEventListener('mousemove',elMov);
                    document.addEventListener('mouseup',(ev) => { ev.stopPropagation(); document.removeEventListener('mousemove',elMov); }, {once:true});
                });
                this.eScrollbarV.addEventListener('touchstart',(ev) => {
                    ev.stopPropagation();
                    ev.preventDefault();

                    let startY = ev.touches[0].clientY;
                    const elMov = (ev) => {
                        ev.stopPropagation();
                        if (ev.cancelable) ev.preventDefault();

                        const deltaY = ev.touches[0].clientY - startY;
                        startY = ev.touches[0].clientY;
                        this.#deltaMov(0,deltaY);
                    };
                    document.addEventListener('touchmove',elMov,{passive:false});
                    document.addEventListener('touchend',(ev) => { ev.stopPropagation(); document.removeEventListener('touchmove',elMov); }, {once:true});
                }, {passive:false});
                this.eScrollbarH.addEventListener('touchstart',(ev) => {
                    ev.stopPropagation();
                    ev.preventDefault();

                    let startX = ev.touches[0].clientX;
                    const elMov = (ev) => {
                        ev.stopPropagation();
                        if (ev.cancelable) ev.preventDefault();

                        const deltaX = ev.touches[0].clientX - startX;
                        startX = ev.touches[0].clientX;
                        this.#deltaMov(deltaX,0);
                    };
                    document.addEventListener('touchmove',elMov,{passive:false});
                    document.addEventListener('touchend',(ev) => { ev.stopPropagation(); document.removeEventListener('touchmove',elMov); }, {once:true});
                }, {passive:false});
                this.eContent.addEventListener('touchstart',(ev) => {
                    ev.stopPropagation();

                    let startX = ev.touches[0].clientX;
                    let startY = ev.touches[0].clientY;
                    const elMov = (ev) => {
                        ev.stopPropagation();

                        const deltaX = ev.touches[0].clientX - startX;
                        const deltaY = ev.touches[0].clientY - startY;
                        startX = ev.touches[0].clientX;
                        startY = ev.touches[0].clientY;

                        this.#deltaMov(-deltaX,-deltaY);
                    };
                    document.addEventListener('touchmove',elMov,{passive:false});
                    document.addEventListener('touchend',(ev) => { ev.stopPropagation(); document.removeEventListener('touchmove',elMov); }, {once:true});
                }, {passive:true});

                this.eScrollbarV.addEventListener('click',(ev) => ev.stopPropagation());
                this.eScrollbarH.addEventListener('click',(ev) => ev.stopPropagation());

                this.eScrollbarVCont.addEventListener('click',(ev) => {
                    ev.stopPropagation();

                    const y = this.eScrollbarV.getBoundingClientRect().y;
                    if (ev.clientY > y) this.#deltaMov(0,15);
                    else if (ev.clientY < y) this.#deltaMov(0,-15);
                });
                this.eScrollbarHCont.addEventListener('click',(ev) => {
                    ev.stopPropagation();

                    const x = this.eScrollbarH.getBoundingClientRect().x;
                    if (ev.clientX > x) this.#deltaMov(15,0);
                    else if (ev.clientX < x) this.#deltaMov(-15,0);
                });

                this.addEventListener('wheel',(ev) => { ev.stopPropagation(); ev.preventDefault(); this.#deltaMov(ev.deltaX*0.33,ev.deltaY*0.33); }, {passive:false});

                const mutObs = new MutationObserver(() => this.recalculScrollbars());
                mutObs.observe(this,{subtree:true, childList:true, attributes:true});
                const resizeObs = new ResizeObserver(() => this.recalculScrollbars());
                resizeObs.observe(this);
            }

            #interv = null;
            connectedCallback() {
                if (this.#interv != null) { clearInterval(this.#interv); this.#interv = null; }
                this.#interv = setInterval(() => {
                    const rect = this.getBoundingClientRect();
                    if (rect.height == 0 || rect.width == 0) return;
                    this.recalculScrollbars();
                    clearInterval(this.#interv);
                    this.#interv = null;
                }, 50);
            };

            recalculScrollbars() {
                const elemRect = this.getBoundingClientRect();
                const scollbarVContRect = this.eScrollbarVCont.getBoundingClientRect();
                const scollbarHContRect = this.eScrollbarHCont.getBoundingClientRect();

                const ratioV = elemRect.height / this.eContent.scrollHeight;
                const scrollbarVHeight = (scollbarVContRect.height * ratioV);
                const scrollbarVRatio = (scrollbarVHeight / scollbarVContRect.height);
                this.eScrollbarV.style.height = scrollbarVHeight+'px';
                if (ratioV >= 0.997) { this.eScrollbarVCont.style.display = 'none'; this.eContent.style.top = '0px'; }
                else this.eScrollbarVCont.style.display = '';

                const ratioH = elemRect.width / this.eContent.scrollWidth;
                const scrollbarHWidth = (scollbarHContRect.width * ratioH);
                const scrollbarHRatio = (scrollbarHWidth / scollbarHContRect.width);
                this.eScrollbarH.style.width = scrollbarHWidth+'px';
                if (ratioH >= 0.997) { this.eScrollbarHCont.style.display = 'none'; this.eContent.style.left = '0px'; }
                else this.eScrollbarHCont.style.display = '';
            };

            #deltaMov(deltaX, deltaY) {
                this.recalculScrollbars();
                const scrollbarVRect = this.eScrollbarVCont.getBoundingClientRect();
                const scrollbarHRect = this.eScrollbarHCont.getBoundingClientRect();
                const scrollbarVStyle = getComputedStyle(this.eScrollbarV);
                const scrollbarHStyle = getComputedStyle(this.eScrollbarH);

                // Vertical
                if (deltaY != null && deltaY != 0) {
                    let vHeight = parseFloat(scrollbarVStyle.height);
                    let v = this.#trueVVal + deltaY;
                    if (v < 0) v = 0;
                    else if (v + vHeight > scrollbarVRect.height) v = scrollbarVRect.height - vHeight;

                    let o = {top:this.eScrollbarV.style.top};
                    gsap.to(o, { top:v, duration:this.slideDuration, onUpdate:() => this.eScrollbarV.style.top = o.top+'px' });
                    this.#trueVVal = v;

                    const ratio = this.eContent.scrollHeight / scrollbarVRect.height;
                    let o2 = {top:this.eContent.style.top};
                    gsap.to(o2, { top:(-v*ratio), duration:this.slideDuration, onUpdate:() => this.eContent.style.top = o2.top+'px' });
                }

                // Horizontal
                if (deltaX != null && deltaX != 0) {
                    const vWidth = parseFloat(scrollbarHStyle.width);
                    let v = this.#trueHVal + deltaX;
                    if (v < 0) v = 0;
                    else if (v + vWidth > scrollbarHRect.width) v = scrollbarHRect.width - vWidth;

                    let o = {left:this.eScrollbarH.style.left};
                    gsap.to(o, { left:v, duration:this.slideDuration, onUpdate:() => this.eScrollbarH.style.left = o.left+'px' });
                    this.#trueHVal = v;

                    const ratio = this.eContent.scrollWidth / scrollbarHRect.width;
                    let o2 = {left:this.eContent.style.left};
                    gsap.to(o2, { left:(-v*ratio), duration:this.slideDuration, onUpdate:() => this.eContent.style.left = o2.left+'px' });
                }
            };
        }

        JAVASCRIPT;
        if ($withDefine) {
            $js .= <<<JAVASCRIPT
            customElements.define("c-div", Div);

            JAVASCRIPT;
        }
        return $js;
    }
}
?>