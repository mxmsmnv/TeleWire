# TeleWire - Telegram Notifications for ProcessWire

![ProcessWire](https://img.shields.io/badge/ProcessWire-3.0.210+-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-8.2+-purple.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

Send notifications via Telegram Bot API directly from your ProcessWire CMS. A modern alternative to email notifications with instant delivery, rich formatting, and support for media attachments.

## ✨ Features

- 🚀 **Instant Notifications** - Send messages directly to Telegram users and groups
- 📝 **Rich Formatting** - Support for HTML, Markdown, and MarkdownV2
- 📸 **Media Support** - Send photos and documents with captions
- 👥 **Multiple Recipients** - Send to multiple chat IDs simultaneously
- 🔧 **Easy Configuration** - Simple admin interface with test button
- 📊 **Logging** - Optional logging with debug mode for troubleshooting
- ⚡ **Performance** - Optimized with configurable timeouts and rate limiting
- 🔒 **Secure** - Uses official Telegram Bot API with SSL verification

## 📋 Requirements

- ProcessWire 3.0.210 or later
- PHP 8.2 or later
- cURL extension enabled
- Active Telegram Bot (get token from [@BotFather](https://t.me/BotFather))

## 📦 Installation

### Method 1: Manual Installation

1. Download or clone this repository
2. Copy the `TeleWire` folder to `/site/modules/`
3. Go to **Modules** → **Refresh**
4. Click **Install** next to TeleWire

### Method 2: Composer (coming soon)
```bash
composer require maxalexim/telewire
```

## ⚙️ Configuration

### 1. Create a Telegram Bot

1. Open Telegram and find [@BotFather](https://t.me/BotFather)
2. Send `/newbot` command
3. Follow instructions to create your bot
4. Copy the bot token (example: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

### 2. Get Your Chat ID

1. Find [@userinfobot](https://t.me/userinfobot) in Telegram
2. Send any message to get your Chat ID
3. **Important:** Start a conversation with your bot by sending `/start`

### 3. Configure the Module

1. Go to **Modules** → **Configure** → **TeleWire**
2. Enter your **Bot Token**
3. Enter your **Chat ID(s)** (one per line or comma-separated)
4. Click **Send Test Message** to verify configuration
5. Configure additional options as needed

## 🚀 Usage

### Basic Usage
```php
// Get the module
$telewire = $modules->get('TeleWire');

// Send a simple message
$telewire->send('Hello from ProcessWire!');

// Send message with HTML formatting
$message = '<b>New Order Received</b>' . "\n";
$message .= 'Order #: 12345' . "\n";
$message .= 'Total: $99.99' . "\n";
$message .= '✅ Payment confirmed';

$telewire->send($message);
```

### Send Photos
```php
$telewire = $modules->get('TeleWire');

// From local file
$telewire->sendPhoto('/path/to/photo.jpg', 'Photo caption');

// From URL
$telewire->sendPhoto('https://example.com/photo.jpg', 'External photo');
```

### Send Documents
```php
$telewire = $modules->get('TeleWire');

// Send PDF invoice
$telewire->sendDocument('/path/to/invoice.pdf', 'Invoice #123');

// Send any document type
$telewire->sendDocument('/path/to/report.xlsx', 'Monthly Report');
```

### Custom Options
```php
$telewire = $modules->get('TeleWire');

// Silent notification (no sound)
$telewire->send('Quiet message', [
    'disable_notification' => true
]);

// Different parse mode
$telewire->send('**Bold** _italic_', [
    'parse_mode' => 'Markdown'
]);

// Enable link preview
$telewire->send('Check https://processwire.com', [
    'disable_web_page_preview' => false
]);
```

## 📚 Real-World Examples

### E-commerce Order Notifications
```php
// Hook into FormBuilder form submission
$wire->addHookAfter('FormBuilderProcessor::processInputDone', function($event) {
    $form = $event->object;
    
    if($form->name === 'order-form') {
        $telewire = $this->modules->get('TeleWire');
        
        $message = '🛒 <b>New Order</b>' . "\n\n";
        $message .= '👤 Customer: ' . $form->get('customer_name')->value . "\n";
        $message .= '📧 Email: ' . $form->get('email')->value . "\n";
        $message .= '💰 Total: $' . $form->get('total')->value . "\n";
        $message .= '📅 Date: ' . date('Y-m-d H:i:s');
        
        $telewire->send($message);
    }
});
```

### Page Save Notifications
```php
// Notify when important pages are modified
$wire->addHookAfter('Pages::saved', function($event) {
    $page = $event->arguments(0);
    
    if($page->template == 'product' && !$page->isNew()) {
        $telewire = $this->modules->get('TeleWire');
        
        $message = '📝 <b>Product Updated</b>' . "\n\n";
        $message .= 'Title: ' . $page->title . "\n";
        $message .= 'Price: $' . $page->price . "\n";
        $message .= 'Updated by: ' . $this->user->name . "\n";
        $message .= 'URL: ' . $page->httpUrl;
        
        $telewire->send($message);
    }
});
```

### Contact Form Notifications
```php
// Template file: contact.php
if($input->post->submit) {
    $name = $sanitizer->text($input->post->name);
    $email = $sanitizer->email($input->post->email);
    $message = $sanitizer->textarea($input->post->message);
    
    // Send to Telegram
    $telewire = $modules->get('TeleWire');
    
    $notification = '📨 <b>New Contact Form Submission</b>' . "\n\n";
    $notification .= '👤 Name: ' . $name . "\n";
    $notification .= '📧 Email: ' . $email . "\n";
    $notification .= '💬 Message:' . "\n" . $message;
    
    $telewire->send($notification);
}
```

### System Monitoring
```php
// Notify on critical errors
$wire->addHookAfter('Session::loginFailed', function($event) {
    $telewire = $this->modules->get('TeleWire');
    
    $name = $event->arguments(0);
    $message = '⚠️ <b>Failed Login Attempt</b>' . "\n\n";
    $message .= 'Username: ' . $name . "\n";
    $message .= 'IP: ' . $this->session->getIP() . "\n";
    $message .= 'Time: ' . date('Y-m-d H:i:s');
    
    $telewire->send($message);
});
```

### User Registration Notifications
```php
$wire->addHookAfter('Pages::added', function($event) {
    $page = $event->arguments(0);
    
    if($page->template == 'user') {
        $telewire = $this->modules->get('TeleWire');
        
        $message = '👤 <b>New User Registered</b>' . "\n\n";
        $message .= 'Username: ' . $page->name . "\n";
        $message .= 'Email: ' . $page->email . "\n";
        $message .= 'Role: ' . implode(', ', $page->roles->explode('name')) . "\n";
        $message .= 'Date: ' . date('Y-m-d H:i:s');
        
        $telewire->send($message);
    }
});
```

### File Upload Notifications
```php
// When files are uploaded
$wire->addHookAfter('InputfieldFile::fileAdded', function($event) {
    $file = $event->arguments(0);
    
    $telewire = $this->modules->get('TeleWire');
    
    $message = '📁 <b>File Uploaded</b>' . "\n\n";
    $message .= 'File: ' . $file->basename . "\n";
    $message .= 'Size: ' . wireBytesStr($file->filesize()) . "\n";
    $message .= 'User: ' . $this->user->name;
    
    $telewire->send($message);
});
```

## 🎨 HTML Formatting Guide

TeleWire supports HTML formatting (default mode):
```php
// Bold text
$message = '<b>Bold text</b>';

// Italic text
$message = '<i>Italic text</i>';

// Underline
$message = '<u>Underlined text</u>';

// Strikethrough
$message = '<s>Strikethrough</s>';

// Links
$message = '<a href="https://processwire.com">ProcessWire</a>';

// Code
$message = '<code>inline code</code>';

// Code block
$message = '<pre>code block</pre>';

// Combine formatting
$message = '<b>Bold</b> and <i>italic</i> and <code>code</code>';
```

## ⚙️ Module Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Bot Token** | Your Telegram bot token from @BotFather | Required |
| **Chat IDs** | Comma or newline separated chat IDs | Required |
| **Parse Mode** | Message formatting: None, HTML, Markdown, MarkdownV2 | HTML |
| **Max Message Length** | Split messages longer than this | 4096 |
| **Disable Link Previews** | Don't show link previews in messages | Enabled |
| **Silent Notifications** | Send without notification sound | Disabled |
| **Enable Logging** | Log sent messages | Enabled |
| **Enable Debug Logging** | Detailed debug logs | Disabled |
| **API Timeout** | Timeout for API requests (seconds) | 5 |

## 🔍 Troubleshooting

### Messages not sending

1. ✅ **Check bot token** - Verify it's correct in module settings
2. ✅ **Start conversation** - Send `/start` to your bot in Telegram
3. ✅ **Verify Chat ID** - Use @userinfobot to get correct ID
4. ✅ **Check logs** - Look at `/site/assets/logs/telewire-errors.txt`
5. ✅ **Enable debug** - Turn on debug logging for detailed info

### Common Error Messages

**"Bot was blocked by the user"**
- User needs to unblock the bot in Telegram

**"Chat not found"**
- Invalid Chat ID or bot can't access the chat
- For groups: Make sure bot is added as admin

**"Request timeout"**
- Increase timeout in module settings
- Check your server's internet connection

### Enable Debug Logging

1. Go to module configuration
2. Enable **Debug Logging** checkbox
3. Check `/site/assets/logs/telewire-debug.txt` for details

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Maxim Alex**
- Website: [smnv.org](https://smnv.org)
- GitHub: [@mxmsmnv](https://github.com/mxmsmnv)
- Email: maxim@smnv.org

## 🙏 Acknowledgments

- Built for [ProcessWire CMS](https://processwire.com)
- Uses [Telegram Bot API](https://core.telegram.org/bots/api)
- Inspired by WireMail modules

## 📝 Changelog

### Version 1.0.0 (2026-01-23)
- Initial release
- Basic message sending
- Photo and document support
- Multiple recipients
- HTML/Markdown formatting
- Logging and debugging
- Admin test button

---

**Need help?** Open an issue on [GitHub](https://github.com/mxmsmnv/TeleWire/issues) or check the [ProcessWire forums](https://processwire.com/talk/).