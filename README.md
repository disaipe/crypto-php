# Crypto-PHP

Библиотека для просмотра установленных сертификатов, подписания данных и верификации подписей с помощью расширения [КриптоПро для PHP](http://cpdn.cryptopro.ru/content/cades/phpcades.html).

## Перед использованием

Перед использованием Crypto-PHP необходимо произвести установку *КриптоПРО CSP* и скомпилировать расширение *libphpcades* для вашей версии PHP.

Если подразумевается подписание данных, то дополнительно потребуется установить необходимые ключи для пользователя, из-под которого запущен веб-сервер.

Более подробно описано в [инструкции по установке](INSTALL.md).

## Использование

Полный пример использования можно посмотреть на странице [examples/index.php](examples/index.php).

### Создание экземпляра
```php
require 'CryptoHelper.php';

$crypto = new CryptoHelper();
```

#### Получение сертификатов

Их хранилища пользователя запрашиваются только активные и валидные сертификаты.

```php
$certificates = $crypto->GetCertificates();
print_r($certificates);

//=> Array (
//     [0] => CryptoCertificate Object (
//             [original:CryptoCertificate:private] => CPCertificate Object ()
//             [pin:CryptoCertificate:private] => 
//             [Subject] => stdClass Object (
//                     [email] => admin@example.com
//                     [name] => Horns and Hoofs, LLC (TEST)
//                     [company] => ООО "Рога и Копыта"
//                 )
//             [Issuer] => stdClass Object (
//                     [email] => support@cryptopro.ru
//                     [city] => Moscow
//                     [company] => CRYPTO-PRO LLC
//                     [name] => CRYPTO-PRO Test Center 2
//                 )
//             [Version] => 3
//             [SerialNumber] => 120039CB6175D20B28460D384200010039CB61
//             [Thumbprint] => 81E3BF1FB98BE9FA37637072491C58F8A9FEFC30
//             [ValidFrom] => 13.08.2019 06:37:33
//             [ValidTo] => 13.11.2019 06:47:33
//             [HasPrivate] => 1
//             [IsValid] => 1

//         )

// )
```

#### Подписание

Класс `CryptoHelper` содержит несколько методов подписания данных:

|Метод|Аргументы|Описание|
|---|---|---|
|Sign|(CryptoСertificate, string *$data*, *$toBase64* = true)|Подписание строки|
|SignFile|(CryptoСertificate, string *$dataFilePath*, $string $signFilePath = null)|Подписание файла и сохранение в файл *$signFilePath* (если указан)|

```php
// Если у сертификата задан pin, то необходимо его указать
$certificate->SetPin('123456');

// Прдписание строки
$secret = 'My secret string';
$sign = $crypto->Sign($certificate, $secret);
// => 'MIIIgAYJKoZIhvc...';

// Подписание файла
$sign = $crypto->SignFile($certificate, 'file.pdf', 'file.pdf.sgn');
// => 'MIIIgAYJKoZIhvc...';
```

#### Верификация

Класс `CryptoHelper` содержит несколько методов верификации данных:

|Метод|Аргументы|Описание|
|---|---|---|
|Verify|(string *$data*, *$toBase64* = true)|Верификация строки|
|VerifyFile|(string *$dataFilePath*, $string $signFilePath = null)|Верификация файлов|

```php
$data = 'My secret string';
$sign = 'MIIIgAYJKoZIhvc...';

$signInfo = $crypto->Verify($data, $sign, true);

if (!$signInfo) {
    // Подпись не валидна
} else {
    // Подпись валидна, вывести список подписей
    foreach ($signInfo as $sign) {
		echo "\nTimestamp: {$sign->ts}, Name: {$sign->cert->Subject->Name}\n";
	}
}

// Верификация файлов
$signInfo = $crypto->VerifyFile('file.pdf', 'file.pdf.sgn');
```

## Полезные ссылки

* [Linux & КриптоПРО](https://www.altlinux.org/КриптоПро)
* [Работа с КриптоПро на linux сервере](http://pushorigin.ru/cryptopro/cryptcp)