# docs — 文書の地図

doujin-profile は `https://profile.xwp.jp/<slug>/` にユーザごとの同人サークル紹介サイトを出す WordPress mu-plugin。
いまは仕様をまとめている段階で、コードはまだ無い。

**このリポジトリは公開**。パスワード・ログイン URL の変更値・メールアドレス・サポートとのやり取りの原文・DB ダンプは入れない。

| 文書 | 役割 | 書き方 |
|---|---|---|
| [`spec.md`](spec.md) | 仕様の**現在形**。何を・どう作るか、要決定事項 | 裁定が出たら本文を更新し、末尾の改訂履歴に残す |
| [`design-log.md`](design-log.md) | 設計の**過程**。いつ何を調べ、何を比べ、誰が何を決めたか | 追記のみ・時系列。裁定は「ユーザ裁定 YYYY-MM-DD」 |
| [`environment.md`](environment.md) | 導入先で**実測した事実**と作業ガード | 確かめたことだけ。調査日と方法を明記 |
| [`xserver-inquiry.md`](xserver-inquiry.md) | ホスト(Xserver)への**問い合わせ文案**と、回答ごとの次の動き | 送るのはサイト所有者。回答の原文は公開リポジトリに載せず、design-log には日付と要旨だけ |
| [`multisite-trial.md`](multisite-trial.md) | Multisite 化の検証(Phase 0)の**手順・結果・戻し方** | 変更の前に手順と戻し方を書き、実施後に結果を足す |

読む順番: はじめてなら `spec.md` の §0(要約)→ `design-log.md`(なぜそうなったか)。本番を触る前に `environment.md` の §10。
