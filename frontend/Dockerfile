# syntax=docker/dockerfile:1
# check=skip=SecretsUsedInArgOrEnv
# (a chave do Reverb é pública: o navegador precisa dela para abrir o WebSocket)

# Imagem de produção do front: o build do Vite servido pelo Caddy, que também é a porta
# de entrada do Kairo (HTTPS automático) e repassa /api e o WebSocket para a API.

FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .

# Variáveis do Vite entram no build (ficam no JavaScript, então nada de segredos aqui).
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST=""
ARG VITE_REVERB_PORT="443"
ARG VITE_REVERB_SCHEME="https"
ENV VITE_API_URL="" \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm run build

FROM caddy:2-alpine
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist /srv
