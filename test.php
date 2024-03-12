<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once 'onesender.php';

$sender = OneSender::setup(
    url: 'https://engoymo13agbp.x.pipedream.net/api/v1/messages',
    key: 'uuuuuuuu.123456789012345678901234567890',
    countryCode: '62', 
    validateUrl: true, 
);

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
    url: 'https://livedemo.biz.id/gtx-680m-chip.png',
    aggregate: true,
);

// Tambahkan pesan ketiga
$sender->sendDocument(
    phone: '0833333333333333,6281234567890@g.us',
    caption: 'Hello ini dokumen',
    url: 'https://livedemo.biz.id/gtx-680m-chip.png',
    filename: 'chip-image', // without file extension
    aggregate: true,
);


list($response, $error) = $sender->send();

header('Content-Type: text/plain');
var_dump($response) . PHP_EOL;
var_dump($error) . PHP_EOL;
var_dump($sender->getInvalidMessages()) . PHP_EOL;