<?php
namespace TeacherStory\Pages\WWW;

use LDLib\Server\ServerContext;
use LDLib\Utils\Utils;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Index {
    private static ?string $c_hash = null;

    public static function getHash() {
        return self::$c_hash ??= hash('md5',self::getPage(withHash:false));
    }

    public static function getPage(?Request $request=null, ?Response $response=null, bool $withHash=true, bool $withCSP=true) {
        $root = Utils::getRootLink();

        $csp = "default-src https: {$_SERVER['LD_LINK_WEBSOCKET']}; style-src 'unsafe-inline' {$_SERVER['LD_LINK_DOMAIN']}; script-src 'unsafe-inline' {$_SERVER['LD_LINK_DOMAIN']} *.stripe.com; img-src $root/favicon.ico {$_SERVER['LD_LINK_RES']}; media-src {$_SERVER['LD_LINK_RES']}; form-action 'none'; frame-ancestors 'none'";
        if ($_SERVER['LD_CSP_REPORTING'] === '1') {
            $response?->header('Report-To',preg_replace('/\r?\n/',' ',<<<JSON
            {
                "group": "csp-endpoint",
                "max_age": 30,
                "endpoints": [
                    { "url": "https://{$_SERVER['LD_LINK_DOMAIN']}/csp-reports" }
                ]
            }
            JSON));
            $csp .= "; report-uri https://{$_SERVER['LD_LINK_DOMAIN']}/csp-reports; report-to csp-endpoint";
        }
        if ($withCSP) $response?->header('Content-Security-Policy',$csp);
        $response?->header('X-Frame-Options','DENY');
        $response?->header('X-XSS-Protection','0');
        $response?->header('X-Content-Type-Options','nosniff');

        $debug = (int)$_SERVER['LD_DEBUG'];
        $local = (int)$_SERVER['LD_LOCAL'];
        $response?->header('Content-Type', 'text/html');
        $v = <<<HTML
        <!DOCTYPE html> 

        <html>
            <head>
                <meta charset="UTF-8">
                <meta id="meta_viewport" name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

                <link rel="manifest" href="$root/manifest.webmanifest"/>
                <title>INDEX.PHP</title>

                <script src="$root/scripts/events.js"></script>
                <script src="$root/scripts/serviceworker.js"></script>
            </head>

            <body>
                <div style="width:100vw; height:100vh; background:black; color:white; position:fixed; top:0px; left:0px; margin:0; padding:0; z-index:1000;">
                    <p id="contactP" style="position:absolute; top:1em; color:grey; left:50%; transform:translate(-50%, 0%); margin:0; width:100%; text-align:center; line-height:1.5;">Support: {$_SERVER['LD_SERVER_SUPPORT_EMAIL']}</p>
                    <p id="initP" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%);">Loading...</p>
                    <p id="initErrorP" style="position:absolute; bottom:1em; color:red;"></p>
                </div>

                <script>
                    let errorCaught = false;
                    window.onerror = (msg,src,nLine,nCol,error) => {
                        errorCaught = true;
                        try { Events.trigger(new EventObject('init_error',error)); } catch (e) { }
                        document.querySelector('#initErrorP').innerText += `\\nLoading Failure: (\${src}:\${nLine}:\${nCol}) \${msg}`;
                        try { initClean(); } catch (e) {};
                    };
                    window.onunhandledrejection = (ev) => {
                        errorCaught = true;
                        try { Events.trigger(new EventObject('init_error',error)); } catch (e) { }
                        document.querySelector('#initErrorP').innerText += `\\nLoading Failure: \${ev.reason}`;
                        try { initClean(); } catch (e) {};
                    };

                    var __debug = $debug;
                    const toTimer = setTimeout(() => {
                        document.querySelector('#initErrorP').innerText += `\\nIt shouldn't take this long. Contact support if you can't load the website, or make sure you are using an up to date version of Chrome, Firefox or Safari.`;
                    }, 3000);
                    const initClean = (allDone=false) => {
                        try { clearTimeout(toTimer); } catch (e) {}
                        try { Events.removeEventListener(['sw_init_state'],'initListener'); } catch (e) { }
                        try { Events.removeEventListener(['init_files'],'initLoader'); } catch (e) { }
                        try { Events.removeEventListener(['sw_message'],'initSWInfosFetch'); } catch (e) { }
                        if (allDone) { window.onerror = window.onunhandledrejection = null; }
                    };

                    Events.addEventListener(['sw_init_state'],'initListener',(eo) => {
                        if (eo.name != 'sw_init_state') return;
                        document.querySelector('#initP').innerText = 'Service Worker: '+eo.data;
                        if (eo.data === 'active') Events.removeEventListener(['sw_init_state'],'initListener');
                        else if (eo.data === 'INSTALLATION_ERROR') {
                            throw new Error("Service worker failed installing.");
                        }
                    });
                    Events.addEventListener(['init_files'],'initLoader',async (eo) => {
                        if (eo.name !== 'init_files') return;
                        document.querySelector('#initP').innerText = 'Downloading files...';
                        const a = eo.data.data;
                        const promises = [];

                        try {
                            const nMax = a.scripts.length + a.styles.length;
                            let n = 0;
                            for (const url of a.scripts) {
                                const script = document.createElement("script");
                                script.type = "text/javascript";
                                script.src = url;
                                document.querySelector("head").appendChild(script);
                                script.onload = () => n++;
                                script.onerror = (err) => { console.log(err); throw new Error(`Evaluing \${url} failed. (\${err})`); }
                            }
                            for (const url of a.styles) {
                                const link = document.createElement('link');
                                link.rel = 'stylesheet';
                                link.type = 'text/css';
                                link.href = url;
                                document.head.appendChild(link);
                                link.onload = () => n++;
                                link.onerror = (err) => { throw new Error(`Evaluing \${url} failed. (\${err})`); }
                            }
                            let interv = setInterval(() => {
                                try {
                                    if (errorCaught) {
                                        document.querySelector('#initP').innerText = 'Loading failure, contact support or come back later.';
                                        clearInterval(interv);
                                        return;
                                    }
                                    if (n != nMax) return;
                                    clearInterval(interv);
                                    if (SW.readyForUpdate) window.location.reload(true);
                                    document.querySelector('#initP').innerText = 'Done.';
                                    initClean(true);
                                    Start.resetBody();
                                } catch (err) {
                                    document.querySelector('#initP').innerText = 'Loading failure, contact support or come back later.';
                                    clearInterval(interv);
                                    throw err;
                                }
                            }, 20);
                        } catch (err) {
                            document.querySelector('#initP').innerText = 'Loading failure, contact support or come back later.';
                            throw err;
                        }
                    });
                    Events.addEventListener(['sw_message'],'initSWInfosFetch',(eo) => {
                        if (eo.name != 'sw_message' || eo.data?.data?.data?.swHash == null) return;
                        document.querySelector('#contactP').innerText += `\\n Service Worker hash: \${eo.data.data.data.swHash}`;
                    });

                    if ($local === 1) Events.addEventListener(['sw_readyForUpdate'],'tmp_sw_updateRefresh',(eo) => {
                        if (eo.name != 'sw_readyForUpdate') return;
                        window.location.reload();
                    });

                    SW.init();
                </script>
            </body>
        </html>

        HTML;
        if ($withHash) $response?->header('ETag',self::getHash());
        return ($request != null && $response != null) ? ServerContext::applyContentEncoding($request,$response,$v) : $v;
    }
}