#!/usr/bin/env bash
#
# sync-watch.sh — equivalente Mac di "Keep remote directory up to date" (WinSCP).
#
#   fswatch  osserva la cartella locale
#   lftp     carica sul server i file cambiati (ftp / ftps / sftp)
#
# Uso:
#   tools/sync-watch.sh --check            prova la connessione ed elenca il remoto (NON carica)
#   tools/sync-watch.sh --once             allineamento iniziale (carica i piu' recenti)
#   tools/sync-watch.sh                     watch continuo (Ctrl-C per fermare)
#   tools/sync-watch.sh --dry-run           mostra cosa farebbe, senza caricare nulla
#   tools/sync-watch.sh --target prod       solo produzione
#   tools/sync-watch.sh --target both       produzione E staging insieme
#
# Config in tools/sync.conf (NON versionato: contiene la password).
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF="$REPO_ROOT/tools/sync.conf"

TARGET="staging"; MODE="watch"; DRY_RUN=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --once)    MODE="once";  shift ;;
    --check)   MODE="check"; shift ;;
    --watch)   MODE="watch"; shift ;;
    --dry-run) DRY_RUN=1;    shift ;;
    --target)  TARGET="${2:?--target richiede staging|prod|both}"; shift 2 ;;
    -h|--help) sed -n '2,16p' "$0"; exit 0 ;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 2 ;;
  esac
done

[[ -f "$CONF" ]] || { echo "ERRORE: manca $CONF — copia tools/sync.conf.example e compilalo." >&2; exit 1; }
# shellcheck disable=SC1090
source "$CONF"
: "${PROTOCOL:?manca PROTOCOL in sync.conf}" "${HOST:?manca HOST}" "${USERNAME:?manca USERNAME}" "${PASSWORD:?manca PASSWORD}"

case "$TARGET" in
  staging) LOCAL_DIR="$REPO_ROOT/${STAGING_LOCAL:?}"; REMOTE_DIR="${STAGING_REMOTE:?}" ;;
  prod)    LOCAL_DIR="$REPO_ROOT/${PROD_LOCAL:?}";    REMOTE_DIR="${PROD_REMOTE:?}" ;;
  both)
    # staging vive dentro il tree di prod: un solo fswatch copre entrambi,
    # poi ogni file viene instradato al server giusto (vedi route_remote).
    LOCAL_DIR="$REPO_ROOT/${PROD_LOCAL:?}"; REMOTE_DIR="${PROD_REMOTE:?}"
    : "${STAGING_REMOTE:?}"
    if [[ "$REPO_ROOT/${STAGING_LOCAL:?}" != "$REPO_ROOT/$PROD_LOCAL/staging" ]]; then
      echo "ERRORE: --target both presuppose STAGING_LOCAL = \$PROD_LOCAL/staging." >&2
      echo "        Trovato invece: STAGING_LOCAL=$STAGING_LOCAL, PROD_LOCAL=$PROD_LOCAL" >&2
      echo "        Usa --target prod e --target staging separatamente." >&2; exit 2
    fi ;;
  *) echo "ERRORE: --target deve essere 'staging', 'prod' o 'both'." >&2; exit 2 ;;
esac
LOCAL_DIR="${LOCAL_DIR%/}"
[[ -d "$LOCAL_DIR" ]] || { echo "ERRORE: cartella locale inesistente: $LOCAL_DIR" >&2; exit 1; }

command -v lftp >/dev/null || { echo "ERRORE: manca lftp — brew install lftp" >&2; exit 1; }

# ── File che NON devono MAI salire: credenziali e contenuti generati dal server ──
#    (percorsi relativi alla cartella sorvegliata)
EXCLUDE_REGEX='(^|/)(\.git|\.DS_Store|node_modules)(/|$)|(^|/)inc/config\.php$|(^|/)\.env|(^|/)(uploads|images)(/|$)|\.log$|performance_log\.txt$'

# Il tree di staging vive DENTRO quello di produzione (web/htdocs/staging/):
# sincronizzando 'prod' va escluso, altrimenti staging finirebbe dentro il sito live.
if [[ "$TARGET" == "prod" ]]; then
  EXCLUDE_REGEX="(^|/)staging(/|\$)|$EXCLUDE_REGEX"
fi

# Stessi criteri, nella sintassi -x di lftp mirror
# NB: macOS ha bash 3.2, che NON supporta '${array[*]@Q}' (lo ignora in silenzio,
#     lasciando i pattern senza apici). Le virgolette le mettiamo quindi a mano.
MIRROR_X_PATTERNS=(
  '(^|/)\.git(/|$)'       '(^|/)\.DS_Store$'
  '(^|/)inc/config\.php$' '(^|/)\.env'
  '(^|/)uploads(/|$)'     '(^|/)images(/|$)'
  '\.log$'                'performance_log\.txt$'
)
[[ "$TARGET" == "prod" ]] && MIRROR_X_PATTERNS+=( '(^|/)staging(/|$)' )

case "$PROTOCOL" in
  ftp)  URL="ftp://$HOST";  SSL_SETUP=$'set ftp:ssl-force no\n' ;;
  ftps) URL="ftp://$HOST";  SSL_SETUP=$'set ftp:ssl-force yes\nset ftp:ssl-protect-data yes\nset ssl:verify-certificate no\n' ;;
  sftp) URL="sftp://$HOST"; SSL_SETUP="" ;;
  *) echo "ERRORE: PROTOCOL deve essere ftp, ftps o sftp (trovato: $PROTOCOL)." >&2; exit 2 ;;
esac

# Esegue comandi lftp da un file temporaneo con permessi 600:
# la password non compare mai nella lista dei processi (ps).
run_lftp() {
  local body="$1" tmp rc=0
  tmp="$(mktemp "${TMPDIR:-/tmp}/lftp.XXXXXX")"
  chmod 600 "$tmp"
  {
    printf '%s' "$SSL_SETUP"
    printf 'open %s\n' "$URL"
    printf 'user "%s" "%s"\n' "${USERNAME//\"/\\\"}" "${PASSWORD//\"/\\\"}"
    printf '%s\n' "$body"
  } > "$tmp"
  lftp -f "$tmp" || rc=$?
  rm -f "$tmp"
  return $rc
}

# Cita un percorso per lftp (apici singoli)
q() { printf "'%s'" "${1//\'/\'\\\'\'}"; }

echo "────────────────────────────────────────────────────────"
echo " target : $TARGET"
echo " locale : $LOCAL_DIR"
if [[ "$TARGET" == "both" ]]; then
  echo " remoto : $URL → $REMOTE_DIR  (prod)"
  echo "                   → $STAGING_REMOTE  (staging)"
else
  echo " remoto : $URL → $REMOTE_DIR"
fi
[[ $DRY_RUN -eq 1 ]] && echo " MODO   : DRY-RUN — nessun file verra' caricato"
echo "────────────────────────────────────────────────────────"

build_mirror_x() {
  MIRROR_X=""
  local pat
  for pat in "${MIRROR_X_PATTERNS[@]}"; do MIRROR_X="$MIRROR_X -x $(q "$pat")"; done
  # 'both' fa due mirror distinti: in quello di prod staging va escluso a mano.
  if [[ "${1:-}" == "skip-staging" ]]; then
    MIRROR_X="$MIRROR_X -x $(q '(^|/)staging(/|$)')"
  fi
  # NB: con 'set -e' una funzione che termina con un '&&' non soddisfatto ritorna 1
  # e fa uscire lo script — da qui il return esplicito.
  return 0
}

do_mirror() {   # $1 = dir locale, $2 = dir remota, $3 = etichetta, $4 = skip-staging?
  local dry=""; if [[ $DRY_RUN -eq 1 ]]; then dry="--dry-run"; fi
  build_mirror_x "${4:-}"
  echo "[$(date '+%H:%M:%S')] mirror $3: $(basename "$1") → $2"
  run_lftp "set mirror:parallel-transfer-count 3
mirror -R --only-newer --no-perms $dry$MIRROR_X $(q "$1") $(q "$2")
bye"
}

do_check() {   # $1 = dir remota, $2 = etichetta — sola lettura, non scrive nulla
  echo
  echo "── $2 → $1 ─────────────────────────────"
  if run_lftp "cd $(q "$1")
pwd
cls -1 --sort=name
bye"; then
    echo "   ✓ connessione OK, cartella remota raggiungibile"
  else
    echo "   ✗ FALLITO: controlla HOST/USERNAME/PASSWORD/PROTOCOL o il percorso remoto" >&2
    return 1
  fi
  return 0
}

if [[ "$MODE" == "check" ]]; then
  echo "[$(date '+%H:%M:%S')] prova di connessione — nessun file verra' caricato"
  RC=0
  if [[ "$TARGET" == "both" ]]; then
    do_check "$REMOTE_DIR"     "[prod]"    || RC=1
    do_check "$STAGING_REMOTE" "[staging]" || RC=1
  else
    do_check "$REMOTE_DIR" "[$TARGET]" || RC=1
  fi
  echo
  [[ $RC -eq 0 ]] && echo "Tutto a posto. Passo successivo: --once --dry-run" \
                  || echo "Correggi tools/sync.conf e riprova." >&2
  exit $RC
fi

if [[ "$MODE" == "once" ]]; then
  echo "[$(date '+%H:%M:%S')] mirror completo (solo file piu' recenti del remoto)…"
  if [[ "$TARGET" == "both" ]]; then
    do_mirror "$LOCAL_DIR"          "$REMOTE_DIR"     "[prod]"    skip-staging
    do_mirror "$LOCAL_DIR/staging"  "$STAGING_REMOTE" "[staging]"
  else
    do_mirror "$LOCAL_DIR" "$REMOTE_DIR" "[$TARGET]"
  fi
  echo "[$(date '+%H:%M:%S')] fatto."
  exit 0
fi

command -v fswatch >/dev/null || { echo "ERRORE: manca fswatch — brew install fswatch" >&2; exit 1; }

upload_one() {
  local abs="$1" rel ts; ts="$(date '+%H:%M:%S')"
  rel="${abs#"$LOCAL_DIR"/}"
  [[ "$rel" == "$abs" ]] && return              # evento fuori dalla cartella sorvegliata

  if [[ "$rel" =~ $EXCLUDE_REGEX ]]; then echo "[$ts] — escluso   $rel"; return; fi
  # Le cancellazioni locali NON vengono propagate: togliere file dal server resta manuale.
  if [[ ! -f "$abs" ]]; then echo "[$ts] — rimosso in locale, remoto intatto: $rel"; return; fi
  if [[ $DRY_RUN -eq 1 ]]; then echo "[$ts] DRY-RUN → $rel"; return; fi

  # In modalita' 'both' i file sotto staging/ vanno sul remoto di staging, gli altri su prod.
  local base="$REMOTE_DIR" routed="$rel" label=""
  if [[ "$TARGET" == "both" ]]; then
    if [[ "$rel" == staging/* ]]; then base="$STAGING_REMOTE"; routed="${rel#staging/}"; label=" [staging]"
    else label=" [prod]"; fi
  fi

  local sub remote_path; sub="$(dirname "$routed")"
  remote_path="$base"; [[ "$sub" != "." ]] && remote_path="$base/$sub"

  if run_lftp "mkdir -p $(q "$remote_path")
cd $(q "$remote_path")
put -O . $(q "$abs")
bye" >/dev/null 2>&1; then
    echo "[$ts] ✓ caricato$label $routed"
  else
    echo "[$ts] ✗ ERRORE$label   $routed" >&2
  fi
}

echo "In ascolto… (Ctrl-C per fermare)"
fswatch -0 --latency 1 --event Created --event Updated --event Renamed --event Removed "$LOCAL_DIR" \
| while IFS= read -r -d '' changed; do upload_one "$changed"; done
