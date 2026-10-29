#!/usr/bin/env bash
set -Eeuo pipefail

REPO_URL="https://github.com/ahvazgsm/ahwazbot.git"
APP_DIR="/opt/ahwazbot"
LOG_FILE="/var/log/ahwazbot-install.log"

log() { printf '[%s] %s\n' "$(date '+%F %T')" "$*" | tee -a "$LOG_FILE"; }
die() { log "ERROR: $*"; exit 1; }
trap 'rc=$?; log "ERROR: installer stopped at line ${LINENO} (exit ${rc})"; exit "$rc"' ERR

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root: sudo bash install_ahwazbot.sh"
  exit 1
fi

mkdir -p "$(dirname "$LOG_FILE")"
touch "$LOG_FILE"
chmod 600 "$LOG_FILE"

echo "=================================================="
echo " AhwazBot - safe Ubuntu/Debian installer"
echo " Source: $REPO_URL"
echo " Target: $APP_DIR"
echo "=================================================="
echo "It clones the repository and installs dependencies based on manifests found."
echo "It does not delete databases or overwrite an existing /opt/ahwazbot folder."
echo
read -r -p "Continue? Type YES: " answer
[[ "$answer" == "YES" ]] || die "Cancelled by user."

command -v apt-get >/dev/null 2>&1 || die "Requires Ubuntu/Debian with apt-get."
export DEBIAN_FRONTEND=noninteractive
log "Installing basic tools"
apt-get update
apt-get install -y --no-install-recommends ca-certificates git curl python3 python3-venv python3-pip

[[ ! -e "$APP_DIR" ]] || die "$APP_DIR already exists. Nothing was overwritten; back it up or choose another target."

TMP_DIR="$(mktemp -d /tmp/ahwazbot-clone.XXXXXX)"
cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

log "Cloning repository from GitHub"
git clone --depth 1 "$REPO_URL" "$TMP_DIR/repo" || die "Clone failed. Check network access and repository visibility."
[[ -d "$TMP_DIR/repo/.git" ]] || die "Clone verification failed."
[[ -n "$(find "$TMP_DIR/repo" -mindepth 1 -maxdepth 1 -print -quit)" ]] || die "Repository appears empty."

mv "$TMP_DIR/repo" "$APP_DIR"
chown -R root:root "$APP_DIR"
chmod -R go-w "$APP_DIR"
cd "$APP_DIR"
log "Source installed at $APP_DIR"

for f in README.md README.rst README.txt readme.md; do
  if [[ -f "$f" ]]; then
    log "Project documentation found: $f"
    break
  fi
done

PYTHON_ENTRY=""
for f in main.py bot.py app.py run.py; do
  if [[ -f "$f" ]]; then PYTHON_ENTRY="$f"; break; fi
done

if [[ -f "requirements.txt" || -f "pyproject.toml" ]]; then
  log "Detected Python project; creating virtual environment"
  python3 -m venv "$APP_DIR/.venv"
  "$APP_DIR/.venv/bin/python" -m pip install --upgrade pip
  if [[ -f "requirements.txt" ]]; then
    "$APP_DIR/.venv/bin/pip" install -r requirements.txt
  else
    "$APP_DIR/.venv/bin/pip" install .
  fi
  if [[ -n "$PYTHON_ENTRY" ]]; then
    echo
    echo "Possible Python entrypoint: $APP_DIR/$PYTHON_ENTRY"
    echo "Review the README and required environment variables before running:"
    echo "  cd $APP_DIR && ./.venv/bin/python $PYTHON_ENTRY"
  else
    log "No standard Python entrypoint found; review project documentation."
  fi
fi

if [[ -f "package.json" ]]; then
  log "Detected Node.js project; installing Node.js and npm"
  apt-get install -y --no-install-recommends nodejs npm
  if [[ -f "package-lock.json" ]]; then npm ci --omit=dev; else npm install --omit=dev; fi
  echo "Review package.json scripts before starting the application. No service was guessed."
fi

if [[ -f "composer.json" ]]; then
  log "Detected composer.json. PHP version/extensions were not guessed."
  echo "Review the README, install the required PHP packages and then run composer install."
fi

if [[ ! -f "requirements.txt" && ! -f "pyproject.toml" && ! -f "package.json" && ! -f "composer.json" ]]; then
  log "No recognized dependency manifest found; dependencies were not guessed."
fi

echo
log "Installation preparation finished. No database or existing app data was deleted."
echo "Project: $APP_DIR"
echo "Review the project's README/configuration before launching."
echo "Keep bot tokens and passwords out of GitHub; use environment variables or a protected .env file."
