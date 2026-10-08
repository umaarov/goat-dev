#!/usr/bin/env bash
# one-time, idempotent host setup for an Ubuntu 24.04 server: bash server-setup.sh
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

apt-get update -qq
apt-get -y -qq -o Dpkg::Options::=--force-confold upgrade
apt-get -y -qq install ca-certificates curl gnupg ufw fail2ban unattended-upgrades rsync jq

if ! command -v docker >/dev/null; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get -y -qq install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

cat > /etc/docker/daemon.json <<'JSON'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" },
  "live-restore": true,
  "no-new-privileges": true
}
JSON
systemctl enable --now docker
systemctl reload docker || systemctl restart docker

# docker publishes ports around ufw, so only 80/443 are ever published by the compose file
ufw --force reset >/dev/null
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

cat > /etc/fail2ban/jail.d/goat.local <<'JAIL'
[sshd]
enabled = true
maxretry = 4
findtime = 10m
bantime = 1h
JAIL
systemctl enable --now fail2ban
systemctl restart fail2ban

cat > /etc/apt/apt.conf.d/20auto-upgrades <<'APT'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
APT
systemctl enable --now unattended-upgrades

cat > /etc/sysctl.d/99-goat.conf <<'SYS'
vm.overcommit_memory = 1
net.core.somaxconn = 1024
net.ipv4.tcp_syncookies = 1
SYS
sysctl --system >/dev/null

cat > /etc/logrotate.d/goat <<'LOGROTATE'
/opt/goat/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
LOGROTATE

timedatectl set-timezone UTC
mkdir -p /opt/goat
echo "setup done: $(docker --version)"
