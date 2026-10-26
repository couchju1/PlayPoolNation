#!/usr/bin/env bash
# Deploy playpoolnation-core to the live site over SSH, then (optionally) load
# enrichment output into the review queue.
#
#   PPN_SSH=u123456789@1.2.3.4 PPN_WP_PATH=domains/playpoolnation.com/public_html tools/deploy.sh
#   tools/deploy.sh --import sioux-falls      # also run `wp ppn enrich import sioux-falls`
#   tools/deploy.sh --dry-run                 # show what would happen, change nothing
#
# Settings (environment, or tools/deploy.env next to this script):
#   PPN_SSH       user@host for SSH (required)
#   PPN_SSH_PORT  SSH port (Hostinger uses 65002; default 65002)
#   PPN_WP_PATH   WordPress root on the server, relative to the SSH home or absolute (required)
#   PPN_PLUGIN_DIR  where the plugin lives under wp-content (default novamira-sandbox, see the plugin README)
#
# What it does:
#   1. Checks the checkout is clean and on the commit you mean to ship, and runs the unit tests.
#   2. Uploads wordpress/playpoolnation-core/ (no vendor/, tests/ or caches) to a new folder
#      next to the live one, writes DEPLOYED_COMMIT, then swaps the folders in one `mv`, so the
#      site never runs a half-uploaded copy. The previous copy is kept as playpoolnation-core.prev.
#   3. Runs `wp ppn migrate` (creates the review-queue table on first deploy).
#   4. With --import <city>: uploads tools/enrich/out/<city>/ to ~/ppn-enrich/out/<city>/ and runs
#      `wp ppn enrich import <city>`. Nothing on the public site changes until you approve items
#      under PlayPoolNation → Review.
#   5. Reminds you to purge the Hostinger CDN (LiteSpeed purges itself on the next web request).
#
# Roll back: tools/deploy.sh --rollback (swaps playpoolnation-core.prev back in).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
[ -f "$ROOT/tools/deploy.env" ] && . "$ROOT/tools/deploy.env"

DRY=0
IMPORT=""
ROLLBACK=0
SKIP_TESTS=0
while [ $# -gt 0 ]; do
	case "$1" in
		--dry-run) DRY=1 ;;
		--import) IMPORT="${2:?--import needs a city slug}"; shift ;;
		--rollback) ROLLBACK=1 ;;
		--skip-tests) SKIP_TESTS=1 ;;
		-h|--help) sed -n '2,30p' "$0"; exit 0 ;;
		*) echo "Unknown option: $1" >&2; exit 2 ;;
	esac
	shift
done

: "${PPN_SSH:?Set PPN_SSH=user@host (or put it in tools/deploy.env)}"
: "${PPN_WP_PATH:?Set PPN_WP_PATH to the WordPress root on the server}"
PORT="${PPN_SSH_PORT:-65002}"
PLUGIN_DIR="${PPN_PLUGIN_DIR:-novamira-sandbox}"
TARGET="$PPN_WP_PATH/wp-content/$PLUGIN_DIR/playpoolnation-core"

say() { printf '\n== %s\n' "$*"; }
remote() {
	if [ "$DRY" = 1 ]; then printf '[dry-run] ssh: %s\n' "$*"; else ssh -p "$PORT" "$PPN_SSH" "$@"; fi
}

if [ "$ROLLBACK" = 1 ]; then
	say "Rolling back to the previous copy"
	remote "set -e; cd '$PPN_WP_PATH/wp-content/$PLUGIN_DIR'; test -d playpoolnation-core.prev; mv playpoolnation-core playpoolnation-core.bad; mv playpoolnation-core.prev playpoolnation-core; rm -rf playpoolnation-core.bad; cat playpoolnation-core/DEPLOYED_COMMIT"
	echo "Rolled back. Purge the Hostinger CDN in hPanel."
	exit 0
fi

say "Checking the checkout"
cd "$ROOT"
if [ -n "$(git status --porcelain -- wordpress/playpoolnation-core)" ]; then
	echo "wordpress/playpoolnation-core has uncommitted changes. Commit or stash them first." >&2
	exit 1
fi
COMMIT="$(git rev-parse --short HEAD)"
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
echo "Deploying $COMMIT ($BRANCH): $(git log -1 --format=%s)"
[ "$BRANCH" = "main" ] || echo "Note: not on main."

if [ "$SKIP_TESTS" = 0 ]; then
	say "Running unit tests"
	if [ -x wordpress/playpoolnation-core/vendor/bin/phpunit ]; then
		(cd wordpress/playpoolnation-core && vendor/bin/phpunit -c tests/phpunit.xml)
	else
		echo "PHPUnit not installed (run: cd wordpress/playpoolnation-core && composer install). Skipping PHP tests."
	fi
	find wordpress/playpoolnation-core -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l >/dev/null
	echo "PHP syntax OK."
fi

say "Uploading the plugin"
STAGE="$TARGET.new"
remote "rm -rf '$STAGE' && mkdir -p '$STAGE'"
if [ "$DRY" = 1 ]; then
	echo "[dry-run] would upload wordpress/playpoolnation-core/ to $PPN_SSH:$STAGE"
else
	tar -C wordpress/playpoolnation-core \
		--exclude=./vendor --exclude=./tests --exclude=./.phpunit.cache --exclude=./composer.json --exclude=./composer.lock --exclude=./.gitignore \
		-czf - . | ssh -p "$PORT" "$PPN_SSH" "tar -xzf - -C '$STAGE'"
fi
remote "set -e
	echo '$COMMIT' > '$STAGE/DEPLOYED_COMMIT'
	cd '$(dirname "$TARGET")'
	rm -rf playpoolnation-core.prev
	if [ -d playpoolnation-core ]; then mv playpoolnation-core playpoolnation-core.prev; fi
	mv playpoolnation-core.new playpoolnation-core
	echo \"Live: \$(cat playpoolnation-core/DEPLOYED_COMMIT)\""

say "Running migrations"
remote "cd '$PPN_WP_PATH' && wp ppn migrate"

if [ -n "$IMPORT" ]; then
	SRC="tools/enrich/out/$IMPORT"
	[ -d "$SRC" ] || { echo "No $SRC. Run: python3 tools/enrich/enrich.py run $IMPORT" >&2; exit 1; }
	say "Loading $IMPORT into the review queue"
	remote "mkdir -p ppn-enrich/out/'$IMPORT'"
	if [ "$DRY" = 1 ]; then
		echo "[dry-run] would upload $SRC/*.json to $PPN_SSH:ppn-enrich/out/$IMPORT/"
	else
		tar -C "$SRC" --exclude='.tmp-*' -czf - . | ssh -p "$PORT" "$PPN_SSH" "tar -xzf - -C ppn-enrich/out/'$IMPORT'"
	fi
	remote "cd '$PPN_WP_PATH' && wp ppn enrich import '$IMPORT' --dir=\"\$HOME/ppn-enrich/out/$IMPORT\" && wp ppn enrich status"
fi

say "Done"
echo "Purge the Hostinger CDN in hPanel. LiteSpeed's page cache purges itself on the next web request."
[ -n "$IMPORT" ] && echo "Review: https://playpoolnation.com/wp-admin/admin.php?page=ppn-enrich-review&city=$IMPORT"
echo "Roll back with: tools/deploy.sh --rollback"
