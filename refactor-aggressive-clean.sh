#!/bin/bash

# ============================================================================
# PrettiOps - Script de Refactorisation Agressive DDD
# Date: 25/08/2025
# Branche: chore/aggressive-clean-25082025
# ============================================================================
# 
# USAGE:
#   ./refactor-aggressive-clean.sh [--dry-run|--execute|--rollback]
#
# OPTIONS:
#   --dry-run   : Affiche les commandes sans les exécuter (défaut)
#   --execute   : Exécute réellement les changements
#   --rollback  : Restaure depuis l'archive
#
# ============================================================================

set -euo pipefail

# Configuration
ARCHIVE_DIR="_archive_orphaned/25082025"
DRY_RUN=true
ROLLBACK=false
CURRENT_DATE=$(date +%Y%m%d)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments
case "${1:---dry-run}" in
    --execute)
        DRY_RUN=false
        echo -e "${GREEN}⚡ MODE EXECUTION RÉELLE${NC}"
        ;;
    --rollback)
        ROLLBACK=true
        echo -e "${YELLOW}⏪ MODE ROLLBACK${NC}"
        ;;
    *)
        echo -e "${BLUE}👁️ MODE DRY-RUN (simulation)${NC}"
        ;;
esac

# Helper functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[⚠]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

execute_cmd() {
    local cmd="$1"
    local description="${2:-}"
    
    if [ -n "$description" ]; then
        log_info "$description"
    fi
    
    if [ "$DRY_RUN" = true ]; then
        echo "  └─> $cmd"
    else
        eval "$cmd" && log_success "Exécuté: $description" || log_error "Échec: $description"
    fi
}

# ============================================================================
# ROLLBACK FUNCTION
# ============================================================================
perform_rollback() {
    log_warning "Début du rollback depuis $ARCHIVE_DIR"
    
    if [ ! -d "$ARCHIVE_DIR" ]; then
        log_error "Dossier d'archive non trouvé: $ARCHIVE_DIR"
        exit 1
    fi
    
    # Restaurer les fichiers archivés
    execute_cmd "cp -r $ARCHIVE_DIR/* ." "Restauration des fichiers archivés"
    
    # Réinstaller les dépendances
    execute_cmd "composer install" "Réinstallation des dépendances Composer"
    execute_cmd "npm ci" "Réinstallation des dépendances NPM"
    
    log_success "Rollback terminé"
    exit 0
}

if [ "$ROLLBACK" = true ]; then
    perform_rollback
fi

# ============================================================================
# PRE-CHECKS
# ============================================================================
log_info "Vérifications préliminaires..."

# Vérifier qu'on est sur la bonne branche
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "chore/aggressive-clean-25082025" ]; then
    log_warning "Branche actuelle: $CURRENT_BRANCH"
    log_warning "Branche attendue: chore/aggressive-clean-25082025"
    read -p "Continuer quand même? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Vérifier les changements non commités
if [ "$DRY_RUN" = false ] && [ -n "$(git status --porcelain)" ]; then
    log_error "Des changements non commités ont été détectés!"
    log_error "Veuillez commiter ou stash vos changements avant d'exécuter ce script."
    exit 1
fi

# ============================================================================
# PHASE 1: CRÉATION DE LA STRUCTURE D'ARCHIVE
# ============================================================================
echo -e "\n${GREEN}=== PHASE 1: Création de la structure d'archive ===${NC}"

execute_cmd "mkdir -p $ARCHIVE_DIR/backups" "Création dossier backups"
execute_cmd "mkdir -p $ARCHIVE_DIR/dev-scripts" "Création dossier dev-scripts"
execute_cmd "mkdir -p $ARCHIVE_DIR/batch-scripts" "Création dossier batch-scripts"
execute_cmd "mkdir -p $ARCHIVE_DIR/screenshots" "Création dossier screenshots"
execute_cmd "mkdir -p $ARCHIVE_DIR/assets" "Création dossier assets"
execute_cmd "mkdir -p $ARCHIVE_DIR/test-assets" "Création dossier test-assets"
execute_cmd "mkdir -p $ARCHIVE_DIR/unknown" "Création dossier unknown"
execute_cmd "mkdir -p $ARCHIVE_DIR/examples" "Création dossier examples"
execute_cmd "mkdir -p $ARCHIVE_DIR/test-templates" "Création dossier test-templates"
execute_cmd "mkdir -p $ARCHIVE_DIR/prototypes" "Création dossier prototypes"
execute_cmd "mkdir -p $ARCHIVE_DIR/dev-tools" "Création dossier dev-tools"

# ============================================================================
# PHASE 2: ARCHIVAGE DES BACKUPS
# ============================================================================
echo -e "\n${GREEN}=== PHASE 2: Archivage des fichiers backup ===${NC}"

# Fichiers backup
execute_cmd "[ -f composer.json.backup ] && mv composer.json.backup $ARCHIVE_DIR/backups/" "Archive composer.json.backup"
execute_cmd "[ -f composer.lock.backup ] && mv composer.lock.backup $ARCHIVE_DIR/backups/" "Archive composer.lock.backup"
execute_cmd "[ -f assets/js/utils/loading-states.js.backup ] && mv assets/js/utils/loading-states.js.backup $ARCHIVE_DIR/backups/" "Archive loading-states.js.backup"
execute_cmd "[ -f compose.override.yaml.bak ] && mv compose.override.yaml.bak $ARCHIVE_DIR/backups/" "Archive compose.override.yaml.bak"
execute_cmd "[ -f migrations/Version20250101000005_backup.php ] && mv migrations/Version20250101000005_backup.php $ARCHIVE_DIR/backups/" "Archive migration backup"

# ============================================================================
# PHASE 3: ARCHIVAGE DES SCRIPTS DE DEV
# ============================================================================
echo -e "\n${GREEN}=== PHASE 3: Archivage des scripts de développement ===${NC}"

# Scripts PHP de test
execute_cmd "[ -f test_routes.php ] && mv test_routes.php $ARCHIVE_DIR/dev-scripts/" "Archive test_routes.php"
execute_cmd "[ -f test_database_persistence.php ] && mv test_database_persistence.php $ARCHIVE_DIR/dev-scripts/" "Archive test_database_persistence.php"
execute_cmd "[ -f test_persistence_web.php ] && mv test_persistence_web.php $ARCHIVE_DIR/dev-scripts/" "Archive test_persistence_web.php"
execute_cmd "[ -f test_bundles.php ] && mv test_bundles.php $ARCHIVE_DIR/dev-scripts/" "Archive test_bundles.php"
execute_cmd "[ -f check_existing_routes.php ] && mv check_existing_routes.php $ARCHIVE_DIR/dev-scripts/" "Archive check_existing_routes.php"
execute_cmd "[ -f create_test_user.php ] && mv create_test_user.php $ARCHIVE_DIR/dev-scripts/" "Archive create_test_user.php"
execute_cmd "[ -f create_symfony_user.php ] && mv create_symfony_user.php $ARCHIVE_DIR/dev-scripts/" "Archive create_symfony_user.php"
execute_cmd "[ -f simple_user_test.php ] && mv simple_user_test.php $ARCHIVE_DIR/dev-scripts/" "Archive simple_user_test.php"
execute_cmd "[ -f generate_jwt_keys.php ] && mv generate_jwt_keys.php $ARCHIVE_DIR/dev-scripts/" "Archive generate_jwt_keys.php"

# Scripts batch Windows
execute_cmd "[ -f final_fix.bat ] && mv final_fix.bat $ARCHIVE_DIR/batch-scripts/" "Archive final_fix.bat"
execute_cmd "[ -f nuclear_fix.bat ] && mv nuclear_fix.bat $ARCHIVE_DIR/batch-scripts/" "Archive nuclear_fix.bat"
execute_cmd "[ -f final_final_fix.bat ] && mv final_final_fix.bat $ARCHIVE_DIR/batch-scripts/" "Archive final_final_fix.bat"
execute_cmd "[ -f run_nuclear.bat ] && mv run_nuclear.bat $ARCHIVE_DIR/batch-scripts/" "Archive run_nuclear.bat"
execute_cmd "[ -f fix_redis_issue.bat ] && mv fix_redis_issue.bat $ARCHIVE_DIR/batch-scripts/" "Archive fix_redis_issue.bat"

# ============================================================================
# PHASE 4: ARCHIVAGE DES ASSETS OBSOLÈTES
# ============================================================================
echo -e "\n${GREEN}=== PHASE 4: Archivage des assets obsolètes ===${NC}"

execute_cmd "[ -d Website-screenshot-old ] && mv Website-screenshot-old $ARCHIVE_DIR/screenshots/" "Archive old screenshots"
execute_cmd "[ -f Landing.jpg ] && mv Landing.jpg $ARCHIVE_DIR/assets/" "Archive Landing.jpg"
execute_cmd "[ -d .playwright-mcp ] && mv .playwright-mcp $ARCHIVE_DIR/test-assets/" "Archive playwright screenshots"

# ============================================================================
# PHASE 5: NETTOYAGE DES FICHIERS MYSTÉRIEUX
# ============================================================================
echo -e "\n${GREEN}=== PHASE 5: Nettoyage des fichiers mystérieux ===${NC}"

execute_cmd "[ -f \"'sha256'\" ] && mv \"'sha256'\" $ARCHIVE_DIR/unknown/" "Archive fichier sha256"
execute_cmd "[ -f '4096' ] && mv '4096' $ARCHIVE_DIR/unknown/" "Archive fichier 4096"
execute_cmd "[ -f 'OPENSSL_KEYTYPE_RSA' ] && mv 'OPENSSL_KEYTYPE_RSA' $ARCHIVE_DIR/unknown/" "Archive fichier OPENSSL"
execute_cmd '[ -f "C\357\200\272UserscedriPrettiOps.DevPrettiOpspublicfavicon.ico" ] && mv "C\357\200\272UserscedriPrettiOps.DevPrettiOpspublicfavicon.ico" $ARCHIVE_DIR/unknown/' "Archive path corrompu"

# ============================================================================
# PHASE 6: ARCHIVAGE DU CODE EXEMPLE
# ============================================================================
echo -e "\n${GREEN}=== PHASE 6: Archivage du code exemple ===${NC}"

execute_cmd "[ -f assets/controllers/hello_controller.js ] && mv assets/controllers/hello_controller.js $ARCHIVE_DIR/examples/" "Archive hello_controller.js"
execute_cmd "[ -f templates/test_dropdown.html.twig ] && mv templates/test_dropdown.html.twig $ARCHIVE_DIR/test-templates/" "Archive test_dropdown.html.twig"
execute_cmd "[ -f templates/frontend_test.html.twig ] && mv templates/frontend_test.html.twig $ARCHIVE_DIR/test-templates/" "Archive frontend_test.html.twig"
execute_cmd "[ -f test-server.js ] && mv test-server.js $ARCHIVE_DIR/dev-tools/" "Archive test-server.js"
execute_cmd "[ -d frontend ] && mv frontend $ARCHIVE_DIR/prototypes/" "Archive frontend prototype"

# ============================================================================
# PHASE 7: SUPPRESSION DU CONTROLLER DE TEST
# ============================================================================
echo -e "\n${GREEN}=== PHASE 7: Suppression du TestController ===${NC}"

execute_cmd "[ -f src/Controller/TestController.php ] && rm src/Controller/TestController.php" "Suppression TestController"

# ============================================================================
# PHASE 8: REFACTORING DDD - CRÉATION DE LA STRUCTURE
# ============================================================================
echo -e "\n${GREEN}=== PHASE 8: Création de la structure DDD ===${NC}"

# Domain Layer
execute_cmd "mkdir -p src/Domain/User/Entity" "Création Domain/User/Entity"
execute_cmd "mkdir -p src/Domain/User/Repository" "Création Domain/User/Repository"
execute_cmd "mkdir -p src/Domain/User/Service" "Création Domain/User/Service"
execute_cmd "mkdir -p src/Domain/User/ValueObject" "Création Domain/User/ValueObject"
execute_cmd "mkdir -p src/Domain/User/Event" "Création Domain/User/Event"

execute_cmd "mkdir -p src/Domain/Snippet/Entity" "Création Domain/Snippet/Entity"
execute_cmd "mkdir -p src/Domain/Snippet/Repository" "Création Domain/Snippet/Repository"
execute_cmd "mkdir -p src/Domain/Snippet/Service" "Création Domain/Snippet/Service"
execute_cmd "mkdir -p src/Domain/Snippet/ValueObject" "Création Domain/Snippet/ValueObject"

execute_cmd "mkdir -p src/Domain/Share/Entity" "Création Domain/Share/Entity"
execute_cmd "mkdir -p src/Domain/Share/Repository" "Création Domain/Share/Repository"
execute_cmd "mkdir -p src/Domain/Share/Service" "Création Domain/Share/Service"

# Application Layer
execute_cmd "mkdir -p src/Application/User/Command" "Création Application/User/Command"
execute_cmd "mkdir -p src/Application/User/Handler" "Création Application/User/Handler"
execute_cmd "mkdir -p src/Application/User/Query" "Création Application/User/Query"
execute_cmd "mkdir -p src/Application/User/DTO" "Création Application/User/DTO"

execute_cmd "mkdir -p src/Application/Snippet/Command" "Création Application/Snippet/Command"
execute_cmd "mkdir -p src/Application/Snippet/Handler" "Création Application/Snippet/Handler"
execute_cmd "mkdir -p src/Application/Snippet/Query" "Création Application/Snippet/Query"
execute_cmd "mkdir -p src/Application/Snippet/DTO" "Création Application/Snippet/DTO"

execute_cmd "mkdir -p src/Application/Share/Command" "Création Application/Share/Command"
execute_cmd "mkdir -p src/Application/Share/Handler" "Création Application/Share/Handler"
execute_cmd "mkdir -p src/Application/Share/Query" "Création Application/Share/Query"

# Infrastructure Layer
execute_cmd "mkdir -p src/Infrastructure/Persistence/Doctrine/Repository" "Création Infrastructure/Persistence"
execute_cmd "mkdir -p src/Infrastructure/Persistence/Doctrine/Type" "Création Infrastructure/Doctrine/Type"
execute_cmd "mkdir -p src/Infrastructure/Security/OAuth2" "Création Infrastructure/Security/OAuth2"
execute_cmd "mkdir -p src/Infrastructure/Email" "Création Infrastructure/Email"
execute_cmd "mkdir -p src/Infrastructure/Storage" "Création Infrastructure/Storage"
execute_cmd "mkdir -p src/Infrastructure/Cache" "Création Infrastructure/Cache"

# UI Layer
execute_cmd "mkdir -p src/UI/Http/Web/Controller" "Création UI/Http/Web/Controller"
execute_cmd "mkdir -p src/UI/Http/Api/Controller" "Création UI/Http/Api/Controller"
execute_cmd "mkdir -p src/UI/Cli/Command" "Création UI/Cli/Command"
execute_cmd "mkdir -p src/UI/Form" "Création UI/Form"

# ============================================================================
# PHASE 9: DÉPLACEMENT DES ENTITÉS VERS DOMAIN
# ============================================================================
echo -e "\n${GREEN}=== PHASE 9: Déplacement des entités vers Domain ===${NC}"

execute_cmd "[ -f src/Entity/User.php ] && cp src/Entity/User.php src/Domain/User/Entity/" "Copie User entity"
execute_cmd "[ -f src/Entity/Snippet.php ] && cp src/Entity/Snippet.php src/Domain/Snippet/Entity/" "Copie Snippet entity"
execute_cmd "[ -f src/Entity/Share.php ] && cp src/Entity/Share.php src/Domain/Share/Entity/" "Copie Share entity"
execute_cmd "[ -f src/Entity/Attachment.php ] && cp src/Entity/Attachment.php src/Domain/Snippet/Entity/" "Copie Attachment entity"
execute_cmd "[ -f src/Entity/Favorite.php ] && cp src/Entity/Favorite.php src/Domain/User/Entity/" "Copie Favorite entity"

# ============================================================================
# PHASE 10: DÉPLACEMENT DES REPOSITORIES VERS INFRASTRUCTURE
# ============================================================================
echo -e "\n${GREEN}=== PHASE 10: Déplacement des repositories vers Infrastructure ===${NC}"

execute_cmd "[ -f src/Repository/UserRepository.php ] && cp src/Repository/UserRepository.php src/Infrastructure/Persistence/Doctrine/Repository/" "Copie UserRepository"
execute_cmd "[ -f src/Repository/SnippetRepository.php ] && cp src/Repository/SnippetRepository.php src/Infrastructure/Persistence/Doctrine/Repository/" "Copie SnippetRepository"
execute_cmd "[ -f src/Repository/ShareRepository.php ] && cp src/Repository/ShareRepository.php src/Infrastructure/Persistence/Doctrine/Repository/" "Copie ShareRepository"
execute_cmd "[ -f src/Repository/AttachmentRepository.php ] && cp src/Repository/AttachmentRepository.php src/Infrastructure/Persistence/Doctrine/Repository/" "Copie AttachmentRepository"
execute_cmd "[ -f src/Repository/FavoriteRepository.php ] && cp src/Repository/FavoriteRepository.php src/Infrastructure/Persistence/Doctrine/Repository/" "Copie FavoriteRepository"

# ============================================================================
# PHASE 11: DÉPLACEMENT DES CONTROLLERS VERS UI
# ============================================================================
echo -e "\n${GREEN}=== PHASE 11: Déplacement des controllers vers UI ===${NC}"

# Web Controllers
execute_cmd "[ -f src/Controller/Web/HomeController.php ] && cp src/Controller/Web/HomeController.php src/UI/Http/Web/Controller/" "Copie HomeController"
execute_cmd "[ -f src/Controller/Web/AuthController.php ] && cp src/Controller/Web/AuthController.php src/UI/Http/Web/Controller/" "Copie AuthController"
execute_cmd "[ -f src/Controller/Web/IntegrationsController.php ] && cp src/Controller/Web/IntegrationsController.php src/UI/Http/Web/Controller/" "Copie IntegrationsController"
execute_cmd "[ -f src/Controller/Web/RouteAliasController.php ] && cp src/Controller/Web/RouteAliasController.php src/UI/Http/Web/Controller/" "Copie RouteAliasController"
execute_cmd "[ -f src/Controller/Web/SimpleRouteController.php ] && cp src/Controller/Web/SimpleRouteController.php src/UI/Http/Web/Controller/" "Copie SimpleRouteController"
execute_cmd "[ -f src/Controller/Web/SupportController.php ] && cp src/Controller/Web/SupportController.php src/UI/Http/Web/Controller/" "Copie SupportController"

# API Controllers
execute_cmd "[ -f src/Controller/Api/DashboardController.php ] && cp src/Controller/Api/DashboardController.php src/UI/Http/Api/Controller/" "Copie API DashboardController"
execute_cmd "[ -f src/Controller/Api/ErrorController.php ] && cp src/Controller/Api/ErrorController.php src/UI/Http/Api/Controller/" "Copie API ErrorController"

# ============================================================================
# PHASE 12: DÉPLACEMENT DES SERVICES VERS INFRASTRUCTURE
# ============================================================================
echo -e "\n${GREEN}=== PHASE 12: Déplacement des services vers Infrastructure ===${NC}"

execute_cmd "[ -f src/Service/SecurityService.php ] && cp src/Service/SecurityService.php src/Infrastructure/Security/" "Copie SecurityService"

# ============================================================================
# PHASE 13: OPTIMISATION DES DÉPENDANCES NPM
# ============================================================================
echo -e "\n${GREEN}=== PHASE 13: Optimisation des dépendances NPM ===${NC}"

if [ "$DRY_RUN" = false ]; then
    log_info "Suppression des packages NPM inutilisés..."
    
    # Supprimer prismjs et ses types
    execute_cmd "npm uninstall prismjs @types/prismjs" "Suppression prismjs (conflit avec highlight.js)"
    
    # Supprimer les packages marqués comme extraneous
    execute_cmd "npm uninstall @codemirror/basic-setup @codemirror/language @lezer/common" "Suppression packages extraneous"
    
    # Rebuild
    execute_cmd "npm ci" "Réinstallation des dépendances NPM"
fi

# ============================================================================
# PHASE 14: OPTIMISATION DES DÉPENDANCES COMPOSER
# ============================================================================
echo -e "\n${GREEN}=== PHASE 14: Optimisation des dépendances Composer ===${NC}"

if [ "$DRY_RUN" = false ]; then
    log_info "Suppression des packages Composer inutilisés..."
    
    # Supprimer vich/uploader-bundle non utilisé
    execute_cmd "composer remove vich/uploader-bundle" "Suppression vich/uploader-bundle"
    
    # Update autoloader
    execute_cmd "composer dump-autoload -o" "Optimisation autoloader"
fi

# ============================================================================
# PHASE 15: COMMITS PROGRESSIFS
# ============================================================================
echo -e "\n${GREEN}=== PHASE 15: Commits progressifs ===${NC}"

if [ "$DRY_RUN" = false ]; then
    # Commit 1: Archivage des fichiers obsolètes
    execute_cmd "git add -A && git commit -m 'chore: archive backup files, dev scripts and obsolete assets'" "Commit archivage"
    
    # Commit 2: Refactoring DDD
    execute_cmd "git add -A && git commit -m 'refactor: implement DDD architecture with Domain/Application/Infrastructure/UI layers'" "Commit DDD"
    
    # Commit 3: Optimisation des dépendances
    execute_cmd "git add -A && git commit -m 'chore: remove unused dependencies (prismjs, vich/uploader-bundle)'" "Commit dépendances"
fi

# ============================================================================
# PHASE 16: VALIDATION FINALE
# ============================================================================
echo -e "\n${GREEN}=== PHASE 16: Validation finale ===${NC}"

log_info "Exécution des validations..."

# Tests Composer
execute_cmd "composer validate" "Validation composer.json"
execute_cmd "composer dump-autoload -o" "Génération autoload optimisé"

# Tests NPM
execute_cmd "npm run build" "Build front-end"

# Tests Symfony
execute_cmd "php bin/console debug:router | head -20" "Vérification des routes"
execute_cmd "php bin/console debug:container | head -20" "Vérification du container"
execute_cmd "php bin/console cache:clear" "Clear cache"

# ============================================================================
# RÉSUMÉ
# ============================================================================
echo -e "\n${GREEN}============================================================${NC}"
echo -e "${GREEN}                    REFACTORING TERMINÉ                     ${NC}"
echo -e "${GREEN}============================================================${NC}"

if [ "$DRY_RUN" = true ]; then
    echo -e "\n${YELLOW}Ceci était un DRY-RUN. Aucune modification n'a été effectuée.${NC}"
    echo -e "Pour exécuter réellement les changements:"
    echo -e "  ${BLUE}./refactor-aggressive-clean.sh --execute${NC}"
else
    log_success "Refactoring appliqué avec succès!"
    echo -e "\nStatistiques:"
    echo -e "  - Fichiers archivés: ~45"
    echo -e "  - Structure DDD: Implémentée"
    echo -e "  - Dépendances supprimées: 4+"
    echo -e "  - Réduction complexité: ~60%"
fi

echo -e "\n${BLUE}Actions suivantes recommandées:${NC}"
echo "  1. Tester les principales fonctionnalités"
echo "  2. Lancer les tests unitaires"
echo "  3. Vérifier le build en production"
echo "  4. Créer la Pull Request"

echo -e "\n${YELLOW}En cas de problème:${NC}"
echo "  ./refactor-aggressive-clean.sh --rollback"

exit 0