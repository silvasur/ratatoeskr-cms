#!/bin/sh

set -e

githooks_dir="$(git rev-parse --git-dir)"
for hook in hooks/*; do
    hook="${hook#hooks/}"
    if [ -f "$githooks_dir/hooks/$hook" ]; then
        mv "$githooks_dir/hooks/$hook" "$githooks_dir/hooks/own_$hook"
    fi

    echo '#!/bin/sh
        exec "$(git rev-parse --git-dir)/../git-hooks/loader.sh" '"$hook"' "$@"
    ' > "$githooks_dir/hooks/$hook"
    chmod +x "$githooks_dir/hooks/$hook"
done
