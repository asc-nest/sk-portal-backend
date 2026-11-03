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

# The incoming commit must not track runtime data.
runtime_paths=(.env vendor storage bootstrap/cache database/database.sqlite public/storage)
# Do not overwrite server edits, switch branches, or reset the checkout.
[[ "$(git branch --show-current)" = main ]] || { echo 'Server checkout must be on main.' >&2; exit 1; }
git diff --cached --quiet
git diff --quiet -- . ':(exclude)storage/**' ':(exclude)bootstrap/cache/**' ':(exclude)database/database.sqlite'
git fetch --no-tags origin main
git merge-base --is-ancestor "$deploy_sha" origin/main
# Reject stale deployments or divergent history before changing the checkout.
git merge-base --is-ancestor HEAD "$deploy_sha"
if [[ -n "$(git ls-tree -r --name-only "$deploy_sha" -- "${runtime_paths[@]}")" ]]; then
    echo 'The incoming commit still tracks runtime files. Commit the staged runtime cleanup first.' >&2
    exit 1
fi

# One-time transition from older commits that tracked generated files/uploads.
# Back them up before Git removes tracking, then restore the live copies.
mapfile -d '' -t tracked_runtime < <(git ls-files -z -- storage bootstrap/cache database/database.sqlite)
if (( ${#tracked_runtime[@]} )); then
    command -v tar >/dev/null
    backup_dir=$(mktemp -d "${TMPDIR:-/tmp}/sk-portal-runtime.XXXXXXXX")
    chmod 700 "$backup_dir"
    existing_runtime=()
    for runtime_path in storage bootstrap/cache database/database.sqlite; do
        [[ ! -L "$runtime_path" ]] || { echo "Back up and untrack symlinked runtime path manually: $runtime_path" >&2; exit 1; }
        if [[ -e "$runtime_path" ]]; then
            existing_runtime+=("$runtime_path")
        fi
    done
    # Refuse unexpected missing runtime directories instead of resetting them.
    (( ${#existing_runtime[@]} )) || { echo 'No runtime data found to back up.' >&2; exit 1; }
    tar -cpf "$backup_dir/runtime.tar" -- "${existing_runtime[@]}"
    tar -tf "$backup_dir/runtime.tar" >/dev/null
    restore_runtime() {
        restore_status=$?
        trap - EXIT
        if ! tar -xpf "$backup_dir/runtime.tar"; then
            echo "Runtime restore failed. Backup retained at $backup_dir/runtime.tar" >&2
            exit 1
        fi
        echo "Runtime backup retained at $backup_dir/runtime.tar"
        exit "$restore_status"
    }
    trap restore_runtime EXIT
    # Only these backed-up runtime files may be reset; application edits are rejected above.
    git restore --worktree -- "${tracked_runtime[@]}"
fi

git merge --ff-only "$deploy_sha"
[[ "$(git rev-parse HEAD)" = "$deploy_sha" ]]
if (( ${#tracked_runtime[@]} )); then
    tar -xpf "$backup_dir/runtime.tar"
    trap - EXIT
    echo "Runtime data preserved. Backup retained at $backup_dir/runtime.tar"
fi

mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs

# Preserve .env, APP_KEY, uploads, and existing database records.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress
php artisan config:clear
php artisan migrate --force
if [[ ! -L public/storage ]]; then
    php artisan storage:link
fi
# Do not cache config while InvitationController reads FRONTEND_URL via env().
echo "Deployed $deploy_sha"
