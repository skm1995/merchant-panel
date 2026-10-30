# Production

The demo runs on one Oracle Cloud Always Free server: an ARM virtual machine (Ampere A1,
4 cores, 24 GB of memory, Ubuntu 24.04) running [`compose.prod.yaml`](../../compose.prod.yaml).
GitHub Actions deploys every green commit on `master`; nobody changes the server by hand.

## Addresses

| Address | Served by | What |
|---|---|---|
| `https://szymonkozlowski.is-a.dev` | GitHub Pages, from the `skm1995.github.io` repository | The personal site that introduces the project |
| `https://demo.szymonkozlowski.is-a.dev` | Caddy on the server | The merchant panel and the API |
| `https://mail.szymonkozlowski.is-a.dev` | Caddy on the server | The demo's tools behind the user `demo` and a password: Mailpit, and Kibana under `/kibana/` |

The domains are free subdomains from [is-a.dev](https://is-a.dev), registered through a pull
request to its repository: the site's name points to GitHub Pages with a CNAME record, and
`demo` and `mail` point to the server with A records. Caddy obtains and renews certificates
from Let's Encrypt for both server names.

The shared demo account (`demo@example.com`, password in
[`docker/env/demo.env`](../../docker/env/demo.env)) has merchant access, so visitors can try
the panel. Deployment creates it once and leaves it alone afterwards.

## What runs

A host-level Caddy reverse proxy terminates TLS and forwards incoming traffic for
`demo.${BASE_DOMAIN}` and `mail.${BASE_DOMAIN}` to the containerized Caddy on `127.0.0.1:8001`.
MySQL also listens on the server's loopback
address, `127.0.0.1:3306`, for a database client through an SSH tunnel (see
[Database access](#database-access)); Redis, RabbitMQ, Mailpit, Elasticsearch, and Kibana are
reachable only on the Compose network. Elasticsearch gets a 2 GB heap within a 3 GB limit
and Kibana 1.5 GB, so with MySQL's buffer pool the server keeps several gigabytes free;
[`provision.sh`](../../docker/server/provision.sh) raises `vm.max_map_count`, which
Elasticsearch needs. The images contain the code: PHP runs without Xdebug and with OPcache
that never rechecks files, and the worker, the outbox relay, and the maintenance commands run
as `www-data`. The `simulator` container runs the [Black Friday simulations](../flows/black-friday.md)
started in the panel. See [System](../architecture/system.md) for every container.

MySQL gets an 8 GB buffer pool, so the sales history and its indexes stay in memory, a 2 GB
redo log for fast bulk inserts, and no binary log, which only replicas, point-in-time
recovery, or change data capture would need; PHP-FPM may run up to 48 requests in parallel
([`fpm-pool.conf`](../../docker/php/fpm-pool.conf)) instead of the image's default of 5.

The Compose project keeps the repository's earlier name, `vanilla-php-playground`, because
the volumes holding the database and the other data are named after it.

## Database access

A database client, such as PhpStorm, reaches the production database through an SSH tunnel:
SSH to the server with the deploy key, then MySQL at `127.0.0.1:3306`, database `app`, user
`app` with the `MYSQL_PASSWORD` secret. Nothing else can reach that port: it listens on the
loopback address only, and the firewall and Oracle's security list allow just 80 and 443 from
outside. The tunnel gives the application's own privileges, so treat writes as changes to the
live demo.

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
server's `.env` with mode 600; [CI/CD](ci-cd.md) lists them. `compose.prod.yaml` requires
each one, so a deployment missing a secret fails to start instead of falling back to the
public development value in `docker/env/`. They never appear in the
repository, in logs, or in workflow output, and gitleaks checks every commit for secrets.

## Nightly maintenance

| Time | Job | What |
|---|---|---|
| 03:15 | [`backup-database.sh`](../../docker/server/backup-database.sh) | Dumps the database into `~/backups` (mode 600), keeps a week of daily dumps there, and uploads each dump to [Object Storage](#off-site-backups) |
| 03:45 | [`bin/purge-unconfirmed-accounts.php`](../../backend/bin/purge-unconfirmed-accounts.php) | Deletes accounts left unconfirmed for a week |

Both write to the system log under the `merchant-panel` tag:
`journalctl -t merchant-panel` on the server.

## Off-site backups

Every dump is also uploaded to a private bucket in Oracle Cloud Object Storage, which the
Always Free tier includes, so the backups survive losing the server. The server holds only a
pre-authenticated request that may write objects into the bucket: it cannot list, read, or
delete the earlier backups, so a compromised server cannot destroy them. The bucket's
lifecycle rule deletes dumps after 7 days, so the bucket stays well within the Always Free
tier's 10 GB limit. Setting it up once, in the Oracle Cloud console:

1. **Identity & Security → Policies**, in the root compartment: create a policy with the
   statement `Allow service objectstorage-<region> to manage object-family in tenancy`, with
   the server's region identifier, such as `eu-frankfurt-1`. Lifecycle rules need it.
2. **Storage → Buckets → Create Bucket:** name `merchant-panel-backups`, the Standard tier,
   Oracle-managed encryption.
3. In the bucket, **Lifecycle Policy Rules → Create Rule:** name `delete-after-7-days`, action
   Delete, after 7 days.
4. In the bucket, **Pre-Authenticated Requests → Create Pre-Authenticated Request:** target
   Bucket, access type **Permit object writes**, and an expiration date a year or more ahead.
   Copy the URL, which ends with `/o/`; it is shown only once.
5. Store the URL as the `BACKUP_UPLOAD_URL` secret in GitHub; the next deployment writes it to
   the server's `.env`.

The [restore runbook](runbooks.md#restore-the-database-from-a-backup) covers dumps from the
bucket, and a monthly check restores the latest one into a scratch database.

## Limitations

- One server, so a hardware or account problem takes the demo down; there is no failover.
- A failed backup or upload shows only in the system log, not in the alerts.
- The pre-authenticated request expires on the date chosen when it was created; before then,
  [rotate it](runbooks.md#rotate-a-secret) like any secret.
- The dumps hold account emails and password hashes; Oracle encrypts them at rest, but they
  are not encrypted before they leave the server.
- The nightly dump includes the generated sales history, which makes each dump a few hundred
  megabytes larger. Dumping it separately would need several `mysqldump` runs that do not
  see the same moment, so the history stays in the one consistent dump.
- Oracle may reclaim an Always Free machine that stays idle for a long time.
