#!/usr/bin/env bash
# =============================================================================
#  docker-setup.sh — Full Docker-based setup
#  Usage:  bash docker-setup.sh
# =============================================================================

set -e
BLUE='\033[0;34m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'

info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }

echo -e "\n${BLUE}╔══════════════════════════════════════════════╗"
echo -e "║  Event Reservation — Docker Setup           ║"
echo -e "╚══════════════════════════════════════════════╝${NC}\n"

command -v docker >/dev/null 2>&1 || { echo -e "${RED}Docker not found.${NC}"; exit 1; }
command -v docker-compose >/dev/null 2>&1 || command -v docker compose >/dev/null 2>&1 || { echo -e "${RED}docker-compose not found.${NC}"; exit 1; }

# ── .env for Docker ───────────────────────────────────────────────────────
if [ ! -f ".env.docker" ]; then
    info "Generating .env.docker..."
    APP_SECRET=$(openssl rand -hex 32)
    JWT_PASSPHRASE=$(openssl rand -hex 24)

    cat > .env.docker <<EOF
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=${APP_SECRET}
JWT_PASSPHRASE=${JWT_PASSPHRASE}
JWT_TOKEN_TTL=3600
APP_DOMAIN=localhost
WEBAUTHN_RP_NAME=Event Reservation App
POSTGRES_DB=event_reservation
POSTGRES_USER=app
POSTGRES_PASSWORD=secretpassword
CORS_ALLOW_ORIGIN=^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$
EOF
    success ".env.docker created"
fi

# ── JWT Keys ──────────────────────────────────────────────────────────────
if [ ! -f "config/jwt/private.pem" ]; then
    info "Generating JWT keys..."
    mkdir -p config/jwt
    JWT_PASSPHRASE=$(grep "JWT_PASSPHRASE=" .env.docker | cut -d'=' -f2)
    openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa \
        -pkeyopt rsa_keygen_bits:4096 -pass pass:"$JWT_PASSPHRASE" 2>/dev/null
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem \
        -pubout -passin pass:"$JWT_PASSPHRASE" 2>/dev/null
    chmod 600 config/jwt/private.pem config/jwt/public.pem
    success "JWT keys generated"
fi

# ── Docker Compose up ─────────────────────────────────────────────────────
info "Starting containers..."
docker compose --env-file .env.docker up -d --build
success "Containers started"

info "Waiting for DB to be ready..."
sleep 8

# ── Install deps & migrate inside container ───────────────────────────────
info "Installing Composer dependencies..."
docker compose exec php composer install --no-interaction --prefer-dist

info "Running database migrations..."
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

info "Loading sample data..."
docker compose exec php composer require --dev doctrine/doctrine-fixtures-bundle --no-interaction 2>/dev/null || true
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

info "Clearing cache..."
docker compose exec php php bin/console cache:clear

echo -e "\n${GREEN}╔══════════════════════════════════════════╗"
echo -e "║  ✅  Docker setup complete!              ║"
echo -e "╚══════════════════════════════════════════╝${NC}"
echo -e "\n  App:      ${BLUE}http://localhost:8080${NC}"
echo -e "  Admin:    ${BLUE}http://localhost:8080/admin${NC}  (admin / Admin@1234)"
echo -e "  Adminer:  ${BLUE}http://localhost:8081${NC}  (DB GUI)"
echo -e "\n  Stop:  ${YELLOW}docker compose down${NC}"
echo -e "  Logs:  ${YELLOW}docker compose logs -f${NC}\n"
