<?php
namespace TeacherStory\DataFetcher;

use Swoole\Coroutine;
use Swoole\Http\Request;
use Swoole\Table;
use LDLib\Cache\LDRedis;
use LDLib\Context\IWSContext;
use LDLib\Database\DatabaseUtils;
use LDLib\Database\LDPDO;
use LDLib\PaginationVals;
use LDLib\Logger\Logger;
use LDLib\Logger\LogLevel;
use Ds\Set;

enum DataType {
    case User;
}

class DataFetcher implements \LDLib\DataFetcher\IHTTPDataFetcher,\LDLib\DataFetcher\IWSDataFetcher {
    public static Table $t_users;

    public static Set $aPrepared;
    public static Set $aPreparedM;

    public static Table $t_conns;

    public static bool $busy = false;

    public static array $aDatas = [
        'users' => [],
        'usersM' => []
    ];

    public static function init() {
        self::$aPrepared = new Set();
        self::$aPreparedM = new Set();

        $dbName = (bool)$_SERVER['LD_TEST'] ? $_SERVER['LD_TEST_DB_NAME'] : $_SERVER['LD_DB_NAME'];
        $pdo = new \PDO("mysql:host={$_SERVER['LD_DB_HOST']};dbname={$dbName}", $_SERVER['LD_DB_USER'], $_SERVER['LD_DB_PWD']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        self::$t_users = new Table(128);
        self::$t_users->column('id', Table::TYPE_INT);
        self::$t_users->column('roles', Table::TYPE_STRING, 500);
        self::$t_users->column('name', Table::TYPE_STRING, 50);
        self::$t_users->column('registration_date', Table::TYPE_STRING, 25);
        self::$t_users->column('settings', Table::TYPE_STRING, 30);
        self::$t_users->column('_cachedate_', Table::TYPE_STRING, 25);
        self::$t_users->create();
        echo '[TABLE] Users : '.self::$t_users->getMemorySize().' bytes'.PHP_EOL;
        Logger::log(LogLevel::INFO, 'Table', 'Users, allocated '.self::$t_users->getMemorySize().' bytes');
    }

    public static function wsInit() {
        self::$t_conns = new Table(1024);
        self::$t_conns->column('fd', Table::TYPE_INT);
        self::$t_conns->column('ip', Table::TYPE_STRING, 12);
        self::$t_conns->column('port', Table::TYPE_INT);
        self::$t_conns->column('connect_time', Table::TYPE_INT);
        self::$t_conns->column('dispatch_time', Table::TYPE_INT);
        self::$t_conns->column('sid', Table::TYPE_STRING, 50);
        self::$t_conns->column('is_event_triggerer', Table::TYPE_INT);
        self::$t_conns->create();
        echo '[TABLE] Connections : '.self::$t_conns->getMemorySize().' bytes'.PHP_EOL;
        Logger::log(LogLevel::INFO, 'Table', 'Connections, allocated '.self::$t_conns->getMemorySize().' bytes');
    }

    public static function init2() {
        return;
    }

    public static function getTablesStats():array {
        $f = static function(Table $table, string $name) {
            $stats = $table->stats();
            return [
                'name' => $name,
                'count' => $table->count(),
                'size' => $table->getSize(),
                'memorySize' => $table->memorySize,
                'stats_num' => $stats['num'],
                'stats_conflict_count' => $stats['conflict_count'],
                'stats_conflict_max_level' => $stats['conflict_max_level'],
                'stats_insert_count' => $stats['insert_count'],
                'stats_update_count' => $stats['update_count'],
                'stats_delete_count' => $stats['delete_count'],
                'stats_available_slice_num' => $stats['available_slice_num'],
                'stats_total_slice_num' => $stats['total_slice_num']
            ];
        };

        return [$f(self::$t_users, 't_users')];
    }

    public static function getWSTablesStats():array {
        $f = static function(Table $table, string $name) {
            $stats = $table->stats();
            return [
                'name' => $name,
                'count' => $table->count(),
                'size' => $table->getSize(),
                'memorySize' => $table->memorySize,
                'stats_num' => $stats['num'],
                'stats_conflict_count' => $stats['conflict_count'],
                'stats_conflict_max_level' => $stats['conflict_max_level'],
                'stats_insert_count' => $stats['insert_count'],
                'stats_update_count' => $stats['update_count'],
                'stats_delete_count' => $stats['delete_count'],
                'stats_available_slice_num' => $stats['available_slice_num'],
                'stats_total_slice_num' => $stats['total_slice_num']
            ];
        };

        return [$f(self::$t_users, 't_users'),$f(self::$t_conns, 't_conns')];
    }

    public static function exec(LDPDO $pdo, LDRedis $redis) {
        while (self::$busy) Coroutine::sleep(0.10);
        self::$busy = true;
        try {
            foreach (self::$aPreparedM as $v) switch ($v[0]) {
                case DataType::User: self::$aDatas['usersM'][] = $v[1]; break;
            }
            self::execFunctions($pdo,$redis);

            foreach (self::$aPrepared as $v) switch ($v[0]) {
                case DataType::User: self::$aDatas['users'][] = $v[1]; break;
            }
            self::execFunctions($pdo,$redis);
        } finally {
            self::$busy = false;
        }
    }

    private static function execFunctions(LDPDO $pdo, LDRedis $redis) {
        if (!empty(self::$aDatas['usersM']) || !empty(self::$aDatas['users'])) self::execUsers($pdo,$redis);
    }

    /** Users **/

    public static function storeUser(LDRedis $redis, array $userRow, \DateTimeInterface $date) {
        $table = self::$t_users;
        if (!$table->set((int)$userRow['id'],[
            'id' => $userRow['id'],
            'roles' => $userRow['roles'],
            'name' => $userRow['name'],
            'registration_date' => $userRow['registration_date'],
            'settings' => 'redis::settings:'.$userRow['id'],
            '_cachedate_' => $date->format('Y-m-d H:i:s')]
        )) self::storageError('t_users',"Couldn't store userRow.");

        if (!$redis->set("settings:{$userRow['id']}", $userRow['settings'])) self::storageError('redis',"Couldn't store settings:{$userRow['id']} in redis.");
    }

    public static function forgetUser(LDRedis $redis, int $userId) {
        self::$t_users->del($userId);
        $redis->del("settings:{$userId}");
    }

    public static function prepUser(LDRedis $redis, int $userId, int $freshness = 5) {
        $cacheDate = self::$t_users->get($userId,'_cachedate_');

        if ($cacheDate === false || (time() - strtotime($cacheDate)) > $freshness || $redis->exists("$userId:settings") == 0)
            self::$aPrepared->add([DataType::User,$userId]);
    }

    public static function unprepUser(int $userId) {
        self::$aPrepared->remove([DataType::User,$userId]);
    }

    public static function getUser(LDRedis $redis, int $userId) {
        $res = self::$t_users->get($userId);
        if ($res == null) return null;

        $res['settings'] = $redis->get("settings:$userId");
        if ($res['settings'] == null) return null;

        return ['data' => $res, 'metadata' => null];
    }

    public static function prepOrGetUser(LDRedis $redis, int $userId) {
        $v = self::getUser($redis,$userId);
        if (!is_array($v)) self::prepUser($redis,$userId);
        return $v;
    }

    public static function storeUsers(LDRedis $redis, PaginationVals $pag, array $rows, int $ttl=180) {
        $key = "usersM:{$pag->getString()}";
        if (!$redis->set($key,json_encode($rows),$ttl)) self::storageError('redis', "Couldn't store '$key'.");
    }

    public static function prepUsers(PaginationVals $pag) {
        self::$aPreparedM->add([DataType::User,$pag]);
    }

    public static function unprepUsers(PaginationVals $pag) {
        self::$aPreparedM->remove([DataType::User,$pag]);
    }

    public static function getUsers(LDRedis $redis, PaginationVals $pag) {
        $data = $redis->get("usersM:{$pag->getString()}");
        if ($data == null) return null;
        return json_decode($data,true);
    }

    public static function prepOrGetUsers(LDRedis $redis, PaginationVals $pag) {
        $v = self::getUsers($redis, $pag);
        if ($v == null) self::prepUsers($pag);
        return $v;
    }

    private static function execUsers(LDPDO $pdo, LDRedis $redis) {
        // Fetch multiple
        $pags = self::$aDatas['usersM'];
        foreach ($pags as $pag) {
            $date = new \DateTimeImmutable('now');
            DatabaseUtils::pagRequest($pdo, 'users', '', $pag, 'id',
                fn($row) => base64_encode($row['id']),
                fn($curs) => base64_decode($curs),
                function($row) use($date,$redis) {
                    self::unprepUser($row['data']['id']);
                    self::storeUser($redis,$row['data'],$date);
                },
                function($rows) use($pag,$redis) {
                    self::unprepUsers($pag);
                    self::storeUsers($redis,$pag,$rows);
                },
                'id,roles,name,registration_date,settings'
            );
        }
        self::$aDatas['usersM'] = [];

        // Fetch individual ids
        $userIds = self::$aDatas['users'];
        if (count($userIds) > 0) {
            $sqlWhere = '';
            foreach ($userIds as $userId) {
                if ($sqlWhere != '') $sqlWhere .= ' OR ';
                $sqlWhere .= "id=$userId";
                self::unprepUser($userId);
            }
            $stmt = $pdo->query("SELECT id,roles,name,registration_date,settings FROM users WHERE $sqlWhere");
            $date = new \DateTimeImmutable('now');
            while ($userRow = $stmt->fetch()) self::storeUser($redis,$userRow,$date);
        }
        self::$aDatas['users'] = [];
    }

    /** Websocket **/

    public static function storeConnInfo(\Swoole\WebSocket\Server $server, Request $request):bool {
        $fd = $request->fd;
        $ci = $server->getClientInfo($fd);
        if ($ci == null) return false;
        $a = [
            'fd' => $fd,
            'ip' => $ci['remote_ip'],
            'port' => $ci['remote_port'],
            'connect_time' => $ci['connect_time'],
            'dispatch_time' => $ci['last_dispatch_time'],
            'sid' => $request->cookie['sid']??'',
            'is_event_triggerer' => ($request->get['pass']??null) === $_SERVER['LD_WEBSOCKET_PRIVATE_KEY']
        ];

        go(function() use($fd,$ci,$request) {
            try {
                $userId = 'anon';
                if (isset($request->cookie['sid'])) {
                    $dbName = (bool)$_SERVER['LD_TEST'] ? $_SERVER['LD_TEST_DB_NAME'] : $_SERVER['LD_DB_NAME'];
                    $conn = new \PDO("mysql:host={$_SERVER['LD_DB_HOST']};dbname={$dbName}", $_SERVER['LD_DB_USER'], $_SERVER['LD_DB_PWD']);
                    $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

                    $stmt = $conn->prepare('SELECT * FROM connections WHERE session_id=? LIMIT 1');
                    $stmt->execute([$request->cookie['sid']]);
                    $row = $stmt->fetch();
                    if (is_array($row)) $userId = $row['user_id'];
                }
                Logger::log(LogLevel::INFO, "Cache - Connection", "Connection $fd,{$ci['connect_time']} from $userId");
            } catch (\Throwable $t) {
                Logger::log(LogLevel::ERROR, "Cache - Connection", "Connection $fd,{$ci['connect_time']} auth check failed");
                Logger::logThrowable($t);
            }
        });

        if (!self::$t_conns->set($fd,$a)) { self::storageError('t_conns',"Couldn't store connection in t_conns."); return false; }

        return true;
    }

    public static function removeConnInfo(int $fd) {
        return self::$t_conns->delete($fd);
    }

    public static function getConnInfo(int $fd) {
        return self::$t_conns->get($fd);
    }

    public static function getConnInfos():Table {
        return self::$t_conns;
    }

    public static function storeSubscription(IWSContext $context, string $json) {
        $subRequest = $context->subRequest;
        $connId = "{$context->connInfo['fd']}:{$context->connInfo['connect_time']}";

        switch ($subRequest->name) {
            case 'listenToShoutings':
                $redis = $context->getLDRedis();
                $key = "subscription:{$subRequest->name}:$connId";
                $res = $redis->set($key,$json);
                $redis->toPool();
                if (!$res) self::storageError('redis', "Couldn't store $key in redis.");
                return true;
        }

        Logger::log(LogLevel::ERROR, 'WS - Cache', "Subscription '{$subRequest->name}' not found.");
        return false;
    }

    /**
     * 1 = found and deleted ; 0 = valid subscription name but nothing found ; -1 = not a valid subscription name
     **/
    public static function removeSubscription(IWSContext $context, string $subName, mixed $subData=null):int {
        $connId = "{$context->connInfo['fd']}:{$context->connInfo['connect_time']}";

        switch ($subName) {
            case 'listenToShoutings':
                $redis = $context->getLDRedis();
                $key = "subscription:$subName:$connId";
                $res = $redis->del($key);
                $redis->toPool();
                return $res > 0 ? 1 : 0;
        }

        return -1;
    }

    /** Utils **/

    public static function resetCache(LDRedis $redis):array {
        $nRedis = $redis->delM('*');
        if (!is_int($nRedis)) $nRedis = -1;
        $nTables = 0;
        $nTables += self::emptyTable(self::$t_users);
        self::init2();
        return ['nRedis' => $nRedis, 'nTables' => $nTables];
    }

    private static function emptyTable(Table $table):int {
        $keys = [];
        $n = 0;
        while($table->count() > 0) {
            foreach ($table as $row) {
                $keys[] = $table->key($row);
                if (count($keys) > 10000) break;
            }
            foreach ($keys as $key) { $table->del($key); $n++; }
        }
        return $n;
    }

    private static function storageError(string $storageName, string $text) {
        Logger::log(LogLevel::FATAL, 'Cache', "storage: '$storageName' - $text");
        throw new \Exception('Storage error.');
    }
}
?>