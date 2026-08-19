# Agent instructions

このリポジトリで Cloud Agent / Cursor Agent が作業するときの共通ルールです。

## PR 作成・更新（必須）

PR を作成または更新する前に、**必ず**次を実行してください。

1. `.github/PULL_REQUEST_TEMPLATE.md` を Read する
2. テンプレートの見出し・チェックリスト構造をそのまま残し、各セクションを埋める
3. `ManagePullRequest` の `body` には、テンプレート準拠の内容だけを渡す
4. **PR タイトルは日本語**で書く（英語タイトルは不可）

### PR タイトルの形式

既存 PR に合わせ、次の形式を使う:

```
[ カテゴリ ] 変更内容の要約
```

カテゴリ例: `不具合修正` / `機能追加` / `仕様変更` / `セキュリティ修正` / `開発環境` / `その他`

例:

- `[ その他 ] Tested up to を WordPress 7.1 に更新`
- `[ 開発環境 ] AGENTS.md を追加（PR テンプレート必須ルール）`

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
