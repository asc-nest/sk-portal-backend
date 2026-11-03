#!/usr/bin/env bash
set -euo pipefail

deploy_path=${1:?Pass the absolute backend repository path}
deploy_sha=${2:?Pass the tested commit SHA}

[[ "$deploy_path" = /* && "$deploy_path" != / ]] || { echo 'GODADDY_DEPLOY_PATH must be an absolute repository path.' >&2; exit 1; }
[[ "$deploy_sha" =~ ^[0-9a-f]{40}$ ]] || { echo 'Invalid commit SHA.' >&2; exit 1; }
cd -- "$deploy_path"
[[ -d .git && -f artisan && -f composer.json ]] || { echo 'GODADDY_DEPLOY_PATH must point to the backend Git checkout.' >&2; exit 1; }
[[ -f .env ]] || { echo 'Configure the production .env on the server first.' >&2; exit 1; }
command -v php >/dev/null
command -v composer >/dev/null

# Runtime files must live outside Git so pulls cannot replace live uploads.
runtime_paths=(.env vendor storage bootstrap/cache database/database.sqlite public/storage)
if [[ -n "$(git ls-files -- "${runtime_paths[@]}")" ]]; then
    echo 'Runtime files are tracked in Git. Follow deploy/README.md cleanup before deploying.' >&2
    exit 1
fi

# Do not overwrite server edits, switch branches, or reset the checkout.
[[ "$(git branch --show-current)" = main ]] || { echo 'Server checkout must be on main.' >&2; exit 1; }
git diff --quiet
git diff --cached --quiet
git fetch --no-tags origin main
git merge-base --is-ancestor "$deploy_sha" origin/main
# Reject stale deployments or divergent history before changing the checkout.
git merge-base --is-ancestor HEAD "$deploy_sha"
git merge --ff-only "$deploy_sha"
[[ "$(git rev-parse HEAD)" = "$deploy_sha" ]]

# Preserve .env, APP_KEY, uploads, and existing database records.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
php artisan config:clear
php artisan migrate --force
if [[ ! -L public/storage ]]; then
    php artisan storage:link
fi
# Do not cache config while InvitationController reads FRONTEND_URL via env().
echo "Deployed $deploy_sha"
