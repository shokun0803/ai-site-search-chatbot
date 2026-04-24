# AI Site Search Chatbot

WordPress サイト訪問者向けに、サイト内検索と生成 AI を組み合わせたチャットボットを提供するプラグインです。

## 現在実装済み

- 公開 REST API: POST /wp-json/ai-site-search-chatbot/v1/chat
- サイト内検索: 公開投稿タイプを対象にキーワード検索
- AI 応答: OpenAI Chat Completions を利用（API キー未設定時は検索ベース応答にフォールバック）
- フロント UI: ショートコードで埋め込める訪問者向けチャット画面
- 管理画面: API キー、モデル名、システムプロンプト、参照件数の設定

## インストール

1. プラグインを有効化
2. 管理画面 設定 > AI Site Search Chatbot を開く
3. 必要に応じて API キーとモデルを設定

## 使い方

固定ページや投稿本文に次を配置します。

```text
[ai_site_search_chatbot]
```

任意属性:

```text
[ai_site_search_chatbot title="サイト案内チャット" greeting="ご質問をどうぞ。サイト内を検索して回答します。"]
```

## 補足

- 公開エンドポイントのため、簡易レート制限を実装しています。
- 返答には参照元 URL 一覧を同時に返します。