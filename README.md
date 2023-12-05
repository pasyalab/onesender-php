# onesender-php
Library PHP untuk aplikasi OneSender


## Quick start

```PHP
require_once 'onesender.php';

// Versi PHP >= 8.0
$sender = OneSender::setup(
    url: 'https://myapi.com/api/v1/messages',
    key: 'uuuuuuuu.123456789012345678901234567890',
    countryCode: '62', // opsional. Default kode negara
    validateUrl: true, // opsional. Check link pesan gambar valid atau tidak
);

// Kirim pesan text
$sender->sendText(
    phone: '628120000001',
    text: 'Selamat pagi, Cikgu!',
);

// Atau
// Kirim pesan dan ambil data [response, error]
list($response, $error) = $sender->sendText(
    phone: '628120000001',
    text: 'Selamat pagi, Cikgu!',
);

var_dump($response);
var_dump($error);
```

### Kirim bulk message

Tambahkan parameter `aggregate` dengan value `true`.

```PHP

// Tambahkan pesan pertama
$sender->sendText(
    phone: '628120000001',
    text: 'Selamat pagi, Cikgu!',
    aggregate: true,
);

// Tambahkan pesan kedua
$sender->sendImage(
    phone: '124281234567890@g.us',
    caption: 'Hello ini caption gambar',
    url: 'https://cdn.wccftech.com/wp-content/uploads/2023/11/1700811649860-1-728x380.jpeg',
    aggregate: true,
);

// Tambahkan pesan ketiga
$sender->sendDocument(
    phone: '0833333333333333,6281234567890@g.us',
    caption: 'Hello ini dokumen',
    url: 'https://cdn.wccftech.com/wp-content/uploads/2023/11/1700811649860-1-728x380.jpeg',
    filename: 'image',
    aggregate: true,
);


list($response, $error) = $sender->send();

var_dump($response);
var_dump($error);
var_dump($sender->getInvalidMessages());
```

## Kirim ke group
```PHP
$sender->sendText(
    phone: '628120000001-123456789@g.us',
    text: 'Pesan ke group',
);
```


## Kirim satu pesan ke beberapa nomor
```PHP

$sender->sendText(
    phone: '628120000001,628120000002',
    text: 'Semua tujuan berupa nomor kontak',
);

$sender->sendText(
    phone: '628120000001, 628120000001-123456789@g.us',
    text: 'Kombinasi nomor kontak dan group',
);
```

## Error message
Cek list pesan yang invalid atau tidak bisa dikirim

```PHP
$sender->getInvalidMessages();
```

## Parse template
```PHP
$shortcode = [
    'name' => 'John smith',
    'gender' => 'Male',
    'weight' => '60',
];

$template = "Report:
{name} is {gender} and weighs {weight} kg.
End";

$text = OneSender::parseTemplate($template, $shortcode);

var_dump($text);

```

## Filter

Filter digunakan untuk mengubah data tanpa perlu mengubah kode library.
Contoh:
```PHP
$sender->addFilter('messages', function($messages) {
    // Ubah $messages
    return $messages;
}, 17);
```


**Parameter**

Variable | Tipe | Deskripsi
---|---|---
$hook | string | id variabel
$callback | Closure function | function untuk memproses data
$priority | integer | prioritas, nilai paling dipanggil terlebih dahulu

### api_url
```PHP
$sender->addFilter('api_url', function($apiUrl) {
    return $apiUrl;
}, 17);
```

### api_key
```PHP
$sender->addFilter('api_key', function($apiKey) {
    return $apiKey;
}, 17);
```

### recipients
```PHP
$sender->addFilter('recipients', function($recipients) {
    return $recipients;
}, 17);
```

### messages
```PHP
$sender->addFilter('messages', function($messages) {
    $messages[0]['text']['body'] = $messages[0]['text']['body'] .PHP_EOL . time();
    return $messages;
}, 17);
```


### Troubleshoot

### Pesan terkirim dobel / lebih dari satu
Untuk mengantisipasi agar pesan tidak terkirim beberapa kali. Ketika terjadi error di runtime curl/php. Maka gunakan parameter `unique` dengan value `true`.

```PHP
$sender->sendText(
    phone: '628120000001',
    text: 'Selamat pagi, Cikgu!',
    unique: true,
);
``` 
