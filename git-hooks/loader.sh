#!/bin/sh

set -e

hook="$1"
shift

gitdir="$(git rev-parse --git-dir)"
[ -x "$gitdir/hooks/own_$hook" ] && "$gitdir/hooks/own_$hook" "$@"
[ -x "$gitdir/../git-hooks/hooks/$hook" ] && "$gitdir/../git-hooks/hooks/$hook" "$@"
