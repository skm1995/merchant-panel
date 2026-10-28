#!/usr/bin/env bash
# Prepares an Ubuntu server for compose.prod.yaml. bin/deploy runs it before every
# deployment, so each step changes nothing when it is already done.

set -euo pipefail

if ! command -v docker >/dev/null; then
    sudo apt-get update -q
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -q docker.io docker-compose-v2
fi

# Rotate container logs so they cannot fill the disk.
daemon_config='{"log-driver": "json-file", "log-opts": {"max-size": "20m", "max-file": "5"}}'
if [ "$(sudo cat /etc/docker/daemon.json 2>/dev/null)" != "${daemon_config}" ]; then
    echo "${daemon_config}" | sudo tee /etc/docker/daemon.json >/dev/null
    sudo systemctl restart docker
fi

# Oracle's Ubuntu image rejects inbound traffic other than SSH. Open HTTP, HTTPS, and HTTP/3
# now and in the saved rules that load at boot, ahead of the final REJECT rule.
rules_file=/etc/iptables/rules.v4
for rule in "-p tcp -m tcp --dport 80" "-p tcp -m tcp --dport 443" "-p udp -m udp --dport 443"; do
    # shellcheck disable=SC2086 # the rule is a list of iptables arguments
    sudo iptables -C INPUT ${rule} -j ACCEPT 2>/dev/null || sudo iptables -I INPUT 1 ${rule} -j ACCEPT

    if [ -f "${rules_file}" ] && ! sudo grep -qxF -- "-A INPUT ${rule} -j ACCEPT" "${rules_file}"; then
        sudo sed -i "0,/^-A INPUT -j REJECT/s//-A INPUT ${rule} -j ACCEPT\n&/" "${rules_file}"
    fi
done

# Elasticsearch maps its index files into memory and needs more memory map areas than the
# kernel allows by default, now and after a reboot.
if [ "$(sysctl -n vm.max_map_count)" -lt 262144 ]; then
    echo "vm.max_map_count=262144" | sudo tee /etc/sysctl.d/99-elasticsearch.conf >/dev/null
    sudo sysctl --quiet --load /etc/sysctl.d/99-elasticsearch.conf
fi

# Daily maintenance: a database backup, then deleting accounts left unconfirmed for a week.
# Output goes to the system log under the merchant-panel tag. The cron file of the project's
# earlier name is removed, so the jobs never run twice.
sudo rm -f /etc/cron.d/vanilla-php-playground
cron_file=/etc/cron.d/merchant-panel
cron_jobs="SHELL=/bin/bash
15 3 * * * ${USER} ${HOME}/app/docker/server/backup-database.sh 2>&1 | logger -t merchant-panel
45 3 * * * ${USER} cd ${HOME}/app && sudo docker compose -f compose.prod.yaml run --rm --no-deps --user www-data php php bin/purge-unconfirmed-accounts.php 2>&1 | logger -t merchant-panel"
if [ "$(sudo cat "${cron_file}" 2>/dev/null)" != "${cron_jobs}" ]; then
    echo "${cron_jobs}" | sudo tee "${cron_file}" >/dev/null
fi
