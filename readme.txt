=== AI Site Search Chatbot ===
Contributors: shokun0803
Tags: chatbot, ai, search, faq, gutenberg
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.0
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
* Follow-up questions keep the earlier conversation, so a reply such as "the August one" narrows down the previous question (the number of earlier messages sent is configurable)
* Optional search of selected custom fields, and search of text output by shortcodes and plugin dynamic blocks
* When nothing relevant is found, suggested re-search keywords are limited to terms that actually return pages on the site
* General site-guidance answers (using the site name, tagline, and a summary of public content) for questions that are not a direct content match
* Choice of AI provider and model per site, with connection validation and an in-admin chat test
* Saved Knowledge Base: review and approve generalized question/answer pairs so future matching questions can be answered without a new AI call; CSV export and import included
* Retry button on chat log entries that fell back to a non-AI answer (missing config, provider error, no search results, or a hit usage limit); a successful retry can auto-generate a Saved Knowledge Base draft, and a retry that still fails seeds an empty draft entry so the question is not lost
* Source post previews in the Saved Knowledge Base editor, automatic handling of knowledge entries whose source posts are no longer public, and review marks on date-dependent drafts
* Outdated versions of the same notice are dropped from search results so answers use the newest information
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

= Will a plugin update overwrite my custom system prompt? =

No. If you have never changed the system prompt, it follows the bundled default and picks up improvements automatically with each update. If you have customized it, your text is kept; when the bundled default changes, the settings screen shows the new default with buttons to switch to it or keep your own.

= Can the chatbot search custom fields? =

Yes, but only the custom field keys you list under **Custom Fields to Search** (none by default). Custom fields may contain non-public data, and anything searched can appear in answers to any visitor, so list only fields that hold public information such as product names or prices.

= Where can I place the chat widget manually? =

Use the `[ai_site_search_chatbot]` shortcode, or add the AI Site Search Chatbot block in the block editor. Both support an optional `theme` attribute (`business` or `cute`), and the shortcode also supports `title` and `greeting` attributes.

== Screenshots ==

1. Public chat widget answering a visitor question with linked sources.
2. Admin settings screen for choosing the AI provider, model, and display options.
3. Saved Knowledge Base review screen with approval status and CSV export/import.

== Changelog ==

= 1.3.0 =
* Follow-up questions now keep the conversation. The widget sends the most recent messages with each question, and the existing AI routing call decides whether the message continues the earlier question (for example answering "which month?") and rewrites it into a standalone question used for search, caching, saved knowledge, and logs. The admin log shows the visitor's original input as "Follow-up input". No extra AI calls are made.
* New "Conversation History" setting for the number of earlier messages sent as context: default 4 (the last two exchanges), allowed range 2-10, enforced on the server as well.
* When no relevant page is found, the AI now suggests only re-search keywords that are verified to return results on the site. Candidates come from post titles (including series names) and taxonomy terms, are labelled with their custom post type, are checked with a real search, and are cached for an hour. The no-result fallback answer lists a few of them too.
* New "Custom Fields to Search" setting: listed meta keys (with optional trailing * wildcard) are searched and their values, including serialized and JSON data, are passed to the AI. Empty by default; the settings screen lists the keys your posts use.
* Text output by shortcodes and by plugin dynamic blocks can now be found by search. Expanded text is cached per post (refreshed when the post changes or after 12 hours), at most 10 posts are expanded per search, and the newest 100 such posts are considered. Can be turned off with the new "Shortcode and Block Output" setting.
* Search result content now renders dynamic blocks before shortcodes.
* The default system prompt now follows plugin updates. It is stored only when customized; saved copies of earlier defaults (in any language) are switched to the current default automatically. Customized prompts are never changed, but a notice offers the new default when it changes, and the screen shows whether the prompt is default or customized with a reset button.
* Fixed the partial-match fallback search, which never returned results because its query values were passed in the wrong order.
* Search terms no longer keep Japanese quotation brackets.

= 1.2.0 =
* Saved Knowledge Base editor: each source post ID is now shown with its title (on hover), can be previewed in a popup showing its text content, and can be opened for viewing or editing in a new tab. Unpublished, password-protected, deleted, or unreadable posts are highlighted.
* Approved knowledge entries no longer reuse source posts that stopped being public (unpublished, trashed, deleted, or password protected). An entry whose sources are all gone is skipped immediately, then re-checked in the background: a fresh site search regenerates its answer and sources when possible, and the entry is returned to draft for review either way. A daily sweep catches changes made outside the normal editor.
* Outdated information is now dropped from site search results: when a newer result has the same title and opening text once dates and numbers are ignored (for example successive "business hours change" notices), only the newest is sent to the AI and listed as a source. Series posts and periodic reports with different content are kept. Publish dates and today's date are also passed to the AI so it prefers the newest information. This runs locally, without extra AI calls.
* Approved knowledge entries whose source post has been superseded by a newer post in the current search results are no longer reused, so a fresh answer is generated instead.
* Knowledge drafts that contain dates or temporary, time-limited information (announcements, events, campaigns, deadlines) are still saved, but are marked "Check dates" for review before approval. Lasting facts that merely include a date are saved normally.

= 1.1.0 =
* Added a "Retry" button to chat log entries whose original answer fell back to a non-AI response (missing config, provider error, no search results, or a hit usage limit), so a visitor question can be re-run through search and AI answer generation on demand.
* A successful retry reuses the existing auto-draft flow to save the new answer as a Saved Knowledge Base draft when enabled.
* A retry that still cannot get an AI answer now seeds an empty-answer draft entry from the question, so a question that failed twice is queued for a manual answer instead of being lost in the log.
* Fixed a bug in rule-based search query extraction where trimming a Japanese (or other multi-byte) question could corrupt its leading character, because `trim()`'s character-mask argument strips individual bytes rather than whole characters. This could silently degrade site search matching (and, since discovered, would have corrupted the question text saved into a knowledge draft) for affected questions.

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

= 1.3.0 =
Adds follow-up questions, verified re-search suggestions, custom field and shortcode search, and makes the default system prompt follow updates. Custom field search is off until you list field keys; existing customized system prompts are kept.

= 1.2.0 =
Improves how outdated information is handled in answers and saved knowledge. No settings changes are required.

= 1.0.0 =
Initial public release on WordPress.org.

= 0.7.0 =
Adds an optional capability for non-admin users to manage the Saved Knowledge Base. No action required for existing sites; all prior behavior for administrators is unchanged.

= 0.6.0 =
AI provider connections now go through WordPress 7.0's Connectors API instead of this plugin's own API key storage. After updating, re-connect your provider under Settings > Connectors and re-select it on this plugin's settings screen. Requires WordPress 7.0+.
