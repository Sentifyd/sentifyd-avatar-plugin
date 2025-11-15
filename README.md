# Sentifyd Avatar WordPress Plugin

![License](https://img.shields.io/badge/license-GPLv2%20or%20later-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-21759b.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)

Easily deploy intelligent, real-time 3D AI avatars on your WordPress site with the Sentifyd Avatar plugin.

## 🌟 Overview

The **Sentifyd Avatar** plugin allows you to quickly integrate AI-powered 3D avatars into your WordPress website. These interactive avatars combine natural voice-driven conversations with lifelike animations, providing an immersive user experience that goes beyond traditional chatbots.

### What are Sentifyd 3D AI Agents?

Sentifyd.io empowers you to deploy intelligent, real-time 3D AI agents—fully animated avatars capable of natural, voice-driven conversations. These interactive 3D agents combine:

- **Retrieval-Augmented Generation (RAG)**: Access and reason over your own content (manuals, FAQs, documents)
- **Dynamic Motion**: Lifelike gestures, movement, and tone for immersive experiences
- **Tool Integration**: Connect with MCP servers and other powerful tools for task execution
- **Real-Time Interaction**: Natural voice and language understanding with instant responses
- **Multi-Language Support**: UI supports English, French, German, Spanish, Arabic, and Chinese

## ✨ Features

- **Easy Installation**: Simple plugin setup with minimal configuration
- **Secure Token Management**: Built-in REST endpoint for secure token generation
- **Customizable Appearance**: Brand colors, logos, backgrounds, and border radius
- **Flexible Deployment**: Auto-inject as toggler or embed using shortcode
- **Localization Ready**: Supports 6+ languages for both admin panel and avatar UI
- **Privacy-Focused**: Session-scoped data only, cleared when browser tab closes
- **WordPress Standards**: Follows WordPress coding standards and best practices

## 📦 Installation

### From GitHub Releases (Recommended)

1. **Get Your Sentifyd Credentials**
   - Sign up at [sentifyd.io](https://sentifyd.io) and create your avatar
   - Note your **Avatar ID** and **API Key** from the avatar page

2. **Download the Plugin**
   - Go to the [Releases](https://github.com/Sentifyd/sentifyd-avatar-plugin/releases) page
   - Download the latest `sentifyd-avatar-plugin-X.X.X.zip` file

3. **Install in WordPress**
   - Log in to your WordPress admin panel
   - Navigate to **Plugins** → **Add New** → **Upload Plugin**
   - Click **Choose File** and select the downloaded ZIP file
   - Click **Install Now**
   - Click **Activate Plugin**

4. **Configure the Plugin**
   - Go to **Sentifyd Avatar** in your WordPress admin sidebar
   - Enter your **Avatar API Key** (required)
   - Enter your **Avatar ID** (required)
   - Optionally configure branding, theme colors, and other settings
   - Click **Save Settings**

5. **Verify Installation**
   - Visit your website's front-end
   - The avatar should appear in the bottom-right corner (default toggler mode)

### Manual Installation

If you want to install directly from source:

```bash
# Clone the repository
git clone https://github.com/Sentifyd/sentifyd-avatar-plugin.git

# Create a zip file (excluding dev files)
cd sentifyd-avatar-plugin
zip -r sentifyd-avatar-plugin.zip . \
  -x ".git*" \
  -x ".github/*" \
  -x "*.md" \
  -x ".vscode/*"
```

Then upload the ZIP file via **WordPress Admin** → **Plugins** → **Add New** → **Upload Plugin**.

## ⚙️ Configuration

### Required Settings

| Setting | Description |
|---------|-------------|
| **Avatar API Key** | Your avatar's API key from sentifyd.io (stored server-side only) |
| **Avatar ID** | Your avatar's unique identifier from sentifyd.io |

### Optional Settings

#### Deployment Mode

- **Enable Toggler** (default: ON): Auto-injects avatar as a minimizable button in bottom-right
- **Shortcode Mode**: Disable toggler and use `[sentifyd_avatar]` shortcode to place avatar anywhere

#### Avatar Attributes

- **Compact Mode**: Display avatar without header/footer
- **Enable Captions**: Show captions on the avatar by default

#### Branding

- **Brand Name**: Your organization's name (used in transcripts)
- **Brand Logo URL**: Logo displayed in avatar header
- **Terms Link**: URL to your Terms of Service
- **Privacy Link**: URL to your Privacy Policy

#### Theme Customization

- **Avatar Background**: CSS color value (hex, rgb, gradient)
- **Curved Corner Radius**: Border radius (e.g., `12px`, `1rem`, `50%`)
- **Primary Color**: Main theme color
- **Secondary Color**: Accent color
- **Text Colors**: Customize text colors for different backgrounds

### Advanced: Custom Token Endpoint

If you want full control over token issuance:

1. Implement your own secure token endpoint
2. Enter the endpoint URL in **Secure Token Endpoint**
3. The plugin will call your endpoint instead of the built-in one
4. Your endpoint must return tokens in the expected format (see [documentation](https://docs.sentifyd.io))

## 🚀 Usage

### As a Toggler (Default)

By default, the avatar appears as a minimizable button in the bottom-right corner on all pages.

### Using Shortcode

1. Disable **Enable Toggler** in settings
2. Add the shortcode where you want the avatar to appear:

```
[sentifyd_avatar]
```

You can place this shortcode in posts, pages, widgets, or theme templates.

## 🔒 Privacy & Security

- **API Key Security**: Stored server-side only, never exposed to browser
- **Token Management**: Short-lived tokens generated via secure REST endpoint
- **Session Storage**: UI state stored in browser's sessionStorage (cleared on tab close)
- **No Cookies**: Plugin does not use cookies or localStorage
- **GDPR Friendly**: Privacy policy content auto-generated for WordPress Privacy Policy page

### Data Stored in Browser Session

- `showChatbot_{avatar_id}`: UI open/closed state
- `authData_{id}`: Short-lived authentication tokens
- `conversationData_{avatarId}`: Session conversation context

All data is cleared when the browser tab/window closes.

## 🌍 Localization

The plugin supports multiple languages:

**Admin Panel**: English, German, French, Spanish, Chinese (Simplified), Arabic

**Avatar UI**: Automatically detects WordPress site language and sets appropriate UI language (en, fr, de, es, ar, zh)

## 📝 Requirements

- **WordPress**: 6.3 or higher
- **PHP**: 7.4 or higher
- **Sentifyd Account**: Free trial available at [sentifyd.io](https://sentifyd.io)

## 🆘 Support

- **Documentation**: [docs.sentifyd.io](https://docs.sentifyd.io)
- **Sign Up**: [sentifyd.io](https://sentifyd.io)
- **Pricing**: [sentifyd.io/pricing](https://sentifyd.io/pricing)
- **Issues**: [GitHub Issues](https://github.com/Sentifyd/sentifyd-avatar-plugin/issues)

## 📄 License

This plugin is licensed under the GNU General Public License v2 or later.

See [LICENSE](LICENSE) file for details.

## 🙏 Credits

Developed by [Sentifyd](https://sentifyd.io)

---

**Get Started for Free**: [Sign up and get up to 500 conversation minutes](https://sentifyd.io)
