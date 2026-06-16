<?php namespace ProcessWire;

/**
 * TeleWire - Telegram Notifications Module
 * 
 * Send notifications via Telegram Bot API
 * 
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 * @license MIT
 * @link https://github.com/mxmsmnv/TeleWire
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 */

require_once(__DIR__ . '/TelegramAPI.php');

class TeleWire extends WireData implements Module, ConfigurableModule {

    /**
     * Module information
     */
    public static function getModuleInfo() {
        return array(
            'title' => 'TeleWire - Telegram Notifications',
            'version' => '1.0.0',
            'summary' => __('Send notifications via Telegram Bot API. Alternative to email notifications.'),
            'href' => 'https://github.com/mxmsmnv/TeleWire',
            'author' => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'icon' => 'telegram',
            'singular' => true,
            'autoload' => true,
            'requires' => [
                'ProcessWire>=3.0.210',
                'PHP>=8.2'
            ]
        );
    }

    /**
     * Default configuration
     */
    protected static $defaultConfig = array(
        'botToken' => '',
        'chatIds' => '',
        'parseMode' => 'HTML',
        'enableLogging' => true,
        'enableDebugLogging' => false,
        'disableLinkPreviews' => true,
        'enableSilentMode' => false,
        'maxMessageLength' => 4096,
        'timeout' => 5
    );

    /**
     * TelegramAPI instance
     */
    protected $telegram = null;

    /**
     * Initialize the module
     */
    public function init() {
        // Merge default config with user config
        foreach(self::$defaultConfig as $key => $value) {
            if(!isset($this->$key)) $this->set($key, $value);
        }

        // Initialize Telegram API
        if($this->botToken) {
            $this->telegram = new TelegramAPI($this->botToken, [
                'timeout' => (int)$this->timeout,
                'parseMode' => $this->parseMode
            ]);
        }
    }

    /**
     * Ready
     */
    public function ready() {
        // Handle AJAX test request
        if($this->wire('config')->ajax && $this->wire('input')->post('telewire_test')) {
            $this->handleTestRequest();
        }
    }

    /**
     * Handle test message request
     */
    protected function handleTestRequest() {
        // Prevent session locking
        if(session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        if(!$this->wire('user')->isSuperuser()) {
            echo json_encode([
                'success' => false,
                'message' => 'Access denied'
            ]);
            exit;
        }

        $this->debugLog("Test message request started");

        // Test connection first
        $connectionTest = $this->testConnection();
        if(!$connectionTest['success']) {
            $this->debugLog("Connection test failed: " . $connectionTest['message']);
            echo json_encode($connectionTest);
            exit;
        }

        // Send test message
        $botInfo = $connectionTest['data'];
        $botName = $botInfo['first_name'] ?? 'TeleWire';
        
        $message = "🔔 <b>Test Message</b>\n\n";
        $message .= "✅ Connection successful!\n";
        $message .= "🤖 Bot: {$botName}\n";
        $message .= "🕐 Time: " . date('Y-m-d H:i:s') . "\n";
        $message .= "🌐 Site: " . $this->wire('config')->httpHost;

        $this->debugLog("Sending test message");
        $result = $this->send($message);

        if($result) {
            $chatIds = $this->parseChatIds($this->chatIds);
            $this->debugLog("Test message sent successfully");
            echo json_encode([
                'success' => true,
                'message' => 'Test message sent successfully to ' . count($chatIds) . ' chat(s)!'
            ]);
        } else {
            $lastError = $this->telegram ? $this->telegram->getLastError() : 'Unknown error';
            $this->debugLog("Test message failed: " . $lastError);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send: ' . $lastError
            ]);
        }
        
        exit;
    }

    /**
     * Send notification to configured chat IDs
     * 
     * @param string $message Message text
     * @param array $options Additional options
     * @return bool Success status
     */
    public function send($message, array $options = []) {
        $startTime = microtime(true);
        
        if(!$this->telegram) {
            $this->logError('Telegram API not initialized. Check bot token.');
            return false;
        }

        if(empty($this->chatIds)) {
            $this->logError('No chat IDs configured.');
            return false;
        }

        // Parse chat IDs
        $chatIds = $this->parseChatIds($this->chatIds);
        
        if(empty($chatIds)) {
            $this->logError('Invalid chat IDs configuration.');
            return false;
        }

        $this->debugLog("Sending message to " . count($chatIds) . " chat(s)");

        // Merge options
        $defaultOptions = [
            'parse_mode' => $this->parseMode,
            'disable_web_page_preview' => $this->disableLinkPreviews,
            'disable_notification' => $this->enableSilentMode
        ];
        
        $options = array_merge($defaultOptions, $options);

        // Split long messages
        $messages = $this->splitMessage($message);
        
        $success = true;
        $sentCount = 0;
        $errors = [];

        foreach($chatIds as $chatId) {
            foreach($messages as $msg) {
                $this->debugLog("Sending to chat ID: {$chatId}");
                
                $result = $this->telegram->sendMessage($chatId, $msg, $options);
                
                if($result) {
                    $sentCount++;
                    $this->debugLog("✓ Sent to chat ID: {$chatId}");
                } else {
                    $success = false;
                    $error = $this->telegram->getLastError();
                    $errors[] = "Chat ID {$chatId}: {$error}";
                    $this->logError("✗ Failed to send to chat ID {$chatId}: {$error}");
                }

                // Small delay to avoid rate limiting
                usleep(30000); // 30ms
            }
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if(!empty($errors)) {
            $this->debugLog("Errors occurred: " . implode("; ", $errors));
        }

        if($sentCount > 0) {
            $this->log("Sent {$sentCount} message(s) to " . count($chatIds) . " chat(s) in {$duration}ms");
        }

        $this->debugLog("Total execution time: {$duration}ms");

        return $success;
    }

    /**
     * Send message with photo
     * 
     * @param string $photo Photo URL or file path
     * @param string $caption Optional caption
     * @param array $options Additional options
     * @return bool
     */
    public function sendPhoto($photo, $caption = '', array $options = []) {
        if(!$this->telegram) return false;

        $chatIds = $this->parseChatIds($this->chatIds);
        if(empty($chatIds)) return false;

        $success = true;

        foreach($chatIds as $chatId) {
            $this->debugLog("Sending photo to chat ID: {$chatId}");
            $result = $this->telegram->sendPhoto($chatId, $photo, $caption, $options);
            if(!$result) {
                $success = false;
                $this->logError("Failed to send photo to chat ID {$chatId}: " . $this->telegram->getLastError());
            }
        }

        return $success;
    }

    /**
     * Send message with document
     * 
     * @param string $document Document URL or file path
     * @param string $caption Optional caption
     * @param array $options Additional options
     * @return bool
     */
    public function sendDocument($document, $caption = '', array $options = []) {
        if(!$this->telegram) return false;

        $chatIds = $this->parseChatIds($this->chatIds);
        if(empty($chatIds)) return false;

        $success = true;

        foreach($chatIds as $chatId) {
            $this->debugLog("Sending document to chat ID: {$chatId}");
            $result = $this->telegram->sendDocument($chatId, $document, $caption, $options);
            if(!$result) {
                $success = false;
                $this->logError("Failed to send document to chat ID {$chatId}: " . $this->telegram->getLastError());
            }
        }

        return $success;
    }

    /**
     * Split long message into chunks
     * 
     * @param string $message
     * @return array
     */
    protected function splitMessage($message) {
        $maxLength = (int)$this->maxMessageLength;
        
        if(mb_strlen($message) <= $maxLength) {
            return [$message];
        }

        $parts = [];
        $remaining = $message;

        while(mb_strlen($remaining) > 0) {
            if(mb_strlen($remaining) <= $maxLength) {
                $parts[] = $remaining;
                break;
            }

            // Try to split at last newline before limit
            $chunk = mb_substr($remaining, 0, $maxLength);
            $lastNewline = mb_strrpos($chunk, "\n");

            if($lastNewline !== false && $lastNewline > $maxLength * 0.5) {
                $splitPos = $lastNewline + 1;
            } else {
                $splitPos = $maxLength;
            }

            $parts[] = mb_substr($remaining, 0, $splitPos);
            $remaining = mb_substr($remaining, $splitPos);
        }

        return $parts;
    }

    /**
     * Parse chat IDs from configuration string
     * 
     * @param string $chatIds Comma or newline separated chat IDs
     * @return array
     */
    protected function parseChatIds($chatIds) {
        if(empty($chatIds)) return [];

        // Split by comma or newline
        $ids = preg_split('/[\s,]+/', trim($chatIds), -1, PREG_SPLIT_NO_EMPTY);
        
        // Validate and clean
        $validIds = [];
        foreach($ids as $id) {
            $id = trim($id);
            // Chat ID can be negative (for groups) or positive (for users)
            if(preg_match('/^-?\d+$/', $id)) {
                $validIds[] = $id;
            }
        }

        return array_unique($validIds);
    }

    /**
     * Test connection with current configuration
     * 
     * @return array Result with status and message
     */
    public function testConnection() {
        if(!$this->telegram) {
            return [
                'success' => false,
                'message' => 'Bot token not configured'
            ];
        }

        $this->debugLog("Testing connection to Telegram API");
        $botInfo = $this->telegram->getMe();
        
        if($botInfo) {
            $username = $botInfo['username'] ?? 'Unknown';
            $firstName = $botInfo['first_name'] ?? 'Unknown';
            
            $this->debugLog("Connection successful: {$firstName} (@{$username})");
            
            return [
                'success' => true,
                'message' => "Connected to bot: {$firstName} (@{$username})",
                'data' => $botInfo
            ];
        }

        $error = $this->telegram->getLastError();
        $this->debugLog("Connection failed: {$error}");
        
        return [
            'success' => false,
            'message' => 'Failed to connect: ' . $error
        ];
    }

    /**
     * Get updates (for webhook setup or testing)
     * 
     * @param int $offset Update offset
     * @param int $limit Number of updates to retrieve
     * @return array|false
     */
    public function getUpdates($offset = 0, $limit = 10) {
        if(!$this->telegram) return false;
        return $this->telegram->getUpdates($offset, $limit);
    }

    /**
     * Log message
     * 
     * @param string $message
     */
    protected function log($message) {
        if($this->enableLogging) {
            $this->wire('log')->save('telewire', $message);
        }
    }

    /**
     * Log error
     * 
     * @param string $message
     */
    protected function logError($message) {
        $this->wire('log')->save('telewire-errors', $message);
    }

    /**
     * Debug log (only when debug logging is enabled)
     * 
     * @param string $message
     */
    protected function debugLog($message) {
        if($this->enableDebugLogging) {
            $timestamp = date('Y-m-d H:i:s');
            $this->wire('log')->save('telewire-debug', "[{$timestamp}] {$message}");
        }
    }

    /**
     * Module configuration
     * 
     * @param InputfieldWrapper $inputfields
     */
    public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
        $modules = $this->wire('modules');

        // Bot Token
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'botToken');
        $f->label = $this->_('Bot Token');
        $f->description = $this->_('Get your bot token from @BotFather on Telegram');
        $f->notes = $this->_('Example: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz');
        $f->attr('value', $this->botToken);
        $f->required = true;
        $f->columnWidth = 70;
        $inputfields->add($f);

        // Test Connection Status
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Connection Status');
        $f->columnWidth = 30;
        
        if($this->botToken) {
            $testResult = $this->testConnection();
            $status = $testResult['success'] ? 'ok' : 'error';
            $icon = $testResult['success'] ? 'check-circle' : 'times-circle';
            $color = $testResult['success'] ? 'green' : 'red';
            
            $f->value = "
                <div style='padding: 10px; margin-top: 28px;'>
                    <p>
                        <i class='fa fa-{$icon}' style='color: {$color};'></i>
                        <strong>{$testResult['message']}</strong>
                    </p>
                </div>
            ";
        } else {
            $f->value = "<p style='padding: 10px; margin-top: 28px;'>" . 
                        $this->_('Enter bot token to test connection') . "</p>";
        }
        $inputfields->add($f);

        // Chat IDs
        $f = $modules->get('InputfieldTextarea');
        $f->attr('name', 'chatIds');
        $f->label = $this->_('Chat IDs');
        $f->description = $this->_('Chat IDs to receive notifications (one per line or comma-separated)');
        $f->notes = $this->_('Use @userinfobot to get your chat ID. For groups, use negative IDs. IMPORTANT: You must start a conversation with your bot first by sending /start command!');
        $f->attr('value', $this->chatIds);
        $f->attr('rows', 3);
        $f->required = true;
        $inputfields->add($f);

        // === TEST MESSAGE BUTTON ===
        if($this->botToken && $this->chatIds) {
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Send Test Message');
            $f->description = $this->_('Click the button to send a test notification to all configured chat IDs');
            
            $moduleUrl = $this->wire('config')->urls->admin . 'module/edit?name=TeleWire';
            
            $f->value = "
                <div id='telewire-test-container' style='margin: 20px 0;'>
                    <button type='button' id='telewire-test-btn' class='ui-button ui-widget ui-corner-all' style='min-width: 180px;'>
                        <i class='fa fa-paper-plane'></i> 
                        " . $this->_('Send Test Message') . "
                    </button>
                    <div id='telewire-test-result' style='margin-top: 15px;'></div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    // Remove previous handlers
                    $('#telewire-test-btn').off('click.telewire');
                    
                    $('#telewire-test-btn').on('click.telewire', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var \$btn = $(this);
                        var \$result = $('#telewire-test-result');
                        
                        // Disable button
                        \$btn.prop('disabled', true);
                        \$btn.html('<i class=\"fa fa-spinner fa-spin\"></i> Sending...');
                        
                        // Clear previous result
                        \$result.html('');
                        
                        // Send AJAX request
                        $.ajax({
                            url: '{$moduleUrl}',
                            type: 'POST',
                            data: { telewire_test: 1 },
                            dataType: 'json',
                            timeout: 15000
                        })
                        .done(function(response) {
                            var icon = response.success ? 'check-circle' : 'times-circle';
                            var color = response.success ? 'green' : 'red';
                            var bgColor = response.success ? '#d4edda' : '#f8d7da';
                            var borderColor = response.success ? '#c3e6cb' : '#f5c6cb';
                            
                            \$result.html(
                                '<div style=\"padding: 12px; background: ' + bgColor + '; border: 1px solid ' + borderColor + '; border-radius: 4px; color: #000;\">' +
                                '<i class=\"fa fa-' + icon + '\" style=\"color: ' + color + ';\"></i> ' +
                                '<strong>' + response.message + '</strong>' +
                                '</div>'
                            );
                        })
                        .fail(function(xhr, status, error) {
                            var errorMsg = status === 'timeout' ? 
                                'Request timeout - check your bot configuration' : 
                                'Request failed: ' + error;
                            
                            \$result.html(
                                '<div style=\"padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;\">' +
                                '<i class=\"fa fa-times-circle\" style=\"color: red;\"></i> ' +
                                '<strong>' + errorMsg + '</strong>' +
                                '</div>'
                            );
                        })
                        .always(function() {
                            // ALWAYS re-enable button
                            \$btn.prop('disabled', false);
                            \$btn.html('<i class=\"fa fa-paper-plane\"></i> Send Test Message');
                        });
                        
                        return false;
                    });
                });
                </script>
            ";
            
            $inputfields->add($f);
        }
        
        // Parse Mode
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'parseMode');
        $f->label = $this->_('Parse Mode');
        $f->description = $this->_('How to parse message formatting');
        $f->addOptions([
            '' => $this->_('None'),
            'HTML' => 'HTML',
            'Markdown' => 'Markdown',
            'MarkdownV2' => 'MarkdownV2'
        ]);
        $f->attr('value', $this->parseMode);
        $f->columnWidth = 50;
        $inputfields->add($f);

        // Max Message Length
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'maxMessageLength');
        $f->label = $this->_('Max Message Length');
        $f->description = $this->_('Maximum characters per message (Telegram limit is 4096)');
        $f->attr('value', $this->maxMessageLength ?: 4096);
        $f->columnWidth = 50;
        $inputfields->add($f);

        // Disable Link Previews
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'disableLinkPreviews');
        $f->label = $this->_('Disable Link Previews');
        $f->description = $this->_('Disable web page previews for links in messages');
        $f->attr('checked', $this->disableLinkPreviews ? 'checked' : '');
        $f->columnWidth = 33;
        $inputfields->add($f);

        // Enable Silent Mode
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableSilentMode');
        $f->label = $this->_('Silent Notifications');
        $f->description = $this->_('Send messages without notification sound');
        $f->attr('checked', $this->enableSilentMode ? 'checked' : '');
        $f->columnWidth = 33;
        $inputfields->add($f);

        // Enable Logging
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableLogging');
        $f->label = $this->_('Enable Logging');
        $f->description = $this->_('Log sent messages to telewire.txt');
        $f->attr('checked', $this->enableLogging ? 'checked' : '');
        $f->columnWidth = 34;
        $inputfields->add($f);

        // Enable Debug Logging
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'enableDebugLogging');
        $f->label = $this->_('Enable Debug Logging');
        $f->description = $this->_('Enable detailed debug logging to telewire-debug.txt (useful for troubleshooting)');
        $f->notes = $this->_('Warning: This will create large log files. Enable only when debugging issues.');
        $f->attr('checked', $this->enableDebugLogging ? 'checked' : '');
        $f->columnWidth = 50;
        $inputfields->add($f);

        // Timeout
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'timeout');
        $f->label = $this->_('API Timeout');
        $f->description = $this->_('Timeout for API requests in seconds');
        $f->notes = $this->_('Recommended: 3-10 seconds. Lower values = faster failures, higher values = more reliable delivery.');
        $f->attr('value', $this->timeout ?: 5);
        $f->attr('min', 1);
        $f->attr('max', 30);
        $f->columnWidth = 50;
        $inputfields->add($f);

        // Usage Example
        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Usage Example');
        $f->value = "<pre style='background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;'><code>" . htmlspecialchars(
"// Simple message
\$telewire = \$modules->get('TeleWire');
\$telewire->send('Hello from ProcessWire!');

// With HTML formatting
\$message = '<b>New Order</b>' . \"\\n\";
\$message .= 'Order: #123' . \"\\n\";
\$message .= 'Total: \$99.99';
\$telewire->send(\$message);

// Send photo
\$telewire->sendPhoto('/path/to/photo.jpg', 'Caption');

// Send document
\$telewire->sendDocument('/path/to/file.pdf', 'Invoice #123');

// Hook example
\$wire->addHookAfter('Pages::saved', function(\$event) {
    \$page = \$event->arguments(0);
    if(\$page->template == 'order') {
        \$telewire = \$this->modules->get('TeleWire');
        \$telewire->send(\"New order: {\$page->title}\");
    }
});
") . "</code></pre>";
        $inputfields->add($f);

        return $inputfields;
    }

    /**
     * Install
     */
    public function ___install() {
        $this->log('TeleWire module installed');
    }

    /**
     * Uninstall
     */
    public function ___uninstall() {
        $this->log('TeleWire module uninstalled');
    }
}