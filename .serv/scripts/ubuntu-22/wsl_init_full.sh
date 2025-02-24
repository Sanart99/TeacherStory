echo "Continue only if you checked the script file and finds no problem with it. Continue? (y)"
read yn
if [ "$yn" == "${yn#[Yy]}" ]; then
    echo "Cancelled."
    exit
fi
if [[ $(/usr/bin/id -u) -ne 0 ]]; then
    echo "Not running as root"
    exit
fi

dir=$(dirname "$(readlink -f "$0")")
projDir=$(readlink -f "$dir/../../..")
sh "$dir/wsl_init.sh"
bash "$projDir/.serv/configs/ubuntu22_makeFiles.sh" "$projDir"
bash "$projDir/.serv/configs/ubuntu22_placeFiles.sh"
systemctl restart redis
systemctl status redis