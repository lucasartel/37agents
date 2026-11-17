=== 37agents ===
Contributors: 37graus
Tags: ai, chatbot, gutenberg, rag, lms
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Personalized AI agents for WordPress (37agents). Integrates with WordPress AI Connectors, OpenRouter, Auto-RAG, Gutenberg, LMS, and LGPD/GDPR privacy tools.

== Description ==

37agents lets you create and deploy intelligent AI assistants directly in your WordPress site. Each agent can be trained with your own documents or public WordPress content (Auto-RAG), remember user context, and respond via a modern streaming chat, guided step-by-step diagnostic, or virtual learning environment (LMS) interface.

**Key features:**

* **Multi-provider AI & Coding Plan Subscriptions** — Works with WordPress AI Client / Connectors, OpenRouter, and OpenAI-compatible AI plans including Xiaomi MiMo Token Plan, Alibaba Cloud Qwen/Bailian Coding Plan, Zhipu GLM Coding Plan / Z.ai, Moonshot Kimi Code, MiniMax M Plan, Volcengine Ark, DeepSeek, SiliconFlow, and custom OpenCode/OpenAI endpoints.
* **Gutenberg Block & Shortcodes** — Insert AI agents anywhere using the visual `37agents/agent-widget` block (`Agent Widget (37agents)`), `[37agents_chat]`, or legacy `[wpagent_chat]` shortcodes with `layout` and `theme` options.
* **1-Click Starter Templates** — Launch configured agents in one click for Atendimento & Suporte, Diagnóstico & Pré-venda, Tutor de Curso (EAD), and Copiloto Editorial.
* **Auto-RAG for WordPress Content** — Automatically index published `post`, `page`, WooCommerce `product`, and public Custom Post Types on save or manual sync.
* **Progressive Streaming (SSE)** — Stream AI chat responses progressively via Server-Sent Events (`/wpagent/v1/chat/stream`) with transparent JSON fallback.
* **Privacy & LGPD/GDPR Tools** — Built-in WordPress Personal Data Exporters, Personal Data Eraser, Privacy Policy guide text, and optional per-agent consent banner.
* **Lead Capture & Webhooks** — Optional guest lead capture bar before or during interactions and non-blocking SSRF-safe outbound webhooks (`lead_captured`, `conversation_started`, `steps_report_generated`).
* **Frontend Layouts (Chat, STEPS & EAD)** — Support interactive conversations, guided diagnostic questionnaires with printable reports, and full LMS courses with an integrated AI Tutor.
* **Document RAG & Role Access** — Index TXT, Markdown, CSV, JSON, DOCX and PDF documents, control access per WordPress user role, and track token usage per user.
* **Editorial & Moderation Copilots** — Suggest contextual comment replies, bulk-summarize posts, schedule periodic content drafts, and chat with the Admin Assistant.
* **i18n ready** — Full support for Portuguese (Brazil), Spanish, and English.

== External Services ==

This plugin can communicate with external third-party services only when explicitly configured by the site administrator:

**1. OpenRouter (openrouter.ai)**
When an OpenRouter API key is configured, this plugin sends chat messages and RAG context to the OpenRouter API to generate AI responses (standard JSON or Server-Sent Events streaming).
- Service: https://openrouter.ai
- Privacy Policy: https://openrouter.ai/privacy
- Terms of Use: https://openrouter.ai/terms

**2. WordPress AI Client / Connectors**
When the official WordPress AI plugin (wordpress.org/plugins/ai/) is installed and active, this plugin uses the WordPress AI Client (`wp_ai_client_prompt()`) to route requests through configured connectors (OpenAI, Anthropic, Google, etc.).
- WordPress Privacy Policy: https://wordpress.org/about/privacy/

**3. Compatible AI Subscriptions & APIs (Optional — Xiaomi MiMo, Alibaba Qwen/Bailian, Zhipu GLM / Z.ai, Moonshot Kimi, MiniMax, Volcengine Ark, DeepSeek, SiliconFlow, or Custom OpenCode/OpenAI endpoint)**
When an administrator explicitly configures a Compatible Plan API key (`compatible_api_key`) and selects one of the built-in presets or enters a custom OpenAI-compatible endpoint URL, chat messages and RAG context are transmitted over HTTPS (`wp_safe_remote_post`) to the selected provider's `/chat/completions` endpoint.
- Supported provider consoles include `platform.xiaomimimo.com`, `dashscope.aliyuncs.com`, `open.bigmodel.cn`, `z.ai`, `kimi.ai`, `kimi.com`, `api.minimaxi.com`, `volces.com`, `deepseek.com`, `siliconflow.com`, or any custom OpenAI-compatible URL entered by the administrator.

**4. Outbound Webhook Integration (Optional, per-agent)**
When an administrator explicitly enables Outbound Webhooks and enters a valid destination URL in an agent's settings, the plugin dispatches non-blocking HTTP POST JSON payloads (`wp_safe_remote_post`) when configured events occur (`lead_captured`, `conversation_started`, `steps_report_generated`).
- Data transmitted: Event name, timestamp, agent slug, session/conversation identifier, and—when lead capture is enabled—the visitor's submitted name and email address or generated Steps summary. No webhook data is sent unless enabled and configured by the administrator.

== Privacy Policy ==

37agents stores conversation transcripts, interaction logs, user memories, Steps/EAD progress, and optional visitor lead submissions locally in your WordPress database.

* **WordPress Privacy Tools:** Under *Tools > Export Personal Data* and *Tools > Erase Personal Data*, 37agents registers Personal Data Exporters and a Personal Data Eraser covering conversations, interactions, memories, progress, projects, and token usage records associated with a user's email address, plus suggested Privacy Policy guide content (*Settings > Privacy*).
* **Consent Notice & Lead Capture:** Administrators can enable an optional per-agent privacy consent notice linking to the site's Privacy Policy page (`wp_get_privacy_policy_url()`) and an optional lead capture bar (name and email) for unauthenticated visitors.
* Administrators remain responsible for reviewing their Privacy Policy when connecting third-party AI providers or custom outbound webhook endpoints.

== Installation ==

1. Upload the `wpagent` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **37agents > Configurações** (Settings) to configure your AI provider:
   - **WordPress AI**: Install the official AI plugin and configure a Connector.
   - **OpenRouter**: Enter your API key for direct multi-model access.
4. Go to **37agents > Agentes** to create your first agent (or start quickly using a 1-click Starter Template).
5. Insert the **Agent Widget (37agents)** block in Gutenberg or use `[37agents_chat agent="your-agent-slug"]` (backward-compatible with `[wpagent_chat]`).

== Frequently Asked Questions ==

= Does this plugin work without an OpenRouter key? =

Yes. If you have the official WordPress AI plugin installed with a configured Connector, 37agents will use it automatically — no OpenRouter key required.

= What AI models and subscriptions are supported? =

Through OpenAI-compatible AI plans & Coding/Token subscriptions: Xiaomi MiMo Token Plan (`mimo-v2.6-pro`, `mimo-v2.6-flash`), Alibaba Cloud Qwen / Bailian Coding Plan (`qwen3.5-plus`, `qwen3-coder-plus`, `qwen3.8-max`), Zhipu AI GLM Coding Plan / Z.ai (`glm-5.3`, `glm-5.3-flash`, `glm-4.7`), Moonshot Kimi Code (`kimi-k2.7-code`, `kimi-k3`), MiniMax M Plan (`MiniMax-M3`), Volcengine Ark / Doubao, DeepSeek (`deepseek-chat`, `deepseek-reasoner`), SiliconFlow, and any custom OpenCode/OpenAI endpoint.
Through OpenRouter: hundreds of models including GPT-4, Claude, Llama, Mistral, Gemini, and more.
Through WordPress AI Connectors: any provider supported by the official WordPress AI plugin.

= Is the chat widget visible to all visitors? =

By default, agents are not publicly visible. You control visibility per-agent: "Public Site Assistant" for front-end float widget, the Gutenberg block **Agent Widget (37agents)**, or via shortcode `[37agents_chat]` / `[wpagent_chat]`. You can also restrict access by WordPress user role.

= What file types can I use for training? =

Admin document uploads support TXT, Markdown (.md), CSV, JSON, HTML, DOCX, and PDF (text-based). Auto-RAG also indexes published WordPress posts, pages, WooCommerce products, and public custom post types directly from your database. Public front-end uploads allow TXT, Markdown, CSV, JSON, PDF, and DOCX.

= Does this plugin store chat messages and comply with LGPD/GDPR? =

Conversations and optional guest leads are stored locally in your WordPress database. 37agents integrates with WordPress's native Privacy Tools (*Export Personal Data*, *Erase Personal Data*, and *Privacy Policy Guide*) and provides a per-agent privacy consent banner toggle linking directly to your site's Privacy Policy page.

== Screenshots ==

1. Dashboard with active AI provider badge
2. Agent configuration with 1-click Starter Templates, Auto-RAG & Privacy tabs
3. Streaming chat widget with Lead Capture & Privacy Notice
4. Guided diagnostic (STEPS) and LMS (EAD) layouts
5. Gutenberg Agent Widget block with live theme/layout preview

== Changelog ==

= 0.8.0 =
* **New — AI Coding / Token Plan Subscriptions & OpenAI-Compatible APIs:** Added native presets and encrypted credential storage for Xiaomi MiMo Token Plan (`tp-...`/`ttp-...` and standard `sk-...`), Alibaba Cloud Qwen / Bailian Coding Plan, Zhipu GLM Coding Plan / Z.ai, Moonshot Kimi Code, MiniMax M Plan, Volcengine Ark, DeepSeek, SiliconFlow, and custom OpenCode/OpenAI endpoints (with global settings and per-agent overrides).
* **Rebrand:** Renamed plugin to **37agents** across the Admin UI, Settings, Dashboard, and new `[37agents_chat]` shortcode alias (fully compatible with `[wpagent_chat]`).
* **WP.org Compliance:** Removed `Update URI` header, moved all inline `<script>` blocks into dedicated enqueued scripts (`assets/js/admin.js`), scoped admin styles to plugin screens, and loaded textdomains cleanly on `init` (WordPress 6.7+ compatibility). Set `Requires at least: 6.2` and `Tested up to: 6.8`.
* **Privacy (LGPD/GDPR):** Added WordPress Personal Data Exporters (conversations, interactions, memories, user progress), Personal Data Eraser, Privacy Policy suggested content (`WPAgent_Privacy`), and per-agent Privacy Consent notice bar.
* **New — Gutenberg Block:** Added `37agents/agent-widget` block (`Agent Widget (37agents)`) with agent, layout (`chat`/`steps`/`ead`), theme (`light`/`dark`), and title inspector controls.
* **New — Starter Templates:** Added 1-click presets in Agent creation/editing for Support, Diagnostic (`steps`), Course Tutor (`ead`), and Editorial Copilot.
* **New — Auto-RAG for WordPress Content:** Index published `post`, `page`, WooCommerce `product`, and public custom post types automatically on `save_post` or manual sync.
* **New — Progressive Streaming (SSE):** Added `/wpagent/v1/chat/stream` Server-Sent Events endpoint and client progressive Markdown rendering with automatic JSON fallback.
* **New — Lead Capture & Webhooks:** Added optional guest Lead Capture bar (`/wpagent/v1/leads`) and SSRF-safe non-blocking outbound webhooks (`lead_captured`, `conversation_started`, `steps_report_generated`).
* **Maintenance:** Updated `uninstall.php` to clean up all new metadata keys cleanly on uninstall.

= 0.7.1 =
* **Security:** Hardened Markdown link rendering against DOM XSS across Chat, Steps, and EAD layouts
* **Security:** Restricted global EAD lesson regeneration to administrators and editors
* **Security:** Isolated guest conversations and restricted project tabs to authenticated users
* **Security:** Removed HTML/HTM from public chat uploads and enforced real MIME/extension validation
* **Security:** Encrypted OpenRouter API key at rest (AES-256-CBC) and added `WPAGENT_OPENROUTER_API_KEY` constant support
* **Security:** Added strict sanitization and payload size limits on `progress` and `steps/generate-report` endpoints
* **Performance:** Eliminated per-request `SHOW TABLES` / `SHOW COLUMNS` queries via schema version guard and static cache
* **Performance:** Moved Periodic Content and Comment Moderation triggers to non-blocking background WP-Cron execution
* **UI/UX:** Organized Agent Settings metabox into 4 horizontal tabs (Geral & Layout, Prompt & Contexto, Modelo & Limites, Permissões & Recursos)
* **UI/UX:** Added full Dark Mode support, theme switcher, collapsible mobile modules drawer, and per-lesson Tutor IA history persistence in EAD layout
* **UI/UX:** Replaced native browser `prompt()`, `confirm()`, and `alert()` dialogs with accessible inline modals

= 0.7.0 =
* **New:** Layout Frontend STEPS — interactive step-by-step guided questionnaire with automated AI final report generation
* **New:** Layout Frontend EAD — full virtual learning environment (LMS) with module/lesson sidebar, progress tracking, and integrated AI Lesson Tutor
* **New:** User Progress Persistence — automatic state recovery across sessions for logged-in and guest users
* **New:** AI Syllabus Generator — 1-click automatic course outline generation based on agent training documents
* **New:** Agent layout configuration dropdown in Agent Settings (`chat`, `steps`, `ead`)
* **New:** Shortcode layout override support: `[wpagent_chat layout="steps|ead|chat"]`

= 0.6.1 =
* **New:** Moderação de Comentários com IA — automated spam classification, sentiment detection, and 1-click suggested replies
* **New:** Ability `wpagent/audit-comments` added to Admin Abilities system
* **Fix:** Periodic comment moderation background processing

= 0.6.0 =
* **New:** Front-end File Upload & RAG Scope Selection — upload documents in chat widget with conversation/project scope selection
* **Fix:** Project conversations list in sidebar — fixed payload and database query for project-bound conversations
* **New:** Dashboard badge showing active AI provider (WordPress AI or OpenRouter)
* **New:** Dismissible notice when no AI provider is configured
* **New:** Role-based access control — restrict which WordPress roles can use each agent
* **New:** Suggest Reply for Comments — generate AI reply suggestions for comment moderation
* **New:** Bulk Post Summarization — generate `post_excerpt` for multiple posts at once
* **New:** AI Content Traceability — track posts generated by Periodic Content (agent, timestamp, prompt)
* **New:** Configurable AI request timeout (filter: `wpagent_ai_request_timeout`)
* **Fix:** All admin notices are now dismissible (WP.org Rule 11 compliance)
* **Fix:** External services disclosure added to Settings page (WP.org Rule 7 compliance)
* **Improved:** Plugin version bumped to 0.6.0

= 0.5.9 =
* Internationalization: all strings wrapped with i18n functions (pt_BR, es_ES, en_US)
* Periodic content (DRIP) timestamp bug fixed
* Email scheduling module temporarily hidden pending stability improvements
* Various admin UI and security hardening improvements

== Upgrade Notice ==

= 0.8.0 =
Rebranded to 37agents with WP.org compliance updates, Gutenberg block, Starter Templates, Auto-RAG, SSE streaming, LGPD/GDPR tools, Lead Capture, and Webhooks. Fully backward-compatible with [wpagent_chat].

= 0.6.0 =
Major feature release. Update recommended. No database schema changes required.
