# 元の大きい画像の置き場所の調査(T.51.1)

2026-10-24 調査(Fable による読み取りだけの調査を Claude が整理)。料金・規約は断定しない。契約前に再確認する。

## 前提

- 本体は長辺 2560px を超える画像を縮め、表示には `-scaled` 版を使う。元の画像(20〜30MB)もサーバに残る(`wp_get_original_image_path()`)。1 作品 32 枚で約 0.8GB。
- サイト所有者の方針(2026-10-24): 「元画像を保管するサービスを探す」。

## 分かったこと

- **「元の画像だけを外へ出し、表示用はサーバに残す」を設定だけでできる配布プラグインは見つからなかった。** 配布物でできるのは「メディア全部を外へ出す」か「元をその場で縮める・消す」のどちらか。
- Advanced Media Offloader の「Smart Local Cleanup」は、元を残してサイズ違いを消す(望みの逆)。
- Media Cloud(`ilab-media-tools`)は 2026-08-20 にセキュリティ問題で配布停止。

### プラグイン

| プラグイン | 有効 | 最終更新 / 対応 WP | Multisite | 保管先 | 無料版 |
|---|---|---|---|---|---|
| Advanced Media Offloader | 7,000+ | 2026-10-21 / 7.1.3 | 公式の記述なし(4.7.0 で multisite の不具合修正の記録あり) | S3・R2・B2・Wasabi・DO・MinIO | 全部無料。ローカルを残す / サイズ違いを消す / 全部消す |
| WP Offload Media Lite | 30,000+ | 2026-09-21 / 7.1.3 | ネットワーク単位の設定、全サイト 1 バケット(公式に明記) | S3・GCS・DO(Wasabi・MinIO は公式ブログの手順) | 新規分は無料。既存分の一括移行などは有料版 |
| Imsanity | 200,000+ | 2026-08-20 / 7.1.3 | 記述なし | (外へ出さない) | アップロード時に縮めて元を置き換える |

### 保管サービス(保管料だけの目安)

| サービス | 300GB / 1TB | 取り出し | 備考 |
|---|---|---|---|
| Cloudflare R2 | 約 $4.4 / 約 $15 | 無料 | 10GB 無料。S3 互換。公開配信に独自ドメインが要るか未確認 |
| Backblaze B2 | 約 $2 / 約 $7 | 保管量の 3 倍まで無料 | アジアのリージョンなし |
| Wasabi | $7.99 / $7.99 | 無料 | 最低 1TB 課金・90 日。東京・大阪あり |
| さくらのクラウド オブジェクトストレージ | 約 1,485 円 / 約 5,100 円 | さくら以外からの取り出しは有料(88 円/10GiB) | 1 バケット 495 円/月に 100GiB 込み。日本円 |
| AWS S3(東京)・GCS | 約 $7〜8 / 約 $23〜25 | 有料(単価は未確認) | 公式の単価を取れなかった |

メディア全部を外へ出すプラグインは、画像の URL を保管先に書き換えるので、**訪問者の画像の表示が保管先から出る**。取り出しが無料の R2 か B2 が向く。

## 案

- **配布プラグインで全部を外へ**: Advanced Media Offloader + Cloudflare R2(自作なし、当面は無料枠〜月数百円)。Multisite は実機で確かめる必要がある。
- **実績重視で全部を外へ**: WP Offload Media Lite + S3(自作なし、月 $7〜 + 取り出し料)。既存分の移行は有料版。
- **元を消す**: 縮めたあと元を消す(数行の自作、または Imsanity)。月 0 円。元の原稿は作者の手元にある前提。
- **元だけ外へ**: 自作が大きい(保管先への送信と署名の実装)。標準優先に反する。

## 分からなかったこと

Advanced Media Offloader の Multisite の正式な対応、WP Offload Media Lite で R2 を使えるか、S3・GCS の現行の単価、Imsanity のネットワーク設定、R2 の公開配信の条件。

## 出典(すべて 2026-10-24 閲覧)

- https://wordpress.org/plugins/advanced-media-offloader/
- https://wordpress.org/plugins/amazon-s3-and-cloudfront/ ・ https://deliciousbrains.com/wp-offload-media/doc/multisite-per-subsite-bucket-and-custom-domain-settings/
- https://wordpress.org/plugins/imsanity/ ・ https://wordpress.org/plugins/ilab-media-tools/
- https://wp-kama.com/2284/the-scaled-suffix-for-images
- https://developers.cloudflare.com/r2/pricing/ ・ https://www.backblaze.com/cloud-storage/pricing ・ https://wasabi.com/pricing ・ https://cloud.sakura.ad.jp/products/object-storage/
