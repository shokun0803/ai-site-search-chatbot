=== AI Site Search Chatbot ===
Contributors: shokun0803
Tags: chatbot, ai, search, faq, gutenberg
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a visitor-facing AI chatbot that searches your site content and answers questions using WordPress's built-in AI provider connectors.

== Description ==

AI Site Search Chatbot adds a chat widget for your site visitors. When a visitor asks a question, the plugin searches your public content (posts, pages, and other public post types) and sends the matching results, along with the question, to an AI provider connected through WordPress core's Connectors API. The AI then answers using your site's own content and includes links to the sources it used.

The plugin does not include its own AI integration or API key storage. It uses the AI Client (`wp_ai_client_prompt()`) introduced in WordPress 7.0, and relies on one of the official provider plugins to supply the connection:

* AI Provider for OpenAI
* AI Provider for Anthropic (Claude)
* AI Provider for Google (Gemini)

Install and connect one of these provider plugins under **Settings > Connectors**, then configure this plugin to use that connection.

= Features =

* Public REST endpoint that powers the chat widget (`POST /wp-json/ai-site-search-chatbot/v1/chat`)
* Site search across public post types, with AI-generated answers grounded in the matching content
* General site-guidance answers (using the site name, tagline, and a summary of public content) for questions that are not a direct content match
* Choice of AI provider and model per site, with connection validation and an in-admin chat test
* Saved Knowledge Base: review and approve generalized question/answer pairs so future matching questions can be answered without a new AI call; CSV export and import included
* Optional capability that lets non-administrator users manage the Saved Knowledge Base without full admin access
* Three display modes: automatic on all pages, automatic on the front page only, or manual placement via shortcode or a Gutenberg block
* Two built-in widget designs (Business and Cute)
* Visitor chat log (most recent 50 conversations) and daily AI usage metrics in the admin area
* Rate limiting for the public chat endpoint, including a site-wide daily cap on AI calls
* Adjustable data-retention behavior on uninstall (keep or remove plugin settings, logs, and saved knowledge)

= External services =

This plugin does not call any third-party service directly. It sends chat requests to WordPress core's AI Client, which in turn calls the AI provider you have connected under **Settings > Connectors** (OpenAI, Anthropic, or Google), using the official provider plugin's own connection and API key. No API keys are stored or transmitted by this plugin. See the relevant provider's terms of service and privacy policy for details on how your visitors' questions are processed once sent to that provider:

* OpenAI: [https://openai.com/policies/](https://openai.com/policies/)
* Anthropic: [https://www.anthropic.com/legal](https://www.anthropic.com/legal)
* Google: [https://policies.google.com/](https://policies.google.com/)

== Installation ==

1. Make sure your site is running WordPress 7.0 or later.
2. Install and activate the official provider plugin for the AI service you want to use (AI Provider for OpenAI, AI Provider for Anthropic, or AI Provider for Google).
3. Go to **Settings > Connectors** and connect that provider with your API key.
4. Install and activate this plugin.
5. Go to **Settings > AI Site Search Chatbot**, choose the connected provider, and set a model ID.
6. Optionally use **Validate Connection and Model** and **Run Admin Chat Test** to confirm everything is working.
7. Enable **Enable chatbot display on the public site** and save.
8. Choose how the widget appears: automatically on all pages, automatically on the front page only, or manually via the `[ai_site_search_chatbot]` shortcode or the AI Site Search Chatbot block.

== Frequently Asked Questions ==

= Does this plugin store API keys? =

No. API keys are managed centrally by WordPress core under **Settings > Connectors**, using the official provider plugin for OpenAI, Anthropic, or Google. This plugin only sends requests through that existing connection.

= What happens if there are no good search matches on my site? =

The chatbot can still answer general "about this site" questions using your site's name, tagline, and a summary of your public content, and it will say so rather than presenting an unrelated answer.

= Can I limit how often the AI is used? =

Yes. The settings screen includes per-connection limits (per 10 minutes and per hour) and a site-wide daily cap on AI calls to help control usage and cost.

= What happens to my data when I uninstall the plugin? =

You choose. The **Uninstall Data Policy** setting lets you keep or remove the plugin's settings, chat logs, and saved knowledge base when the plugin is deleted.

= Where can I place the chat widget manually? =

Use the `[ai_site_search_chatbot]` shortcode, or add the AI Site Search Chatbot block in the block editor. Both support an optional `theme` attribute (`business` or `cute`), and the shortcode also supports `title` and `greeting` attributes.

== Screenshots ==

1. Public chat widget answering a visitor question with linked sources.
2. Admin settings screen for choosing the AI provider, model, and display options.
3. Saved Knowledge Base review screen with approval status and CSV export/import.

== Changelog ==

= 1.0.0 =
* Initial public release on WordPress.org.

= 0.7.0 =
* Added a new capability (`aiscb_manage_knowledge_base`) that lets non-administrator users create and edit Saved Knowledge Base entries without full admin access, managed from a checkbox list on the settings screen.
* Added a dedicated top-level admin menu, "Saved Knowledge Base," for users who hold the new capability but not `manage_options`.
* Extended the related REST endpoints (list, get, create, update) to accept the new capability; delete, export, and import remain administrator-only.

= 0.6.0 =
* Switched AI provider connections from plugin-managed API key storage to WordPress 7.0's Connectors API / AI Client (`Settings > Connectors`, `wp_ai_client_prompt()`). Supported providers are now OpenAI, Anthropic (Claude), and Google Gemini.
* Removed support for GitHub Models, which has no official Connectors provider. Sites previously using it are switched to OpenAI automatically, with an admin notice.
* Removed the plugin's own encrypted API key storage, environment variable/constant overrides, and the bundled Anthropic PHP SDK, simplifying the codebase.
* Raised the minimum WordPress version to 7.0.
* Updated translation files, translating remaining admin-screen strings.

= 0.5.7 =
* Security: visitor IP for rate limiting now defaults to `REMOTE_ADDR` only. Forwarded headers (e.g. `X-Forwarded-For`) are used only when a new "trust proxy headers" setting is explicitly enabled for sites behind a reverse proxy or CDN, preventing rate-limit bypass via header spoofing.
* Security: added a site-wide daily cap on AI calls (new setting, default 500) covering routing, search-query extraction, and answer generation, to guard against distributed or automated abuse driving up API costs.
* Fixed message-routing AI calls still firing after the daily cap was reached; the plugin now falls back to rule-based handling once the cap is hit.

= 0.5.6 =
* Chat history is now kept in localStorage for up to 3 hours, so a conversation is restored after navigating to a new page.

= 0.5.5 =
* Shortcode output is now resolved before content extraction, so content generated by shortcodes (e.g. contact form field labels) is available to the AI.
* Added a rule preventing the AI from including raw shortcode tags in its replies.
* Strengthened prompt-injection defenses: instructions found inside search results are ignored, and disclosure of admin, user, or configuration details is disallowed.

= 0.5.4 =
* Improved AI usage accounting to track thinking tokens separately and align output token totals with Google Gemini's billing display.
* Improved Gemini usage metadata collection on HTTP errors and answer extraction for thinking-model responses.
* Extended the AI provider request timeout to 60 seconds to avoid timeouts with thinking models.

= 0.5.3 =
* Added a "Clear Plugin Cache" admin action to manually clear cached AI responses and transient state.
* Cleaned up transient removal, including on multisite.
* Noted in the admin UI and documentation that displayed token counts are estimates.

= 0.5.2 =
* Added daily usage metrics (cards, chart, and table) to the admin logs tab.
* Added a data-deletion action to the logs screen.

= 0.5.1 =
* Sources attached to answers are now ordered by relevance to the answer content.
* Added per-request AI usage accounting, viewable in the admin logs.

= 0.5.0 =
* Added the Saved Knowledge Base tab: create, edit, delete, and manage approval status for generalized question/answer pairs.
* Added CSV export/import and search/status filters for the Saved Knowledge Base.
* Added an Uninstall Data Policy setting and `uninstall.php` to control data retention on removal.

= 0.4.1 =
* API keys and bearer tokens are encrypted at rest using a WordPress-salt-derived key.
* Secrets set via environment variables or constants now take precedence over values saved in the admin screen.

= 0.4.0 =
* Added an authentication method switch for the Claude provider (metered API key or bearer token).

= 0.3.0 =
* Switched the Claude provider to the official Anthropic PHP SDK, with system-prompt caching to reduce API costs.

= 0.2.0 =
* Added general site-guidance answers, an admin chat log viewer, AI reply rate limits, the Google Gemini provider, and Gutenberg block support.

== Upgrade Notice ==

= 1.0.0 =
Initial public release on WordPress.org.

= 0.7.0 =
Adds an optional capability for non-admin users to manage the Saved Knowledge Base. No action required for existing sites; all prior behavior for administrators is unchanged.

= 0.6.0 =
AI provider connections now go through WordPress 7.0's Connectors API instead of this plugin's own API key storage. After updating, re-connect your provider under Settings > Connectors and re-select it on this plugin's settings screen. Requires WordPress 7.0+.
