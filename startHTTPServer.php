<?php
$apiDir = __DIR__.'/api';
require_once __DIR__.'/vendor/autoload.php';
require_once $apiDir.'/DataFetcher.php';
\LDLib\DataFetcher\DataFetcher::bindCache('\\TeacherStory\\DataFetcher\\DataFetcher');

use TeacherStory\Context\HTTPContext;
use TeacherStory\Context\OAuthContext;
use LDLib\GraphQL\GraphQL;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Security\Security;
use LDLib\Server\HTTPServer;
use LDLib\Server\ServerContext;
use LDLib\Server\WorkerContext;
use LDLib\Utils\Utils;
use Swoole\Http\Request;
use Swoole\Http\Response;

function badRequest(Response $response, string $msg='Bad request.') {
    $response->header('Content-Type', 'text/plain');
    $response->status(400);
    $response->end($msg);
}

function badMethod(Response $response, string $msg='Bad method.') {
    $response->header('Content-Type', 'text/plain');
    $response->status(405);
    $response->end($msg);
}

function forbidden(Response $response, string $msg='Unauthorized access.') {
    $response->header('Content-Type', 'text/plain');
    $response->status(403);
    $response->end($msg);
}

function fileNotFound(Response $response, string $msg='File not found.') {
    $response->status(404);
    $response->header('Content-Type', 'text/plain');
    $response->end($msg);
}

function defaultAccessControlOrigin(Request $request, Response $response) {
    switch ($request->header['origin']??'') {
        case $_SERVER['LD_LINK_OAUTH']: $response->header('Access-Control-Allow-Origin', $_SERVER['LD_LINK_OAUTH']); break;
        case $_SERVER['LD_LINK_WWW']: $response->header('Access-Control-Allow-Origin', $_SERVER['LD_LINK_WWW']); break;
        case $_SERVER['LD_LINK_ROOT']: $response->header('Access-Control-Allow-Origin', $_SERVER['LD_LINK_ROOT']); break;
        default: break;
    }
    if (isset($request->header['origin'])) $response->header('Access-Control-Expose-Headers','ETag');
}

$resolver = function(Request $request, Response $response) {
    require_once __DIR__.'/vendor/lansd/ldlib/src/Swoole/WorkerContext.php';
    if (!WorkerContext::$initialized) { $response->setStatusCode(500); $response->end('Server not ready.'); return; }
    $workerId = HTTPServer::$server->getWorkerId();
    ServerContext::workerInc($workerId,'nRequests',1);

    $requestHost = $request->header['host']??'';
    $remoteAddr = Utils::getRealRemoteAddress($request);
    if (!isset($requestHost)) { badRequest($response); return; }

    $requestMethod = $request->server['request_method']??'';
    $requestURI = urldecode($request->server['request_uri'])??'/';

    $subdomain = '';
    if (preg_match('/^(?:(?:0.0.0.0)|(?:(?:([\w-]+)\.){0,3}[\w-]+\.\w+)(?:\:\d+)?)$/',$requestHost,$m,PREG_UNMATCHED_AS_NULL) > 0) {
        $subdomain = $m[1];
        switch ($subdomain) {
            case 'www': $response->redirect('https://'.$_SERVER['LD_LINK_DOMAIN'].$requestURI); return;
            case 'mta-sts':
                if ($requestURI === '/.well-known/mta-sts.txt' && $request->server['server_port'] === 443) {
                    $filePath = HTTPServer::$rootPath.$requestURI;
                    if (file_exists($filePath)) { $response->header('Content-Type','text/plain'); $response->sendfile($filePath); }
                    else fileNotFound($response);
                    return;
                }
        }
    }
    if (isset($request->header['x-subdomain'])) $subdomain = $request->header['x-subdomain'];
    if (!in_array($subdomain,['www','res','api','oauth','mta-sts'])) $subdomain = 'www';

    $response->header('Server', 'Swoole');

    // Handle letsEncrypt challenge
    if (str_starts_with($requestURI, '/.well-known/acme-challenge/')) {
        $filePath = HTTPServer::$rootPath.$requestURI;
        if (str_contains($filePath,'/../')) { badRequest($response); return; }

        if (file_exists($filePath)) $response->sendfile($filePath);
        else fileNotFound($response);
        return;
    }

    // Force HTTPS
    if ($request->server['server_port'] == 80) {
        $response->redirect(
            'https://'.(isset($m[1]) ? $m[1].'.' : '').$_SERVER['LD_LINK_DOMAIN'].$requestURI,
            ($requestMethod === 'GET' || $requestMethod === 'HEAD') ? 301 : 308
        );
        return;
    }
    $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

    // Secret Access Key logic
    if (in_array($subdomain,['www','api','oauth']) && ($requestMethod == 'GET' || $requestMethod == 'POST')) {
        if (isset($_SERVER['LD_SECRET_ACCESS_KEY'])) {
            $secKey = $_SERVER['LD_SECRET_ACCESS_KEY'];

            if ($secKey !== ($request->cookie['secretAccessKey']??'')) {
                if ($secKey === ($request->get['secretAccessKey']??''))
                    $response->setCookie('secretAccessKey',$secKey,time()+3600*24*7,'/',$_SERVER['LD_LINK_DOMAIN'],true,true,'Lax');
                else { forbidden($response,'Unauthorized access. (The link you were given might have become invalid.)'); return; }
            }
        }
    }

    // Security: check if ip is banned
    if (Security::isIPBanned($remoteAddr)) { forbidden($response,'Your IP Address is banned.'); return; }

    // Security: check limits for unauthentified users
    if (!isset($request->cookie['sid'])) {
        if (Security::isQueryComplexityLimitReached($remoteAddr)) { forbidden($response,'API limit reached.'); return; }
        else if (Security::isRequestsLimitReached($remoteAddr)) { forbidden($response,'Requests limit reached.'); return; }
    }

    // Create context object
    try {
        $context = null;
        if (($request->header['authorization']??'') != null) $context = new OAuthContext(HTTPServer::$server,$request,$response);
        if ($context?->asUser == null) {
            $context = new HTTPContext(HTTPServer::$server,$request,$response);
            // Security check for authentified users
            $user = $context->getAuthenticatedUser();
            if ($user?->hasRole('Administrator') === false && is_int($user?->id)) {
                if (Security::isQueryComplexityLimitReached_Users($user->id)) { forbidden($response,'API limit reached.'); return; }
                else if (Security::isRequestsLimitReached_Users($user->id)) { forbidden($response,'Requests limit reached.'); return; }
            }
        }
        if ($context == null) new \LogicException('Context ???');
    } catch (\Throwable $t) {
        Logger::log(LogLevel::FATAL, "Context", "Context couldn't be initialized.");
        Logger::logThrowable($t);
        $response->header('Content-Type', 'application/json');
        $response->status(503);
        $response->end('{"error":"server not ready"}');
        return;
    }

    // Process file extension
    $fileExtension = '';
    if ($requestURI != '/') {
        $requestURI = preg_replace('/\/+$/','',$requestURI);
        if (preg_match('/\.(\w+)$/',$requestURI,$m) === 0) { $requestURI .= '.php'; $fileExtension = 'php'; }
        else $fileExtension = $m[1];
    }

    // Security: Increment request counter
    if (!($context instanceof OAuthContext)) go(function() use($context,$user,$remoteAddr) {
        $pdo = $context->getLDPDO();
        if ($user !== null) {
            $pdo->pdo->query("INSERT INTO sec_users_total_requests (user_id,count) VALUES ($user->id,1) ON DUPLICATE KEY UPDATE count=count+1");
        } else {
            $pdo->pdo->query("INSERT INTO sec_total_requests (remote_address,count) VALUES ('{$remoteAddr}',1) ON DUPLICATE KEY UPDATE count=count+1");
        }
        $pdo->toPool();
    });

    // CSP reporting
    if ($_SERVER['LD_CSP_REPORTING'] === '1' && $requestURI == '/csp-reports.php' && $requestMethod == 'POST') {
        if ($request->header['content-type'] == 'application/csp-report') {
            $res = json_decode($request->getContent(),true);
            if ($res != null) error_log(print_r($res,true));
            $response->end('OK');
        } else $response->end('NOT OK');
    }

    if ($subdomain === 'api' && $requestURI === '/graphql.php') {
        $response->header('Access-Control-Allow-Credentials','true');
        $response->header('Access-Control-Allow-Headers','Content-Type, Cache-Control');
        $response->header('Accept-Encoding','zstd, br, gzip, deflate',false);
        defaultAccessControlOrigin($request,$response);

        if ($requestMethod !== 'POST') {
            $response->status(200);
            $response->end('...');
            return;
        }

        GraphQL::processQuery($context);
    } else if ($requestMethod == 'GET' && ($subdomain == 'www' || $subdomain == 'res' || $subdomain == 'oauth')) {
        $path = $_SERVER['LD_SUBDOMAINS_PATH']??null;
        if ($path === null) { $response->header('Content-Type', 'text/html'); $response->end('Internal error.'); return; }

        try {
            if ($subdomain === 'www') {
                $response->header('Access-Control-Allow-Credentials','true');
                defaultAccessControlOrigin($request,$response);
                switch ($requestURI) {
                    case '/':
                    case '/index.php': $response->end(\TeacherStory\Pages\WWW\Index::getPage($request,$response)); break;
                    case '/manifest.webmanifest': $response->end(\TeacherStory\Pages\WWW\Manifest::getPage($request,$response)); break;
                    case '/style.css': $response->end(\TeacherStory\Pages\WWW\Style::getPage($request,$response)); break;
                    case '/styleReset.css': $response->end(\TeacherStory\Pages\WWW\StyleReset::getPage($request,$response)); break;
                    case '/scripts/sw/sw.js': $response->end(\TeacherStory\Pages\WWW\Scripts\SW\SW::getPage($request,$response)); break;
                    case '/scripts/sw/init.js': $response->end(\TeacherStory\Pages\WWW\Scripts\SW\Init::getPage($request,$response)); break;
                    case '/scripts/start.js': $response->end(\TeacherStory\Pages\WWW\Scripts\Start::getPage($request,$response)); break;
                    case '/scripts/components.js': $response->end(\TeacherStory\Pages\WWW\Scripts\Components::getPage($request,$response)); break;
                    case '/scripts/serviceworker.js': $response->end(\TeacherStory\Pages\WWW\Scripts\ServiceWorker::getPage($request,$response)); break;
                    case '/scripts/events.js': $response->end(\TeacherStory\Pages\WWW\Scripts\Events::getPage($request,$response)); break;
                    case '/scripts/functions.js': $response->end(\TeacherStory\Pages\WWW\Scripts\Functions::getPage($request,$response)); break;
                    case '/scripts/linkinterceptor.js': $response->end(\TeacherStory\Pages\WWW\Scripts\LinkInterceptor::getPage($request,$response)); break;
                    case '/scripts/wsEvents.js': $response->end(\TeacherStory\Pages\WWW\Scripts\WSEvents::getPage($request,$response)); break;
                    case '/scripts/external/gsap.min.3.12.5.js':
                        $filePath = HTTPServer::$rootPath."/$path/$subdomain{$requestURI}";
                        if (file_exists($filePath)) { $response->header('Cache-Control','max-age=31536000'); $response->sendfile($filePath); }
                        else fileNotFound($response);
                        break;
                    case '/favicon.ico':
                        $response->header('Content-Type','image/x-icon');
                        $response->end(file_get_contents(HTTPServer::$rootPath."/$path/res/icons/globe.ico"));
                        break;
                    default: fileNotFound($response); break;
                }
            } else if ($subdomain === 'res') {
                $response->header('Access-Control-Allow-Credentials','true');
                defaultAccessControlOrigin($request,$response);

                $filePath = HTTPServer::$rootPath."/$path"."/$subdomain".$requestURI;
                if (str_contains($filePath,'/../')) { badRequest($response); return; }
                if (!file_exists($filePath)) fileNotFound($response);


                $contentType = match ($fileExtension) {
                    'svg' => 'image/svg+xml',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'ico' => 'image/x-icon',
                    default => null
                };
                if ($contentType != null) $response->header('Content-Type',$contentType);
                $response->end(@file_get_contents($filePath));
            } else fileNotFound($response);
        } catch (\Throwable $t) {
            $response->status(500);
            $response->header('Content-Type', 'text/plain');
            $response->end("Couldn't load resource.");
            Logger::logThrowable($t);
            return;
        }
    } else {
        badRequest($response);
        return;
    }
};

$onWorkerStart = function() {
    require_once __DIR__.'/api/GraphQL.php';
    require_once __DIR__.'/api/Schema.php';
    require_once __DIR__.'/lib/Auth.php';
    require_once __DIR__.'/lib/Classroom.php';
    require_once __DIR__.'/lib/Context.php';
    require_once __DIR__.'/lib/Paths.php';
    require_once __DIR__.'/lib/Pupil.php';
    require_once __DIR__.'/lib/User.php';
    require_once __DIR__.'/public_html/components/Buttons/Button.php';
    require_once __DIR__.'/public_html/components/Containers/Div.php';
    require_once __DIR__.'/public_html/components/Containers/Modal.php';
    require_once __DIR__.'/public_html/components/Containers/Router.php';
    require_once __DIR__.'/public_html/components/Containers/WideTopBorder.php';
    require_once __DIR__.'/public_html/components/Inputs/Checkbox.php';
    require_once __DIR__.'/public_html/components/Inputs/Select.php';
    require_once __DIR__.'/public_html/components/Inputs/TextField.php';
    require_once __DIR__.'/public_html/subdomains/www/index.php';
    require_once __DIR__.'/public_html/subdomains/www/manifest.php-webmanifest';
    require_once __DIR__.'/public_html/subdomains/www/style.php-css';
    require_once __DIR__.'/public_html/subdomains/www/styleReset.php-css';
    require_once __DIR__.'/public_html/subdomains/www/scripts/sw/sw.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/sw/init.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/start.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/components.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/serviceworker.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/events.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/functions.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/linkinterceptor.php-js';
    require_once __DIR__.'/public_html/subdomains/www/scripts/wsEvents.php-js';
    \TeacherStory\GraphQL::init();
    \LDLib\PostHog\PostHog::initMain();
};

$httpPort = intval(getenv('TEACHERSTORY_HTTP_PORT',true));
if ($httpPort === 0) $httpPort = 80;
$httpsPort = intval(getenv('TEACHERSTORY_HTTPS_PORT',true));
if ($httpsPort === 0) $httpsPort = 443;
$wssPort = intval(getenv('TEACHERSTORY_WSS_PORT',true));
if ($wssPort === 0) $wssPort = 1443;
echo "HTTPS port: $httpsPort.".PHP_EOL;
echo "WSS port: $wssPort.".PHP_EOL;

HTTPServer::init(__DIR__,'0.0.0.0',$httpsPort,$resolver,onWorkerStart:$onWorkerStart);
HTTPServer::initEventBridge('0.0.0.0',$wssPort,true);
if (HTTPServer::$server->listen('0.0.0.0',$httpPort,SWOOLE_TCP) !== false) echo "HTTP port $httpPort.".PHP_EOL;
HTTPServer::$server->start();
?>