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

## 7.1 決定(2026-10-24)

サイト所有者が選んだもの: Q1 = 管理画面の中の専用画面、Q3 = 足りないものだけ npm で同梱(CDN は使わない)、Q2・Q18・Q19 = `@wordpress/scripts`(手元で build → コミット → `deploy.sh`。Actions は検査だけ。Astro は使わない)。

残りは「Fable max で判断」。Fable は「自作は最小・複雑にしない・要るときに足す」で見直し、前回の推奨から 7 件を変えた。

| 問い | 決定 | 根拠 |
|---|---|---|
| Q4 並べ替え | **ドラッグで並べ替える**(SortableJS を npm で同梱。← → ボタンも付ける)。初期はファイル名の自然順。ユーザ裁定 2026-10-24「ドラッグで並べ替える」(Fable はボタンだけにしていた) | 並べ替えの部品は WP 同梱に無い。SortableJS は DOM を直接動かし React と二重管理になる。並びはファイル名で決まるので直す場面はまれ |
| Q5 アップロード | 標準のメディアモーダル(複数選択)+ サンプル欄と書影欄に DropZone でその場にドロップ | すべて WP 同梱、保存は標準 REST |
| Q6 入口 | 最上位メニュー「マイページ」。メインサイト以外でログインしたら誰でもマイページへ | 特権管理者で分けると `ftsf` に効かない |
| Q7 ほかのメニュー | 最初の版では隠さない。持ち主を招待する段(T.33)で、迷った所だけ隠す | 隠すのは見かけだけ |
| Q8 作品のデータ | 標準の項目 + 投稿メタ + 分類。あらすじは**抜粋**(`post_excerpt`)。メタは `doujin_release_at`・`doujin_release_label`・`doujin_shop_links`・`doujin_sample_ids`。発売前後の URL 差し替えは持たない(ユーザ裁定 2026-10-24「手で直す」)| ブロックの解析が要らず、標準の検索・OGP にそのまま乗る |
| Q9 サンプルの並び | メタ `doujin_sample_ids`(配列の順) | 添付の `menu_order` は REST で書けない |
| Q10 トップの並び | 公開日時の新しい順 + 保存時の 1 ルール(公開日時 = 発売日時と今の早いほう) | 未来の日時は「予約」になって公開されない |
| Q11 公開状態 | 下書き / 公開。予告は自動の印 | 標準のまま |
| Q12 リンク集 | サイト設定 `doujin_profile_links`(`shops[]`・`accounts[]`) | 標準の `wp/v2/settings` で読み書きできる |
| Q13 プロフィール | サークル名 = `blogname`、リード文 = `blogdescription`、アイコン = `site_icon`。独自の設定は作らない | 3 つとも REST の設定にある(`site_icon` はコアの site-logo.php が登録) |
| Q14 英語欄 | 最初の版では出さない。データにも持たない(ユーザ裁定 2026-10-24「最初は日本語だけ」) | 全項目が倍になる。あとから足すのは容易 |
| Q15 見た目 | 「テーマを選ぶ」「色と文字」の標準画面へのリンクだけ | テーマの REST は読み取りだけ。独自の保存口が要る |
| Q16 書影 | メディアから(ドロップ / 選ぶ)。**加えて、FANZA や DLsite の作品番号から半自動で設定できるようにする**(ユーザ裁定 2026-10-24「FANZA や DLsite の作品番号を指定することで半自動になる設定も追加したい」)→ ユーザ裁定 2026-10-24「ボタンで取り込む」: 作品番号(FANZA の cid、DLsite の RJ…)を入れて「書影を取り込む」を押すと、サーバが画像を取ってメディアに保存し、アイキャッチにする。販売ページへのリンクも同時に埋める。直接表示(ホットリンク)はしない | ホットリンクは壊れる |
| Q17 ブラウザ側の縮小 | しない | 7.1 の仕組みはブロックエディタ系の 4 画面だけ |
| Q20 くわしい編集 | 残す。テンプレートロックは入れない | 作品カードに属性が無いので守る中身が無い |
| Q21 配布プラグインの併用 | 入れない | メタと設定の登録は数十行 |

最初の版の見積り: PHP 200〜300 行、JS 600〜1,000 行。後回し: 英語欄、メニューを隠す、テンプレートロック、発売前後の URL 差し替え、外部 URL の書影、ブラウザ側の縮小、準備中 / 公開の表示(T.11)、成人向けの表示(T.21)。

spec の書き換えが要る箇所: §1.2 原則 4、§1.3、§2、§3.1、§6.0、§6.2、§6.4、§7.2、§7.3、§8、§9.1、§12、§13(確認が済んでからまとめて直す)。

## 8. 分からなかったこと

`site_icon` が REST の設定に載っているか(→ 載っている。2026-10-24 確認)/ SCF の無料・有料の線引き / 自前画面でのブラウザ側の縮小 / React 19 への移行時の追随(externals なら再ビルドで済むはず)。

## 出典(すべて 2026-10-24)

- WordPress 7.1 Field Guide: https://make.wordpress.org/core/2026/08/05/wordpress-7-1-field-guide/
- Block Templates: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/
- @wordpress/media-utils: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-media-utils/
- @wordpress/scripts: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/
- Secure Custom Fields: https://wordpress.org/plugins/secure-custom-fields/
- SortableJS: https://cdnjs.com/libraries/Sortable ・ FilePond: https://pqina.nl/filepond/docs/api/instance/properties/ ・ Uppy: https://uppy.io/docs/uppy/
- Astro: https://docs.astro.build/en/concepts/why-astro/ ・ vite-for-wp: https://github.com/kucrut/vite-for-wp
- さくら 国外 IP アドレスフィルタ: https://help.sakura.ad.jp/rs/2234/

## 9. 書影の取り込みの下調べ(T.36.4、2026-10-24)

さくらのサーバから `curl` で確かめた(読むだけ。f_tsf の実在の作品番号を使った)。

| | FANZA(cid 例 `d_737824`) | DLsite(作品番号 例 `RJ01634826`) |
|---|---|---|
| 書影の画像 | `https://doujin-assets.dmm.co.jp/digital/comic/<cid>/<cid>pl.jpg` → 200(約 100KB)。年齢確認なしで取れる | 作品情報の JSON の `work_image`(例 `//img.dlsite.jp/modpub/images2/work/doujin/RJ01635000/RJ01634826_img_main.jpg`)→ 200(約 200KB)。番号から組むより JSON を読むほうが確実(予告作品の `ann` の規則では 404 だった) |
| 作品情報 | 作品ページは年齢確認の画面へ 302。Cookie `age_check_done=1` を付けると 200。`og:title` は無く、`og:image` は `<cid>pr.jpg` | `https://www.dlsite.com/maniax/product/info/ajax?product_id=<番号>` → 200 の JSON。`work_name`(題名)・`work_image`・`regist_date`(発売日)・`site_id`・`maker_id` がある |
| 販売ページの URL | `https://www.dmm.co.jp/dc/doujin/-/detail/=/cid=<cid>/` | `https://www.dlsite.com/<site_id>/work/=/product_id/<番号>.html`(予告の URL は 301 でここへ) |

分かったこと:
- どちらも、さくらから画像が取れる。地域の制限には当たらなかった(さくらは国内)。
- 取り込みボタンは「番号から URL を組む → `media_sideload_image()` でメディアに保存 → アイキャッチに → 販売先リンクに URL を足す」で作れる(コア標準の関数)。DLsite は JSON から題名と発売日も埋められる。FANZA は画像とリンクだけ(題名は手で入れる)。
- 決めていないこと: アフィリエイトのリンク(f_tsf は FANZA が `al.fanza.co.jp` の `af_id`、DLsite が `dlaf.jp` の `aid`)。取り込みで埋めるのは素の URL にし、アフィリエイトの ID は持ち主ごとの設定で包むかどうかを後で決める。
- 相手の URL の規則が変わると取り込みが失敗する(表示は壊れない。取り込み済みの画像はサイトにある)。そのときは手でドロップすればよい。
