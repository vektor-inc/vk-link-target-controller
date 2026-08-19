# Agent instructions

このリポジトリで Cloud Agent / Cursor Agent が作業するときの共通ルールです。

## PR 作成・更新（必須）

PR を作成または更新する前に、**必ず**次を実行してください。

1. `.github/PULL_REQUEST_TEMPLATE.md` を Read する
2. テンプレートの見出し・チェックリスト構造をそのまま残し、各セクションを埋める
3. `ManagePullRequest` の `body` には、テンプレート準拠の内容だけを渡す

**禁止**: テンプレートを読まずに PR 本文を独自フォーマットで書くこと。

### チェックリスト（PR 作成前）

- [ ] `.github/PULL_REQUEST_TEMPLATE.md` を Read した
- [ ] テンプレートの全セクションを埋めた
- [ ] 複数の意図の変更を 1 PR に混ぜていない

## ブランチ命名（Cloud Agent）

Cloud Agent がブランチを作る場合:

- プレフィックス: `cursor/`
- 形式: `cursor/<descriptive-name>-9368`

## その他

- 変更は必要最小限に留める
- 既存のコーディング規約・ツール（phpcs, phpunit, npm test など）に従う
- `readme.txt` の `Stable tag` と Changelog は、プラグインバージョンを上げる変更のときだけ更新する
