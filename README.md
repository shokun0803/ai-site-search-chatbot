# AI Site Search Chatbot

※ 100% AI 生成のため自己責任でご利用ください。

WordPress サイト訪問者向けに、サイト内検索と生成 AI を組み合わせたチャットボットを提供するプラグインです。

## 動作要件

- PHP 8.1 以上
- WordPress 7.0 以上（Connectors API / AI Client がコアに必要）
- 利用したい AI プロバイダーの公式プロバイダープラグイン（AI Provider for OpenAI / AI Provider for Anthropic / AI Provider for Google のいずれか）を導入し、`設定 > コネクタ` で API キーを接続していること

Composer 依存はありません。プラグイン本体は WordPress コアの AI Client（`wp_ai_client_prompt()`）を通じて接続済みプロバイダーにリクエストを送るだけです。

## 現在実装済み

- 公開 REST API: POST /wp-json/ai-site-search-chatbot/v1/chat
- サイト内検索: 公開投稿タイプを対象にキーワード検索
- AI 応答: WordPress 7.0 の Connectors API 経由で接続した OpenAI、Claude (Anthropic)、Google Gemini を切り替えて利用可能
- 汎用サイト案内: サイト名、説明文、公開コンテンツ要約をもとに、サイトの使い方や掲載情報の種類などの案内質問にも AI が応答
- 管理画面: プロバイダーの接続状況表示、モデル名、システムプロンプト、参照件数、表示場所、デザインの設定
- Saved Knowledge Base: 一般化した質問・回答ペアを管理画面でレビューし、承認状態を切り替えながら再利用可能
- ナレッジ管理: Saved Knowledge Base を CSV でエクスポート / インポート可能
- 接続確認: モデル ID のバリデーション、および管理画面上でのチャットテスト
- 利用状況確認: 最新 50 件の公開チャット履歴を管理画面で確認可能。管理画面に表示される使用トークン数は目安であり、実際のプロバイダー側使用量や課金と差異が出る場合があります。
- フロント UI: 自動表示、ショートコード、Gutenberg ブロックに対応した訪問者向けチャット画面
- デザイン切り替え: Business / Cute の 2 テーマを選択可能

## インストール

1. WordPress 7.0 以上で、利用したい AI プロバイダーの公式プロバイダープラグイン（AI Provider for OpenAI / AI Provider for Anthropic / AI Provider for Google）を導入・有効化
2. 管理画面 設定 > コネクタ で対象プロバイダーの API キーを入力して接続
3. 本プラグインを有効化し、管理画面 設定 > AI Site Search Chatbot を開く
4. 接続済みのプロバイダーを選び、モデル ID を設定
5. 必要に応じて「Validate Connection and Model」と「Run Admin Chat Test」で接続確認
6. 「Enable chatbot display on the public site」を有効化して保存

補足: API キーはこのプラグインではなく WordPress コアの `設定 > コネクタ` 画面で一元管理されます（複数の対応プラグインで共有可能）。必要に応じて、wp-config.php またはサーバー環境変数（例: `OPENAI_API_KEY` / `ANTHROPIC_API_KEY` / `GOOGLE_API_KEY`）を定義すると、コネクタ画面の保存値よりそちらが優先されます。

### Claude (Anthropic) を使う場合

AI Provider for Anthropic プラグインを有効化し、[Anthropic コンソール](https://platform.claude.com/settings/keys) で発行した API キーを `設定 > コネクタ` に登録してください。本プラグインの設定画面でモデル ID に `claude-sonnet-4-6` または `claude-opus-4-7` などを指定します。

## 使い方

表示方法は 3 通りあります。

### 1. 全ページまたはトップページに自動表示

管理画面の Display Location で以下を選ぶと、ショートコードを置かなくても自動表示されます。

- Display on all pages
- Display only on the site top page

### 2. ショートコードで表示

Display Location を Display only where the shortcode is placed に設定したうえで、固定ページや投稿本文に次を配置します。

```text
[ai_site_search_chatbot]
```

任意属性:

```text
[ai_site_search_chatbot title="サイト案内チャット" greeting="ご質問をどうぞ。サイト内を検索して回答します。"]
```

テーマも個別指定できます。

```text
[ai_site_search_chatbot theme="cute"]
```

### 3. Gutenberg ブロックで表示

ブロックエディタで AI Site Search Chatbot ブロックを追加すると、ショートコードを手入力せずに配置できます。

## 主な設定項目

- AI Provider: OpenAI / Claude (Anthropic) / Google Gemini（各プロバイダーの接続は 設定 > コネクタ で管理）
- Model: 利用するモデル ID をそのまま指定
- System Prompt: 回答方針を調整するシステムプロンプト
- Maximum Sources: 回答時に参照する検索結果の上限件数
- AI Reply Limit (10 minutes / 1 hour): 同一接続からの AI 応答回数の上限。まずは 10 分で 6 から 10 回、1 時間で 20 から 40 回程度を目安に調整
- Saved Knowledge Base: 一般化した質問・回答ペアの確認、承認状態の切り替え、CSV のエクスポート / インポート
- Display Location: 全ページ表示、トップページのみ表示、ショートコードまたはブロック設置箇所のみ表示
- Chatbot Design: Business / Cute
- Uninstall Data Policy: プラグイン削除時に設定・ログ・保存済みナレッジを保持するか削除するかを選択
- Recent Visitor Chat Logs: 公開チャットの質問、回答、時刻、参照件数、マスク済み IP を管理画面で確認

## 補足

- 公開エンドポイントのため、簡易レート制限を実装しています。
- 返答には参照元 URL 一覧を同時に返します。
- サイト内検索結果が少ない場合でも、サイト名、説明文、公開コンテンツ要約を使ったサイト案内向け回答を返せます。
- 管理画面に表示される使用トークン数は目安であり、実際のプロバイダー側使用量や課金と差異が出る場合があります。

## 変更履歴

### 0.6.0

- AI プロバイダーの接続方式を、プラグイン独自の API キー保存から WordPress 7.0 の公式 Connectors API / AI Client（`設定 > コネクタ`、`wp_ai_client_prompt()`）経由に変更。対応プロバイダーは OpenAI / Claude (Anthropic) / Google Gemini の 3 つに統一
- 公式コネクタが存在しない GitHub Models プロバイダーのサポートを終了。既存サイトで選択されていた場合は自動的に OpenAI に切り替え、管理画面に通知を表示
- Claude 独自の Bearer Token（Agent SDK クレジット）認証を廃止し、他プロバイダーと同様に公式コネクタの API キー認証に統一
- プラグイン独自の API キー暗号化保存、環境変数 / 定数によるオーバーライド、Anthropic PHP SDK 同梱（`vendor/`）を削除し、実装を簡素化
- WordPress 7.0 以上が必須に（`Requires at least: 7.0`）
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新し、未翻訳だった管理画面文字列（接続状況表示、サイト案内・ナレッジ生成用の内部プロンプトなど）を含めすべて翻訳

### 0.5.7

- セキュリティ強化：レート制限に使う訪問者 IP を既定で `REMOTE_ADDR` のみから取得するよう変更。`X-Forwarded-For` などの偽装可能な転送ヘッダーは、リバースプロキシ/CDN 配下であることを設定で明示した場合のみ使用（新設「プロキシヘッダーを信頼する」設定）。これにより、ヘッダー偽装によるレート制限回避を防止
- セキュリティ強化：サイト全体の 1 日あたり AI 呼び出し回数に上限（新設「AI 返信上限（サイト全体・日次）」設定、既定 500 回）を追加。IP 分散・自動化された濫用による API コストの暴走を防ぐハード上限として、ルーティング・検索クエリ抽出・回答生成を含むすべての AI 呼び出しを集計・制限
- セキュリティ強化：AI 利用上限に達した後もメッセージ分類（ルーティング）用の AI 呼び出しが発生していた問題を修正し、上限到達後は AI を呼ばずルールベース処理にフォールバック

### 0.5.6

- チャット履歴を localStorage に最大3時間保持するよう変更。ページ遷移後にチャットを開くと会話内容が復元されるようになり、回答中のリンクをクリックした後でも履歴を見直せるように改善

### 0.5.5

- ショートコードを実行した後にテキストを抽出するよう変更し、お問い合わせフォームのフィールドラベルやコンテンツ一覧など、ショートコードで生成されるページ内容を AI が正確に参照できるように修正
- チャット回答にショートコードタグ文字列（`[shortcode_name ...]` 形式）が含まれないよう AI への命令文に禁止ルールを追加
- プロンプトインジェクション対策を強化：検索結果コンテンツ内の指示を無視するよう明示、管理者情報・ユーザー情報・設定内容の開示を禁止するルールをシステムプロンプトに追加

### 0.5.4

- AI 利用量集計で thinking tokens を個別に追跡しつつ、Google Gemini の課金表示と揃うよう出力トークン集計を調整
- Gemini の usageMetadata を HTTP エラー時も回収し、thinking model 応答の thought パートをスキップして回答本文を安定して抽出
- thinking model 利用時のタイムアウトを避けるため、AI プロバイダーへのリクエストタイムアウトを 60 秒に延長

### 0.5.3

- 管理画面に「Clear Plugin Cache」アクションを追加し、キャッシュ済み AI 応答と transient 状態を手動でクリア可能に
- マルチサイト環境を含め、プラグイン用 transient の削除処理を整理
- 管理画面と README に、表示されるトークン数が推定値であり実際の課金値と差異があり得る旨を追記
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新

### 0.5.2

- 管理画面のログタブに日次利用メトリクス表示を追加し、使用量カード・グラフ・集計テーブルを確認可能に
- ログ管理画面にデータ削除アクションを追加し、運用時のメンテナンス性を改善
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新

### 0.5.1

- 回答に添付する参照ソースを、回答内容との関連度が高い順で並べるよう改善
- 1 回のチャット処理ごとに AI 利用量を集計し、管理画面のログでトークン数や利用内訳を確認できるよう対応
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新

### 0.5.0

- Saved Knowledge Base タブを追加し、一般化した質問・回答ペアの作成、編集、削除、承認状態管理に対応
- Saved Knowledge Base の CSV エクスポート / インポート、および検索・状態フィルタを追加
- 管理画面のナレッジ導線と操作性を改善
- Uninstall Data Policy と uninstall.php を追加し、プラグイン削除時のデータ保持 / 削除を選択可能に
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新

### 0.4.1

- API キー / Bearer Token を WordPress のソルト由来キーで暗号化して保存するよう改善
- 環境変数 / 定数に設定した秘密情報を管理画面の保存値より優先して利用するよう対応
- 管理画面で保存済みの秘密情報を再表示せず、空欄保存時は既存値を保持する挙動を追記
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新

### 0.4.0

- Claude プロバイダーに認証方法の切り替えを追加（API キー従量課金 / Bearer Token Agent SDK クレジット）
- Bearer Token モード選択時は Anthropic PHP SDK の `authToken` パラメータで認証し、Claude サブスクリプションの月次クレジットを消費
- 管理画面の Claude 設定に「認証方法」ラジオボタンと Bearer Token 入力フィールドを追加（Claude 選択時のみ表示）
- バリデーション・管理チャットテストで認証方法に応じた入力チェックとエラーメッセージに対応
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新

### 0.3.0

- Claude プロバイダーを公式 Anthropic PHP SDK（`anthropic-ai/sdk`）を使った実装に変更
- システムプロンプトキャッシュ（`cacheControl: ephemeral`）を有効化し、Claude 利用時の API コストを削減
- Anthropic PHP SDK を `vendor/` に同梱。サーバー側での Composer 実行が不要に
- 管理画面の Claude プロバイダー説明文を SDK 統合の内容に更新
- 翻訳ファイル（`.pot` / `ja.po` / `ja_JP.po` / `.mo`）を更新

### 0.2.0

- 汎用サイト案内機能を追加（サイト名・説明文・コンテンツ要約を活用した案内応答）
- 管理画面チャットログビューアを追加（最新 50 件）
- AI 応答回数の上限設定（10 分・1 時間）を追加
- Google Gemini プロバイダーを追加
- ブロックエディタ対応・表示場所の切り替え機能を追加