# Runbooks

Step-by-step procedures for situations that need an operator. Commands on the server run in
`~/app`, where deployment places the current release:

```sh
ssh ubuntu@<server> && cd ~/app
alias dc='sudo docker compose -f compose.prod.yaml'
```

## Give someone merchant access

The person registers on the demo and confirms the email (Mailpit shows it). Then:

```sh
dc run --rm --no-deps --user www-data php php bin/grant-merchant.php person@example.com
```

Access applies to their next request; no sign-out is needed. To create a merchant directly,
with a password that the person changes later:

```sh
dc run --rm --no-deps --user www-data -e MERCHANT_PASSWORD='…' php php bin/create-merchant.php person@example.com
```

## Restore the database from a backup

The last week of dumps is on the server; older ones, or all of them after losing the server,
are in the `merchant-panel-backups` bucket: download one in the Oracle Cloud console
(**Storage → Buckets → merchant-panel-backups**, the object's menu, **Download**) and copy it
to the server with `scp`. Then:

```sh
ls -lt ~/backups
gunzip -c ~/backups/app-<timestamp>.sql.gz | dc exec -T mysql \
    sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

The dump replaces the tables it contains. Take a fresh backup first
(`~/app/docker/server/backup-database.sh`) if the current data may still be needed.

## Check that the backups restore

Once a month, restore the latest dump into a scratch database and compare it with the live
one; a backup is only as good as its last restore:

```sh
mysql_root() { dc exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$@"' sh "$@"; }
latest=$(ls -t ~/backups/app-*.sql.gz | head -n 1)
mysql_root -e 'CREATE DATABASE restore_check'
gunzip -c "${latest}" | mysql_root restore_check
mysql_root -e 'SELECT COUNT(*) FROM restore_check.orders; SELECT COUNT(*) FROM app.orders'
mysql_root -e 'DROP DATABASE restore_check'
```

The counts differ only by the orders placed since the dump. In the bucket, the newest object
should be from last night.

## Retry failed messages

Messages that failed every retry wait in the `failed_messages` queue. Find the cause in the
worker's logs (`dc logs worker`) and fix it first, then:

```sh
dc run --rm --no-deps --user www-data php php bin/retry-failed-messages.php
```

The command acknowledges a message only after it was dispatched again, and reports messages
it can no longer decode, for example after a message class was renamed.

## Generate or remove the sales history

The demo can hold three years of generated orders, so the reports run on realistic volume.
Generating 3 million orders takes about 15–20 minutes and about 2.5 GB of disk; run it in
`tmux` or `screen`, since it stops if the SSH session ends:

```sh
dc run --rm --no-deps --user www-data php php bin/generate-sales-history.php            # 3 million
dc run --rm --no-deps --user www-data php php bin/generate-sales-history.php 100000     # a sample
```

It also fills the daily sales summary for every generated day. It refuses to run while
history exists. The history is marked by SKUs starting with
`history-` and idempotency keys starting with `history:`, so removing it never touches real
orders; checkout refuses that prefix from customers. The same command also removes the orders
of Black Friday simulations, marked by `simulation:`. Removal deletes orders in batches, so the demo keeps running, and then rebuilds the
tables with `OPTIMIZE TABLE` to give the disk space back (MySQL keeps the space of deleted
rows otherwise):

```sh
dc run --rm --no-deps --user www-data php php bin/remove-sales-history.php
```

## When the monitor fails

GitHub emails a failed run of the [monitor](observability.md#alerts). Open the run in the
repository's **Actions** tab; its log names the failing check:

- **The panel or the API does not answer:** check the services with `dc ps` and the logs of
  the one that is not healthy with `dc logs --since 30m <service>`; a deployment that is still
  running restarts them for a minute or two.
- **The logs hold errors:** the log lists the latest ones with their request ids; search
  `app.extra.request_id : "<id>"` in Kibana's Discover to see the whole request, including the
  exception's stack trace.
- **The logs cannot be searched:** Kibana or Elasticsearch is down (`dc ps`), or a Kibana
  upgrade changed its console proxy.

Run the monitor again with **Run workflow** to confirm a fix. GitHub disables the schedule
after 60 days without a commit; **Enable workflow** on the Monitor workflow's page turns it
back on.

## A deployment failed

Read the failed step in the GitHub Actions log. A failed `images` job or image pull leaves
the running release as it was. If the new release does not start, roll back: in the
repository's **Actions** tab, open the CI run of the last commit that worked and choose
**Re-run all jobs**. Its images are still in the registry under its commit, so the deploy
only pulls them again. Then revert the broken commit, so the next push does not bring it
back. On the server, `dc ps` and `dc logs <service>` show which container is unhealthy and
why.

## Rotate a secret

1. Set the new value: `gh secret set NAME` in the repository.
2. Rerun the latest deploy job, which writes the new `.env` and recreates the containers.

Some secrets need more:

| Secret | Also do |
|---|---|
| `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | MySQL reads them only when it creates the database. Change the password inside MySQL first (`ALTER USER`), then update the secret. |
| `RABBITMQ_PASSWORD` | The same: change it with `rabbitmqctl change_password` first. |
| `ORDER_ACCESS_SECRET` | Every existing order token stops working; guests lose access to their open orders. |
| `FAKEPAY_WEBHOOK_SECRET`, `QUICKPAY_WEBHOOK_SECRET` | Update the provider at the same time, or its webhooks are rejected. |
| `BACKUP_UPLOAD_URL` | Create a new pre-authenticated request first ([Off-site backups](production.md#off-site-backups)); after the deployment, delete the old one in the bucket. |

## Redis is down

Requests keep working without the product cache and without rate limits, and the logs show
warnings. Signed-in sessions are unavailable, so merchants cannot use the panel. Check
`dc ps redis` and `dc logs redis`; `dc up -d redis` starts it again, and the sessions stored
in its volume return.

## HTTPS stopped working

`dc logs caddy | grep -i certificate` shows certificate errors. A common cause is Let's
Encrypt's rate limit after several failed attempts, for example while a domain or the firewall
was misconfigured; Caddy retries on its own once the limit expires, usually within an hour.
Repeated restarts only extend the wait.

## Rebuild the server from scratch

Create a new Ubuntu machine with the deploy key, allow ports 80 and 443 in the network's
security list, point the `demo` and `mail` records at its address, update `DEPLOY_HOST` and
`DEPLOY_KNOWN_HOSTS` in GitHub, and rerun the deploy job. Provisioning installs everything;
restore the latest backup afterwards.
