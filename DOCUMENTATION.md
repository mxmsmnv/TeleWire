# TeleWire — Technical Documentation

> Full reference for developers and AI agents working with the TeleWire ProcessWire module.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [File Structure](#file-structure)
3. [Configuration Reference](#configuration-reference)
4. [Public API Reference](#public-api-reference)
5. [TelegramAPI Class Reference](#telegramapi-class-reference)
6. [Internal Methods](#internal-methods)
7. [Logging System](#logging-system)
8. [Message Formatting](#message-formatting)
9. [Usage Examples](#usage-examples)
10. [Hook Examples](#hook-examples)
11. [Error Handling](#error-handling)
12. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

TeleWire consists of two classes:

```
TeleWire (ProcessWire Module)          TelegramAPI (HTTP wrapper)
┌─────────────────────────┐            ┌──────────────────────────┐
│  - Module config UI     │  uses ───► │  - sendMessage()         │
│  - send()               │            │  - sendPhoto()           │
│  - sendPhoto()          │            │  - sendDocument()        │
│  - sendDocument()       │            │  - getMe()               │
│  - testConnection()     │            │  - getUpdates()          │
│  - getUpdates()         │            │  - setWebhook()          │
│  - Logging              │            │  - request() (cURL)      │
└─────────────────────────┘            └──────────────────────────┘
```

**TeleWire** is the ProcessWire `ConfigurableModule` that manages configuration, chat ID routing, message splitting, and logging. It delegates all HTTP communication to **TelegramAPI**, which is a standalone wrapper around the Telegram Bot API.

The module is `singular` (only one instance exists) and `autoload` (loaded on every request). The `TelegramAPI` instance is created during `init()` if a bot token is present.

---

## File Structure

```
TeleWire/
├── TeleWire.module.php      # Main ProcessWire module class
├── TelegramAPI.php          # Telegram Bot API HTTP wrapper
├── README.md                # Marketing/overview page
├── DOCUMENTATION.md         # This file
└── LICENSE                  # MIT License
```

---

## Configuration Reference

Configuration is stored in ProcessWire's module config system and accessible via `$this->propertyName` inside the module or `$modules->get('TeleWire')->propertyName` externally.

| Property | Type | Default | Description |
|---|---|---|---|
| `botToken` | `string` | `''` | Telegram bot token from @BotFather. Required. |
| `chatIds` | `string` | `''` | Raw string of chat IDs, comma or newline separated. |
| `parseMode` | `string` | `'HTML'` | Message parse mode: `''`, `'HTML'`, `'Markdown'`, `'MarkdownV2'` |
| `maxMessageLength` | `int` | `4096` | Max chars per message chunk. Telegram hard limit is 4096. |
| `disableLinkPreviews` | `bool` | `true` | Passes `disable_web_page_preview` to Telegram API. |
| `enableSilentMode` | `bool` | `false` | Passes `disable_notification` to Telegram API. |
| `enableLogging` | `bool` | `true` | Logs sent messages to `telewire` log. |
| `enableDebugLogging` | `bool` | `false` | Logs step-by-step debug info to `telewire-debug` log. |
| `timeout` | `int` | `5` | cURL timeout in seconds for API requests. |

### Chat ID Formats

```
# User chat ID (positive integer)
123456789

# Group chat ID (negative integer)
-1001234567890

# Multiple IDs — comma separated
123456789, -1001234567890

# Multiple IDs — newline separated
123456789
-1001234567890
```

---

## Public API Reference

### `send(string $message, array $options = []): bool`

Sends a text message to **all configured chat IDs**. Long messages are automatically split into chunks.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$message` | `string` | Message text. HTML/Markdown allowed depending on `parseMode`. |
| `$options` | `array` | Optional Telegram API parameters to override module defaults. |

**Supported `$options` keys:**

| Key | Type | Description |
|---|---|---|
| `parse_mode` | `string` | Override `parseMode` for this message only. |
| `disable_web_page_preview` | `bool` | Override link preview setting. |
| `disable_notification` | `bool` | Override silent mode. |
| `reply_to_message_id` | `int` | Reply to a specific message. |
| `protect_content` | `bool` | Prevent forwarding/saving of message. |

**Returns:** `true` if all messages to all chats succeeded, `false` if any failed.

**Note:** Returns `true` even if only some chats succeeded. Check error logs for per-chat failures.

```php
$telewire = $modules->get('TeleWire');

// Basic
$telewire->send('Hello World');

// With override options
$telewire->send('<b>Alert!</b>', [
    'disable_notification' => false,  // force sound even in silent mode
    'parse_mode' => 'HTML'
]);
```

---

### `sendPhoto(string $photo, string $caption = '', array $options = []): bool`

Sends a photo to all configured chat IDs.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$photo` | `string` | Absolute file path or public URL. |
| `$caption` | `string` | Optional caption text (max 1024 chars). |
| `$options` | `array` | Additional Telegram API parameters. |

```php
// From local file
$telewire->sendPhoto('/var/www/site/assets/files/123/photo.jpg', 'Product image');

// From URL
$telewire->sendPhoto('https://example.com/image.png', '<b>New arrival</b>');

// Without caption
$telewire->sendPhoto('/path/to/screenshot.png');
```

**Note:** Local file paths are passed directly to `TelegramAPI::sendPhoto()`, which sends them as JSON body. If your server blocks outgoing file uploads, use URLs instead.

---

### `sendDocument(string $document, string $caption = '', array $options = []): bool`

Sends a document (any file type) to all configured chat IDs.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$document` | `string` | Absolute file path or public URL. |
| `$caption` | `string` | Optional caption text (max 1024 chars). |
| `$options` | `array` | Additional Telegram API parameters. |

```php
// Send a PDF report
$telewire->sendDocument('/var/www/site/assets/files/456/report.pdf', 'Monthly Report');

// Send a spreadsheet
$telewire->sendDocument('/tmp/export.xlsx', 'Data Export ' . date('Y-m-d'));
```

---

### `testConnection(): array`

Tests the bot token by calling the Telegram `getMe` endpoint. Used internally by the admin UI, but also callable from code.

**Returns:** Associative array:

```php
// On success:
[
    'success' => true,
    'message' => 'Connected to bot: MyBot (@my_bot)',
    'data'    => [ /* Telegram bot object */ ]
]

// On failure:
[
    'success' => false,
    'message' => 'Failed to connect: API Error (401): Unauthorized'
]
```

```php
$result = $modules->get('TeleWire')->testConnection();
if (!$result['success']) {
    throw new RuntimeException('TeleWire not configured: ' . $result['message']);
}
```

---

### `getUpdates(int $offset = 0, int $limit = 10): array|false`

Retrieves pending updates from the Telegram Bot API. Useful for finding Chat IDs during initial setup.

```php
$updates = $modules->get('TeleWire')->getUpdates();
foreach ($updates as $update) {
    $chatId = $update['message']['chat']['id'] ?? null;
    $text   = $update['message']['text'] ?? '';
    echo "Chat ID: {$chatId}, Text: {$text}\n";
}
```

---

## TelegramAPI Class Reference

`TelegramAPI` extends `WireData` and can be used independently of the ProcessWire module if needed.

### Constructor

```php
$api = new TelegramAPI($token, [
    'timeout'   => 10,
    'parseMode' => 'HTML'
]);
```

### Methods

#### `sendMessage(string|int $chatId, string $text, array $options = []): array|false`

Sends a message to a single chat. Returns Telegram's `result` object on success, `false` on failure.

```php
$result = $api->sendMessage(123456789, 'Hello!', [
    'parse_mode'              => 'HTML',
    'disable_web_page_preview'=> true
]);

if ($result === false) {
    echo $api->getLastError();
}
```

#### `sendPhoto(string|int $chatId, string $photo, string $caption = '', array $options = []): array|false`

#### `sendDocument(string|int $chatId, string $document, string $caption = '', array $options = []): array|false`

#### `getMe(): array|false`

Returns bot info from Telegram (`id`, `first_name`, `username`, `is_bot`, etc.).

#### `getUpdates(int $offset = 0, int $limit = 100, int $timeout = 0): array|false`

#### `setWebhook(string $url, array $options = []): array|false`

#### `deleteWebhook(): array|false`

#### `getLastError(): string`

Returns the last error string. Set after any failed `request()` call.

#### `getLastResponse(): array|null`

Returns the raw decoded JSON response from the last API call.

---

## Internal Methods

These are `protected` methods on `TeleWire` — not part of the public API but useful to understand behavior.

### `parseChatIds(string $chatIds): array`

Parses the raw `chatIds` config string into a clean array of validated IDs.

- Splits on whitespace, commas, or newlines
- Validates format: optionally negative, digits only
- Deduplicates
- Returns `[]` on empty/invalid input

```php
// Input: "123456, -100987\n123456"
// Output: ['123456', '-100987']   (deduped)
```

### `splitMessage(string $message): array`

Splits a long message into parts ≤ `maxMessageLength` characters.

- Uses `mb_strlen` / `mb_substr` for multibyte safety
- Prefers splitting at newline boundaries when the last newline is in the second half of the chunk
- Falls back to hard split at character limit

### `handleTestRequest()`

Called during `ready()` when `$input->post('telewire_test')` is set (AJAX from admin UI). Superuser-only. Returns JSON response and calls `exit`.

---

## Logging System

TeleWire writes to three ProcessWire log files in `/site/assets/logs/`:

| File | Trigger | Contents |
|---|---|---|
| `telewire.txt` | `enableLogging = true` | Successful send summary: count, duration |
| `telewire-errors.txt` | Always | Per-chat send failures, configuration errors |
| `telewire-debug.txt` | `enableDebugLogging = true` | Step-by-step trace of every operation |

**Reading logs in code:**

```php
// Via ProcessWire log API
$logs = $this->wire('log')->getEntries('telewire-errors', ['limit' => 20]);
foreach ($logs as $entry) {
    echo $entry['text'] . "\n";
}
```

**Log format (debug):**
```
[2026-01-23 14:22:01] Sending message to 2 chat(s)
[2026-01-23 14:22:01] Sending to chat ID: 123456789
[2026-01-23 14:22:01] ✓ Sent to chat ID: 123456789
[2026-01-23 14:22:01] Total execution time: 312.5ms
```

> ⚠️ Debug logging creates large files quickly. Enable only during active troubleshooting.

---

## Message Formatting

### HTML Mode (default)

Telegram supports a limited HTML subset. Unsupported tags are stripped silently.

```php
$message  = "<b>Bold text</b>\n";
$message .= "<i>Italic text</i>\n";
$message .= "<u>Underlined</u>\n";
$message .= "<s>Strikethrough</s>\n";
$message .= "<code>inline code</code>\n";
$message .= "<pre>code block\nmulti-line</pre>\n";
$message .= "<a href='https://processwire.com'>Link text</a>\n";
$message .= "<b>Nested <i>bold italic</i></b>\n";

$telewire->send($message);
```

**Special characters** in HTML mode that must be escaped if used literally:
`<` → `&lt;`   `>` → `&gt;`   `&` → `&amp;`

### Markdown Mode

```php
$telewire->send('*Bold* _italic_ `code`', ['parse_mode' => 'Markdown']);
```

### MarkdownV2 Mode

Stricter than Markdown. Most punctuation must be escaped with `\`.

```php
// Characters requiring escape: _ * [ ] ( ) ~ ` > # + - = | { } . !
$telewire->send('Hello\! This is *bold* and \_italic\_', ['parse_mode' => 'MarkdownV2']);
```

### No Formatting

```php
$telewire->send('Plain text <with> special &chars; unescaped', ['parse_mode' => '']);
```

---

## Usage Examples

### Minimal Send

```php
$telewire = $modules->get('TeleWire');
$telewire->send('Deployment complete ✅');
```

---

### Structured Notification

```php
$telewire = $modules->get('TeleWire');

$message  = "🛒 <b>New Order #" . $order->id . "</b>\n\n";
$message .= "👤 Customer: " . $order->customer_name . "\n";
$message .= "📧 Email: " . $order->email . "\n";
$message .= "💰 Total: $" . number_format($order->total, 2) . "\n";
$message .= "📦 Items: " . $order->items->count . "\n";
$message .= "🕐 Time: " . date('Y-m-d H:i:s') . "\n\n";
$message .= "<a href='" . $order->adminUrl . "'>View in admin →</a>";

$telewire->send($message);
```

---

### Silent Background Notification

```php
$telewire->send('Cron job completed: ' . $result, [
    'disable_notification' => true
]);
```

---

### Send with Link Preview Enabled

```php
// Module default disables previews; override per-message:
$telewire->send('Check out https://example.com/new-post', [
    'disable_web_page_preview' => false
]);
```

---

### Send a Report File

```php
$pdfPath = wire('config')->paths->assets . 'reports/monthly-' . date('Y-m') . '.pdf';

if (file_exists($pdfPath)) {
    $telewire->sendDocument($pdfPath, '📊 Monthly Report ' . date('F Y'));
} else {
    $telewire->send('⚠️ Report file not found: ' . $pdfPath);
}
```

---

### Send a Screenshot or Product Image

```php
$page    = $pages->get('/products/widget-pro/');
$imgPath = $page->images->first()->filename;

$telewire->sendPhoto($imgPath, "🖼 Updated: <b>" . $page->title . "</b>");
```

---

### Check Return Value and Log

```php
$ok = $telewire->send('Important alert!');

if (!$ok) {
    wire('log')->save('my-app', 'TeleWire failed to send alert');
    // Fallback: send email, etc.
}
```

---

### Using TelegramAPI Directly (without module config)

```php
require_once wire('config')->paths->siteModules . 'TeleWire/TelegramAPI.php';

$api = new \ProcessWire\TelegramAPI('YOUR_BOT_TOKEN', ['timeout' => 8]);

$result = $api->sendMessage('-1001234567890', 'Direct API call');

if ($result === false) {
    echo "Error: " . $api->getLastError();
} else {
    echo "Sent! Message ID: " . $result['message_id'];
}
```

---

## Hook Examples

### New Order via FormBuilder

```php
// /site/ready.php
wire()->addHookAfter('FormBuilderProcessor::processInputDone', function($event) {
    $form = $event->object;
    if ($form->name !== 'checkout') return;

    $telewire = $this->modules->get('TeleWire');

    $msg  = "🛒 <b>New Order</b>\n\n";
    $msg .= "Customer: " . $form->get('name')->value . "\n";
    $msg .= "Email: "    . $form->get('email')->value . "\n";
    $msg .= "Total: $"   . $form->get('total')->value;

    $telewire->send($msg);
});
```

---

### Page Save Notification

```php
wire()->addHookAfter('Pages::saved', function($event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'product') return;

    $telewire = $this->modules->get('TeleWire');

    $label = $page->isNew() ? '🆕 New Product' : '✏️ Product Updated';

    $msg  = "<b>{$label}</b>\n\n";
    $msg .= "Title: "   . $page->title . "\n";
    $msg .= "Price: $"  . $page->price . "\n";
    $msg .= "By: "      . $this->user->name . "\n";
    $msg .= "URL: "     . $page->httpUrl;

    $telewire->send($msg);
});
```

---

### Failed Login Alert

```php
wire()->addHookAfter('Session::loginFailed', function($event) {
    $username = $event->arguments(0);
    $telewire = $this->modules->get('TeleWire');

    $msg  = "⚠️ <b>Failed Login Attempt</b>\n\n";
    $msg .= "Username: " . $username . "\n";
    $msg .= "IP: "       . $this->session->getIP() . "\n";
    $msg .= "Time: "     . date('Y-m-d H:i:s');

    $telewire->send($msg, ['disable_notification' => false]); // force sound
});
```

---

### New User Registration

```php
wire()->addHookAfter('Pages::added', function($event) {
    $page = $event->arguments(0);
    if ($page->template->name !== 'user') return;

    $telewire = $this->modules->get('TeleWire');

    $msg  = "👤 <b>New User Registered</b>\n\n";
    $msg .= "Username: " . $page->name . "\n";
    $msg .= "Email: "    . $page->email . "\n";
    $msg .= "Roles: "    . implode(', ', $page->roles->explode('name')) . "\n";
    $msg .= "Date: "     . date('Y-m-d H:i:s');

    $telewire->send($msg);
});
```

---

### File Upload Notification

```php
wire()->addHookAfter('InputfieldFile::fileAdded', function($event) {
    $file    = $event->arguments(0);
    $telewire = $this->modules->get('TeleWire');

    $msg  = "📎 <b>File Uploaded</b>\n\n";
    $msg .= "File: "  . $file->basename . "\n";
    $msg .= "Size: "  . wireBytesStr($file->filesize()) . "\n";
    $msg .= "User: "  . $this->user->name;

    $telewire->send($msg);
});
```

---

### Scheduled Report via LazyCron

```php
wire()->addHook('LazyCron::everyDay', function($event) {
    $telewire = $this->modules->get('TeleWire');

    $pageCount = $this->pages->count('template=product, modified>=' . (time() - 86400));

    $msg  = "📊 <b>Daily Summary</b>\n\n";
    $msg .= "Products modified today: {$pageCount}\n";
    $msg .= "Generated: " . date('Y-m-d H:i:s');

    $telewire->send($msg, ['disable_notification' => true]);
});
```

---

## Error Handling

### Return Values

All three public send methods return `bool`. A `false` return means at least one delivery failed.

```php
$ok = $telewire->send($message);

if (!$ok) {
    // Check telewire-errors.txt for per-chat details
    // Or access the last Telegram API error directly:
    // (requires accessing protected $telegram — not recommended)
}
```

### Common Error Codes from Telegram API

| HTTP Code / API Error | Meaning | Fix |
|---|---|---|
| `401 Unauthorized` | Invalid bot token | Check token in module settings |
| `403 Forbidden` | Bot blocked by user | User must unblock the bot |
| `400 chat not found` | Invalid chat ID | Verify chat ID; bot must be a member |
| `429 Too Many Requests` | Rate limit hit | Reduce send frequency; module adds 30ms delay between sends |
| cURL timeout | Server can't reach Telegram | Increase timeout; check firewall/DNS |

### Rate Limiting

TeleWire adds a **30ms delay** (`usleep(30000)`) between each individual API call (per chat × per message chunk). This is intentional to avoid Telegram's rate limits.

Telegram's documented limit is 30 messages/second globally and 1 message/second per chat. For high-volume applications, implement your own queue.

---

## Troubleshooting

### Step 1 — Verify bot token

Go to **Modules → Configure → TeleWire**. The "Connection Status" panel calls `getMe()` on load and shows the bot name if the token is valid.

### Step 2 — Verify chat ID

Send `/start` to your bot in Telegram, then call:

```php
$updates = $modules->get('TeleWire')->getUpdates();
// Inspect $updates[0]['message']['chat']['id']
```

Or use [@userinfobot](https://t.me/userinfobot).

### Step 3 — Enable debug logging

In module settings, check **Enable Debug Logging**, then trigger a send and read `/site/assets/logs/telewire-debug.txt`.

### Step 4 — Check error log

`/site/assets/logs/telewire-errors.txt` always records failures regardless of the `enableLogging` setting.

### Step 5 — Test cURL manually

```bash
curl -s "https://api.telegram.org/bot<TOKEN>/getMe"
```

If this fails from your server, the issue is network-level (firewall, DNS, SSL).

### Groups and Supergroups

- Bot must be **added as a member** (not just invited)
- For supergroups the chat ID is a large negative number (e.g. `-1001234567890`)
- Use `getUpdates()` after adding the bot to capture the correct ID
