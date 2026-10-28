#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fail() { echo "PROD : $* — voir AGENTS.md et packaging/README.md." >&2; exit 1; }
[[ ${GITHUB_ACTIONS:-} == true ]] || fail 'construction uniquement dans GitHub Actions'
[[ ${GITHUB_REPOSITORY:-} == pigeonfou/gestion-projet ]] || fail 'dépôt non autorisé'
[[ ${GITHUB_REF:-} == refs/heads/extraction-oddworks-autonome ]] || fail 'branche de packaging obligatoire'
[[ ${GITHUB_WORKFLOW:-} == 'OddWorks - construire paquet Debian' ]] || fail 'workflow officiel obligatoire'
[[ ${GITHUB_WORKFLOW_REF:-} == pigeonfou/gestion-projet/.github/workflows/package-debian.yml@refs/heads/extraction-oddworks-autonome ]] || fail 'fichier de workflow non autorisé'
[[ ${RUNNER_OS:-} == Linux ]] || fail 'runner Linux requis'
. /etc/os-release
[[ ${ID:-} == ubuntu && ${VERSION_ID:-} == 24.04 ]] || fail 'Ubuntu 24.04 requis'
[[ ${GITHUB_SHA:-} =~ ^[0-9a-f]{40}$ ]] || fail 'SHA complet requis'
[[ $(git -C "$ROOT" rev-parse HEAD) == "$GITHUB_SHA" ]] || fail 'checkout différent du commit annoncé'
[[ -z $(git -C "$ROOT" status --porcelain --untracked-files=all) ]] || fail 'fichiers modifiés ou non enregistrés'
printf 'Source PROD autorisée : %s\n' "$GITHUB_SHA"
