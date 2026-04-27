# AI Site Search Chatbot

※ 100% AI 生成のため自己責任でご利用ください。

WordPress サイト訪問者向けに、サイト内検索と生成 AI を組み合わせたチャットボットを提供するプラグインです。

## 現在実装済み

- 公開 REST API: POST /wp-json/ai-site-search-chatbot/v1/chat
- サイト内検索: 公開投稿タイプを対象にキーワード検索
- AI 応答: OpenAI、Claude、GitHub Models、Google Gemini を切り替えて利用可能
- 管理画面: API キー、モデル名、システムプロンプト、参照件数、表示場所、デザインの設定
- 接続確認: API キーとモデル ID のバリデーション、および管理画面上でのチャットテスト
- フロント UI: 自動表示、ショートコード、Gutenberg ブロックに対応した訪問者向けチャット画面
- デザイン切り替え: Business / Cute の 2 テーマを選択可能

## インストール

1. プラグインを有効化
2. 管理画面 設定 > AI Site Search Chatbot を開く
3. 利用したい AI プロバイダを選び、API キーとモデル ID を設定
4. 必要に応じて「Validate API Key and Model」と「Run Admin Chat Test」で接続確認
5. 「Enable chatbot display on the public site」を有効化して保存

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
- Display Location: 全ページ表示、トップページのみ表示、ショートコードまたはブロック設置箇所のみ表示
- Chatbot Design: Business / Cute

## 補足

- 公開エンドポイントのため、簡易レート制限を実装しています。
- 返答には参照元 URL 一覧を同時に返します。
- GitHub Models を使う場合は、外部 API 用の models:read 権限付きトークンが必要です。