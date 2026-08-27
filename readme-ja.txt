=== AI Site Search Chatbot ===
Contributors: shokun0803
Tags: chatbot, ai, search, faq, gutenberg
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

サイト内検索と生成 AI を組み合わせ、訪問者向けに応答するチャットボットを追加します。

== 説明 ==

AI Site Search Chatbot は、サイト訪問者向けのチャットウィジェットを追加するプラグインです。訪問者が質問すると、プラグインは公開コンテンツ（投稿、固定ページ、その他の公開投稿タイプ）を検索し、その検索結果と質問を、WordPress コアの Connectors API 経由で接続された AI プロバイダーに送信します。AI はサイト自身のコンテンツをもとに回答し、参照元のリンクも合わせて提示します。

本プラグイン自体は AI 連携や API キーの保存機能を持ちません。WordPress 7.0 で導入された AI Client（`wp_ai_client_prompt()`）を利用し、以下いずれかの公式プロバイダープラグインが提供する接続を使用します。

* AI Provider for OpenAI
* AI Provider for Anthropic (Claude)
* AI Provider for Google (Gemini)

`設定 > コネクタ` でいずれかのプロバイダープラグインを導入・接続したうえで、本プラグインの設定画面でその接続を選択してください。

= 主な機能 =

* チャットウィジェットを動かす公開 REST エンドポイント（`POST /wp-json/ai-site-search-chatbot/v1/chat`）
* 公開投稿タイプを対象にしたサイト内検索と、検索結果に基づく AI 回答
* 直接一致するコンテンツがない場合でも、サイト名・説明文・公開コンテンツ要約を使った汎用的なサイト案内回答
* サイトごとに AI プロバイダーとモデルを選択可能。接続確認と管理画面上でのチャットテストに対応
* Saved Knowledge Base: 一般化した質問・回答ペアをレビューし承認状態を管理することで、以降の類似質問に AI 呼び出しなしで応答可能。CSV エクスポート / インポートにも対応
* 管理者以外のユーザーに Saved Knowledge Base の管理のみを許可する任意の権限（capability）
* 全ページ自動表示、トップページのみ自動表示、ショートコードまたは Gutenberg ブロックによる手動配置の 3 種類の表示方法
* Business / Cute の 2 種類のウィジェットデザイン
* 管理画面での訪問者チャットログ（直近 50 件）と日次 AI 利用状況の確認
* 公開チャットエンドポイントのレート制限（サイト全体の日次上限を含む）
* プラグイン削除時に設定・ログ・保存済みナレッジを保持するか削除するかを選べるデータ保持設定

= 外部サービスについて =

本プラグインが直接サードパーティサービスを呼び出すことはありません。チャットのリクエストは WordPress コアの AI Client に送られ、そこから `設定 > コネクタ` で接続した AI プロバイダー（OpenAI、Anthropic、Google のいずれか）へ、各公式プロバイダープラグイン自身の接続と API キーを使って送信されます。本プラグインが API キーを保存・送信することはありません。プロバイダーに送信された後の訪問者の質問がどのように扱われるかについては、各プロバイダーの利用規約・プライバシーポリシーをご確認ください。

* OpenAI: [https://openai.com/policies/](https://openai.com/policies/)
* Anthropic: [https://www.anthropic.com/legal](https://www.anthropic.com/legal)
* Google: [https://policies.google.com/](https://policies.google.com/)

== インストール ==

1. WordPress 7.0 以上で動作していることを確認してください。
2. 利用したい AI プロバイダーの公式プロバイダープラグイン（AI Provider for OpenAI / AI Provider for Anthropic / AI Provider for Google のいずれか）を導入・有効化します。
3. `設定 > コネクタ` を開き、対象プロバイダーの API キーを入力して接続します。
4. 本プラグインを導入・有効化します。
5. `設定 > AI Site Search Chatbot` を開き、接続済みのプロバイダーを選択してモデル ID を設定します。
6. 必要に応じて「Validate Connection and Model」と「Run Admin Chat Test」で動作を確認します。
7. 「Enable chatbot display on the public site」を有効化して保存します。
8. 表示方法を選びます。全ページ自動表示、トップページのみ自動表示、または `[ai_site_search_chatbot]` ショートコードや AI Site Search Chatbot ブロックによる手動配置のいずれかです。

== よくある質問 ==

= このプラグインは API キーを保存しますか？ =

保存しません。API キーは WordPress コアの `設定 > コネクタ` 画面で、OpenAI / Anthropic / Google の各公式プロバイダープラグインにより一元管理されます。本プラグインはその接続を利用してリクエストを送信するだけです。

= サイト内検索で良い候補が見つからない場合はどうなりますか？ =

そのような場合でも、サイト名・説明文・公開コンテンツ要約をもとにした「サイトについて」の一般的な案内に回答できます。無関係な内容を提示する代わりに、その旨を伝えます。

= AI の利用回数を制限できますか？ =

できます。設定画面には接続単位の利用回数上限（10 分あたり・1 時間あたり）と、サイト全体の AI 呼び出し日次上限があり、利用量やコストの管理に役立ちます。

= プラグインをアンインストールするとデータはどうなりますか？ =

選択できます。「Uninstall Data Policy」設定で、プラグイン削除時に設定・チャットログ・保存済みナレッジベースを保持するか削除するかを指定できます。

= チャットウィジェットを手動で配置するには？ =

`[ai_site_search_chatbot]` ショートコード、または固定ページ・投稿編集画面で AI Site Search Chatbot ブロックを追加してください。どちらも任意の `theme` 属性（`business` または `cute`）に対応し、ショートコードはさらに `title` と `greeting` 属性にも対応しています。

== スクリーンショット ==

1. 参照元リンク付きで訪問者の質問に回答する公開チャットウィジェット
2. AI プロバイダー、モデル、表示オプションを設定する管理画面
3. 承認状態と CSV エクスポート / インポートに対応した Saved Knowledge Base のレビュー画面

== 変更履歴 ==

= 1.0.0 =
* WordPress.org での公開初期バージョン

= 0.7.0 =
* 管理者以外のユーザーに、フルの管理者権限なしで Saved Knowledge Base のエントリを作成・編集できる新しい権限（capability）`aiscb_manage_knowledge_base` を追加。設定画面のチェックボックス一覧から付与・解除できる
* 上記権限を持つが `manage_options` を持たないユーザー向けに、独立したトップレベル管理メニュー「Saved Knowledge Base」を新設
* 対応する REST エンドポイント（一覧・取得・作成・更新）を新しい権限でも利用できるよう拡張。削除・エクスポート・インポートは引き続き管理者専用

= 0.6.0 =
* AI プロバイダーの接続方式を、プラグイン独自の API キー保存から WordPress 7.0 の Connectors API / AI Client（`設定 > コネクタ`、`wp_ai_client_prompt()`）経由に変更。対応プロバイダーは OpenAI / Anthropic (Claude) / Google Gemini
* 公式コネクタが存在しない GitHub Models のサポートを終了。既存サイトは自動的に OpenAI に切り替わり、管理画面に通知を表示
* プラグイン独自の API キー暗号化保存、環境変数 / 定数によるオーバーライド、同梱 Anthropic PHP SDK を削除し実装を簡素化
* WordPress 7.0 以上が必須に
* 翻訳ファイルを更新し、未翻訳だった管理画面文字列を翻訳

= 0.5.7 =
* レート制限に使う訪問者 IP を既定で `REMOTE_ADDR` のみから取得するよう変更。転送ヘッダーはリバースプロキシ/CDN 配下であることを明示した場合のみ使用し、ヘッダー偽装によるレート制限回避を防止
* サイト全体の AI 呼び出し日次上限（既定 500 回）を追加し、分散・自動化された濫用による API コストの暴走を防止
* AI 利用上限到達後もメッセージ分類用の AI 呼び出しが発生していた問題を修正

= 0.5.6 =
* チャット履歴を localStorage に最大 3 時間保持するよう変更し、ページ遷移後も会話内容を復元可能に

= 0.5.5 =
* ショートコード実行後にテキストを抽出するよう変更し、ショートコードが生成するページ内容を AI が正確に参照できるように修正
* チャット回答にショートコードタグ文字列が含まれないようルールを追加
* プロンプトインジェクション対策を強化

= 0.5.4 =
* AI 利用量集計の精度を改善し、Google Gemini の課金表示と揃うよう調整
* thinking model 利用時のタイムアウトを避けるためリクエストタイムアウトを延長

= 0.5.3 =
* 「Clear Plugin Cache」アクションを追加
* プラグイン用 transient の削除処理を整理

= 0.5.2 =
* 管理画面ログタブに日次利用メトリクス表示を追加
* ログ管理画面にデータ削除アクションを追加

= 0.5.1 =
* 回答の参照ソースを関連度順に並べるよう改善
* リクエストごとの AI 利用量集計を追加

= 0.5.0 =
* Saved Knowledge Base タブを追加
* CSV エクスポート / インポート、検索・状態フィルタを追加
* Uninstall Data Policy と uninstall.php を追加

= 0.4.1 =
* API キー / トークンを WordPress ソルト由来キーで暗号化して保存

= 0.4.0 =
* Claude プロバイダーに認証方法の切り替えを追加

= 0.3.0 =
* Claude プロバイダーを公式 Anthropic PHP SDK ベースの実装に変更

= 0.2.0 =
* 汎用サイト案内機能、管理画面チャットログビューア、AI 応答回数上限、Google Gemini プロバイダー、ブロックエディタ対応を追加

== Upgrade Notice ==

= 1.0.0 =
WordPress.org での公開初期バージョンです。

= 0.7.0 =
Saved Knowledge Base を管理者以外のユーザーに任せられる任意の権限を追加しました。既存サイトでの管理者の挙動に変更はありません。

= 0.6.0 =
AI プロバイダーの接続方式が WordPress 7.0 の Connectors API 経由に変わりました。更新後は `設定 > コネクタ` でプロバイダーを再接続し、本プラグインの設定画面で選び直してください。WordPress 7.0 以上が必要です。
