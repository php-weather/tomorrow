# PHP Weather Provider for Tomorrow.io

![Packagist Version](https://img.shields.io/packagist/v/php-weather/tomorrow)  
![PHP Weather Common Version](https://img.shields.io/badge/phpweather--core-0.4.*-brightgreen)
![PHP Weather HTTP Provider Version](https://img.shields.io/badge/phpweather--http--provider-0.5.*-brightgreen)  
![GitHub Release Date](https://img.shields.io/github/release-date/php-weather/tomorrow)
![GitHub commits since tagged version](https://img.shields.io/github/commits-since/php-weather/tomorrow/0.2.1)
![GitHub last commit](https://img.shields.io/github/last-commit/php-weather/tomorrow)  
![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/php-weather/tomorrow/php.yml?branch=main)
![GitHub](https://img.shields.io/github/license/php-weather/tomorrow)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/php-weather/tomorrow)

This is the [Tomorrow.io](https://brightsky.dev/) provider from PHP Weather.

> Tomorrow.io’s weather API delivers the fast, reliable, and hyper-accurate weather data you need, with the flexibility to integrate this data source with any application, system, or program.

## Installation

Via Composer

```shell
composer require php-weather/tomorrow
```

## Usage

```php
$tomorrowKey = 'key';

$httpClient = new \Http\Adapter\Guzzle7\Client();
$tomorrowIo = new \PhpWeather\Provider\Tomorrow\Tomorrow($httpClient, $tomorrowKey);

$latitude = 47.873;
$longitude = 8.004;

$currentWeatherQuery = \PhpWeather\Common\WeatherQuery::create($latitude, $longitude);
$currentWeather = $tomorrowIo->getCurrentWeather($currentWeatherQuery);
```