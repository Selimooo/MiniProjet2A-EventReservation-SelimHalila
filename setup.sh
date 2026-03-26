#!/usr/bin/env bash
# =============================================================================
#  setup.sh — Automated project setup for Event Reservation App
#  Usage:  bash setup.sh
# =============================================================================

set -e  # Exit on first error

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[OK]${NC}   $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERR]${NC}  $1"; exit 1; }

echo ""
echo -e "${BLUE}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   Event Reservation App — Automated Setup           ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════╝${NC}"
echo ""

# ── Prerequisite checks ───────────────────────────────────────────────────
info "Checking prerequisites..."
command -v php  >/dev/null 2>&1 || error "PHP not found. Install PHP 8.1+"
command -v composer >/dev/null 2>&1 || error "Composer not found. Install from https://getcomposer.org"
command -v openssl  >/dev/null 2>&1 || error "OpenSSL not found."

PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
info "PHP version: $PHP_VERSION"

# ── .env.local setup ──────────────────────────────────────────────────────
if [ ! -f ".env.local" ]; then
    info "Creating .env.local from template..."
    cp .env.local.dist .env.local

    # Generate random secrets
    APP_SECRET=$(openssl rand -hex 32)
    JWT_PASSPHRASE=$(openssl rand -hex 24)

    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/generate_a_strong_random_secret_here/$APP_SECRET/" .env.local
        sed -i '' "s/your_strong_passphrase_here/$JWT_PASSPHRASE/" .env.local
    else
        sed -i "s/generate_a_strong_random_secret_here/$APP_SECRET/" .env.local
        sed -i "s/your_strong_passphrase_here/$JWT_PASSPHRASE/" .env.local
    fi
    success ".env.local created with generated secrets"
    warn "Edit .env.local to set your DATABASE_URL if needed"
else
    success ".env.local already exists"
fi

# ── Install dependencies ──────────────────────────────────────────────────
info "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist
success "Dependencies installed"

# ── JWT Keys ──────────────────────────────────────────────────────────────
if [ ! -f "config/jwt/private.pem" ]; then
    info "Generating JWT RSA keys..."
    mkdir -p config/jwt

    # Read passphrase from .env.local
    JWT_PASSPHRASE=$(grep "JWT_PASSPHRASE=" .env.local | cut -d'=' -f2 | tr -d '"' | tr -d "'")

    openssl genpkey \
        -out config/jwt/private.pem \
        -aes256 \
        -algorithm rsa \
        -pkeyopt rsa_keygen_bits:4096 \
        -pass pass:"$JWT_PASSPHRASE" 2>/dev/null

    openssl pkey \
        -in config/jwt/private.pem \
        -out config/jwt/public.pem \
        -pubout \
        -passin pass:"$JWT_PASSPHRASE" 2>/dev/null

    chmod 600 config/jwt/private.pem config/jwt/public.pem
    success "JWT keys generated at config/jwt/"
else
    success "JWT keys already exist"
fi

# ── Database setup ────────────────────────────────────────────────────────
info "Setting up database..."

if php bin/console doctrine:database:exists --quiet 2>/dev/null; then
    success "Database already exists"
else
    php bin/console doctrine:database:create --no-interaction
    success "Database created"
fi

info "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction
success "Migrations applied"

# ── Fixtures ──────────────────────────────────────────────────────────────
read -p "$(echo -e "${YELLOW}Load sample data (admin + 4 events)? [y/N]:${NC} ")" LOAD_FIXTURES
if [[ "$LOAD_FIXTURES" =~ ^[Yy]$ ]]; then
    composer require --dev doctrine/doctrine-fixtures-bundle --no-interaction 2>/dev/null || true
    php bin/console doctrine:fixtures:load --no-interaction
    success "Sample data loaded"
    echo -e "${GREEN}  Admin credentials:  username=admin  password=Admin@1234${NC}"
fi

# ── Cache clear ───────────────────────────────────────────────────────────
info "Clearing cache..."
php bin/console cache:clear
success "Cache cleared"

# ── Done ──────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✅  Setup complete!                                 ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Start the server:    ${BLUE}php -S localhost:8000 -t public/${NC}"
echo -e "  Or with Symfony CLI: ${BLUE}symfony serve${NC}"
echo -e "  Admin panel:         ${BLUE}http://localhost:8000/admin${NC}"
echo -e "  API docs:            See README.md"
echo ""
