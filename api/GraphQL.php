<?php
namespace TeacherStory;

use GraphQL\Type\Schema;
use TeacherStory\Schema\Generator;
use TeacherStory\Schema\Types;

class GraphQL {
    public static function init(bool $wsMode=false) {
        $schema = new Schema([
            'query' => Types::Query(),
            'mutation' => ($wsMode ? Types::WebSocketMode_Mutation() : Types::Mutation()),
            'subscription' => Types::Subscription(),
            'typeLoader' => function(string $name) {
                if (str_ends_with($name,'sConnection')) return Types::getConnectionObjectType(preg_replace('/sConnection$/','',$name));
                if (str_ends_with($name,'Edge')) return Types::getEdgeObjectType(preg_replace('/Edge$/','',$name));
                if ($name !== 'Operation' && str_starts_with($name,'Operation')) return Types::getOperationObjectType(preg_replace('/^Operation/','',$name));
                if (method_exists(Types::class,$name)) return Types::$name();
                return null;
            },
            'types' => []
        ]);

        \LDLib\GraphQL\GraphQL::init($schema,Generator::class);
    }
}
?>