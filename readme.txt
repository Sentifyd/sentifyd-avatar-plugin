=== Sentifyd Avatar ===
Contributors: sentifyd
Tags: sentifyd, AI, avatars, agents, 3d avatar
Requires at least: 6.3
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Easily install Sentifyd.io 3D AI avatars on your WordPress site.

== Description ==

This plugin allows you to quickly and easily add a Sentifyd AI-powered 3D avatar to your WordPress website. Simply install the plugin, enter your avatar API key and Avatar ID on the settings page, and the Sentifyd avatar web component will be installed on your site.

The admin page allows you to configure your avatar API Key, Avatar ID, branding, and other important attributes for the avatar. However, you need to sign up to sentifyd.io to create your avatar and train it. 

Sentifyd.io empowers you to deploy intelligent, real-time 3D AI agents—fully animated avatars capable of natural, voice-driven conversations. These interactive 3D agents go beyond traditional chatbots by combining Retrieval-Augmented Generation (RAG), dynamic motion, and customizable tool integration.

With Sentifyd, your 3D AI agents can:

* Understand and respond to user input in real time using natural voice and language.

* Express themselves through lifelike gestures, movement, and tone for an immersive user experience.

* Access and reason over your own content—such as manuals, FAQs, or internal documents—while maintaining strict privacy and security protocols.

* Connect with powerful tools like MCP servers and more, enabling task execution and contextual awareness.

These agents can be easily embedded into your website or application, offering users a compelling, intelligent interface that feels truly alive.

**Get Started for Free**

* [Sign up and get up to 500 conversation minutes (Free Trial)](https://sentifyd.io)
* [Buy Conversation Minutes](https://sentifyd.io/pricing)

== Localization ==

This plugin admin panel is ready for translation and includes compiled translations for several languages. The avatar widget UI now supports multiple languages and the plugin will automatically set the widget's `ui-language` attribute based on your WordPress site language (first two letters). If the detected language is one of: English (en), French (fr), German (de), Spanish (es), Arabic (ar), or Chinese (zh), the widget will use that language. Otherwise it will fall back to English.

* Text Domain: `sentifyd-avatar`
* Domain Path: `/languages`
* Included admin panel locales: English (US), German, French, Spanish, Chinese (Simplified), Arabic
* Widget UI auto-language support: en, fr, de, es, ar, zh (falls back to en when unsupported)

=== Installation ===

The installation of the Sentifyd Avatar plugin is straightforward:

1.  If you don't have a Sentifyd avatar yet, sign up to sentifyd.io and create your avatar. You need the avatar's API key and ID which you can get from the avatar page in sentifyd.io platform. 
2.  Add the `sentifyd-avatar` plugin from the WordPress Plugins Directory.
3.  Activate the plugin through the 'Plugins' menu in WordPress, and go to the new 'Sentifyd Avatar' menu in your WordPress admin sidebar.
4.  Enter your "API Key" and your "Avatar ID" (both required for simple API Key deployment). Alternatively, provide your "Secure Token Endpoint" and your "Avatar ID" if you want more control of the API key's security. Check the documentation at docs.sentifyd.io.
5.  By default, the avatar will be installed on the bottom right corner like a site chatbot in all pages. If you want to embed the avatar in a specific location in you site, uncheck the "Enable Toggler" option, and use the short code [avatar_avatar] to add the avatar in your site. 
6.  Optionally, set your branding attributes such as Brand Name, Brand Logo URL, etc.
7.  You can also optionally change the installed avatar widget theme.
8.  Click "Save Settings". The avatar will now appear on your site.

== Frequently Asked Questions ==

= Where do I get an API Key? =

You can get an API Key by signing up for a trial at [sentifyd.io](https://sentifyd.io) and create your first avatar. The avatar API key is available from the avatar page in sentifyd.io: "Actions" menu > "API Key" 

= What is the difference between the avatar API Key and the Secure Token Endpoint? Do I still need an Avatar ID? =

With the **API Key** installation option, your key is stored server-side by this plugin and used only to mint short‑lived tokens via the plugin’s built‑in REST endpoint. This is the recommended, simple setup for most sites. You must also provide your **Avatar ID**.

Alternatively, you may provide your own **Secure Token Endpoint** if you want full control over token issuance on your infrastructure. In that case, the plugin will call your endpoint instead of the built‑in one. You must also provide your **Avatar ID**.

== Privacy ==

This plugin embeds the Sentifyd avatar web component and stores *session-scoped* data in the visitor’s browser (sessionStorage). Data is cleared when the tab/window closes.

Stored keys (examples; {avatar_id} is your avatar ID):
- showChatbot_{avatar_id}: "true"/"false" — remembers open/closed state (UI preference).
- authData_{id}: { token, refreshToken, region, avatarId } — short-lived tokens for this session only.
- conversationData_{avatarId}: { conversationId, turnId } — maintains session context.

No data is written to cookies or localStorage by the widget. Tokens are short-lived and not persisted across sessions.


== Changelog ==

= 1.0.0 =
* Initial release of the plugin.

== Screenshots ==

1. The admin panel main settings section for configuring the avatar
2. The admin panel additional avatar settings
3. The admin panel branding attributes
4. The admin panel plugin theme settings
5. Example installed avatar in a website


