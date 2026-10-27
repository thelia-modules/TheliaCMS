#!/usr/bin/env bash
# Plays a round of the determinism corpus: five runs of each brief, one after
# the other, each kept as the output of `thelia_cms:generate --dry-run`.
#
#   run-round.sh <round> [effort] [brief...]
#
# Run from the root of the host, with the bench imported (bench.json). The
# runs go to var/eval/runs/<round>/<brief>/<n>.txt of the host, out of the
# repository: they hold the bench data. Every run is paid.
set -euo pipefail

round="${1:?Name the round, such as round-0.}"
effort="${2:-}"
shift $(( $# > 1 ? 2 : 1 ))
briefs=("$@")
[ ${#briefs[@]} -eq 0 ] && briefs=(01 02 03 04 05 06 07 08 09 10 11 12 13 14 15 16)

module=local/modules/TheliaCMS/docs/evaluation
out="var/eval/runs/$round"
runs="${RUNS:-5}"

page_id() {
    ddev mysql -N -e "SELECT id FROM cms_page_i18n WHERE locale = 'fr_FR' AND slug = '$1' LIMIT 1"
}

for brief in "${briefs[@]}"; do
    case "$brief" in
        0*|10) target=(--page "$(page_id "eval-$brief")" --locale fr_FR --brief "$module/briefs/$brief.md") ;;
        14) target=(--item "product:$(ddev mysql -N -e "SELECT id FROM product WHERE ref = 'PROD001'")" --locale fr_FR --brief "$module/determinism/briefs/14.md") ;;
        15) target=(--page "$(page_id eval-15)" --locale en_US --translate-from fr_FR) ;;
        *) target=(--page "$(page_id "eval-$brief")" --locale fr_FR --brief "$module/determinism/briefs/$brief.md") ;;
    esac

    mkdir -p "$out/$brief"

    for n in $(seq 1 "$runs"); do
        file="$out/$brief/$n.txt"
        [ -s "$file" ] && grep -q ' USD, ' "$file" && continue

        args=("${target[@]}" --dry-run)
        [ -n "$effort" ] && args+=(--effort "$effort")

        if ddev exec php Thelia thelia_cms:generate "${args[@]}" > "$file" 2>&1; then
            echo "$round $brief/$n: $(grep -o '[0-9.]* USD, [0-9.]* s' "$file")"
        else
            echo "$round $brief/$n: FAILED (kept in $file)"
        fi
    done
done
