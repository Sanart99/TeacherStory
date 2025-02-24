dir=$(dirname "$(readlink -f "$0")")
cp -f "$dir/php.ini" /etc/php/8.3/cli/php.ini
cp -f "$dir/redis.conf" /etc/redis/redis.conf
cp -f "$dir/mariadb.cnf" /etc/mysql/mariadb.cnf
cp -f "$dir/php-http-server.service" /etc/systemd/system/php-http-server.service
cp -f "$dir/php-websocket-server.service" /etc/systemd/system/php-websocket-server.service
echo "manually copy my.ini if needed (mariadb windows)"