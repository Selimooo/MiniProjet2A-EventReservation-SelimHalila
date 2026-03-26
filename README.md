# 🎟 Application Web de Gestion de Réservations d'Événements

> **Technologies:** Symfony 7 · JWT · Passkeys (WebAuthn/FIDO2) · PostgreSQL · Docker  
> **Cours:** FIA3-GL — ISSAT Sousse — 2025/2026

---

## 📋 Table des matières

1. [Description du projet](#description)
2. [Technologies utilisées](#technologies)
3. [Architecture](#architecture)
4. [Installation locale (sans Docker)](#installation-locale)
5. [Installation avec Docker](#installation-docker)
6. [Configuration GitHub (étape par étape)](#github)
7. [Utilisation de l'application](#utilisation)
8. [API REST — Endpoints](#api)
9. [Tests](#tests)
10. [Membres de l'équipe](#membres)

---

## 📌 Description du projet <a name="description"></a>

Application web complète permettant :
- **Aux utilisateurs** : consulter des événements, s'authentifier via Passkey (biométrie) ou mot de passe, et réserver des places en ligne.
- **À l'administrateur** : gérer les événements (CRUD complet) et consulter les réservations via un dashboard sécurisé.

### Fonctionnalités principales
- ✅ Authentification sans mot de passe avec **Passkeys / WebAuthn / FIDO2**
- ✅ Autorisation stateless via **JWT** (access token + refresh token)
- ✅ CRUD complet des événements (admin)
- ✅ Réservation de places avec confirmation
- ✅ Dashboard admin protégé
- ✅ API REST complète
- ✅ Déploiement Docker (PHP-FPM + Nginx + PostgreSQL)

---

## 🛠 Technologies utilisées <a name="technologies"></a>

| Couche | Technologie |
|--------|-------------|
| Framework | Symfony 7.0 LTS |
| Authentification forte | WebAuthn / FIDO2 (Passkeys) |
| Autorisation API | JWT (LexikJWTAuthenticationBundle) |
| Refresh tokens | GesdinetJWTRefreshTokenBundle |
| Base de données | PostgreSQL 15 |
| ORM | Doctrine ORM |
| Serveur web | Nginx (Alpine) |
| Conteneurisation | Docker + Docker Compose |
| Tests | PHPUnit 10 |
| Versionning | Git + GitHub |

---

## 🏗 Architecture <a name="architecture"></a>

```
┌─────────────────────────────────────────────────────────┐
│                    Client (Browser)                      │
│  HTML/CSS/JS + WebAuthn API + localStorage (JWT)        │
└──────────────────────┬──────────────────────────────────┘
                       │  HTTP/HTTPS
┌──────────────────────▼──────────────────────────────────┐
│                  Nginx (Reverse Proxy)                   │
└──────────────────────┬──────────────────────────────────┘
                       │  FastCGI
┌──────────────────────▼──────────────────────────────────┐
│              Symfony 7 (PHP-FPM)                        │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │ Controllers │  │   Services   │  │   Entities    │  │
│  │  AuthApi    │  │ PasskeyAuth  │  │  User/Event   │  │
│  │  EventApi   │  │ JWTManager   │  │  Reservation  │  │
│  │  AdminApi   │  │              │  │  WebauthnCred │  │
│  └─────────────┘  └──────────────┘  └───────────────┘  │
└──────────────────────┬──────────────────────────────────┘
                       │  SQL
┌──────────────────────▼──────────────────────────────────┐
│               PostgreSQL 15                              │
└─────────────────────────────────────────────────────────┘

Flux Passkey → JWT :
  1. Client → POST /api/auth/login/options  → challenge WebAuthn
  2. Browser → biométrie (Face ID / fingerprint / PIN)
  3. Client → POST /api/auth/login/verify   → JWT + refresh_token
  4. Client → GET  /api/events  [Authorization: Bearer <jwt>]
```

---

## 💻 Installation locale (sans Docker) <a name="installation-locale"></a>

### Prérequis

```bash
php --version       # >= 8.1
composer --version  # >= 2.x
psql --version      # PostgreSQL >= 14
openssl version
```

### Option A — Script automatique (recommandé)

```bash
# 1. Cloner le projet
git clone https://github.com/VOTRE_USERNAME/MiniProjet2A-EventReservation-NomEquipe.git
cd MiniProjet2A-EventReservation-NomEquipe

# 2. Rendre le script exécutable et lancer
chmod +x setup.sh
bash setup.sh

# 3. Démarrer le serveur
php -S localhost:8000 -t public/
# OU avec Symfony CLI :
symfony serve
```

### Option B — Installation manuelle étape par étape

```bash
# 1. Cloner
git clone https://github.com/VOTRE_USERNAME/MiniProjet2A-EventReservation-NomEquipe.git
cd MiniProjet2A-EventReservation-NomEquipe

# 2. Copier et éditer .env.local
cp .env.local.dist .env.local
# Éditez DATABASE_URL, APP_SECRET, JWT_PASSPHRASE dans .env.local

# 3. Installer les dépendances PHP
composer install

# 4. Générer les clés JWT (RSA 4096 bits)
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa \
    -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
chmod 600 config/jwt/private.pem config/jwt/public.pem
# ⚠️  La passphrase saisie doit correspondre à JWT_PASSPHRASE dans .env.local

# 5. Créer la base de données et lancer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 6. (Optionnel) Charger les données de démonstration
composer require --dev doctrine/doctrine-fixtures-bundle
php bin/console doctrine:fixtures:load --no-interaction
# → Admin: username=admin  password=Admin@1234

# 7. Vider le cache
php bin/console cache:clear

# 8. Démarrer
php -S localhost:8000 -t public/
```

**URLs :**
- 🌐 Application : http://localhost:8000
- 🛡 Admin panel : http://localhost:8000/admin
- 🔌 API base : http://localhost:8000/api

---

## 🐳 Installation avec Docker <a name="installation-docker"></a>

### Prérequis Docker

```bash
docker --version          # >= 24.x
docker compose version    # >= 2.x
```

### Lancement

```bash
# 1. Cloner le projet
git clone https://github.com/VOTRE_USERNAME/MiniProjet2A-EventReservation-NomEquipe.git
cd MiniProjet2A-EventReservation-NomEquipe

# 2. Script tout-en-un
chmod +x docker-setup.sh
bash docker-setup.sh
```

### Commandes Docker utiles

```bash
# Démarrer tous les services
docker compose up -d

# Voir les logs en temps réel
docker compose logs -f

# Exécuter une commande Symfony dans le container
docker compose exec php php bin/console <commande>

# Exemples
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:fixtures:load
docker compose exec php php bin/console cache:clear
docker compose exec php php bin/phpunit

# Arrêter les containers
docker compose down

# Arrêter + supprimer les volumes (reset BDD)
docker compose down -v
```

**URLs Docker :**
- 🌐 Application : http://localhost:8080
- 🛡 Admin panel : http://localhost:8080/admin
- 🗄 Adminer (BDD) : http://localhost:8081

---

## 🐙 Configuration GitHub (étape par étape) <a name="github"></a>

### Étape 1 — Créer le dépôt GitHub

1. Aller sur [github.com](https://github.com) → Se connecter
2. Cliquer **"New repository"** (bouton vert)
3. Remplir :
   - **Repository name :** `MiniProjet2A-EventReservation-NomEquipe`
   - **Visibility :** ✅ Private
   - **Ne pas cocher** "Add README" (on a déjà le nôtre)
4. Cliquer **"Create repository"**

### Étape 2 — Initialiser Git localement

```bash
# Dans le dossier du projet
cd MiniProjet2A-EventReservation-NomEquipe

git init
git add .
git commit -m "feat: initial project setup - Symfony 7 + JWT + Passkeys"

# Connecter au dépôt GitHub
git remote add origin https://github.com/VOTRE_USERNAME/MiniProjet2A-EventReservation-NomEquipe.git
git branch -M main
git push -u origin main
```

### Étape 3 — Créer les branches (comme demandé dans le sujet)

```bash
# Branche de développement/intégration
git checkout -b dev
git push -u origin dev

# Branches features (exemples)
git checkout -b feature/auth-passkey
git push -u origin feature/auth-passkey

git checkout -b feature/event-crud
git push -u origin feature/event-crud

git checkout -b feature/reservation-form
git push -u origin feature/reservation-form

git checkout -b feature/admin-dashboard
git push -u origin feature/admin-dashboard

# Retour sur dev pour travailler
git checkout dev
```

### Étape 4 — Inviter l'enseignant

1. GitHub → Votre dépôt → **Settings** → **Collaborators**
2. Cliquer **"Add people"**
3. Saisir le nom GitHub de l'enseignant : `sofiene.benahmed` (à confirmer)
4. Cliquer **"Add collaborator"**

### Étape 5 — Créer les Milestones GitHub

1. GitHub → Votre dépôt → **Issues** → **Milestones** → **New milestone**

Créer ces 3 milestones :

| Milestone | Description | Deadline |
|-----------|-------------|----------|
| `M1 - Backend & BDD` | Entities, migrations, API REST, JWT | Semaine 2 |
| `M2 - Frontend & Auth` | Passkeys, pages HTML/CSS/JS, formulaires | Semaine 3 |
| `M3 - Docker & Tests` | Docker, PHPUnit, README, déploiement | Semaine 4 |

### Étape 6 — Workflow Git recommandé

```bash
# ── Pour chaque nouvelle fonctionnalité ───────────────────────────────

# 1. Partir de dev à jour
git checkout dev
git pull origin dev

# 2. Créer une branche feature
git checkout -b feature/ma-fonctionnalite

# 3. Travailler + committer régulièrement
git add .
git commit -m "feat: description claire de ce qui a été fait"

# 4. Pusher la branche
git push origin feature/ma-fonctionnalite

# 5. Créer une Pull Request sur GitHub : feature → dev

# 6. Après merge, mettre à jour dev
git checkout dev
git pull origin dev

# ── Quand dev est stable → merger dans main ───────────────────────────
git checkout main
git merge dev
git push origin main
git tag v1.0.0
git push origin v1.0.0
```

### Étape 7 — Convention de commits (minimum 10 par membre)

```bash
# Format : <type>: <description>
git commit -m "feat: add passkey registration endpoint"
git commit -m "feat: implement JWT token generation after passkey login"
git commit -m "feat: create Event entity and CRUD controller"
git commit -m "feat: add reservation form with confirmation"
git commit -m "feat: admin dashboard with event statistics"
git commit -m "fix: correct base64url encoding in WebAuthn JS client"
git commit -m "fix: handle expired JWT with auto-refresh"
git commit -m "test: add PHPUnit tests for auth endpoints"
git commit -m "docker: add docker-compose.yml and Dockerfile"
git commit -m "docs: complete README with setup instructions"
```

---

## 🚀 Utilisation de l'application <a name="utilisation"></a>

### Interface utilisateur

| URL | Description |
|-----|-------------|
| `/` | Liste des événements à venir |
| `/event/{id}` | Détail d'un événement + formulaire de réservation |
| `/auth` | Page de connexion / inscription |

**Connexion avec Passkey :**
1. Aller sur `/auth` → onglet "Register"
2. Saisir votre email → cliquer "Register with Passkey"
3. Le navigateur demande votre biométrie (Face ID / empreinte / PIN)
4. Passkey enregistrée → JWT généré → connecté !

**Connexion suivante :**
1. `/auth` → onglet "Login" → "Login with Passkey"
2. Valider la biométrie → connecté en 1 seconde

### Interface Admin

| URL | Description |
|-----|-------------|
| `/admin` | Dashboard (liste événements + stats) |
| `/admin/events/new` | Créer un événement |
| `/admin/events/{id}/edit` | Modifier un événement |
| `/admin/events/{id}/reservations` | Voir les réservations |

**Connexion admin :** `/admin/login`
- username: `admin`
- password: `Admin@1234`

---

## 🔌 API REST — Endpoints <a name="api"></a>

### Authentification (Public)

```
POST /api/auth/register/options    → Obtenir options WebAuthn (inscription)
POST /api/auth/register/verify     → Vérifier credential + obtenir JWT
POST /api/auth/login/options       → Obtenir challenge WebAuthn (connexion)
POST /api/auth/login/verify        → Vérifier assertion + obtenir JWT
POST /api/auth/login/password      → Connexion classique email/password
POST /api/token/refresh            → Rafraîchir le JWT
```

### Profil (JWT requis)

```
GET /api/auth/me                   → Infos utilisateur connecté
```

### Événements (Public pour GET)

```
GET  /api/events                   → Liste des événements
GET  /api/events/{id}              → Détail d'un événement
POST /api/events/{id}/reserve      → Réserver (JWT optionnel)
```

### Administration (ROLE_ADMIN + JWT)

```
GET    /api/admin/dashboard                        → Statistiques
GET    /api/admin/events                           → Tous les événements
POST   /api/admin/events                           → Créer un événement
GET    /api/admin/events/{id}                      → Détail
PUT    /api/admin/events/{id}                      → Modifier
DELETE /api/admin/events/{id}                      → Supprimer
GET    /api/admin/events/{id}/reservations         → Réservations par événement
```

### Exemple avec curl

```bash
# 1. Obtenir un JWT (login password)
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login/password \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' \
  | jq -r '.token')

# 2. Utiliser le JWT
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/auth/me

# 3. Créer un événement (admin)
curl -X POST http://localhost:8000/api/admin/events \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Mon événement",
    "description": "Description...",
    "date": "2026-06-15T14:00:00",
    "location": "Sousse",
    "seats": 50
  }'
```

---

## 🧪 Tests <a name="tests"></a>

```bash
# Lancer tous les tests
php bin/phpunit

# Tests avec filtre
php bin/phpunit --filter AuthApiControllerTest

# Tests avec rapport de couverture HTML
php bin/phpunit --coverage-html var/coverage
# → Ouvrir var/coverage/index.html dans le navigateur
```

---

## 👥 Membres de l'équipe <a name="membres"></a>

| Nom | Rôle | GitHub |
|-----|------|--------|
| [Votre Nom] | Développeur Full-Stack | [@username] |

---

## 📚 Ressources

- [WebAuthn Level 2 (W3C)](https://www.w3.org/TR/webauthn-2/)
- [JWT RFC 7519](https://datatracker.ietf.org/doc/html/rfc7519)
- [Symfony Security Docs](https://symfony.com/doc/current/security.html)
- [LexikJWT Bundle](https://github.com/lexik/LexikJWTAuthenticationBundle)
- [FIDO Alliance — Passkeys](https://fidoalliance.org/passkeys/)
- [WebAuthn.io (test en ligne)](https://webauthn.io/)
- [JWT.io (décodeur)](https://jwt.io/)
- [Guide Technique (PDF)](./JWT_and_Passkeys.pdf)
- [Annexes Techniques (PDF)](./annexe.pdf)
"# MiniProjet2A-EventReservation-SelimHalila" 
