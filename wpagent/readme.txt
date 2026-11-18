=== 37agents ===
Contributors: 37graus
Tags: ai, chatbot, gutenberg, rag, lms
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create AI agents for WordPress with chat, knowledge bases, Gutenberg blocks, and integrations with external AI providers.

== Description ==

37agents lets you create and deploy intelligent AI assistants directly in your WordPress site. Each agent can be trained with your own documents or public WordPress content (Auto-RAG), remember user context, and respond via a modern streaming chat, guided step-by-step diagnostic, or virtual learning environment (LMS) interface.

**Key features:**

* **Multi-provider AI & Coding Plan Subscriptions** — Works with WordPress AI Client / Connectors, OpenRouter, and OpenAI-compatible AI plans including Xiaomi MiMo Token Plan, Alibaba Cloud Qwen/Bailian Coding Plan, Zhipu GLM Coding Plan / Z.ai, Moonshot Kimi Code, MiniMax M Plan, Volcengine Ark, DeepSeek, SiliconFlow, and custom OpenCode/OpenAI endpoints.
* **Gutenberg Block & Shortcodes** — Insert AI agents anywhere using the visual `37agents/agent-widget` block (`Agent Widget (37agents)`), `[37agents_chat]`, or legacy `[wpagent_chat]` shortcodes with `layout` and `theme` options.
* **1-Click Starter Templates** — Launch configured agents in one click for Atendimento & Suporte, Diagnóstico & Pré-venda, Tutor de Curso (EAD), and Copiloto Editorial.
* **Auto-RAG for WordPress Content** — Automatically index published `post`, `page`, WooCommerce `product`, and public Custom Post Types on save or manual sync.
* **SSE response delivery** — Deliver completed AI responses in chunks via Server-Sent Events (`/wpagent/v1/chat/stream`) with JSON fallback. Provider generation is not streamed.
* **Privacy & LGPD/GDPR Tools** — Built-in WordPress Personal Data Exporters, Personal Data Eraser, Privacy Policy guide text, and optional per-agent consent banner.
* **Lead Capture & Webhooks** — Optional guest lead capture bar before or during interactions and non-blocking SSRF-safe outbound webhooks (`lead_captured`, `conversation_started`, `steps_report_generated`).
* **Frontend Layouts (Chat, STEPS & EAD)** — Support interactive conversations, guided diagnostic questionnaires with printable reports, and full LMS courses with an integrated AI Tutor.
* **Document RAG & Role Access** — Index TXT, Markdown, CSV, JSON, DOCX and PDF documents, control access per WordPress user role, and track token usage per user.
* **Editorial & Moderation Copilots** — Suggest contextual comment replies, bulk-summarize posts, schedule periodic content drafts, and chat with the Admin Assistant.
* **i18n ready** — Full support for Portuguese (Brazil), Spanish, and English.

== External Services ==

This plugin can communicate with external third-party services only when explicitly configured by the site administrator:

**1. OpenRouter (openrouter.ai)**
When configured, this plugin sends chat messages, conversation context, and relevant RAG content to OpenRouter to generate responses. If semantic embeddings are enabled, indexed document chunks and search queries are also sent to its embeddings API. Requests include the site URL and name.
- Service: https://openrouter.ai
- Privacy Policy: https://openrouter.ai/privacy
- Terms of Use: https://openrouter.ai/terms

**2. WordPress AI Client / Connectors**
When the official WordPress AI plugin (wordpress.org/plugins/ai/) is installed and active, this plugin uses the WordPress AI Client (`wp_ai_client_prompt()`) to route requests through configured connectors (OpenAI, Anthropic, Google, etc.).
- WordPress Privacy Policy: https://wordpress.org/about/privacy/

**3. Compatible AI Subscriptions & APIs (Optional — Xiaomi MiMo, Alibaba Qwen/Bailian, Zhipu GLM / Z.ai, Moonshot Kimi, MiniMax, Volcengine Ark, DeepSeek, SiliconFlow, or Custom OpenCode/OpenAI endpoint)**
When an administrator explicitly configures a Compatible Plan API key (`compatible_api_key`) and selects one of the built-in presets or enters a custom OpenAI-compatible endpoint URL, chat messages and RAG context are transmitted over HTTPS (`wp_safe_remote_post`) to the selected provider's `/chat/completions` endpoint.
- Xiaomi MiMo: https://platform.xiaomimimo.com/ — https://www.mi.com/global/about/privacy/
- Alibaba Cloud: https://www.alibabacloud.com/ — https://www.alibabacloud.com/help/en/legal/latest/alibaba-cloud-privacy-policy
- Zhipu / Z.ai: https://z.ai/ — https://z.ai/privacy
- Moonshot: https://platform.moonshot.ai/ — https://www.moonshot.ai/privacy
- MiniMax: https://platform.minimax.io/ — https://www.minimax.io/privacy-policy
- Volcengine: https://www.volcengine.com/ — https://www.volcengine.com/docs/6491/116977
- DeepSeek: https://platform.deepseek.com/ — https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html
- SiliconFlow: https://siliconflow.com/ — https://siliconflow.com/privacy-policy
- For a custom endpoint or WordPress AI connector, the administrator must review the selected provider's terms and privacy policy before use.

**4. Outbound Webhook Integration (Optional, per-agent)**
When an administrator explicitly enables Outbound Webhooks and enters a valid destination URL in an agent's settings, the plugin dispatches non-blocking HTTP POST JSON payloads (`wp_safe_remote_post`) when configured events occur (`lead_captured`, `conversation_started`, `steps_report_generated`).
- Data transmitted: Event name, timestamp, agent slug, session/conversation identifier, and—when lead capture is enabled—the visitor's submitted name and email address or generated Steps summary. No webhook data is sent unless enabled and configured by the administrator.

== Privacy Policy ==

37agents stores conversation transcripts, interaction logs, user memories, Steps/EAD progress, and optional visitor lead submissions locally in your WordPress database.

* **WordPress Privacy Tools:** Exporters cover registered users' conversations, interactions, memories, progress, projects, token usage and conversation summaries, plus email events and subscriptions matched by recipient email. The eraser removes these registered-user records and email records. Suggested policy text is available under *Settings > Privacy*.
* **Guest data limitation:** Guest leads are temporarily stored for seven days by session, not indexed by email. Guest conversations and progress cannot currently be reliably found or erased by email with the WordPress privacy tools. Administrators must provide an appropriate manual process and retention policy.
* **Consent Notice & Lead Capture:** Administrators can enable an optional per-agent privacy consent notice linking to the site's Privacy Policy page (`wp_get_privacy_policy_url()`) and an optional lead capture bar (name and email) for unauthenticated visitors.
* Administrators remain responsible for reviewing their Privacy Policy when connecting third-party AI providers or custom outbound webhook endpoints.
* Deleting the plugin removes its tables, settings and agent posts. Back up any data you need before uninstalling.

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
* Added provider integrations, Gutenberg block, privacy tools, Auto-RAG, SSE delivery, and lead capture.

Previous releases: https://github.com/lucasartel/37agents
