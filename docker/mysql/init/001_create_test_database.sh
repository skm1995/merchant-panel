#!/bin/bash

set -e

mysql --protocol=socket --user=root --password="${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS app_test;
GRANT ALL PRIVILEGES ON app_test.* TO '${MYSQL_USER}'@'%';
SQL
