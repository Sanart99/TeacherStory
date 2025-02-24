<?php
$apiDir = __DIR__.'/api';
require_once __DIR__.'/vendor/autoload.php';
require_once $apiDir.'/DataFetcher.php';
\LDLib\DataFetcher\DataFetcher::bindCache('\\TeacherStory\\DataFetcher\\DataFetcher');

use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;
use LDLib\Event\EventResolver;
use LDLib\GraphQL\GraphQL;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use LDLib\Server\WSServer;
use TeacherStory\Context\WSContext;
use LDLib\Database\LDPDO;

$resolver = function (Server $wsServer, Frame $frame) {
    try {
        $context = new WSContext($wsServer, $frame);
    } catch (\Throwable $t) {
        @$wsServer->push($frame->fd,json_encode(['error' => 'Invalid connection.']));
        $wsServer->disconnect($frame->fd,SWOOLE_WEBSOCKET_CLOSE_SERVER_ERROR);
        Logger::log(LogLevel::ERROR, 'Websocket - Message', 'Invalid connection.');
        Logger::logThrowable($t);
        return;
    }

    $user = $context->getAuthenticatedUser();
    if ($user != null && str_starts_with($frame->data,'errorlog:')) {
        Logger::log(LogLevel::WARN,'BROWSER',"User {$user->getID()} logging an error.");
        $json = json_decode(explode(':',$frame->data,2)[1],true);
        $pdo = new LDPDO();
        $stmt = $pdo->prepare('INSERT INTO log_browser_errors (user_id,date,msg,url,location) VALUES(?,?,?,?,?)');
        $stmt->execute([$user->getID(),(new \DateTime('now'))->format('Y-m-d H:i:s'),$json['msg']??'',$json['url']??'',json_encode($json['location']??'')]);
        @$wsServer->push($frame->fd,json_encode(['error_logging' => true]));
        return;
    }

    if ($context->isEventTriggerer()) {
        $json = json_decode($frame->data,true);
        $b = false;
        if (is_array($json) && isset($json['event'])) $b = EventResolver::resolveEvent($json['event'],$json['data']);
        @$wsServer->push($frame->fd,json_encode(['event_resolution' => $b ? 'succeeded' : 'failed']));
        return;
    }

    GraphQL::processQuery($context);
};

$onWorkerStart = function() {
    require_once __DIR__.'/api/GraphQL.php';
    require_once __DIR__.'/api/Schema.php';
    require_once __DIR__.'/lib/Auth.php';
    require_once __DIR__.'/lib/Context.php';
    require_once __DIR__.'/lib/EventResolver.php';
    require_once __DIR__.'/lib/User.php';
    \TeacherStory\GraphQL::init(true);
};

$wssPort = intval(getenv('TEACHERSTORY_WSS_PORT',true));
if ($wssPort === 0) $wssPort = 1443;
echo "WSS port: $wssPort.".PHP_EOL;

WSServer::init(__DIR__,'0.0.0.0',$wssPort,$resolver,onWorkerStart:$onWorkerStart);
WSServer::$server->start();
?>