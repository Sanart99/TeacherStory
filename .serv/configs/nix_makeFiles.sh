# For all params: escape those characters: & \ #

# Paths
dir=$(dirname "$(readlink -f "$0")")
projDir=$(readlink -f "$1")
tmpDir=$(readlink -f "$2")
curl_cainfo=''
log_dir="$projDir/.serv/logs"
tls_cert_path="$projDir/.serv/SSLCert.crt"
tls_key_path="$projDir/.serv/SSLKey.key"
redis_working_dir="$tmpDir/redis"

# Systemd service
http_server_path="$projDir/startHTTPServer.php"
websocket_server_path="$projDir/startWebSocketServer.php"

# MariaDB
db_server_audit_file_path="$tmpDir/server_audit.log"
# MariaDB - Linux
mariadb_datadir="$tmpDir/mariadb/data"
mariadb_socket="$tmpDir/mariadb/mariadb.sock"
# MariaDB - Windows
win_datadir='C:/Program Files/MariaDB 11.2/data'
win_plugin_dir='C:/Program Files/MariaDB 11.2/lib/plugin'

# Do replaces
sed \
    -e "s#\[\[curl.cainfo\]\]#\"$curl_cainfo\"#g" \
    -e "s#\[\[error_log\]\]#\"$log_dir/php-error.log\"#g" \
    "$dir/php.ini-example" > "$dir/php.ini"

sed \
    -e "s#\[\[tls-cert-file\]\]#\"$tls_cert_path\"#g" \
    -e "s#\[\[tls-key-file\]\]#\"$tls_key_path\"#g" \
    -e "s#\[\[redis-working-dir\]\]#\"$redis_working_dir\"#g" \
    -e "s#\[\[redis-pid-file\]\]#\"$redis_working_dir/redis-server.pid\"#g" \
    -e "s#\[\[redis-log-file\]\]#\"$log_dir/redis-server.log\"#g" \
    "$dir/redis.conf-example" > "$dir/redis.conf"

sed \
    -e "s#\[\[datadir\]\]#\"$win_datadir\"#g" \
    -e "s#\[\[plugin-dir\]\]#\"$win_plugin_dir\"#g" \
    "$dir/my.ini-example" > "$dir/my.ini"

sed \
    -e "s#\[\[datadir\]\]#\"$mariadb_datadir\"#g" \
    -e "s#\[\[tls-cert-file\]\]#\"$tls_cert_path\"#g" \
    -e "s#\[\[tls-key-file\]\]#\"$tls_key_path\"#g" \
    -e "s#\[\[db-pid-file\]\]#\"$projDir/.serv/mariadb.pid\"#g" \
    -e "s#\[\[db-socket\]\]#\"$mariadb_socket\"#g" \
    -e "s#\[\[db-server-audit-file-path\]\]#\"$db_server_audit_file_path\"#g" \
    "$dir/mariadb.cnf-example" > "$dir/mariadb.cnf"

sed \
    -e "s#\[\[execPath\]\]#$http_server_path#g" \
    "$dir/php-http-server.service-example" > "$dir/php-http-server.service"

sed \
    -e "s#\[\[execPath\]\]#$websocket_server_path#g" \
    "$dir/php-websocket-server.service-example" > "$dir/php-websocket-server.service"