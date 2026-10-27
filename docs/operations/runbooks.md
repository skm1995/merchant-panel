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

```sh
ls -lt ~/backups
gunzip -c ~/backups/app-<timestamp>.sql.gz | dc exec -T mysql \
    sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

The dump replaces the tables it contains. Take a fresh backup first
(`~/app/docker/server/backup-database.sh`) if the current data may still be needed.

## Retry failed messages

Messages that failed every retry wait in the `failed_messages` queue. Find the cause in the
worker's logs (`dc logs worker`) and fix it first, then:

```sh
dc run --rm --no-deps --user www-data php php bin/retry-failed-messages.php
```

The command acknowledges a message only after it was dispatched again, and reports messages
it can no longer decode, for example after a message class was renamed.

## A deployment failed

Read the failed step in the GitHub Actions log. If the new release does not start, revert the
commit that broke it and push; the pipeline deploys the previous code. On the server,
`dc ps` and `dc logs <service>` show which container is unhealthy and why.

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
