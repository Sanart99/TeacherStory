{ pkgs ? import (fetchTarball "https://github.com/NixOS/nixpkgs/archive/8b5b6723aca5a51edf075936439d9cd3947b7b2c.tar.gz") {} }:
  let 
    swooleExt = pkgs.php83Extensions.swoole.overrideAttrs (oldAttrs: rec {
      configureFlags = oldAttrs.configureFlags or [] ++ [
        "--enable-openssl=yes"
      ];

      buildInputs = (oldAttrs.buildInputs or []) ++ [
        pkgs.openssl.dev
      ];

      installPhase = ''
        mkdir -p $out/bin
        cp -r . $out/bin
      '';
    });

    php = pkgs.php83.withExtensions ({ enabled, all }: [
      all.mysqli all.redis all.mbstring all.curl all.zip all.pdo all.pdo_mysql all.openssl all.zlib all.filter all.gmp all.zstd all.fileinfo all.bcmath all.gd
    ]);
  in
    pkgs.mkShellNoCC {
      name = "teacherstory";
      packages = [ 
        pkgs.cacert
        php
        pkgs.openssl_3_3
        pkgs.redis
        pkgs.mariadb
        pkgs.ffmpeg_4
        pkgs.imagemagick
        pkgs.curl
        pkgs.unzip
        pkgs.gnused
        pkgs.coreutils
        pkgs.which
        pkgs.php83Packages.composer
        pkgs.brotli
        swooleExt
      ];

      shellHook =  ''
        echo "TeacherStory nix-shell init script: start"

        TMP="$TMPDIR/temp-teacherstory"
        PROJ_DIR="${builtins.toString ./.}"
        MARIADB_INST_DIR="${pkgs.mariadb}"
        PHP_EXT_SWOOLE_FILE="${swooleExt}/bin/.libs/swoole.so"
        PROJ_CONFIGS_DIR="$PROJ_DIR/.serv/configs"
        TMP_CONFIGS_DIR="$TMP/configs"
        TMP_LOGS_DIR="$TMP/logs"
        MARIADB_DIR="$TMP/mariadb"
        REDIS_DIR="$TMP/redis"
        MARIADB_CNF="$TMP_CONFIGS_DIR/mariadb.cnf"
        MARIADB_SOCKET="$TMP/mariadb/mariadb.sock"

        mkdir -p "$TMP_CONFIGS_DIR"
        cd "$PROJ_DIR"

        # Make and place config files
        if [ ! -d "$TMP_CONFIGS_DIR" ]; then
          mkdir -p "$TMP_CONFIGS_DIR"
        fi
        if [ ! -d "$TMP_LOGS_DIR" ]; then
          mkdir -p "$TMP_LOGS_DIR"
        fi
        bash "$PROJ_CONFIGS_DIR/nix_makeFiles.sh" "$PROJ_DIR" "$TMP"
        cp "$PROJ_CONFIGS_DIR/mariadb.cnf" "$MARIADB_CNF"
        cp "$PROJ_CONFIGS_DIR/php.ini" "$TMP_CONFIGS_DIR/php.ini"
        cp "$PROJ_CONFIGS_DIR/redis.conf" "$TMP_CONFIGS_DIR/redis.conf"
        chmod 764 "$TMP_CONFIGS_DIR/php.ini"
        chmod 640 "$TMP_CONFIGS_DIR/redis.conf"
        chmod 644 "$MARIADB_CNF"
        chown redis:redis "$TMP_CONFIGS_DIR/redis.conf"
        echo "Files location: $TMP"

        # Some additions to configs
        echo "extension=$PHP_EXT_SWOOLE_FILE" >> "$TMP_CONFIGS_DIR/php.ini"

        # Install MariaDB?
        if [ ! -d "$MARIADB_DIR/data" ]; then
          mkdir -p "$MARIADB_DIR/data"
          mariadb-install-db --defaults-file="$MARIADB_CNF" --basedir="$MARIADB_INST_DIR" --auth-root-authentication-method=normal --verbose
        fi

        # Launch MariaDB
        mariadbd --defaults-file="$MARIADB_CNF" --user=root 2> "$TMP/mariadb.log" &
        echo "MariaDB socket: $MARIADB_SOCKET"
        until mariadb --user=root --socket="$MARIADB_SOCKET" -e "SELECT 1;" >/dev/null 2>&1; do
          echo "Waiting for MariaDB..."
          sleep 1
        done
        echo "MariaDB launched."

        # Setup database?
        s1=$(mariadb --user=root --socket="$MARIADB_SOCKET" -e "SELECT 1 FROM mysql.user WHERE Host='%' AND User='root'" --silent | head -n 1)
        if [ "$s1" != "1" ]; then
          echo "Setting up mariadb users...";
          mariadb --user=root --socket="$MARIADB_SOCKET" -e "
            CREATE USER 'root'@'%' REQUIRE SSL;
            GRANT ALL ON *.* TO 'root'@'%' WITH GRANT OPTION;
            FLUSH PRIVILEGES;
          "
        fi
        s2=$(mariadb --user=root --socket="$MARIADB_SOCKET" -e "SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='teacherstory'" --silent | head -n 1)
        if [ "$s2" != "1" ]; then
          echo "Setting up databases...";
          mariadb --user=root --socket="$MARIADB_SOCKET" -e "
            CREATE DATABASE teacherstory CHARACTER SET='utf8mb4' COLLATE='utf8mb4_unicode_ci';
            CREATE DATABASE test_teacherstory CHARACTER SET='utf8mb4' COLLATE='utf8mb4_unicode_ci';
            USE test_teacherstory;
            source $PROJ_DIR/.serv/db/create_tables.sql;
            source $PROJ_DIR/.serv/db/init_tables.sql;
            USE teacherstory;
            source $PROJ_DIR/.serv/db/create_tables.sql;
            source $PROJ_DIR/.serv/db/init_tables.sql;
          "
        fi

        # Launch Redis
        if [ ! -d "$REDIS_DIR" ]; then
          mkdir -p "$REDIS_DIR"
        fi
        echo "Launching Redis..."
        redis-server "$TMP_CONFIGS_DIR/redis.conf"

        # EXIT trap
        finish()
        {
          MARIADB_PID=$(head -n 1 "$PROJ_DIR/.serv/mariadb.pid")
          echo "Trying to stop MariaDB with pid \"$MARIADB_PID\""
          mariadb-admin -u root --socket="$MARIADB_SOCKET" shutdown

          REDIS_PID=$(head -n 1 "$REDIS_DIR/redis-server.pid")
          echo "Trying to stop Redis with pid \"$REDIS_PID\""
          kill -15 "$REDIS_PID"
        }
        trap finish EXIT

        echo "TeacherStory nix-shell init script: end"
      '';
    }