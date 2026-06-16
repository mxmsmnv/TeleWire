<?php namespace ProcessWire;

/**
 * Telegram Bot API Wrapper
 * 
 * @author Maxim Semenov <maxim@smnv.org> (smnv.org)
 */

class TelegramAPI extends WireData {

    /**
     * Bot token
     */
    protected $token;

    /**
     * API base URL
     */
    protected $apiUrl = 'https://api.telegram.org/bot';

    /**
     * Default options
     */
    protected $options = [
        'timeout' => 10,
        'parseMode' => 'HTML'
    ];

    /**
     * Last error
     */
    protected $lastError = '';

    /**
     * Last response
     */
    protected $lastResponse = null;

    /**
     * Constructor
     * 
     * @param string $token Bot token
     * @param array $options Additional options
     */
    public function __construct($token, array $options = []) {
        $this->token = $token;
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Send message
     * 
     * @param string|int $chatId Chat ID
     * @param string $text Message text
     * @param array $options Additional options
     * @return array|false Response or false on error
     */
    public function sendMessage($chatId, $text, array $options = []) {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text
        ], $options);

        if(!isset($params['parse_mode']) && $this->options['parseMode']) {
            $params['parse_mode'] = $this->options['parseMode'];
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Send photo
     * 
     * @param string|int $chatId Chat ID
     * @param string $photo Photo URL or file path
     * @param string $caption Optional caption
     * @param array $options Additional options
     * @return array|false
     */
    public function sendPhoto($chatId, $photo, $caption = '', array $options = []) {
        $params = array_merge([
            'chat_id' => $chatId,
            'photo' => $photo
        ], $options);

        if($caption) {
            $params['caption'] = $caption;
        }

        return $this->request('sendPhoto', $params);
    }

    /**
     * Send document
     * 
     * @param string|int $chatId Chat ID
     * @param string $document Document URL or file path
     * @param string $caption Optional caption
     * @param array $options Additional options
     * @return array|false
     */
    public function sendDocument($chatId, $document, $caption = '', array $options = []) {
        $params = array_merge([
            'chat_id' => $chatId,
            'document' => $document
        ], $options);

        if($caption) {
            $params['caption'] = $caption;
        }

        return $this->request('sendDocument', $params);
    }

    /**
     * Get bot information
     * 
     * @return array|false
     */
    public function getMe() {
        return $this->request('getMe');
    }

    /**
     * Get updates
     * 
     * @param int $offset Update offset
     * @param int $limit Number of updates
     * @param int $timeout Long polling timeout
     * @return array|false
     */
    public function getUpdates($offset = 0, $limit = 100, $timeout = 0) {
        $params = [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout
        ];

        return $this->request('getUpdates', $params);
    }

    /**
     * Set webhook
     * 
     * @param string $url Webhook URL
     * @param array $options Additional options
     * @return array|false
     */
    public function setWebhook($url, array $options = []) {
        $params = array_merge(['url' => $url], $options);
        return $this->request('setWebhook', $params);
    }

    /**
     * Delete webhook
     * 
     * @return array|false
     */
    public function deleteWebhook() {
        return $this->request('deleteWebhook');
    }

    /**
     * Make API request
     * 
     * @param string $method API method
     * @param array $params Request parameters
     * @return array|false Response data or false on error
     */
    protected function request($method, array $params = []) {
        $url = $this->apiUrl . $this->token . '/' . $method;

        // Use JSON for better compatibility
        $jsonData = json_encode($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_TIMEOUT => $this->options['timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if($error) {
            $this->lastError = "CURL Error: {$error}";
            return false;
        }

        if($httpCode !== 200) {
            $this->lastError = "HTTP Error: {$httpCode}";
            if($response) {
                $data = json_decode($response, true);
                if(isset($data['description'])) {
                    $this->lastError .= " - " . $data['description'];
                }
            }
            return false;
        }

        $data = json_decode($response, true);
        $this->lastResponse = $data;

        if(!$data || !isset($data['ok'])) {
            $this->lastError = "Invalid API response";
            return false;
        }

        if(!$data['ok']) {
            $errorMsg = $data['description'] ?? 'Unknown error';
            $errorCode = $data['error_code'] ?? 0;
            $this->lastError = "API Error ({$errorCode}): {$errorMsg}";
            
            // Add helpful hints for common errors
            if($errorCode == 403) {
                $this->lastError .= " - Bot was blocked by the user or user hasn't started conversation with bot";
            } elseif($errorCode == 400 && strpos($errorMsg, 'chat not found') !== false) {
                $this->lastError .= " - Invalid chat ID or bot cannot access this chat";
            }
            
            return false;
        }

        return $data['result'] ?? true;
    }

    /**
     * Get last error
     * 
     * @return string
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Get last response
     * 
     * @return array|null
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }
}