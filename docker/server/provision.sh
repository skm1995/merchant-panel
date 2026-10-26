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
