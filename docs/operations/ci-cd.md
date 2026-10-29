# CI/CD

One script, [`bin/ci`](../../bin/ci), runs every check against the Docker Compose stack, the
same way on a laptop and on GitHub. Every push to `master` that passes it is deployed to the
server automatically.

## What `bin/ci` runs

1. **Secret scanning:** gitleaks over the Git history, with
   [`.gitleaks.toml`](../../.gitleaks.toml) allowing the development defaults that are public
   on purpose.
2. **Build:** the images, Composer and npm dependencies, and the production build of the
   panel; then the test stack starts and waits until every service is healthy.
3. **Backend and frontend at the same time**, each writing its own log that is printed when
   both are done:
   - backend: `composer validate`, `composer audit --no-dev`, OpenAPI validation, unit and
     functional tests with coverage, the check that the functional tests called every API
     operation, PHPStan, Deptrac, and the code style check;
   - frontend: `npm audit --omit=dev`, the generated API types are up to date, `tsc`, ESLint,
     Prettier, and the Vitest suite with its coverage minimums.
4. **End to end:** the demo merchant is recreated in the test database, and Playwright runs.
5. **Coverage:** the coverage of all backend suites, including the requests of the
   functional and end-to-end tests, is merged and checked against its minimum
   ([Testing](testing.md#coverage)).

Run it before every push; it takes a little over a minute when the images are built, and
about four minutes on GitHub, where most of the time goes to building them.

## GitHub Actions

[`.github/workflows/ci.yaml`](../../.github/workflows/ci.yaml) has three jobs and no service
configuration of its own:

- `quality` runs `bin/ci` on every push and pull request, prints the service logs when it
  fails, adds the coverage to the run's summary, and keeps the coverage reports as an
  artifact;
- `images`, for pushes to `master` and at the same time as `quality`, runs
  [`bin/publish-images`](../../bin/publish-images) on an ARM runner, the server's
  architecture: it builds the production PHP and Caddy images and pushes them to the GitHub
  Container Registry as `ghcr.io/<repository>/php` and `/caddy`, tagged with the commit;
- `deploy` runs after both succeed, only for pushes to `master`, one at a time, and calls
  [`bin/deploy`](../../bin/deploy) with the secrets and variables below
  ([0029](../decisions/0029-images-built-in-ci.md)).

A second workflow, [`monitor.yaml`](../../.github/workflows/monitor.yaml), checks the demo
every 15 minutes with [`bin/monitor`](../../bin/monitor), and GitHub emails a failed run
([Alerts](observability.md#alerts)).

The workflows' tokens can only read the repository, except that the `images` job may write
packages and the `deploy` job read them. The deploy job hands its token to the server only to
pull the images and logs the server out right after; the token expires with the job.

The audits fail CI when a dependency that reaches the server has a known vulnerability, so a
newly published advisory stops deployments until the dependency is updated.
[Dependabot](../../.github/dependabot.yml) proposes updates every week as pull requests, which
run the same CI: Composer and npm packages, grouped by ecosystem for minor and patch versions,
the base images of the Dockerfiles and Compose files, and the GitHub Actions.

## Deployment

`bin/deploy` needs no manual step on the server:

1. checks that every required secret and variable is set;
2. runs [`docker/server/provision.sh`](../../docker/server/provision.sh) over SSH, which
   installs Docker, log rotation, firewall rules, and the nightly cron jobs, and does nothing
   when they are already in place;
3. uploads the commit with `git archive` and writes the server's `.env` with mode 600,
   including a new random `LOAD_TEST_KEY` for the [Black Friday simulator](../flows/black-friday.md);
4. pulls the commit's images on the server, logged in to the registry with the job's token,
   before it touches the running release, then starts
   [`compose.prod.yaml`](../../compose.prod.yaml), waiting for every service to be healthy,
   and removes the images no container uses;
5. adds the demo catalog, prepares Elasticsearch and Kibana (`kibana-setup`), and creates the
   shared demo account if it is missing;
6. checks that `https://demo.<domain>/api/v1/products` answers.

A failed step fails the job with its output. A failed build or pull stops before the running
release is touched. Compose replaces the containers before checking their health, so a
release that pulls but does not start leaves the demo down until a working commit is
deployed; [Runbooks](runbooks.md#a-deployment-failed) describe how to roll back.

| GitHub setting | Kind | Purpose |
|---|---|---|
| `DEPLOY_HOST`, `DEPLOY_USER` | variables | server address and SSH user |
| `BASE_DOMAIN` | variable | the domain under which `demo.` and `mail.` live |
| `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` | secrets | the deploy key and the server's host key, so the connection is verified |
| `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `RABBITMQ_PASSWORD` | secrets | service credentials |
| `FAKEPAY_WEBHOOK_SECRET`, `QUICKPAY_WEBHOOK_SECRET` | secrets | webhook signing secrets |
| `REPORTS_API_KEY`, `ORDER_ACCESS_SECRET` | secrets | the reports API key and the order token secret |
| `MAILPIT_PASSWORD` | secret | the password for the Mailpit page and Kibana, also used by the [monitor](observability.md#alerts) |
| `BACKUP_UPLOAD_URL` | secret | the pre-authenticated request that uploads the nightly dumps to [Object Storage](production.md#off-site-backups) |

## Commit conventions

Commits follow scoped Conventional Commits, `type(scope): description` with a lowercase scope,
and explain why in the body. A change updates the documentation it affects in the same
commit.

## Limitations

- `bin/ci` still builds its test images on every run, about half of its four minutes on
  GitHub.
- There is no staging environment; every green commit goes straight to the demo.
