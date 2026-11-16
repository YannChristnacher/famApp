#!/usr/bin/env bash
# Seeds Composer's download cache with phpstan/phpstan built from its git tag.
# Needed only where GitHub zipball downloads are blocked (Claude's cloud session):
# phpstan/phpstan has no git source on Packagist, so --prefer-source cannot fetch it.
# Usage: scripts/seed-phpstan-cache.sh <version>   e.g. scripts/seed-phpstan-cache.sh 2.3.6
set -euo pipefail

version="${1:?usage: $0 <phpstan version>}"
workdir="$(mktemp -d)"
trap 'rm -rf "$workdir"' EXIT

GIT_LFS_SKIP_SMUDGE=1 git clone -q --depth 1 --branch "$version" https://github.com/phpstan/phpstan "$workdir/src"
reference="$(git -C "$workdir/src" rev-parse HEAD)"
url="https://api.github.com/repos/phpstan/phpstan/zipball/${reference}"
cache_dir="$(composer config --global cache-files-dir)/phpstan/phpstan"
key="$(php -r "echo sha1('${url}');")"

mkdir -p "$cache_dir"
git -C "$workdir/src" archive --format=zip --prefix="phpstan-phpstan-${reference:0:7}/" -o "$cache_dir/${key}.zip" HEAD
echo "Seeded phpstan ${version} (${reference}) into ${cache_dir}/${key}.zip"
