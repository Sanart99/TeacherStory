<?php
namespace TeacherStory\Event;

use LDLib\Event\EventResolver as ER;
use TeacherStory\DataFetcher\DataFetcher;

class EventResolver {
    public static function init() {
        ER::init(function($eventName, $eventData) {
            switch ($eventName) {
                case 'shout':
                    ER::connIteration('subscription:listenToShoutings:*',function ($data,$conn) {
                        $json = json_decode($data,true);
                        if (isset($json['data'][$json['_alias']??'listenToShoutings']['message'])) $json['data'][$json['_alias']??'listenToShoutings']['message'] = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
                        if (!@\LDLib\Server\WSServer::$server->push($conn['fd'], json_encode($json))) DataFetcher::removeConnInfo($conn['fd']);
                    });
                    return true;
            }
        });
    }
}
EventResolver::init();
?>