# TeleWire

**Telegram notifications for ProcessWire.** A modern alternative to email — instant delivery, rich formatting, media attachments.

---

## Why TeleWire?

Email is slow, lands in spam, and nobody reads it. Telegram messages arrive in seconds, work on every device, and actually get noticed.

TeleWire connects your ProcessWire site to a Telegram bot in minutes — no external services, no subscriptions, no tracking. Just your bot, your chats, your data.

---

## What you can do

- Send instant notifications on form submissions, page saves, user registrations, failed logins, and any other ProcessWire event
- Format messages with bold, italic, links, and code blocks
- Attach photos and documents — invoices, reports, screenshots
- Notify multiple people or groups simultaneously
- Run silently in the background without notification sounds
- Send very long messages — they split automatically

---

## Quick start

```php
$telewire = $modules->get('TeleWire');
$telewire->send('<b>New order received</b>' . "\n" . 'Total: $99.99');
```

That's it. One line to send a formatted Telegram message from anywhere in ProcessWire.

---

## Setup in 3 steps

1. Create a bot via [@BotFather](https://t.me/BotFather) and copy the token
2. Get your Chat ID via [@userinfobot](https://t.me/userinfobot)
3. Install TeleWire, paste both values, click **Send Test Message**

---

## Installation

Copy the `TeleWire` folder to `/site/modules/`, then go to **Modules → Refresh → Install**.

---

## Requirements

- ProcessWire 3.0.210+
- PHP 8.2+
- cURL enabled

---

## Documentation

Full API reference, configuration options, hook examples, formatting guide, and troubleshooting:

**[→ DOCUMENTATION.md](DOCUMENTATION.md)**

---

## Author

**Maxim Alex** — [smnv.org](https://smnv.org) · [GitHub](https://github.com/mxmsmnv) · maxim@smnv.org

## License

MIT — see [LICENSE](LICENSE)