if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "Not running as root"
    exit
fi

cat <<- 'EOF' > /etc/wsl.conf
[boot]
systemd=true

[automount]
enabled = true
EOF

apt -y update
apt -y install unzip curl brotli imagemagick ffmpeg bash xz-utils ca-certificates apt-transport-https software-properties-common

curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
apt -y update

# Install PHP 8.3, Swoole, and other dependencies
apt -y install php8.3 php8.3-mysql php8.3-curl php8.3-mbstring php8.3-zip php8.3-xml php8.3-swoole php8.3-gmp php8.3-zstd php8.3-bcmath php8.3-gd

# Install Redis
apt -y install redis-server=5:7.0.* php8.3-redis