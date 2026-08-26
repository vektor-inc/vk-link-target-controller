# VK Link Target Controller

[![Build Status](https://travis-ci.org/vektor-inc/vk-link-target-controller.svg?branch=master)](https://travis-ci.org/vektor-inc/vk-link-target-controller)

=========================

新着情報一覧などのリストで、タイトルのリンクをクリックした際に詳細ページにリンクするのではなく、特定のページや外部のページにリンク出来るようにするWordPress用プラグインです。

=========================

#VK Link Target Controller

##Plugin presentation

VK Link Target Controller enables to redirect your visitors to another content than the post content when they click on the post title that displays on the Recent Posts list or the Archives Page.

## Example of use

Let's say you have a new product for sale on eBay or Etsy. 

You find it annoying to write a complete post entry on your blog (or WordPress powered website) to explain you have a new product to sell there and would like your visitors to access directly the product page.

With VK Link Target Controller your visitors will access directly that product page when clicking on the post title.
Fast redirection to the product you want to sell!

## Installation and default settings

1. Install the plugin and activate it like other WordPress plugins.
2. Go to VK Link Target Controller settings screen (Settings > Link Target Controller).
3. Select the post types where you want to use VK Link Target Controller.

By default **none of your post types are selected**.

VK Link Target Controller supports custom post types.

## Adding a link for redirection

VK Link Target Controller adds a meta box to your posts edit screen under the main content editing area.

If you need a redirection for this post just fill in the field with the destination url.

VK Link Target Controller supports both
* external links like http://bizvektor.com/en/ (will link to http://bizvektor.com/en/)
* internal links like /theme-documentation/bizvektor-quick-start/ (will link to http://mywebsite.com/theme-documentation/bizvektor-quick-start/)

For external (or absolute) urls both http:// and trailing slash are optional.

For internal (relative) urls you need to add a slash "/" at the beginning as shown on example above.

If you want the link to open in a new window then check the corresponding option.

## Additional information

Japanese characters in urls are supported.

##Theme compatibility

VK Link Target Controller adds a filter on the `the_permalink()` WordPress function, which means the redirection won't work if your theme uses another function, for example `get_permalink()` to display the links.

In order to have the link opened in a new window VK Link Target Controller needs a theme with the post id as class on the <a> parent element.

Your theme probably has it if it follows the WordPress Theme recommendations.

Example:
```php
<div class="post-item post-block front-page-list post-<?php the_ID(); ?>" id="post-<?php the_ID(); ?>">
 <a href="<?php the permalink(); ?>">
  <?php the_title(); ?>
 </a>
</div>
```

## Development: e2e tests (開発者向け: e2e テストの実行)

Playwright を使ったブラウザ e2e テストを `tests/e2e/specs` に用意しています。
（何をするテストかは各 spec ファイル冒頭のコメントを参照してください）

### 事前準備 (Setup)

1. 依存関係をインストールする。
   ```sh
   npm install
   ```
2. `.wp-env.override.json` を用意する（**開発者ごとのポート衝突を避けるため gitignore 済み**）。
   雛形 `.wp-env.override.example.json` をコピーして、必要ならポート番号を自分の環境用に書き換える。
   ```sh
   cp .wp-env.override.example.json .wp-env.override.json
   ```
3. Playwright が使うブラウザ（Chromium）をインストールする（初回のみ）。
   ```sh
   npx playwright install chromium
   ```
4. wp-env を起動する。
   ```sh
   npx wp-env start
   ```
   e2e テストは wp-env の「tests」環境（`.wp-env.override.example.json` の
   `env.tests.port`）に対して実行する想定です。テスト専用のカスタム投稿タイプ
   （`tests/e2e/mu-plugins/register-test-cpt.php`）は、`.wp-env.json` の
   `env.tests.mappings` 設定により **tests 環境にのみ mu-plugin としてマウント** され、
   通常の開発用サイト（development 環境）には表示されません。
5. **ブロックエディタ用パネル（`src/index.js`）をビルドする。**
   ```sh
   npm run build:block
   ```
   `npm run build` ではなく **`npm run build:block`** を使うこと。
   `build`（`package.json`）は `build:block` の後に `jsmin`（`terser` で
   `js/script.min.js` を生成する処理）まで実行するが、`js/script.min.js` は
   `.gitignore` されておらず **git の追跡対象**であり、e2e が必要とするのは
   `build/index.asset.php` と `build/index.js` だけで `js/script.min.js` とは
   無関係。`build` で e2e を回すと、terser のバージョン差などで
   `js/script.min.js` に意図しない差分が生じ、テストを実行しただけで
   作業ツリーが汚れる可能性があるため、e2e 用には `build:block` に限定する。

   `vk-link-target-controller.php` の `add_link_meta_box()` は、`build/index.asset.php`
   が存在する場合のみ、レガシーの classic meta box を非表示化して React サイドバー
   パネル（`src/index.js`）に切り替える。**ビルドが無い状態で e2e を実行すると、
   テストが検証すべき React パネルではなく別の UI（classic meta box）に対して
   操作してしまい、それでもテストが緑になる**という罠があるため、
   `tests/e2e/specs/cpt-meta-save.spec.js` は実行前にビルド成果物の有無を
   チェックし、無ければ理由を添えて即座に失敗するようになっている。
   `npm run test:e2e`（後述）はこのビルドを自動的に実行するが、
   `npx playwright test` を直接叩く場合は事前に必ず `npm run build:block` を
   実行すること。

### 実行 (Run)

`npm run test:e2e` は実行のたびに `npm run build:block`（`jsmin` を含まない、
軽量なパネル1ファイルのビルドで数百ミリ秒程度）を先に実行してからテストを
走らせるため、ビルドし忘れによる誤検知を心配する必要はありません。`tests` 環境の
URL（既定は `http://localhost:8889` ですが、`.wp-env.override.json` で変更している
場合はそのポートに合わせる）を `WP_BASE_URL` として指定して実行します。

```sh
WP_BASE_URL=http://localhost:8889 npm run test:e2e
```

`.wp-env.override.example.json` の設定例（`env.tests.port: 8897`）を使っている場合は、
以下のように実行します。

```sh
WP_BASE_URL=http://localhost:8897 npm run test:e2e
```

`WP_BASE_URL` を省略した場合は `playwright.config.js` のフォールバック値
（`http://localhost:8889`）が使われますが、環境依存でテストが失敗しやすいため
明示することを推奨します。
