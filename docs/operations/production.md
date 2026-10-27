# Production

The demo runs on one Oracle Cloud Always Free server: an ARM virtual machine (Ampere A1,
4 cores, 24 GB of memory, Ubuntu 24.04) running [`compose.prod.yaml`](../../compose.prod.yaml).
GitHub Actions deploys every green commit on `master`; nobody changes the server by hand.

## Addresses

| Address | Served by | What |
|---|---|---|
| `https://szymonkozlowski.is-a.dev` | GitHub Pages, from the `skm1995.github.io` repository | The personal site that introduces the project |
| `https://demo.szymonkozlowski.is-a.dev` | Caddy on the server | The merchant panel and the API |
| `https://mail.szymonkozlowski.is-a.dev` | Caddy on the server | Mailpit, behind the user `demo` and a password |

The domains are free subdomains from [is-a.dev](https://is-a.dev), registered through a pull
request to its repository: the site's name points to GitHub Pages with a CNAME record, and
`demo` and `mail` point to the server with A records. Caddy obtains and renews certificates
from Let's Encrypt for both server names.

The shared demo account (`demo@example.com`, password in
[`docker/env/demo.env`](../../docker/env/demo.env)) has merchant access, so visitors can try
the panel. Deployment creates it once and leaves it alone afterwards.

## What runs

Caddy publishes ports 80 and 443; MySQL, Redis, RabbitMQ, and Mailpit are reachable only on
the Compose network. The images contain the code: PHP runs without Xdebug and with OPcache
that never rechecks files, and the worker, the outbox relay, and the maintenance commands run
as `www-data`. See [System](../architecture/system.md) for every container.

MySQL gets an 8 GB buffer pool, so the sales history and its indexes stay in memory, and a
2 GB redo log for fast bulk inserts; PHP-FPM may run up to 48 requests in parallel
([`fpm-pool.conf`](../../docker/php/fpm-pool.conf)) instead of the image's default of 5.

The Compose project keeps the repository's earlier name, `vanilla-php-playground`, because
the volumes holding the database and the other data are named after it.

## Server setup

[`docker/server/provision.sh`](../../docker/server/provision.sh) runs at the start of every
deployment and changes only what is missing:

- installs Docker and Compose from Ubuntu's packages;
- rotates container logs at 20 MB, keeping five files, so logs cannot fill the disk;
- opens ports 80 and 443 (TCP) and 443 (UDP, for HTTP/3) in the firewall that Oracle's image
  ships, both live and in the rules loaded at boot;
- installs the nightly cron jobs.

Ports 80 and 443 must also be allowed in the Oracle Cloud network's security list. SSH accepts
keys only.

## Secrets

GitHub stores the secrets and passes them to the deploy job, which writes them into the
server's `.env` with mode 600; [CI/CD](ci-cd.md) lists them. They never appear in the
repository, in logs, or in workflow output, and gitleaks checks every commit for secrets.

## Nightly maintenance

| Time | Job | What |
|---|---|---|
| 03:15 | [`backup-database.sh`](../../docker/server/backup-database.sh) | Dumps the database into `~/backups` (mode 600) and keeps a week of daily dumps |
| 03:45 | [`bin/purge-unconfirmed-accounts.php`](../../backend/bin/purge-unconfirmed-accounts.php) | Deletes accounts left unconfirmed for a week |

Both write to the system log under the `merchant-panel` tag:
`journalctl -t merchant-panel` on the server.

## Limitations

- One server, so a hardware or account problem takes the demo down; there is no failover.
- The backups stay on the same server; they protect against mistakes and corrupted data, not
  against losing the server.
- Oracle may reclaim an Always Free machine that stays idle for a long time.
