# doujin-profile 仕様書 v0.2(草案)

| 項目 | 値 |
|---|---|
| 状態 | **草案**。§13 の要決定事項は**すべて未裁定**。本文の「推奨」は提案であって決定ではない |
| 作成 | 2026-09-18 |
| 関連文書 | 導入先の実測 → [`environment.md`](environment.md) / ここに至る経緯と裁定の記録 → [`design-log.md`](design-log.md) |

表記: 👤 = サイト所有者の判断・作業が要るもの。「実測」= [`environment.md`](environment.md) に根拠があるもの。
「未検証」= 実装の最初に小さく試して確かめるべきもの。

---

## 0. 要約

- **何を作るか**: `https://profile.xwp.jp/<slug>/` に、ユーザごとの同人サークル紹介サイト(リンク集+作品紹介)を出す
  WordPress mu-plugin。最初の目標は、`https://takano32.github.io/f_tsf/` と同等の内容を `/f_tsf/` で出すこと。
- **推奨する構成**: WordPress は単一サイトのまま、プロフィールをカスタム投稿タイプで持ち、ルート直下の URL はプラグインが解決する。
  アカウントは WordPress のユーザに統合する。ただし**公開されるプロフィール(slug)とログインアカウントは別の実体**にして紐付け、
  持ち主には専用の最小権限ロールだけを与える(§4・§7)。
- **見た目**: ページの構造は全プロフィール共通にして、見た目は**スキン**(配色・フォントなどのトークン+同梱 CSS)で切り替える。
  WordPress のブロックテーマからは、`theme.json` に入っている配色やフォントの組を機械的に取り込める。
  パターンやテーマそのものまで流用するかは、編集画面の方針とセットで決める(§9.6)。
- **段階導入**: まず「表示+運営者による入力」だけで `/f_tsf/` を完成させる(この段階でログインするのは管理者だけ)。
  持ち主本人による編集(招待制)はその次(§12)。認証まわりの攻撃面を、表示が固まるまで開けない。
- **最大のリスク**: ホスティング規約。XServer for WordPress は猥褻表現・裸体画像の掲載を禁じており、
  f_tsf が同梱する成人向けサンプル画像をそのまま載せると**サイトごと停止されるおそれ**がある(§3.3)。設計より先に方針が要る。

---

## 1. 目的とスコープ

### 1.1 目的(サイト所有者の発言 2026-09-18)

1. あるユーザ `f_tsf` について、`https://takano32.github.io/f_tsf/` の内容が `https://profile.xwp.jp/f_tsf/` で見られるようにする。
2. `hoge` ユーザなら `https://profile.xwp.jp/hoge/` にサイトを作れる。
3. ユーザ管理をしたい。WordPress のユーザ管理と統合するかは迷っている。統合するなら権限に注意が要る。
4. 認証は、他のプラグインを参考にした一般的な設計にしたい。

### 1.2 スコープ

| 含む | 含まない(少なくとも当面) |
|---|---|
| プロフィール(サークル紹介・リンク集・作品紹介)の保持と表示 | 販売・決済・ダウンロード配信 |
| ルート直下 `/<slug>/` の URL 解決 | コメント・フォロー等の SNS 機能 |
| 持ち主のアカウントと権限(§7) | 作品本編の配信 |
| 運営者向けの WP-CLI(投入・書き出し・公開停止・診断) | 公開のセルフ登録(§7.5。やるなら別途設計) |

### 1.3 用語

| 語 | 意味 |
|---|---|
| プロフィール | `/<slug>/` で公開される 1 つのサイト。1 サークル(または 1 作家)に対応する |
| slug | プロフィールの URL 名(`f_tsf`)。ログイン名とは別物 |
| 持ち主 | そのプロフィールを編集できるアカウント |
| 運営者 | サイト所有者 = WordPress の administrator |
| 作品 | プロフィールに載せる頒布物 1 点(タイトル・発売日・販売リンク・サンプル) |
| スキン | プロフィールの見た目の設定一式(§9.6)。**WordPress の「テーマ」とは別物** — テーマはサイト全体の表示を担う WordPress の仕組みで、有効にできるのは 1 つだけ。混同を避けるためにこの仕様では呼び分ける(呼び名は仮。変えてよい) |

---

## 2. 参照実装 f_tsf の棚卸し

ソース: `takano32/f_tsf`(Astro 5、GitHub Pages)。ページは `index.html` と `announce.html` の 2 枚で、中身は同じセクション群。

| # | f_tsf にあるもの | doujin-profile での扱い(案) |
|---:|---|---|
| 1 | セクション: 最新作 / 新作予告 / これまでの作品 / 販売ページ / お知らせ・活動情報 | 同じ並びを既定レイアウトにする。作品の 3 区分は**発売日時から自動で決める**(手で付け替えない) |
| 2 | 作品データ: タイトル・発売日表記・あらすじ(各 ja/en)、FANZA/DLsite の URL、rel | プロフィールの構造化データ(§6) |
| 3 | 表紙画像は `doujin-assets.dmm.co.jp` へのホットリンク(FANZA の ID から URL を組み立て) | 「外部 URL」か「メディアライブラリの画像」を選べるようにする。FANZA ID からの自動組み立ても残す |
| 4 | 店舗・SNS のロゴは各サービスの favicon をホットリンク | サービス種別を選ぶとアイコンが決まる形にする(アイコンの同梱可否はライセンス次第。だめなら汎用アイコン) |
| 5 | サンプルビューア: `samples/<ID>/01.jpg…` を連番で最大 80 枚まで存在確認して表示 | 画像の並びをデータとして持ち、連番の探り当てはしない。**成人向け画像の置き場は §3.3 の裁定次第** |
| 6 | 年齢確認ゲート(localStorage、「いいえ」で離脱) | プロフィール単位の「成人向け」フラグで出し分ける。クライアント側実装を踏襲 |
| 7 | 二言語: `data-ja` / `data-en` をブラウザ言語で切替(URL は 1 つ) | 踏襲(1 つの HTML をキャッシュできる)。en は任意入力、無ければ ja を出す |
| 8 | 発売日時での自動切替(予告→最新作、DLsite の URL と rel の差し替え、予告ページの転送) | データ駆動にする: 作品ごとの発売日時と「発売前 URL / 発売後 URL」。**前段キャッシュがあるので、時刻判定はクライアント側でも行う**(§9.4) |
| 9 | `announce.html`(予告作品用の OGP を持つ別 URL) | `/<slug>/announce/`。予告中の作品が無ければ `/<slug>/` へ転送 |
| 10 | OGP / Twitter Card / JSON-LD / theme-color / canonical(`https://ftsf.me/` を指す) | プロフィール単位で出力。canonical の向き先は要決定(§13 D8。`ftsf.me` は 2026-09-18 現在も稼働中) |
| 11 | GA4(gtag)の埋め込み | 測定 ID だけを入力させる形なら可能。許すかどうかは要決定(§13 D10) |
| 12 | フッタのアフィリエイト表記 | プロフィール単位のフラグ。`sponsored` なリンクが 1 つでもあれば自動表示でもよい |
| 13 | 紫×白のデザイン(theme-color `#7a3cff`)。git 履歴に CSS だけの全面リスキンが 4 回ある | スキン方式(§9.6)。現行の紫×白を既定スキンにする。過去の 4 種も移植できる |

---

## 3. 前提と制約

### 3.1 導入先(実測の要点)

- Xserver for WordPress、`ssh profile`。**WordPress 7.1.1・単一サイト・PHP 8.0.30・MariaDB 10.11・WP-CLI 2.8.1**。node は無い。
- コアは**メジャー含め自動更新**される。プラグインはコアの公開 API だけに依存し、更新で壊れにくく書く。
- `display_errors = On`。公開ページで notice / warning を出すと画面に漏れる。
- 前段に **nginx キャッシュ**があり、消去はサーバパネルからしかできない。ログ類はユーザから読めない。
- `mu-plugins/` は未作成。有効プラグインは CloudSecure WP Security のみ(ログイン URL 変更が有効)。
- WordPress ユーザは `takano32` と `ftsf` で、**どちらも administrator**。ログイン名 `ftsf` と公開したい slug `f_tsf` は一致しない。
- ログイン URL は隠してあるが、**ログイン名は REST・ユーザサイトマップ・著者アーカイブの 3 経路で分かる**。XML-RPC も生きている。

### 3.2 mu-plugin であることの制約

| 制約 | 対処 |
|---|---|
| `mu-plugins/` 直下の `*.php` しか自動ロードされない | ローダ `doujin-profile.php` → 本体 `doujin-profile/doujin-profile.php`(ts-library と同じ構成) |
| **有効化フックが発火しない**(`register_activation_hook` が使えない) | ロール登録などの初期化は「バージョン付きの冪等な初期化」で行う: 保存済みバージョンと比べて差があるときだけ実行。WP-CLI からも同じ処理を呼べるようにする |
| rewrite rules を足すと flush の契機が無い | **rewrite rules を足さない**設計にする(§5.3) |
| 管理画面から無効化できない・更新通知が無い | 意図どおり(データモデルが消える事故を防ぐ)。配備は rsync(§11) |

### 3.3 ホスティング規約 ★ 先に方針が要る

[XServer for WordPress 利用規約](https://wp.xserver.ne.jp/rule/rule.php)の禁止事項(2026-09-18 に原文を確認):

- (11)「猥褻な表現を含む情報、または公衆に羞恥心を抱かせるおそれのある情報、その他公衆の射幸心を仰ぐ情報を掲載し、または他の利用者もしくは第三者に提供する行為」
- (13)「未成年者にとって有害と認められる情報の売買、譲受または掲載する行為、それらを助長する行為」
- (14)「人の死体、**裸体**、児童ポルノ、人を殺傷する現場もしくは児童虐待に相当するおそれのある画像または動画を掲載する行為」
- (22)「利用者が本サービスを利用して、本サービスに定めるサービスと同様のサービスを、有償無償を問わず第三者に提供もしくは再販売、あるいは転売する行為」
- 契約解除事由 (9)「利用者が本サービスを弊社の許可なく他の本サービスの利用者または第三者に使用させた場合」

違反時は利用停止・アカウント削除・情報の削除がありうる。

参考として、別サービスであるエックスサーバー本体の [FAQ](https://www.xserver.ne.jp/support/faq/dont_contents_stint.php) は
「同人系コンテンツの運用自体は、禁止事項に該当をいたしません」としつつ
「性行為、またはそれを連想させる画像・動画を掲載している場合は、禁止事項に該当するコンテンツであるとの判断をいたします」と述べ、
ポルノサイト等への「リンクが含まれるもの」も禁止に挙げている(XServer for WordPress に同じ運用が適用されるかは未確認)。

**doujin-profile への影響**:

1. f_tsf は成人向けサンプル画像を 54 枚同梱している。これを `uploads/` に置いて表示するのは (11)(14) に当たるおそれが高い。
2. 表紙画像は外部(DMM の CDN)へのホットリンクだが、ページに表示する以上「掲載」と見なされる余地がある。
3. FANZA / DLsite の成人向け商品ページへのリンク自体がどう扱われるかは、規約の文面からは判断できない。
4. 不特定の第三者に WordPress の「サイト」を持たせる形(Multisite)は、(22) の「同様のサービス」に近づく。
   プロフィールページを載せるだけの形のほうが説明しやすい。これは §4 で単一サイト案を推す理由の一つ。

**解釈を決めるのは Xserver であって、この文書ではない。** 取りうる方針は §13 D1 に挙げた。
いずれにせよ設計としては「成人向け画像をこのサーバに置かなくても成立する」形にしておく(§6 の画像は外部 URL も選べる)。

---

## 4. 全体構成の選択肢と推奨

### 4.1 比較

| 観点 | **案 A: 単一サイト+CPT+WP ユーザ統合** | 案 B: Multisite(サブディレクトリ型) | 案 C: 単一サイト+独自ユーザ管理 |
|---|---|---|---|
| `/f_tsf/` の URL | ○ 自前で解決。`_` も使える | **× 標準フローでは作れない**: ネットワーク管理画面のサイト追加は `^[a-zA-Z0-9-]+$`、セルフ登録は英小文字と数字のみ・4 文字以上(コアのソースで確認) | ○ |
| 認証の一般性・安全性 | ○ コアの認証(パスワードハッシュ・Cookie・リセット)と CloudSecure の防御がそのまま効く | ○ 同左 | **×** ハッシュ・セッション・リセット・試行制限・2 段階認証を自作することになる |
| 権限の分離 | △→○ 専用ロールを慎重に設計する必要(§7) | ○ サイト単位で分離。ただしサイト管理者は投稿・固定ページ等も触れるので、結局絞り込みが要る | ○ ただし全部自作 |
| 実装量 | 中 | URL とユーザは小、ただしネットワーク化・サイトごとの表示統一が別途要る | 大 |
| 規約 (22) との距離 | ○ プロフィールページの掲載 | △ 第三者に WordPress サイトを提供する形 | ○ |
| 運用 | ○ DB は 1 つ、標準 12 テーブル+α | △ サイトごとに約 10 テーブル増。パネル管理の `wp-config.php` / `.htaccess` を書き換える必要(公式サポート可否は未確認) | △ 独自テーブルと移行の管理 |
| 表示の統一(テーマ非依存) | ○ | △ サイトごとのテーマ設定に左右される | ○ |

### 4.2 推奨: 案 A

- 既存の会員・プロフィール系プラグインの大勢(付録 A)と同じく、**アカウントは `wp_users` に統合する**。
  独自認証(案 C)は「一般的な設計」から最も遠く、セキュリティ上の利点もない。
- ただし **プロフィールはユーザの属性にしない**。プロフィールは投稿(CPT)、持ち主はその投稿の所有者、という関係にする。
  - 公開 slug(`f_tsf`)とログイン名(`ftsf`)を切り離せる。ログイン名を URL に晒さない。
  - 1 人が複数のプロフィール(複数サークル)を持てる。持ち主の交代もできる。
  - 持ち主がログインしない運用(運営者が代理で入力)でも成立する → 段階導入ができる。
- Multisite(案 B)は `f_tsf` が作れない時点で目的 1 を満たさない。

### 4.3 段階導入(詳細は §12)

| 段階 | ログインする人 | できること |
|---|---|---|
| Phase 1 | 運営者だけ | `/f_tsf/` の表示。データは WP-CLI の投入と管理画面で運営者が入れる |
| Phase 2 | 招待された持ち主 | 自分のプロフィールだけを編集 |
| Phase 3 | (要検討) | スキン、作品個別ページ、編集画面の改善、公開登録の是非 |

---

## 5. URL 設計

### 5.1 URL

| URL | 内容 |
|---|---|
| `/<slug>/` | プロフィールのトップ |
| `/<slug>/announce/` | 予告中の作品に焦点を当てた版(独自の OGP)。予告が無ければ `/<slug>/` へ 302 |
| `/<slug>/…`(その他) | 404。将来の作品個別ページ用に空けておく |

- 末尾スラッシュ付きを正規形にし、無しは 301。大文字を含むアクセス(`/F_TSF/`)は小文字へ 301。
- 非公開(下書き・公開停止)のプロフィールは、持ち主と運営者以外には 404。

### 5.2 slug の規則(案)

- 文字: 英小文字・数字・`_`・`-`。先頭と末尾は英数字。長さ 3〜32。**数字だけは不可**(`/2026/` のような日付アーカイブと衝突する)。
- 一意性は `_` と `-` を同一視して判定する(`f_tsf` がいる状態で `f-tsf` を取らせない。なりすまし対策)。
- 予約語は取得不可: WordPress の経路(`wp-admin` `wp-content` `wp-includes` `wp-json` `wp-login` `feed` `comments` `page` `search` `author` `category` `tag` `embed` `trackback` `sitemap` `robots` `favicon` `xmlrpc` ほか)、
  将来使いそうな語(`login` `logout` `register` `signup` `account` `settings` `admin` `api` `assets` `static` `help` `about` `terms` `privacy` `contact` `news` `blog` `profile` `profiles` `user` `users` `works` `announce` ほか)、
  運用上の語(`_probe` `cloudsecurewp_404`)。一覧はコード内の 1 箇所で管理し、フィルタで足せるようにする。
- **固定ページとの衝突**: 既存の固定ページ slug(いまは `sample-page` `privacy-policy`)は予約語扱い。逆向きも塞ぐ —
  プロフィールが使っている slug で固定ページを作ろうとしたら、コアの slug 一意化の仕組みに「使用不可」と答えて連番に逃がす
  (`wp_unique_post_slug_is_bad_hierarchical_slug` フィルタ)。**コアの `wp_unique_post_slug()` は投稿タイプをまたいだ重複を防がない**ので、両方向とも自前で検査する(付録 A-10)。
- slug の変更は運営者だけ。旧 slug からは 301(旧 slug は再取得不可として保持)。

### 5.3 解決方法(rewrite rules を足さない)

いまのパーマリンク構造では、`/f_tsf/` も `/f_tsf/announce/` も WordPress は「固定ページ候補(`pagename`)」として解釈する(実測: 現状は 404)。
そこで **`request` フィルタで、先頭セグメントが公開中のプロフィール slug に一致するときだけ**クエリ変数をプロフィール用に差し替える。

- 一致しなければ何もしない → 固定ページや既存の経路の挙動は一切変わらない。同名の固定ページが実在するときは固定ページが勝つ(コアの挙動のまま)。
- rewrite rules を足さないので flush が要らない(§3.2)。投稿タイプは `rewrite => false` で登録する
  (`'rewrite' => ['slug' => '']` はコアが投稿タイプ名に上書きするので効かない)。
- slug → 投稿 ID の対応はオブジェクトキャッシュ+軽いクエリで引く。
- 未検証: フィード(`/f_tsf/feed/`)や添付ファイル URL など、先に別のルールに一致する経路の扱い。Phase 0 で確かめる。

**ここがこの設計でいちばん壊れやすい箇所**(付録 A-10): 同じ `request` フィルタ方式の先行例 `remove-cpt-base`(解決順は 投稿 → 固定ページ → 対象 CPT)は
更新履歴が 404 まわりの修正の繰り返しで、Ultimate Member は公式にプロフィール URL のルート直下化を「不可能」としている(予約語と経路の競合が理由)。
そこで (1) **登録済みの slug だけを拾う**(汎用の正規表現で 1 セグメントを総取りしない)、(2) 予約語、(3) 投稿タイプ横断の一意性検査、の 3 点で守り、実装の前に小さく検証する。

---

## 6. コンテンツモデル

### 6.1 推奨: 1 プロフィール = 1 投稿(作品・リンクは構造化メタ)

カスタム投稿タイプ `doujin_profile`。作品を別の投稿タイプに分ける案もあるが、MVP では 1 投稿に集約する。

| | 1 投稿に集約(推奨) | 作品を別 CPT に分ける |
|---|---|---|
| 権限検査 | 「この投稿を編集できるか」の 1 点 | 作品→プロフィールの親子関係を保存のたびに検証する必要(他人のプロフィールにぶら下げる攻撃) |
| 保存 | 1 回で全体が整合 | 複数投稿にまたがる |
| 作品個別 URL・横断一覧 | 不向き | 向く |

作品個別ページやサイト横断の新着一覧が要るようになった時点で分割する(書き出し→再投入で移行できるよう、§6.3 の JSON を正規形にする)。

### 6.2 フィールド

| 区分 | フィールド | 備考 |
|---|---|---|
| 基本 | slug / 名称 (ja, en) / リード文 (ja, en) / アイコン | 名称 ja = 投稿タイトル、slug = `post_name`、持ち主 = `post_author` |
| 見た目 | スキン(登録済みの一覧から選ぶ)/ アクセント色の上書き | §9.6。自由な CSS の入力は受け付けない |
| 表示制御 | 成人向けフラグ / アフィリエイト表記フラグ / noindex フラグ | 成人向け → 年齢確認ゲート+`rating` メタ |
| SEO | OGP 画像(トップ用・予告用)/ キーワード / canonical の向き先 | §13 D8 |
| リンク | 販売ページ[]: サービス種別・URL・表示名 (ja, en)・sponsored | サービス種別は登録制の一覧(FANZA・DLsite・メロンブックス・とらのあな・BOOTH・pixiv・X・note・Circle.ms・Bluesky・Misskey・Fantia・FANBOX・Skeb・その他) |
| | アカウント[]: サービス種別・URL | 同上 |
| 作品[] | キー / タイトル (ja, en) / あらすじ (ja, en) | キーはプロフィール内で一意 |
| | 発売日時(タイムゾーン付き)/ 発売日の表記 (発売前 ja, en・発売後 ja, en) | 3 区分(予告・最新作・これまで)は発売日時から導出 |
| | 表紙: 外部 URL(大・小)/ メディア ID / FANZA ID からの自動組み立て | |
| | 販売リンク[]: サービス種別・URL・sponsored・**発売前 URL・発売前 sponsored** | f_tsf の DLsite リンク差し替えを表現する |
| | サンプル[]: 外部 URL またはメディア ID の並び / サンプルの表題 (ja, en) | §3.3 の裁定次第 |
| (要決定) | GA4 測定 ID | `G-` で始まる ID の形式だけ受け付ける。任意のスクリプトは受け付けない |

入力値の規則: テキストはプレーンテキスト(HTML 不可)。URL は `https:` のみ。色は `#rrggbb`。メディア ID は持ち主本人がアップロードした画像だけ。
件数・長さに上限を設ける(作品 200、リンク各 30、サンプル 80 枚/作品 など。値は実装時に確定)。
メタは `register_post_meta` でスキーマごと登録し、保存時に全体を検証する。リビジョン対象にして変更履歴を残す。

### 6.3 交換形式(JSON)

WP-CLI の投入・書き出しと、将来の移行に使う正規形。f_tsf の例(抜粋・URL は省略):

```json
{
  "slug": "f_tsf",
  "name": { "ja": "TSFのF", "en": "TSF no F" },
  "lead": { "ja": "…同人サークル『TSFのF』のリンク集です。", "en": "A link hub for TSF no F, …" },
  "adult": true,
  "affiliate_notice": true,
  "accent_color": "#7a3cff",
  "shops": [
    { "service": "fanza", "url": "https://al.fanza.co.jp/…", "sponsored": true },
    { "service": "melonbooks", "url": "https://www.melonbooks.co.jp/…", "label": { "ja": "メロンブックス", "en": "Melonbooks" } }
  ],
  "accounts": [
    { "service": "pixiv", "url": "https://www.pixiv.net/users/…" },
    { "service": "x", "url": "https://x.com/F_TSF" }
  ],
  "works": [
    {
      "key": "d_771085",
      "title": { "ja": "(作品名)", "en": "(Title)" },
      "summary": { "ja": "…", "en": "…" },
      "release_at": "2026-05-29T00:00:00+09:00",
      "release_label": {
        "before": { "ja": "発売予定日:FANZA 2026年5月29日 / DLsite 2026年5月30日", "en": "Planned release: …" },
        "after":  { "ja": "発売日:FANZA 2026年5月29日 / DLsite 2026年5月30日", "en": "Released: …" }
      },
      "cover": { "fanza_id": "d_771085" },
      "links": [
        { "service": "fanza", "url": "https://al.fanza.co.jp/…", "sponsored": true },
        { "service": "dlsite", "url": "https://dlaf.jp/…", "sponsored": true,
          "before_release": { "url": "https://www.dlsite.com/maniax/announce/…", "sponsored": false } }
      ],
      "samples": { "title": { "ja": "(作品名) サンプル", "en": "… Sample" }, "images": [] }
    }
  ]
}
```

---

## 7. 認証・権限

### 7.1 方針

1. **アカウントは WordPress のユーザ**。独自のユーザテーブル・独自認証は作らない。
   パスワードの保管、認証 Cookie、nonce、パスワードリセット、CloudSecure の防御(ログイン URL 変更・試行制限・2 段階認証・画像認証)をそのまま使う。
2. **プロフィールとアカウントは別の実体**(§4.2)。紐付けは投稿の所有者(`post_author`)。
3. **持ち主に渡すのは専用ロールだけ**。administrator / editor / author を流用しない。
4. **登録は閉じたまま**(`users_can_register = 0` を維持)。アカウントは運営者が作る(招待制)。
5. **管理者は運営者だけ**にする(§7.6)。

### 7.2 なぜ administrator を渡してはいけないか(実測に基づく)

`DISALLOW_FILE_EDIT` が未設定なので、administrator は管理画面からテーマ/プラグインの PHP を編集できる。
つまり **administrator = このホスティングアカウント上で任意のコードを実行できる権限**であり、全プロフィール・全ユーザ・DB に及ぶ。
持ち主が増えるほど、誰か 1 人のパスワード漏洩がサイト全体の侵害になる。

### 7.3 専用ロール `doujin_member`(Phase 2)

投稿タイプを `capability_type => ['doujin_profile', 'doujin_profiles']`・`map_meta_cap => true` で登録し、
コア標準の「自分の投稿だけ編集できる」判定に乗る(独自の判定ロジックを極力書かない)。

| 権限 | 持ち主 | 理由 |
|---|---|---|
| `read` | ○ | 管理画面に入るための最小権限 |
| `edit_doujin_profiles` | ○ | 自分のプロフィールの編集 |
| `edit_published_doujin_profiles` | ○ | 公開後も編集できる |
| `publish_doujin_profiles` | **×**(要決定 D11) | 初回公開は運営者が承認する。規約リスク(§3.3)の歯止め |
| 新規作成(`create_posts` に対応付ける権限) | **×** | 既定では `edit_doujin_profiles` に対応付けられ、持ち主が**プロフィールを何個でも新規作成できてしまう**(slug の占拠)。別の権限に対応付け、運営者だけに与える |
| `edit_others_doujin_profiles` / `read_private_…` / `delete_…` | × | 他人のものに触れない。削除は運営者だけ(slug の使い回し・乗っ取り防止) |
| `upload_files` | ○(画像のみ) | アイコン・OGP・サンプル用。下の制限とセット |
| `edit_posts` ほかコアの投稿系・`unfiltered_html`・`manage_options` など | × | — |

`upload_files` を渡すときの制限(いずれも既知の落とし穴):

- メディアライブラリは**自分のアップロードだけ**が見えるようにする(一覧・モーダル・REST の 3 経路すべて)。
- 受け付けるのは画像(JPEG / PNG / WebP / GIF)だけ。1 枚のサイズと、ユーザあたりの総量に上限。
- 添付ファイルの編集・削除権限はコアでは「投稿」の権限に紐づくため、`upload_files` だけでは自分の画像を消せない。ここだけ権限の対応付けを補う。
- `uploads/` では PHP が実行されない(実測: Xserver の拒否ブロックあり)。
- **アップロードした画像は、その時点で公開物として扱う**。`uploads/` は Web サーバが直接配信し、コアは未認証の `/wp-json/wp/v2/media` で添付ファイルを一覧できる。
  メディアライブラリの絞り込みは表示上のフィルタであってアクセス制御ではない。下書きプロフィールの画像も URL が分かれば見える(一覧 API の制限は §10)。
  「未公開にしたい素材はアップロードしない」と持ち主に明示する。

運営者(administrator)にも `doujin_profile` 系の権限を明示的に付与する(独自 `capability_type` は自動では付かない)。
`map_meta_cap => true` は必ず明示する(`capability_type` が `post` / `page` 以外だと既定で false になり、所有者判定が効かなくなる)。

ロールの登録は §3.2 の「バージョン付きの冪等な初期化」で行う(`add_role()` は DB に書き込むため、毎リクエスト呼ばない)。
既存ロールに対する `add_role()` の再実行は何もしないので、権限の増減は差分を `add_cap()` / `remove_cap()` で適用する。
DB に書かない代替として bbPress の動的ロール方式もある(付録 A-2)。どちらにするかは実装時に決める。

### 7.4 ログインと管理画面

- ログインはコアのログイン画面(CloudSecure が URL を変更済み)。持ち主には URL を個別に伝える。
  持ち主が増えればこの URL は秘密ではなくなるので、**防御の本体は試行制限・2 段階認証・列挙対策(§10)**と考える。
- Phase 2 の編集画面は**絞り込んだ管理画面**を推奨(§8)。持ち主に見えるメニューは「プロフィール」「メディア(自分の分)」「アカウント(パスワード変更)」だけ。
  ダッシュボードに来たら自分のプロフィールの編集画面へ送る。ツールバーの不要項目も消す。
- Application Passwords(REST 用の追加パスワード)は持ち主には無効化する。必要になったら個別に開ける。

### 7.5 招待の流れ(Phase 2)

1. 運営者が `wp doujin invite <メール> --slug=<slug>` を実行(管理画面からでも可)。
2. ユーザを `doujin_member` で作成し、その人を所有者とする下書きプロフィールを作る(slug を確保)。
3. コア標準の通知メールが、パスワード設定用のリンク(期限付きのリセットキー)を送る。
4. 持ち主が内容を入力して「レビュー待ち」にする → 運営者が確認して公開。

公開のセルフ登録は MVP に含めない。やる場合はメール確認・ボット対策・承認キュー・利用規約への同意・slug の占拠対策が一式必要で、
規約 (22)(§3.3)との関係も先に確認が要る。

### 7.6 既存アカウント `ftsf` の扱い 👤

Phase 2 の時点で `ftsf` を administrator から `doujin_member` に変更し、`/f_tsf/` のプロフィールの所有者にする(要決定 D6)。
あわせて `wp-config.php` に `DISALLOW_FILE_EDIT` を入れることを推奨(管理者アカウントが侵害されたときの被害を狭める)。

### 7.7 公開停止と退会

- 運営者は `wp doujin unpublish <slug> --reason=…` で即時に非公開化できる(理由と日時を記録)。
- アカウント停止はロールの剥奪+セッション破棄。プロフィールと slug は保持する(再取得・なりすまし防止)。

---

## 8. 編集手段

| 段階 | 手段 | 内容 |
|---|---|---|
| Phase 1 | **WP-CLI**(運営者) | `wp doujin import <json> [--dry-run]`(slug をキーに冪等)/ `export <slug>` / `list` / `unpublish` / `doctor`(環境・権限・堅牢化の自己診断、読み取り専用)/ `skin list` |
| Phase 3 | WP-CLI(運営者) | `wp doujin skin import-theme <テーマ> [--variation=<名前>]`(ブロックテーマの `theme.json` からスキン JSON を生成。§9.6) |
| Phase 1 | 管理画面(運営者) | `doujin_profile` の編集画面。構造化メタを編集するフォーム(リンク・作品の行の追加/並べ替え、メディア選択) |
| Phase 2 | 絞り込んだ管理画面(持ち主) | 上と同じフォームを、§7.3 の権限で |
| Phase 3 | (要検討)フロントエンド編集 | 公開ページに近い見た目で編集する画面。WooCommerce や Ultimate Member のように管理画面を見せない設計。実装量は大きい |

編集フォームはビルド不要の素朴な構成(PHP のフォーム+少量の素の JavaScript)を基本にする。サーバに node が無く(実測)、
成果物をリポジトリに入れて rsync する運用と相性がよい。

保存経路はどれも同じ検証(§6.2)と権限検査・nonce 検査を通す。投入 JSON も同じ検証器に通す。

---

## 9. 表示

### 9.1 テンプレート

- **テーマに依存しない独立テンプレート**をプラグインが持つ(`template_include` で差し替え、完結した HTML を出す)。
  有効テーマ(いまは Twenty Twenty-Five)の CSS / JS は読み込まない。
- ページの構造(HTML とセクションの並び: ヘッダのナビ → リード文 → 最新作 → 新作予告 → これまでの作品 → 販売ページ → アカウント → フッタ)は全プロフィール共通。
  **見た目はスキンで切り替える**(§9.6)。CSS は最初から全面的に CSS カスタムプロパティ(トークン)で書く — あとからの作り替えは高くつく。

### 9.2 出力の安全性

- ユーザ入力はすべて出力時にエスケープ。HTML を許すフィールドは作らない。
- 外部リンクは `target="_blank"` と `rel="noopener"`、sponsored フラグがあれば `sponsored` を付ける。
- `display_errors = On`(実測)なので、公開テンプレートは未定義キーや型のゆらぎで notice を出さないよう、表示前に値を正規化する。

### 9.3 head

title / description / canonical / robots / OGP / Twitter Card / JSON-LD / theme-color / favicon(プロフィールのアイコン)。
成人向けプロフィールには `<meta name="rating" content="adult">` を付ける。noindex フラグがあれば `noindex`。

### 9.4 時刻で変わる表示と前段キャッシュ

- 作品の 3 区分(予告・最新作・これまで)と、発売前後のリンク差し替えは**発売日時で決まる**。
- サーバ側は描画時点の状態で HTML を作る(JavaScript 無しでも正しい)。
- 前段 nginx キャッシュは消去をこちらから指示できない(実測)。そこで発売日時を `data-` 属性で埋め込み、
  **クライアント側でも現在時刻で並べ替えとリンク差し替えを行う**(f_tsf と同じ考え方をデータ駆動にしたもの)。キャッシュが古くても発売時刻ぴったりに切り替わる。
- 未検証: 前段キャッシュの寿命と、`Cache-Control` での制御可否。Phase 1 で実測し、編集が反映されるまでの時間を仕様として明記する。

### 9.5 クライアント側の部品

| 部品 | 仕様 |
|---|---|
| 言語切替 | `navigator.languages` に `ja` があれば ja、無ければ en。`data-ja` / `data-en` を差し替え、`<html lang>` も変える |
| 年齢確認 | 成人向けプロフィールのみ。確認結果は localStorage(サイト全体で 1 つのキー)。「いいえ」で本文を消して離脱。ダイアログとしてのフォーカス管理を足す |
| サンプルビューア | 画像の並びはページ内の JSON から受け取る。前後移動(クリック・矢印キー)、Esc で閉じる、枚数表示 |
| 発売切替 | §9.4 |

### 9.6 スキン(見た目の切り替え)

f_tsf と違う雰囲気のプロフィールも作れるようにする。WordPress のテーマから部分を取り出して使えないか、というのが出発点(サイト所有者の要望 2026-09-18)。

**考え方**: ページの構造は共通のまま安定させ、見た目だけを差し替える。f_tsf 自身がこの方法で 4 回全面リスキンしている
(実測: どの回も変更は `global.css` と head のフォント指定だけ。CSS は各 1,000〜1,200 行、CSS 変数 27〜47 個)。

#### 2 層構成

| 層 | 中身 | 誰が用意するか |
|---|---|---|
| **トークン** | 配色(背景・面・文字・補助文字・アクセント・境界)、フォントの組(見出し・本文・等幅)、角丸、影、余白の段階、コンテンツ幅。CSS カスタムプロパティ `--dp-*` として出力する | スキンごとの小さな JSON。プロフィール側ではアクセント色だけ上書きできる |
| **スキン CSS**(任意) | トークンでは表せない造形(カードの形、見出しの飾り、ヘッダの組み方) | プラグインに同梱する CSS。運営者が確認して追加する。**持ち主による自由な CSS 入力は受け付けない**(表示崩れ・他要素の偽装・保守の問題) |

アクセント色を上書きしたときは、その上に載る文字色(白か黒)を背景とのコントラスト比から自動で決める。

#### スキンの供給源

1. **手作り**: 既定スキン(f_tsf 現行の紫×白)。f_tsf の過去スキン 4 種(PROOF SHEET / BOUDOIR NOIR / LACQUERED PLUM BOUDOIR / HOT-PINK PULP)は git 履歴から復元して移植できる。
2. **WordPress のブロックテーマから取り込む**: ブロックテーマは見た目を `theme.json` と `styles/*.json`(スタイルバリエーション)に**機械可読な形**で持っている。
   ここからトークン(パレット・フォントの組・余白・角丸・影)を取り出してスキン JSON に変換する。
   - 実測: サーバに入っている既定テーマだけで、フルのバリエーションが **28 個**(Twenty Twenty-Five 8、Twenty Twenty-Four 7、Twenty Twenty-Three 10、Twenty Twenty-Two 3)。
     TT5 はさらに配色だけのプリセット 8 個とフォントの組 7 個を別に持つ。いずれも GPL v2 or later。wordpress.org のブロックテーマならどれでも同じ形式で読める。
   - 変換は WP-CLI(§8)で行い、**生成したスキン JSON をリポジトリに入れる**。実行時にテーマのファイルを読まないので、テーマの更新・削除やコアの自動更新で見た目が変わらない。
   - テーマごとにパレットの slug が違う(TT5 は `base` / `contrast` / `accent-1…`、TT3 は `base` / `contrast` / `primary` / `secondary` / `tertiary`)ので、テーマ別の対応表を持つ。
     取り込み時に文字と背景のコントラスト比(WCAG AA)を検査し、満たさない組は弾くか手で直す。
   - 取り込めるのは「配色と文字の雰囲気」まで。レイアウトや装飾はトークンに入っていないので、要るならスキン CSS を手で足す。
   - テーマ同梱のフォントは欧文のみ(実測: Manrope・Literata・Platypi・Vollkorn ほか)。日本語の書体は別のトークンとして持つ(D15)。
   - `theme.json` を持たないクラシックテーマ(見た目が `style.css` にしか無いもの)は機械的には取り込めない。手での移植になる。

#### WordPress のテーマをどこまで流用するか(深さの 4 段階)

上の「取り込み」は段階 1 にあたる。もっと深く流用することもできるが、深くするほど WordPress 本体とテーマの作りに縛られる。

| 段階 | 流用できるもの | 仕組み | 代償 | 判断 |
|---|---|---|---|---|
| **1. トークン** | 配色・フォントの組・余白・角丸・影 | 取り込み時に `theme.json` をスキン JSON へ変換。実行時はテーマに依存しない | レイアウトと装飾は流用できない | **採る**(上記) |
| 2. ブロック描画 | 上に加えて、テーマのグローバルスタイル(ボタンや見出しの造形)と**ブロックパターン**。TT5 にはパターンが 98 個あり、「Link in bio」のページ用パターンも 3 個ある(実測。コアのブロックだけで組まれている) | プロフィール本体をブロックとして描画し、有効なブロックテーマのスタイルで見せる。プラグインから投稿タイプ用のブロックテンプレートを登録でき(`register_block_template()`。実測で利用可)、プロフィールごとのスタイルバリエーションは theme.json のデータをリクエスト単位でフィルタして当てる | §9.1(テーマに依存しない独立テンプレート)からの転換。有効にできるテーマはサイト全体で 1 つ。コアやテーマの更新で見た目が変わりうる(コアは自動更新)。持ち主にブロック編集を開くと構造化フィールドより攻撃面が広い(使えるブロックの制限とテンプレートのロックが要る)。作品カード・年齢確認・サンプルビューア・発売切替は独自ブロックとして作り直し | 見た目だけでは決めない。利点は**ブロックエディタがそのまま視覚的な編集画面になる**ことなので、編集画面の方針(D5)とセットで判断する(D14) |
| 3. プロフィールごとにテーマを切替 | インストール済みの任意のテーマを丸ごと | リクエストの URL を見て `template` / `stylesheet` を差し替える(テーマ切替系プラグインの手法) | テーマはサイト全体(サイト名・ナビ・フッタ)を前提に作られているので、プロフィール用に見せるには各所の差し替えが要る。テーマごとに検証が要り、更新に弱い。テーマの PHP は全権限で動く | 採らない。ここまでやるなら段階 4 のほうが筋がよい |
| 4. Multisite | サイトごとにテーマを選ぶ、WordPress 本来の形 | §4.1 の案 B | §4.1 のとおり(サイト名に `_` が使えない、規約 (22)、運用の重さ)。プロフィールというより「WordPress のサイトそのもの」を配ることになる | 各ユーザに WordPress のサイトを持たせたいのであれば、これが本筋。D2 で判断 |

段階 1 で始めても、トークンの名前と構造を theme.json に寄せておけば、あとで段階 2 へ進む道は残る(スキン JSON はそのまま theme.json 形式のスタイルバリエーションに書き出せる)。

#### ライセンス

wordpress.org の公式ディレクトリに載っているテーマは、コードも同梱物も GPL 互換であることが掲載条件。そこから取り込む前提で、このプラグインは **GPL-2.0-or-later** にする(D16)。
スキン JSON には出典(テーマ名・バージョン・元ファイル)を記録する。コードは GPL でも CSS や画像が別ライセンスの市販テーマからは取り込まない。
フォントファイルを同梱する場合は、そのフォントのライセンス(多くは SIL OFL)の文書を一緒に置く。

---

## 10. サイト全体の堅牢化(同じリポジトリの別 mu-plugin として持つ)

プロフィールの機能とは独立だが、**持ち主のアカウントを作る前に塞いでおくべき穴**(実測で開いていたもの)。
doujin-profile 本体と分けて `mu-plugins/profile-site-hardening.php`(仮)に置き、本体は他サイトでも使える形に保つ。

前提として、WordPress コアは「ユーザ名やユーザ ID は秘密ではない」という立場で、REST のユーザ一覧が未認証で見えるのも意図された挙動(付録 A-6)。
ここで塞ぐのは多層防御の一枚であって、**防御の本体は強いパスワード・試行制限・2 段階認証**。

| 対象 | 現状(実測) | 対処 |
|---|---|---|
| `/wp-json/wp/v2/users` | 未認証で 200(ユーザを返す) | 未認証にはユーザ系エンドポイントを見せない。露出条件は「`show_in_rest` な投稿タイプで公開投稿を持つユーザ」なので、プロフィールを REST に出すと持ち主全員が一覧に現れる |
| (同上の安価な緩和) | — | 未認証で返るのは `slug`(= `user_nicename`)と `name`(= `display_name`)だけ。**招待時にこの 2 つをログイン名と別の値にしておく**だけで、ログイン名は漏れなくなる |
| `/wp-sitemap-users-1.xml` | 200 | ユーザのサイトマップを無効化(`wp_sitemaps_add_provider`) |
| `/author/<user_nicename>/` | 200 | 著者アーカイブを 404 に |
| oEmbed 応答の著者情報 | 未確認 | `author_name` / `author_url` を除去(`oembed_response_data`)。`doctor` の確認項目に入れる |
| `/wp-json/wp/v2/media` | 未確認(コアの既定では未認証で全件一覧できる) | 未認証の一覧を止める。§7.3 のとおり画像そのものは公開物だが、下書きの素材を一覧で拾われないようにする |
| XML-RPC | POST 200 | 無効化(CloudSecure の設定で可能ならそちらで 👤) |
| コメント | 既定 open | 閉じる(使わない) |

---

## 11. 配備・検収(ts-novels と同じ流儀)

- **リポジトリが正本**。サーバ上で直接編集しない。
- 構成:

  ```
  mu-plugins/
  ├── doujin-profile.php              ← ローダ(直下の *.php しか自動ロードされないため)
  ├── profile-site-hardening.php      ← §10(仮称)
  └── doujin-profile/
      ├── doujin-profile.php          ← 本体
      ├── includes/                   ← モデル・検証 / ルーティング / 描画 / 権限 / 管理画面 / WP-CLI
      ├── templates/
      └── assets/                     ← CSS / JS / アイコン(ビルド不要)
  ```

- 配備は rsync(`--delete` 禁止、先に `-n`)。宛先は `public_html/wp-content/mu-plugins/`。
- **PHP 8.0 互換**(enum・readonly プロパティなど 8.1 以降の構文を使わない)。
- 検収は WP-CLI(DB の中身)とキャッシュバスター付き curl(公開面)。`/f_tsf/` の 200、`/f_tsf/announce/`、未知 slug の 404、
  既存の固定ページが 200 のまま、`/F_TSF/` の 301、下書きプロフィールの 404 などを検査スクリプトにする。
- 開発機での動作確認環境(ローカルの WordPress)をどう用意するかは未定。

---

## 12. ロードマップ(案)

| 段階 | 内容 | 完了の目安 |
|---|---|---|
| Phase 0 | この仕様の裁定(§13)。規約の確認 👤。§5.3 と §9.4 の未検証事項を `_probe` 的に小さく確かめる | 要決定事項 D1〜D5 が決まっている |
| Phase 1 | ローダ+本体、投稿タイプとメタ、URL 解決、独立テンプレート(CSS は全面トークン化)、既定スキンとスキン切替の仕組み、切替の確認用にテーマ由来のスキンを 1 つ手で作る、クライアント部品、WP-CLI(import / export / list / unpublish / doctor / skin list)、§10 の堅牢化、f_tsf のデータ投入 | `/f_tsf/` が f_tsf と同等に見える。スキンを変えると別の雰囲気になる。検査スクリプトが通る |
| Phase 2 | 専用ロールと初期化処理、絞り込んだ管理画面、メディアの分離、招待コマンド、承認フロー、`ftsf` の降格 👤 | 持ち主が自分のプロフィールだけを編集でき、他人のものや他の管理画面に触れられないことを検査で確認 |
| Phase 3 | テーマからのスキン取り込みコマンド、f_tsf 過去スキン 4 種の移植、持ち主によるスキン選択、作品個別ページ(CPT 分割)、フロントエンド編集、トップページ、公開登録の是非 | — |

---

## 13. 要決定事項(👤 裁定待ち)

| # | 論点 | 推奨 | 補足 |
|---|---|---|---|
| **D1** | **成人向けコンテンツの扱い(規約 §3.3)** | まず Xserver サポートに確認する。回答が出るまで、成人向けの画像はこのサーバに置かない前提で設計・実装を進める | 選択肢: (a) 問い合わせて許容範囲を確定 (b) 全年齢の素材だけ載せ、成人向けサンプルは販売サイト側に任せる (c) 成人向けを許容するホストへ移す |
| D2 | 全体構成 | 案 A(単一サイト+CPT) | Multisite は `f_tsf` という名前が作れない |
| D3 | WordPress のユーザ管理と統合するか | 統合する。ただしプロフィール(slug)とアカウントは別実体で紐付け、専用ロールのみ付与 | §4.2・§7 |
| D4 | 登録方式 | 招待制のみ。セルフ登録は MVP に含めない | §7.5 |
| D5 | 持ち主の編集画面(Phase 2) | 持ち主にとっての編集のしやすさを製品の核にするなら**フロントエンド編集**、早く確実に出すなら**絞り込んだ管理画面** | 会員系プラグイン(WooCommerce・MemberPress・Paid Memberships Pro・Ultimate Member)は低権限ユーザを管理画面に入れずフロントで完結させるのが主流。ただし WordPress 公式がそう推奨しているわけではなく、フロント編集は編集 UI を自作する分だけ不具合の余地が増える。どちらでも最後の防御線は権限検査(付録 A-9)。§8 |
| D6 | `ftsf` を administrator から専用ロールへ変更するか、時期 | Phase 2 で変更。`DISALLOW_FILE_EDIT` も入れる | §7.6 |
| D7 | `/`(トップ)に何を出すか | 当面は既定のまま触らない。プロフィールの一覧は出さない | 成人向けプロフィールを一覧に並べるかという問題も付いてくる |
| D8 | `/f_tsf/` の canonical | 要相談。`ftsf.me` を正とするなら `ftsf.me` 向き、こちらへ移すなら自己参照 | `ftsf.me` は稼働中。GitHub Pages 版も canonical は `ftsf.me` |
| D9 | 二言語(ja/en)を全プロフィール共通の仕組みにするか | する。en は任意入力 | §2 #7 |
| D10 | プロフィールごとの GA4 測定 ID を許すか | Phase 1 では見送り、必要なら Phase 2 で ID 形式のみ受け付ける | 同一ドメインなので Cookie が全プロフィールで共有される点に注意 |
| D11 | 初回公開を運営者の承認制にするか | する | §7.3。規約リスクの歯止め |
| D12 | slug の規則 | §5.2 のとおり(3〜32 文字、`_` と `-` を同一視、予約語、数字だけは不可) | — |
| D13 | 作品を 1 投稿に集約するか、別 CPT に分けるか | MVP は集約 | §6.1 |
| D14 | 見た目の切り替え方式と、WordPress テーマの流用の深さ | **スキン方式**で始め、テーマからは `theme.json` のトークンを取り込む(§9.6 の段階 1) | 段階 2(ブロック描画。パターンやテーマの造形まで使える)は D5(編集画面)とセットで判断。段階 4(Multisite。サイトごとにテーマを選べる)は D2 の話になる。「WordPress のテーマをそのまま選べること」がどれだけ重要かで決まる |
| D15 | Web フォント | 既定はシステムフォント。欧文の見出し用は OFL のフォントを自前配信。**日本語 Web フォントに Google Fonts を使うか**は要裁定 | f_tsf の過去スキンは Google Fonts の日本語書体(Murecho・Zen Old Mincho・Reggae One)を使っていた。外部サービスへの通信が発生する。サーバには Xserver の TypeSquare 用プラグインも入っている(無効) |
| D16 | ライセンス | GPL-2.0-or-later | GPL のテーマから取り込むなら必須。WordPress プラグインの通例でもある。§9.6 |

---

## 付録 A. 参考にする既存プラグインとコアの仕組み

2026-09-18 に Web で調査した結果(公式ドキュメント・公式リポジトリを優先。調査は別エージェントが行い、出典を付けて報告したもの)。
番号は本文からの参照用。「推測」と書いたものは事実ではなく調査からの見立て。

### A-1. アカウントの置き場所 — ほぼ全員が `wp_users`

- BuddyPress・bbPress・Ultimate Member・WooCommerce・MemberPress はいずれも `wp_users` / `wp_usermeta` が唯一のアカウント基盤で、独自テーブルは `user_id` を持つ付属データ。
  bbPress は独自テーブルを作らない([bbPress](https://codex.bbpress.org/getting-started/before-installing/stored-database-data/))。
  MemberPress は「ユーザの作成・保存・管理・認証を完全に WordPress API に依存」と明記([MemberPress](https://memberpress.com/docs/where-is-data-stored/))。
- 独自のユーザテーブル+独自認証は少数派(Simple Membership、WP eMember)。しかも Simple Membership の公式文書自身が「会員レコードと WP ユーザレコードの 2 つを作って連携する」と述べており、
  結局は二重管理になっている([Simple Membership](https://simple-membership-plugin.com/understanding-the-connection-between-simple-membership-members-and-wordpress-users/))。
- 「アカウントを持たない人」を扱う定石は独自認証ではなく、`user_id` を空にできるデータ側の設計(WooCommerce、Easy Digital Downloads の customers テーブル)。
  → 本仕様の「プロフィールは投稿、持ち主は任意で紐付く」と同じ考え方。

### A-2. 低権限ロール

- WooCommerce の `customer` ロールの権限は `read` だけ([class-wc-install.php](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-install.php))。
- 管理画面からの締め出しはロール名ではなく**権限で判定**: `edit_posts` / `manage_woocommerce` / `view_admin_dashboard` のどれも無ければ My Account へ転送。
  `admin-ajax.php` と `admin-post.php` は除外([class-wc-admin.php](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/admin/class-wc-admin.php))。いちばん真似しやすい形。
- Ultimate Member はロールごとに「wp-admin に入れるか」「管理バーを隠すか」を設定で持つ([UM](https://docs.ultimatemember.com/article/103-user-roles-settings))。
- bbPress の動的ロール: ロールを DB に保存せず実行時に `$wp_roles` へ足す。理由は「ロールや権限を変えるたびに DB 移行スクリプトが要るのは労力に見合わない」
  ([bbPress フォーラム](https://bbpress.org/forums/topic/roles-and-capabilities-in-bbpress-2-2/))。有効化フックの無い mu-plugin と相性がよい。

### A-3. 公開される人物像とアカウントの分離

- Co-Authors Plus: guest author は投稿タイプ `guest-author`。公開 slug は専用メタ、WP ユーザとの連携は `cap-linked_account`
  ([ソース](https://github.com/Automattic/Co-Authors-Plus/blob/master/php/class-coauthors-guest-authors.php))。
- PublishPress Authors: 著者の実体はタクソノミーのターム。WP ユーザとの対応は term meta で、無ければゲスト = アカウント不要
  ([ソース](https://github.com/publishpress/PublishPress-Authors/blob/development/src/core/Classes/Objects/Author.php))。
- コア: 著者 URL の slug は `user_nicename`(varchar 50)。`user_login` と別の値にできる。予約ユーザ名は `illegal_user_logins` フィルタ。
- BuddyPress のプロフィール URL は既定で `user_nicename`。Ultimate Member は 7 種類から選べる(ユーザ名・氏名・ID・ハッシュ・任意のメタ)。
- **Ultimate Member は、プロフィール URL から `/user/` を取り除いてルート直下にすることを公式に「不可能」としている**。理由はコアの予約語と経路の競合
  ([UM](https://docs.ultimatemember.com/article/1890-remove-the-word-user-or-profile-from-the-profile-url))。→ §5.3。

### A-4. 「自分の投稿だけ編集可」の標準手法

- `capability_type` が `post` / `page` 以外だと `map_meta_cap` は既定で false。**明示的に true にする**([WP_Post_Type::set_props](https://developer.wordpress.org/reference/classes/wp_post_type/set_props/))。
- 所有者限定は「`edit_others_*` / `delete_others_*` を与えない」ことで実現する。公開済みの自分の投稿の編集には `edit_published_*` が要る([map_meta_cap](https://developer.wordpress.org/reference/functions/map_meta_cap/))。
- `create_posts` は既定で `edit_posts` 相当に対応付けられる([get_post_type_capabilities](https://developer.wordpress.org/reference/functions/get_post_type_capabilities/))。→ §7.3 で別権限に分けた理由。
- メディア: コアは既定で絞り込まない。絞るなら `ajax_query_attachments_args`(モーダル)・`pre_get_posts`(一覧)・`rest_attachment_query`(REST)の 3 経路。
  ただし表示上のフィルタであってアクセス制御ではなく、`/wp-json/wp/v2/media` は未認証で全件一覧できる。→ §7.3・§10。
- `unfiltered_html` を与えると KSES フィルタが丸ごと外れる = 保存型 XSS に直結。低権限ロールには付けない。

### A-5. mu-plugin の制約

- 「must-use プラグインでは有効化フックは実行されない」「サブディレクトリは探さない」([公式](https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/))。
- `add_role()` はオプションに書き込む。毎回呼ばず条件付きで。**既存ロールへの再実行は権限リストの変更も含めて何もしない**([add_role](https://developer.wordpress.org/reference/functions/add_role/))。
- `flush_rewrite_rules()` は「性能面で極めて高コスト」([公式](https://developer.wordpress.org/reference/classes/wp_rewrite/flush_rules/))。→ rewrite rules を足さない設計(§5.3)の根拠。
- 「バージョン付きの初期化」を明文で推奨する公式文書は見つかっていない(上の注意書きからの実務的な帰結 = 推測)。

### A-6. ユーザ列挙

- コアの公式見解: 「ユーザ名やユーザ ID は秘密と見なさない。REST の `/wp/v2/users` が公開で見えるのは意図したもの」
  ([Reporting Security Vulnerabilities](https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/))。
- 経路は 4 つで条件が違う: `/?author=N`(公開投稿があるとき転送)、REST(`show_in_rest` な投稿タイプで公開投稿を持つユーザ。`public` かどうかは関係ない)、
  ユーザサイトマップ(`public` な投稿タイプで公開投稿を持つユーザ)、**oEmbed**(`author_name` と `author_url` を返す。見落とされやすい)。
- 未認証で返るのは `slug`(`user_nicename`)と `name`(`display_name`)だけ。`user_login` とメールは返らない。→ §10 の安価な緩和。
- 止め方: `rest_endpoints`、`wp_sitemaps_add_provider`([告知記事](https://make.wordpress.org/core/2020/07/22/new-xml-sitemaps-functionality-in-wordpress-5-5/))、`oembed_response_data`。

### A-7. 登録の流れ

- `wp_new_user_notification()` は平文パスワードを送らず、パスワード設定リンクを送る。鍵はハッシュして保存され、期限は既定 24 時間
  ([get_password_reset_key](https://developer.wordpress.org/reference/functions/get_password_reset_key/))。→ §7.5 の招待はこれに乗る。
- 招待制そのものはコアに無い。実質は「登録を閉じる+管理者が作る」。
- WordPress 7.0 で、一般設定の「新規ユーザのデフォルト権限」から Administrator と Editor が外された
  ([7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/))。背景に、登録フォームの既定ロールを管理者にできてしまう権限昇格の実例がある。
- オープン登録のスパム被害は実例多数。BuddyPress は単一サイトでも `signups` テーブルでメール確認の 2 段階。

### A-8. コアの認証まわり

- WordPress 6.8 でパスワードは bcrypt に。リセット鍵や Application Passwords は BLAKE2b。既存ハッシュは次回ログイン時に自動で更新
  ([告知](https://make.wordpress.org/core/2025/02/17/wordpress-6-8-will-use-bcrypt-for-password-hashing/))。
- Application Passwords は REST / XML-RPC の Basic 認証専用で、**権限の絞り込みができず、そのユーザの全権限を引き継ぐ**
  ([統合ガイド](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/))。→ §7.4 で持ち主には無効化する理由。
- 2 段階認証は 2026-09 時点でもコアに無く、プラグイン([Two-Factor](https://wordpress.org/plugins/two-factor/))。
- nonce は認証・認可の代わりにならない。必ず `current_user_can()` と組み合わせる([Nonces](https://developer.wordpress.org/apis/security/nonces/))。

### A-9. フロントエンド編集か、管理画面か

- 会員系プラグインは**フロントで完結させるのが主流**(WooCommerce の My Account、MemberPress と Paid Memberships Pro の「会員をダッシュボードに入れない」設定、Ultimate Member)。
- 一方で「低権限ユーザに wp-admin を見せるべきでない」という WordPress 公式の推奨は見つかっていない。コアの既定はむしろ、`read` 権限でダッシュボードと自分のプロフィールに入れる。
- 公式が求めるのは場所を問わず nonce + 権限検査 + サニタイズ/エスケープの 3 点([Security](https://developer.wordpress.org/apis/security/))。
- 推測: フロント編集の利点は攻撃面の縮小と使い勝手、欠点は編集 UI を全部自作することによる不具合の増加。

### A-10. ルート直下 `/<slug>/` を投稿タイプに割り当てる

- `'rewrite' => ['slug' => '']` は効かない。コアが空の slug を投稿タイプ名で上書きする([class-wp-post-type.php](https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-includes/class-wp-post-type.php))。
- 投稿タイプの rewrite rule は固定ページに構造上負ける。同名の固定ページが実在すれば必ず固定ページが勝つ。
- **`wp_unique_post_slug()` は投稿タイプをまたいで重複を防がない**([リファレンス](https://developer.wordpress.org/reference/functions/wp_unique_post_slug/))。横断の一意性は自前で担保する。
- 定石は `request` フィルタで 404 を拾い直す方式。wordpress.org 配布の [`remove-cpt-base`](https://plugins.svn.wordpress.org/remove-cpt-base/trunk/remove-cpt-base.php) がこれで、解決順は 投稿 → 固定ページ → 対象 CPT。
  更新履歴は 404 関連の修正の繰り返し = 慢性的に壊れやすい領域。
- Multisite サブディレクトリ型の予約サイト名は 10 個(`page` `comments` `blog` `files` `feed` `wp-admin` `wp-content` `wp-includes` `wp-json` `embed`)。
  ユーザ名側の禁止語は `www` `web` `root` `admin` `main` `invite` `administrator`。サイト名に使える文字は §4.1 のとおり。ユーザテーブルはネットワーク全体で共有。

### A-11. 調査で確認できなかったこと

- core.trac.wordpress.org と wordpress.stackexchange.com は本文を取得できず、チケットや回答の中身は未確認。
- 2 段階認証のコア統合の計画。Xserver for WordPress における Multisite の公式サポート可否。

## 改訂履歴

| 版 | 日付 | 内容 |
|---|---|---|
| v0.1 | 2026-09-18 | 初版(草案)。環境の実測・f_tsf の棚卸し・規約の確認にもとづく提案。すべて未裁定 |
| v0.1.1 | 2026-09-18 | 先行事例の調査結果を反映: 付録 A を出典つきで全面更新。§5.2・§5.3(URL 解決が最も壊れやすい箇所であることと守り方)、§7.3(新規作成権限の分離、画像は公開物、`map_meta_cap` の明示、`add_role()` の挙動)、§10(安価な緩和策、oEmbed、メディア一覧)、D5 を補強 |
| v0.2 | 2026-09-18 | 見た目の切り替えを追加: §9.6 スキン(トークン+同梱 CSS、ブロックテーマの `theme.json` からの取り込み、テーマ流用の深さ 4 段階、ライセンス)、§1.3 用語、§6.2・§8・§9.1・§12 の関連箇所、D14〜D16 |
