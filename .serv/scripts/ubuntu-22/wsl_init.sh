if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "Not running as root"
    exit
fi

apt -y update
apt -y install ca-certificates apt-transport-https software-properties-common
add-apt-repository -y ppa:ondrej/php
apt -y update
apt -y install unzip curl brotli imagemagick ffmpeg

# Install PHP 8.3, Swoole, and other dependencies
apt -y install php8.3 php8.3-mysql php8.3-curl php8.3-mbstring php8.3-zip php8.3-xml php8.3-swoole php8.3-gmp php8.3-zstd php8.3-bcmath php8.3-gd

# Install Redis
apt -y install redis-server=5:6.* php8.3-redis

# Disable and stops Apache
systemctl disable apache2
systemctl stop apache2