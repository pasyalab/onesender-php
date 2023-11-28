<?php

/**
 * Class OneSender
 * 
 * A class for sending messages and media through a messaging API.
 */
class OneSender {

    /** @var OneSender|null $instance */
    public static $instance;

    /**
     * Get an instance of the OneSender class.
     * 
     * @param string $url The API URL.
     * @param string $key The API key.
     * @param string $countryCode The country code.
     * @param bool $validateUrl Whether to validate URLs.
     * @return OneSender The OneSender instance.
     */
    public static function instance($url = '', $key = '',  $countryCode = '62', $validateUrl = false): OneSender {
        if (!self::$instance) {
            self::$instance = new self(
                $url, 
                $key, 
                $countryCode, 
                $validateUrl
            );
        }

        return self::$instance;
    }

    /** @var string $countryCode The default country code. */
    protected string $countryCode = '62';

    /** @var string $apiUrl The API URL. */
    protected string $apiUrl;

    /** @var string $apiKey The API key. */
    protected string $apiKey;

    /** @var bool $validateUrl Whether to validate URLs. */
    protected bool $validateUrl;

    /** @var array $invalidMessages Array to store invalid messages. */
    protected array $invalidMessages = [];

    /** @var array $messages Array to store messages. */
    protected array $messages = [];

    /**
     * OneSender constructor.
     * 
     * @param string $url The API URL.
     * @param string $key The API key.
     * @param string $countryCode The country code.
     * @param bool $validateUrl Whether to validate URLs.
     */
    public function __construct($url = '', $key = '', $countryCode = '62', $validateUrl = false) {
        $this->apiUrl = $url;
        $this->apiKey = $key;
        $this->countryCode = $countryCode;
        $this->validateUrl = $validateUrl;
    }

    /**
     * Send a text message.
     * 
     * @param string $phone The phone number.
     * @param string $text The text of the message.
     * @param bool $unique Whether to make the message unique.
     * @param bool $aggregate Whether to aggregate messages.
     * @return mixed The result of the message sending.
     */
    public function sendText(string $phone = '', string $text = '', $unique = false, $aggregate = false): mixed {
        if (empty($text) || empty($phone)) {
            return [false, 'Text and phone number are required'];
        }

        $phones = $this->parsePhones($phone);

        if (empty($phones)) {
            $this->addInvalidText($phone, $text, 'Invalid phone number');
            return [false, 'Invalid phone number'];
        }

        $messages = $this->map($phones, function($phone) use ($text, $unique) {
            $message = [
                'type' => 'text',
                'to' => $phone['number'],
                'recipient_type' => $phone['type'],
                'text' => [
                    'body' => $text,
                ],
            ];

            if ($unique) {
                $message['tag'] = $this->randomTag();
                $message['unique'] = true;
            }

            return $message;
        });

        if ($aggregate) {
            $this->messages = array_merge($this->messages, $messages);
            return  $this;
        }

        return $this->deliver($messages);
    }

    /**
     * Send an image message.
     * 
     * @param string $phone The phone number.
     * @param string $url The URL of the image.
     * @param string $caption The caption for the image.
     * @param bool $unique Whether to make the message unique.
     * @param bool $aggregate Whether to aggregate messages.
     * @return mixed The result of the message sending.
     */
    public function sendImage(string $phone = '', string $url = '', string $caption = '', $unique = false, $aggregate = false): mixed  {
        return $this->sendMedia('image', $phone, $url, $caption, '', $unique, $aggregate);
    }

    /**
     * Send a document message.
     * 
     * @param string $phone The phone number.
     * @param string $url The URL of the document.
     * @param string $caption The caption for the document.
     * @param string $filename The filename for the media.
     * @param bool $unique Whether to make the message unique.
     * @param bool $aggregate Whether to aggregate messages.
     * @return mixed The result of the message sending.
     */
    public function sendDocument(string $phone = '', string $url = '', string $caption = '', string $filename = '', $unique = false, $aggregate = false): mixed  {
        return $this->sendMedia('document', $phone, $url, $caption, $filename, $unique, $aggregate);
    }

    /**
     * Send a media message (image or document).
     * 
     * @param string $type The type of media ('image' or 'document').
     * @param string $phone The phone number.
     * @param string $url The URL of the media.
     * @param string $caption The caption for the media.
     * @param string $filename The filename for the media.
     * @param bool $unique Whether to make the message unique.
     * @param bool $aggregate Whether to aggregate messages.
     * @return mixed The result of the media message sending.
     */
    public function sendMedia(string $type = 'image', string $phone = '', string $url = '', string $caption = '', $filename = '', $unique = false, $aggregate = false): mixed {
        if (empty($url) || empty($phone)) {
            $this->addInvalidMedia($type, $phone, $url, $caption, 'Url and phone number are required');

            return $aggregate ? $this : [false, 'Url and phone number are required'];
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->addInvalidMedia($type, $phone, $url, $caption, 'Invalid url');

            return $aggregate ? $this : [false, 'Invalid url'];
        } 

        if ($this->validateUrl && $type === 'image' && $this->isUrlImage($url) === false) {
            $this->addInvalidImage($phone, $url, $caption, 'Invalid image url');
            return $aggregate ? $this : [false, 'Invalid image url'];
        }
       
        $phones = $this->parsePhones($phone);

        if (empty($phones)) {
            $this->addInvalidMedia($type, $phone, $url, $caption, 'Invalid phone number');

            return $aggregate ? $this : [false, 'Invalid phone number'];
        }

        $messages = $this->map($phones, function($phone) use ($type, $url, $caption, $filename, $unique) {

            $msg = [
                'link' => $url,
            ];

            if (!empty($caption)) {
                $msg['caption'] = $caption;
            }

            if (!empty($filename)) {
                $msg['filename'] = $filename;
            }

            $message = [
                'to' => $phone['number'],
                'recipient_type' => $phone['type'],
                'type' => $type,
                $type => $msg
            ];

            if ($unique) {
                $message['tag'] = $this->randomTag();
                $message['unique'] = true;
            }

            return $message;
        });

        if ($aggregate) {
            $this->messages = array_merge($this->messages, $messages);
            return $this;
        }

        return $this->deliver($messages);
    }

    /**
     * Deliver messages to the API.
     * 
     * @param array $messages Array of messages to be delivered.
     * @return array The result of the delivery.
     */
    public function deliver(array $messages): array {
        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($messages),
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);

        $error = '';
        $result = [];

        if (curl_errno($curl)) {
            $error = curl_error($curl);
        }

        curl_close($curl);

        $result = json_decode($response, true);
        if (json_last_error()!== JSON_ERROR_NONE) {
            return [[], $error];
        }

        return [$result, $error];
    }

    /**
     * Send all aggregated messages.
     * 
     * @return array The result of sending aggregated messages.
     */
    public function send(): array {
        return $this->deliver($this->messages);
    }

    /**
     * Parse phone numbers and categorize them.
     * 
     * @param string $phone The input phone numbers.
     * @return array Array of parsed and categorized phone numbers.
     */
    private function parsePhones(string $phone): array {
        $phones = [];
        $input = explode(',', $phone);
        $phones = $this->map($input, function($number) {
            if (strpos($number, '@g.us')!== false) {
                return [
                    'number' => trim($number),
                    'type' => 'group',
                ];
            }

            $number = preg_replace( '/[^0-9]/', '', $number );
            if (empty($number)) {
                return false;
            } 

            if (substr($number, 0, 1) == "0") {
                $number = $this->countryCode. substr($number, 1);
            }

            return [
                'number' => $number,
                'type' => 'personal',
            ];
        });

        $phones = $this->filter($phones, fn($item) => $item !== false);

        return $phones;
    }

    /**
     * Generate a random tag for unique messages.
     * 
     * @return string The generated random tag.
     */
    private function randomTag(): string {
        return sprintf(
			'%04x%04x%04x%04x%04x%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
        );
    }

    /**
     * Map a callback function over an array.
     * 
     * @param array $items The input array.
     * @param callable $callback The callback function.
     * @return array The result of mapping the callback over the array.
     */
    private function map(array $items, callable $callback ): array {
        return array_map( $callback, $items, array_keys( $items ) );
    }

    /**
     * Filter an array based on a callback function.
     * 
     * @param array $items The input array.
     * @param callable $callback The callback function.
     * @return array The result of filtering the array.
     */
    private function filter(array $items, callable $callback ) {
        return array_filter( $items, $callback, ARRAY_FILTER_USE_BOTH );
    }

    /**
     * Check if a URL points to an image.
     * 
     * @param string $url The URL to check.
     * @return bool True if the URL is an image, false otherwise.
     */
    private function isUrlImage(string $url): bool {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        if ($response === false) {
            return false;
        } 
        
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);

        preg_match('/content-type: ([\w\/]+)/', strtolower($headers), $matches);

        if (!isset($matches[1])) {
            return false;
        } 
        
        return in_array($matches[1], ['image/jpeg', 'image/png']);
    }

    /**
     * Add an invalid text message to the list of invalid messages.
     * 
     * @param string $phone The phone number.
     * @param string $text The text of the message.
     * @param string $error The error message for the invalid text.
     * @return void
     */
    private function addInvalidText(string $phone, string $text, string $error): void {
        $invalidMessage = [
            'type' => 'text',
            'to' => $phone,
            'recipient_type' => 'individual',
            'text' => [
                'body' => $text,
            ],
            'error' => $error,
        ];
        $this->invalidMessages = array_merge($this->invalidMessages, [$invalidMessage]);
    }

    /**
     * Add an invalid image message to the list of invalid messages.
     * 
     * @param string $phone The phone number.
     * @param string $url The URL of the image.
     * @param string $caption The caption for the image.
     * @param string $error The error message for the invalid image.
     * @return void
     */    
    private function addInvalidImage(string $phone, string $url, string $caption, string $error): void {
        $invalidMessage = [
            'type' => 'image',
            'to' => $phone,
            'recipient_type' => 'individual',
            'image' => [
                'link' => $url,
                'caption' => $caption,
            ],
            'error' => $error,
        ];
        $this->invalidMessages = array_merge($this->invalidMessages, [$invalidMessage]);
    }

    /**
     * Add an invalid document message to the list of invalid messages.
     * 
     * @param string $phone The phone number.
     * @param string $url The URL of the document.
     * @param string $caption The caption for the document.
     * @param string $error The error message for the invalid document.
     * @return void
     */
    private function addInvalidDocument(string $phone, string $url, string $caption, string $error): void {
        $invalidMessage = [
            'type' => 'document',
            'to' => $phone,
            'recipient_type' => 'individual',
            'document' => [
                'link' => $url,
                'caption' => $caption,
            ],
            'error' => $error,
        ];
        $this->invalidMessages = array_merge($this->invalidMessages, [$invalidMessage]);
    }

    /**
     * Add an invalid media message (image or document) to the list of invalid messages.
     * 
     * @param string $type The type of media ('image' or 'document').
     * @param string $phone The phone number.
     * @param string $url The URL of the media.
     * @param string $caption The caption for the media.
     * @param string $error The error message for the invalid media.
     * @return void
     */
    private function addInvalidMedia(string $type, string $phone, string $url, string $caption, string $error): void {
        if ($type === 'image') {
            $this->addInvalidImage($phone, $url, $caption, $error);
        } else if ($type === 'document') {
            $this->addInvalidDocument($phone, $url, $caption, $error);
        }
    }

    /**
     * Get invalid messages.
     * 
     * @return array Array of invalid messages.
     */
    public function getInvalidMessages(): array {
        return $this->invalidMessages;
    }
}
