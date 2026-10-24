# かんたん編集画面(持ち主向け)の設計案(T.36)

2026-10-24。サイト所有者の「Fable max」の指示で Fable が設計し(読み取りだけ。WordPress 7.1.3 のソースを読んだ)、Claude が整理した。**ここに書いたのは提案で、決まったことではない。** 決めごとは末尾の一覧で、1 件ずつ選択式で聞く。裁定は TASKS.md・design-log・spec に反映する。

サイト所有者の要望(原文): 「編集画面は仕様の操作できるようにして、画像とかドラッグアンドドロップだし、その他もいい感じにしたいよ」「安定したプラグインとかCDNにあるJSとか活用したい気がする」「できるんだったらAstro とかのビルドしてデプロイの仕組み載せたりできないかね？」

## 1. 骨子(提案)

1. 管理画面(`/wp-admin/`)の中に 1 画面「マイページ」を作る。WordPress 同梱の React と部品(`wp.components`・`wp.coreData`・`wp.apiFetch`・`wp.mediaUtils`)を使い、保存はすべて標準の REST(`wp/v2/posts`・`media`・`settings`・分類)。独自の保存口は作らない。特権管理者でなければ、ログイン後にここへ直行する。
2. データは「標準の項目 + 登録した投稿メタ + 分類 + サイト設定」に置く。独自ブロックは描くだけで、属性にデータを持たない。かんたん画面・表示・ブロックエディタ・WP-CLI が同じ 1 か所を読む。
3. ビルドは `@wordpress/scripts`(`wp-scripts build`)1 本。独自ブロック 4 種と画面を同じ仕組みでビルドし、`build/` をコミットして、いまの `scripts/deploy.sh` で送る。サーバに node は要らない。
4. 外部の JS は原則使わない。足りない「ドラッグで並べ替え」だけ SortableJS(MIT、44KB、タッチ対応)を npm で同梱する(バージョン固定)。CDN からは読まない。
5. Astro は出番が無い(公開側は WordPress のテーマ、編集画面は wp-admin の中)。
6. 持ち主の権限はコア標準のサイト管理者のまま。見せたくないメニューは数行で隠す(権限は変えない)。
7. 見積り: このプロジェクトで最大の自作になる(PHP 300〜500 行、JS 800〜1,500 行)。

## 2. 持ち主ができる操作

- **プロフィール**: サークル名(`blogname`)、一行の説明(`blogdescription`)、リード文 ja/en(独自の設定)、アイコン(`site_icon`)。成人向けの目印は運営者(T.21)。公開状態の表示(T.11)。
- **リンク集**: 「販売ページ」「お知らせ・活動情報」の 2 つの一覧。足す・消す・並べ替え。1 件はサービス種別(16 種から選ぶ)・URL(`https:` のみ)・表示名・アフィリエイトの有無。アフィリエイトの注記は自動。
- **作品**(1 件 = 1 投稿): 一覧(書影・タイトル・状態)、追加・編集・削除。タイトル ja/en、作家(候補つき、複数可、その場で新規)、あらすじ ja/en、書影(ドロップ / 選ぶ)、発売日時(予告の切り替え時刻)、発売日の表記(空なら自動)、販売先リンクの一覧、**サンプル画像(まとめてドロップ。スマホは複数選択。ドラッグで並べ替え、← → ボタンも。初期の並びはファイル名の連番順)**、下書き / 公開(予告は自動で付く印)、「この作品を見る」。
- **見た目**: テーマを選ぶ。配色・フォントはサイトエディタへのボタン。
- 入れないもの: 固定ページ・メニュー・テンプレート(ブロックエディタ / サイトエディタを「くわしい編集」として残す)、ユーザの追加、ツール、運営者の操作、コメント(止めてある)、アプリケーションパスワード。

## 3. 作り方の比較

| | A 管理画面の 1 画面(推奨) | B 標準エディタを固定 | C 公開ページ上 | D 配布プラグインのフォーム(SCF) |
|---|---|---|---|---|
| 自作 | 多(PHP 300–500 + JS 800–1,500) | 少(PHP 150–250 + JS 200–400) | 最多 | 少(PHP 200 + JSON)+ 依存 |
| 更新で壊れにくさ | 中〜高(公開 API だけ) | 高(見え方は要確認) | 低〜中(テーマと衝突) | 中(ACF/SCF の 2 系統問題) |
| スマホ | 良 | 可(楽ではない) | 良 | 可 |
| ドロップ / 並べ替え | DropZone / SortableJS | ギャラリーブロック | 要自作 | メディアのモーダル |
| 分かりやすさ | 高 | 中 | 高 | 中 |

B のテンプレートロック(`template_lock => 'contentOnly'`)は、A を選んでも「くわしい編集」側に入れて、作品カードを消す事故を防ぐ。

使わなかったもの: DataViews / DataForm(7.1.3 ではスクリプトとして同梱されていない)、7.1 のブラウザ側の縮小(ブロックエディタの画面にだけ Document-Isolation-Policy が出る。自前の画面ではサーバ側で縮める)、WordPress のスマホアプリ(XML-RPC が要る。止めた)。

### JS の取り方

| | 利点 | 欠点 |
|---|---|---|
| WordPress 同梱(externals) | 配備物に JS 本体を含めない。管理画面と見た目が揃い、更新で新しくなる | 並べ替えの部品が無い |
| CDN | ファイルを持たない | 持ち主のブラウザが第三者へ通信する。障害・版の消滅・SRI の管理が要る |
| npm で同梱・固定 | リポジトリが正本。更新は自分の意思 | 年に数回 `npm update` |

候補: SortableJS 1.15.7(44KB、タッチ対応)、FilePond 4.32.12(116KB + CSS。アップロードが WordPress と二重)、Uppy v6(CDN 版は本番非推奨と公式)。

## 4. データの持ち方

| 作品の項目 | 置き場所 |
|---|---|
| タイトル ja / en | `post_title` / メタ `doujin_title_en` |
| 作家 | 分類 `artist`(タグ型、REST 公開)。既存サイトには配備後に一度 `wp rewrite flush` |
| あらすじ ja / en | `post_content`(段落ブロック)/ メタ `doujin_summary_en` |
| 書影 | アイキャッチ画像 |
| 発売日時 | メタ `doujin_release_at`(ISO 8601、JST) |
| 発売日の表記 | メタ `doujin_release_label` |
| 販売先リンク | メタ `doujin_shop_links`(配列、上限 30) |
| サンプル画像 | メタ `doujin_sample_ids`(添付 ID の配列、順 = 並び、上限 80)。添付の親も作品にする |
| 状態 | `post_status`(draft / publish)。予告は「公開 + 発売日時が未来」から導く |
| 並び | `post_date`。保存時に「公開日時 = 発売日時と今の早いほう」にする 1 ルール |

サイト: サークル名 `blogname`、説明 `blogdescription`、リード文 `doujin_profile_lead`、アイコン `site_icon`、リンク集 `doujin_profile_links`(`shops[]`・`accounts[]`)。型・上限・サニタイズは `register_post_meta()` / `register_setting()` の 1 か所に書き、どこから書いても同じ検査を通る。

採るなら spec §6.2(作品カードの属性にデータを持つ)と §6.4 の書き換えが要る。

## 5. 権限と見せないもの

- 権限は administrator のまま。Multisite では管理者でもプラグイン・テーマの導入、ファイル編集、`unfiltered_html`、ほかのサイトには触れない。
- `login_redirect` で、特権管理者でなければマイページへ。
- `remove_menu_page()` で、ダッシュボード・コメント・ユーザー・ツール・設定を隠す(特権管理者以外)。残すのはマイページ・メディア・外観・くわしい編集。URL を直打ちすれば入れる(隠すだけ)。

## 6. ビルド〜配備

初回(手元。node 24.15 以上): `cd mu-plugins/doujin-profile && npm install`

ふだん:

```
npm run build             # src/ → build/
git add -A && git commit  # build/ もコミットする
scripts/deploy.sh         # dry-run
scripts/deploy.sh --go    # rsync でさくらへ
```

GitHub Actions は「コミットした build/ がソースと一致するか」の検証だけにする(秘密情報なし)。Actions から配備すると鍵を GitHub に置くことになり、同じアカウントの別サイトまで被害が届きうる。

## 7. 決めごと(1 件ずつ選択式で。推奨を先頭に)

| # | 問い | 推奨 | ほかの案 |
|---|---|---|---|
| Q1 | 作り方 | 管理画面の 1 画面(WP の部品 + 標準 REST) | 標準エディタを固定 / SCF のフォーム / 公開ページ上 |
| Q2 | ビルド | `@wordpress/scripts` | ビルドなし / Vite + React |
| Q3 | 外部 JS | WP 同梱が主、足りない物だけ npm で同梱 | CDN / 一切使わない |
| Q4 | 並べ替え | SortableJS + ← → ボタン | 矢印だけ / FilePond |
| Q5 | アップロード | `wp.mediaUtils.uploadMedia()` + DropZone + 選ぶボタン | FilePond / Uppy |
| Q6 | 置き場所 | wp-admin の「マイページ」、ログイン後に直行 | 公開ページ上 / 独立した URL |
| Q7 | ほかのメニュー | 数行で隠す(権限は変えない) | 隠さない / マイページ以外に入れない |
| Q8 | 作品のデータ | 標準の項目 + 投稿メタ + 分類。ブロックは描くだけ | ブロックの属性(いまの spec) |
| Q9 | サンプルの並び | メタ `sample_ids`(配列順) | 添付の親 + menu_order / ファイル名順で固定 |
| Q10 | トップの並び | 公開日時順 + 保存時の 1 ルール | 発売日時のメタで / 手で並べ替え |
| Q11 | 公開状態 | 下書き / 公開。予告は自動の印 | 非公開も / 予約も |
| Q12 | リンク集 | サイト設定に 2 つの一覧 | フロントページのブロック属性 |
| Q13 | プロフィール | サイトのタイトル・アイコン(標準)+ リード文だけ独自 | ブロックの属性 |
| Q14 | 英語欄 | 「英語も入れる」を押したときだけ出す | 出さない / 常に出す |
| Q15 | 見た目 | テーマ一覧から選ぶボタン(小さな保存口が 1 つ) | リンクだけ / 配色も |
| Q16 | 書影 | ドロップ / 選ぶ、だけ | 外部 URL も / FANZA ID から自動も |
| Q17 | ブラウザ側の縮小 | しない(サーバで縮める) | 7.1 の仕組みを自前画面でも |
| Q18 | 自動化 | 手元でビルド → 配備。Actions は検証だけ | Actions から配備 / 自動化しない |
| Q19 | Astro | 使わない | 手引きのサイトに / 将来の静的書き出しに |
| Q20 | くわしい編集 | 残す + テンプレートロック | 残すがロックなし / 見せない |
| Q21 | 配布プラグインの併用 | 入れない | SCF でメタ欄だけ借りる |

## 8. 分からなかったこと

`site_icon` が REST の設定に載っているか / SCF の無料・有料の線引き / 自前画面でのブラウザ側の縮小 / React 19 への移行時の追随(externals なら再ビルドで済むはず)。

## 出典(すべて 2026-10-24)

- WordPress 7.1 Field Guide: https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/
- Block Templates: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/
- @wordpress/media-utils: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-media-utils/
- @wordpress/scripts: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/
- Secure Custom Fields: https://wordpress.org/plugins/secure-custom-fields/
- SortableJS: https://cdnjs.com/libraries/Sortable ・ FilePond: https://pqina.nl/filepond/docs/api/instance/properties/ ・ Uppy: https://uppy.io/docs/uppy/
- Astro: https://docs.astro.build/en/concepts/why-astro/ ・ vite-for-wp: https://github.com/kucrut/vite-for-wp
- さくら 国外 IP アドレスフィルタ: https://help.sakura.ad.jp/rs/2234/
