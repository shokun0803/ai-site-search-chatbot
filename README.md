# AI Site Search Chatbot

※ 100% AI 生成のため自己責任でご利用ください。

WordPress サイト訪問者向けに、サイト内検索と生成 AI を組み合わせたチャットボットを提供するプラグインです。

## 動作要件

- PHP 8.1 以上
- WordPress 6.0 以上（推奨）
- 利用したい AI プロバイダーの API キー

依存ライブラリ（[Anthropic PHP SDK](https://github.com/anthropics/anthropic-sdk-php)）は `vendor/` ディレクトリとしてプラグインに同梱しているため、サーバー側での Composer 実行は不要です。

## 現在実装済み

- 公開 REST API: POST /wp-json/ai-site-search-chatbot/v1/chat
- サイト内検索: 公開投稿タイプを対象にキーワード検索
- AI 応答: OpenAI、Claude (Anthropic)、GitHub Models、Google Gemini を切り替えて利用可能
- Claude 統合: 公式 Anthropic PHP SDK を使用。システムプロンプトキャッシュにより、同じシステムプロンプトを繰り返し送信する際の API コストを削減
- 汎用サイト案内: サイト名、説明文、公開コンテンツ要約をもとに、サイトの使い方や掲載情報の種類などの案内質問にも AI が応答
- 管理画面: API キー、モデル名、システムプロンプト、参照件数、表示場所、デザインの設定
- Saved Knowledge Base: 一般化した質問・回答ペアを管理画面でレビューし、承認状態を切り替えながら再利用可能
- ナレッジ管理: Saved Knowledge Base を CSV でエクスポート / インポート可能
- 接続確認: API キーとモデル ID のバリデーション、および管理画面上でのチャットテスト
- 利用状況確認: 最新 50 件の公開チャット履歴を管理画面で確認可能。管理画面に表示される使用トークン数は目安であり、実際のプロバイダー側使用量や課金と差異が出る場合があります。
- フロント UI: 自動表示、ショートコード、Gutenberg ブロックに対応した訪問者向けチャット画面
- デザイン切り替え: Business / Cute の 2 テーマを選択可能

## インストール

1. プラグインを有効化（`vendor/` ディレクトリが同梱されているため Composer は不要）
2. 管理画面 設定 > AI Site Search Chatbot を開く
3. 利用したい AI プロバイダを選び、API キーとモデル ID を設定
4. 必要に応じて「Validate API Key and Model」と「Run Admin Chat Test」で接続確認
5. 「Enable chatbot display on the public site」を有効化して保存

補足: API キー / Bearer Token はデータベースに平文ではなく暗号化して保存されます。入力欄は保存済み値を再表示しないため、空欄のまま保存すると既存の秘密情報は保持されます。

必要に応じて、wp-config.php またはサーバー環境変数で以下を定義すると、管理画面の保存値よりそちらが優先されます。

- OpenAI: AISCB_OPENAI_API_KEY または OPENAI_API_KEY
- Claude API Key: AISCB_CLAUDE_API_KEY または ANTHROPIC_API_KEY
- Claude Bearer Token: AISCB_CLAUDE_BEARER_TOKEN または ANTHROPIC_AUTH_TOKEN
- GitHub Models: AISCB_GITHUB_MODELS_TOKEN または GITHUB_MODELS_TOKEN
- Gemini: AISCB_GEMINI_API_KEY または GEMINI_API_KEY

### Claude (Anthropic) を使う場合

[Anthropic コンソール](https://platform.claude.com/settings/keys) で API キーを発行し、モデル ID に `claude-sonnet-4-6` または `claude-opus-4-7` などを指定してください。公式 PHP SDK を通じてリクエストが送信され、システムプロンプトは自動的にキャッシュされます。

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

- AI Provider: OpenAI / Claude (Anthropic) / GitHub Models / Google Gemini
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
- GitHub Models を使う場合は、外部 API 用の models:read 権限付きトークンが必要です。
- 管理画面に表示される使用トークン数は目安であり、実際のプロバイダー側使用量や課金と差異が出る場合があります。

## 変更履歴

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