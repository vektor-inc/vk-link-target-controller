#!/usr/bin/env bash

set -ex

# テーマのダウンロード
curl -sf https://downloads.wordpress.org/theme/lightning.zip -o theme.zip

# ダウンロードが成功したか確認
if [ $? -ne 0 ]; then
  echo "Error: Theme download failed."
  exit 1
fi

# テーマを展開するディレクトリの作成
mkdir -p ./temp/

# テーマの解凍
unzip theme.zip -d ./temp/themes

# 解凍が成功したか確認
if [ $? -ne 0 ]; then
  echo "Error: Failed to unzip theme.zip."
  exit 1
fi

# ZIPファイルの削除
rm -f theme.zip