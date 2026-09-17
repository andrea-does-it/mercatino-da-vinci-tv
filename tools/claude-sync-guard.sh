#!/usr/bin/env bash
#
# claude-sync-guard.sh — hook PreToolUse.
#
# Avvisa quando si sta per modificare un file sotto web/htdocs/ mentre
# tools/sync-watch.sh e' in ascolto: in quel caso ogni salvataggio finisce
# subito sul server, quindi la modifica e' di fatto un deploy immediato.
#
#   --target staging  → avviso (non blocca)
#   --target prod/both → richiesta di conferma esplicita
#
# In entrambi i casi manda anche una notifica macOS, perche' i messaggi
# dell'hook non sempre sono visibili nella UI.
#
set -uo pipefail

INPUT="$(cat)"

# Percorso toccato: Edit/Write usano file_path, per Bash guardiamo il comando.
FILE_PATH="$(printf '%s' "$INPUT" | jq -r '.tool_input.file_path // empty' 2>/dev/null)"
if [[ -z "$FILE_PATH" ]]; then
  FILE_PATH="$(printf '%s' "$INPUT" | jq -r '.tool_input.command // empty' 2>/dev/null)"
fi

# Ci interessa solo il materiale che viene pubblicato sul sito.
case "$FILE_PATH" in
  *web/htdocs/*) ;;
  *) exit 0 ;;
esac

# Il watcher e' attivo? (esclude questo stesso hook e il grep)
PROCS="$(ps -Ao pid=,args= 2>/dev/null | grep 'sync-watch\.sh' | grep -v 'claude-sync-guard' | grep -v ' grep ')"
[[ -z "$PROCS" ]] && exit 0

# Quale target? --target <x>, altrimenti il default e' staging.
TARGET="$(printf '%s' "$PROCS" | sed -n 's/.*--target[ =]\{1,\}\([a-z]\{1,\}\).*/\1/p' | head -1)"
[[ -z "$TARGET" ]] && TARGET="staging"

case "$TARGET" in
  prod) WHERE="sul SITO IN PRODUZIONE" ;;
  both) WHERE="in PRODUZIONE e su staging" ;;
  *)    WHERE="su staging" ;;
esac

MSG="sync-watch.sh e' in esecuzione (--target $TARGET): salvando questo file finisce subito $WHERE."
CONTEXT="ATTENZIONE deploy automatico: $MSG Avvisa l'utente prima di procedere se la modifica e' parziale o tocca la produzione, e ricorda la regola del dual-tree (ogni modifica va rispecchiata in entrambi gli alberi)."

# Canale 1 — notifica macOS: visibile anche quando la UI non mostra i messaggi dell'hook.
osascript -e "display notification \"${MSG//\"/}\" with title \"Claude Code — sync attivo\" sound name \"Funk\"" >/dev/null 2>&1 || true

# Canale 2 — risposta all'hook.
if [[ "$TARGET" == "prod" || "$TARGET" == "both" ]]; then
  # Produzione: chiede conferma. Una modifica qui e' un deploy sul sito live.
  jq -cn --arg m "$MSG" --arg c "$CONTEXT" '{
    systemMessage: ("⚠️  " + $m),
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "ask",
      permissionDecisionReason: ("⚠️  DEPLOY IMMEDIATO IN PRODUZIONE — " + $m),
      additionalContext: $c
    }
  }'
else
  # Staging: solo avviso, non interrompe il lavoro.
  jq -cn --arg m "$MSG" --arg c "$CONTEXT" '{
    systemMessage: ("⚠️  " + $m),
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      additionalContext: $c
    }
  }'
fi
