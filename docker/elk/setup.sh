#!/bin/sh
# Prepares Elasticsearch and Kibana for the application logs, every time the stack starts:
# daily indices that keep no replica on this single node and are deleted after seven days,
# the "Application logs" data view, and the dashboards. Every call overwrites what it
# creates, so running it again changes nothing.
set -eu

es=http://elasticsearch:9200
kibana="${KIBANA_URL:?KIBANA_URL is required}"

curl --fail --silent --show-error -X PUT "${es}/_ilm/policy/app-logs" \
    -H 'Content-Type: application/json' --data @/elk/ilm-policy.json
curl --fail --silent --show-error -X PUT "${es}/_index_template/app-logs" \
    -H 'Content-Type: application/json' --data @/elk/index-template.json
curl --fail --silent --show-error --output /dev/null -X POST "${kibana}/api/data_views/data_view" \
    -H 'kbn-xsrf: true' -H 'Content-Type: application/json' --data @/elk/kibana-data-view.json
curl --fail --silent --show-error --output /dev/null -X POST "${kibana}/api/saved_objects/_import?overwrite=true" \
    -H 'kbn-xsrf: true' --form file=@/elk/dashboards.ndjson
echo
echo "Elasticsearch and Kibana are ready for the application logs."
