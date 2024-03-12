<?php

class OneSender {

    public static $instance;
    
    public static function instance($url = '', $key = '',  $countryCode = '62', $validateUrl = false, string $app = 'api'): OneSender {
        if (!self::$instance) {
            self::$instance = new self(
                $url, 
                $key, 
                $countryCode, 
                $validateUrl,
                $app
            );
        }

        return self::$instance;
    }

    public static function setup($url = '', $key = '',  $countryCode = '62', $validateUrl = false, string $app = 'api'): OneSender {
        return self::instance($url, $key,  $countryCode, $validateUrl, $app);
    }

    const VERSION = '1.1.3';

    protected string $countryCode = '62';
    protected string $apiUrl;
    protected string $apiKey;
    protected string $app;
    protected bool $validateUrl;
    protected array $invalidMessages = [];

    protected array $filters = [];

    protected array $messages = [];

    public function __construct($url = '', $key = '', $countryCode = '62', $validateUrl = false, string $app = 'api') {
        $this->apiUrl = $url;
        $this->apiKey = $key;
        $this->countryCode = $countryCode;
        $this->validateUrl = $validateUrl;
        $this->app = $app;
    }

    public function sendText(string $phone = '', string $text = '', $unique = false, $aggregate = false) {
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
                'app' => $this->app,
                'type' => 'text',
                'to' => $phone['number'],
                'recipient_type' => $phone['type'],
                'text' => [
                    'body' => $text,
                ],
            ];

            $tag = $this->randomTag();
            $tag = $this->applyFilters('message_tag', $tag, $message);

            if ($unique) {
                $message['tag'] = $tag;
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

    public function sendImage(string $phone = '', string $url = '', string $caption = '', $unique = false, $aggregate = false)  {
        return $this->sendMedia('image', $phone, $url, $caption, $unique, $aggregate);
    }

    public function sendDocument(string $phone = '', string $url = '', string $caption = '', $unique = false, $aggregate = false, $filename = 'document')  {
        $params = [
            'filename' => $filename,
        ];

        return $this->sendMedia('document', $phone, $url, $caption, $unique, $aggregate, $params);
    }

    public function sendMedia(string $type = 'image', string $phone = '', string $url = '', string $caption = '', $unique = false, $aggregate = false, $params = []) {
        if (empty($url) || empty($phone)) {
            $this->addInvalidMedia($type, $phone, $url, $caption, 'Url and phone number are required', $params);

            return $aggregate ? $this : [false, 'Url and phone number are required'];
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->addInvalidMedia($type, $phone, $url, $caption, 'Invalid url', $params);

            return $aggregate ? $this : [false, 'Invalid url'];
        } 

        if ($this->validateUrl && $type == 'image' && $this->isUrlImage($url) !== true) {
            $this->addInvalidImage($phone, $url, $caption, 'Invalid image url');
            return $aggregate ? $this : [false, 'Invalid image url'];
        }
       
        $phones = $this->parsePhones($phone);

        if (empty($phones)) {
            $this->addInvalidMedia($type, $phone, $url, $caption, 'Invalid phone number', $params);

            return $aggregate ? $this : [false, 'Invalid phone number'];
        }

        $messages = $this->map($phones, function($phone) use ($type, $url, $caption, $unique, $params) {

            $msg = [
                'link' => $url,
            ];

            if (!empty($caption)) {
                $msg['caption'] = $caption;
            }

            if ($type === 'document') {
                $msg['filename'] = $params['filename'] ?? 'document';
            }

            $message = [
                'app' => $this->app,
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


    public function deliver(array $messages): array {

        $messages = $this->applyFilters('messages', $messages);
        $this->messages = $messages;

        $apiUrl = $this->applyFilters('api_url', $this->apiUrl, $messages);
        $apiKey = $this->applyFilters('api_key', $this->apiKey, $messages);

        $headers = array(
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $apiUrl,
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

    public function send(): array {
        return $this->deliver($this->messages);
    }

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
                'type' => 'individual',
            ];
        });

        $phones = $this->filter($phones, fn($item) => $item !== false);

        $phones = $this->applyFilters('recipients', $phones);

        return $phones;
    }

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

    private function map(array $items, callable $callback ): array {
        return array_map( $callback, $items, array_keys( $items ) );
    }

    private function filter(array $items, callable $callback ) {
        return array_filter( $items, $callback, ARRAY_FILTER_USE_BOTH );
    }

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

    private function addInvalidDocument(string $phone, string $url, string $caption, string $error, $params): void {
        $invalidMessage = [
            'type' => 'document',
            'to' => $phone,
            'recipient_type' => 'individual',
            'document' => [
                'link' => $url,
                'caption' => $caption,
                'filename' => $params['filename'] ?? 'document',
            ],
            'error' => $error,
        ];
        $this->invalidMessages = array_merge($this->invalidMessages, [$invalidMessage]);
    }

    private function addInvalidMedia(string $type, string $phone, string $url, string $caption, string $error, $params = []): void {
        if ($type === 'image') {
            $this->addInvalidImage($phone, $url, $caption, $error);
        } else if ($type === 'document') {
            $this->addInvalidDocument($phone, $url, $caption, $error, $params);
        }
    }

    public function getMessages(): array {
        return $this->messages;
    }

    public function getInvalidMessages(): array {
        return $this->invalidMessages;
    }

    public static function parseTemplate(string $template = '', array $data = []): string {
        return self::parse_template($template, $data);
    }

    public static function parse_template(string $template = '', array $data = []): string {
        $pattern = '/\{ ?([^}]+) ?\}/s';
        preg_match_all($pattern, $template, $matches);

        foreach ($matches[1] as $match) {
            $key = trim($match);
            $replacement = isset($data[$key]) ? $data[$key] : '';
            $template = str_replace("{{$match}}", $replacement, $template);
        }

        return $template;
    }

    public function addFilter(string $hook, $callback, int $priority) {
        if (!isset($this->filters[$hook])) {
            $this->filters[$hook] = array();
        }
        $this->filters[$hook][] = array(
            'callback' => $callback,
            'priority' => $priority
        );

        return $this;
    }

    public function applyFilters($hook, ...$args) {
        if (isset($this->filters[$hook])) {
            usort($this->filters[$hook], function ($a, $b) {
                return $a['priority'] - $b['priority'];
            });

            foreach ($this->filters[$hook] as $filter) {
                $callback = $filter['callback'];
                $args[0] = call_user_func_array($callback, $args);
            }
        }

        return $args[0] ?? null;
    }
}
