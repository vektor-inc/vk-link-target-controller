#!/usr/bin/env bash

set -ex

# -o 実行結果をファイルへ出力
# -s ファイル出力時の進捗状況を非表示にする(エラーも非表示)
# curl -s $WP_THEME -o theme.zip
curl -sL https://downloads.wordpress.org/theme/lightning.zip -o theme.zip
# -d 指定したディレクトリに展開
unzip theme.zip -d tests/themes
# 展開したのでもとのzipファイルを削除 -f はエラーメッセージを表示しない
rm -f theme.zip