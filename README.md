# onesender-php
Library PHP untuk aplikasi OneSender


## Quick start

```PHP
require_once 'onesender.php';

// Versi PHP >= 8.0
$sender = OneSender::instance(
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

## Error message
Cek list pesan yang invalid atau tidak bisa dikirim

```PHP
$sender->getInvalidMessages();
```
