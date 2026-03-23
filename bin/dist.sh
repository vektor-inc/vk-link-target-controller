#!/bin/bash

set -ex

PLUGIN_NAME='vk-link-target-controller'
PLUGIN_DIR=$(cd "$(dirname "$(dirname "$0")")" && pwd)

dist_dir="${PLUGIN_DIR}/dist"
src_dir="${dist_dir}/${PLUGIN_NAME}"
ZIPBALL="${dist_dir}/${PLUGIN_NAME}.zip"

rm -rf "${src_dir}"
rm -f "${ZIPBALL}"
mkdir -p "${dist_dir}"

if [[ ! -f "${PLUGIN_DIR}/.distignore" ]]; then
    echo "Error: .distignore not found"
    exit 1
fi

rsync -av "${PLUGIN_DIR}/" "${src_dir}/" --exclude="dist/" --exclude-from="${PLUGIN_DIR}/.distignore"

cd "${dist_dir}"

zip -r "${ZIPBALL}" "${PLUGIN_NAME}/"

exit 0
