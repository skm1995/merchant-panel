# Production proxy: Caddy with the built React app baked in.
FROM node:24-alpine AS frontend

WORKDIR /app/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY openapi /app/openapi
COPY frontend/ ./
RUN npm run build

FROM caddy:2.10.2-alpine

COPY docker/caddy/backend.caddy /etc/caddy/backend.caddy
COPY docker/caddy/headers.caddy /etc/caddy/headers.caddy
COPY docker/caddy/access-log.caddy /etc/caddy/access-log.caddy
COPY docker/caddy/prod.Caddyfile /etc/caddy/Caddyfile
COPY --from=frontend /app/frontend/dist /srv/frontend
