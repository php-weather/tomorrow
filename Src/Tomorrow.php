<?php
declare(strict_types=1);

namespace PhpWeather\Provider\Tomorrow;

use DateTime;
use DateTimeZone;
use PhpWeather\Common\Source;
use PhpWeather\Common\UnitConverter;
use PhpWeather\Constants\Type;
use PhpWeather\Constants\Unit;
use PhpWeather\HttpProvider\AbstractHttpProvider;
use PhpWeather\Weather;
use PhpWeather\WeatherCollection;
use PhpWeather\WeatherQuery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class Tomorrow extends AbstractHttpProvider
{
    private string $key;

    public function __construct(ClientInterface $client, string $key, ?RequestFactoryInterface $requestFactory = null)
    {
        $this->key = $key;
        parent::__construct($client, $requestFactory);
    }


    protected function getCurrentWeatherQueryString(WeatherQuery $query): string
    {
        $queryArray = [
            'location' => implode(',', [$query->getLatitude(), $query->getLongitude()]),
            'fields' => $this->getQueryFields(),
            'units' => 'metric',
            'timesteps' => ['current'],
            'apikey' => $this->key,
        ];

        return sprintf('https://api.tomorrow.io/v4/timelines?%s', $this->createQueryString($queryArray));
    }

    /**
     * @return string[]
     */
    private function getQueryFields(): array
    {
        return [
            'cloudCover',
            'dewPoint',
            'temperatureApparent',
            'humidity',
            'precipitationIntensity',
            'precipitationProbability',
            'pressureSeaLevel',
            'temperature',
            'weatherCode',
            'windSpeed',
            'windDirection',
        ];
    }

    /**
     * @param  array<string, mixed>  $queryArray
     * @return string
     */
    private function createQueryString(array $queryArray): string
    {
        $lines = [];
        foreach ($queryArray as $key => $value) {
            if (!is_array($value)) {
                $lines[] = sprintf('%s=%s', $key, $value);
            } else {
                foreach ($value as $valueItem) {
                    $lines[] = sprintf('%s=%s', $key, $valueItem);
                }
            }
        }

        return implode('&', $lines);
    }

    protected function getForecastWeatherQueryString(WeatherQuery $query): string
    {
        $queryArray = [
            'location' => implode(',', [$query->getLatitude(), $query->getLongitude()]),
            'fields' => $this->getQueryFields(),
            'units' => 'metric',
            'timesteps' => ['current', '1h'],
            'apikey' => $this->key,
        ];

        return sprintf('https://api.tomorrow.io/v4/timelines?%s', $this->createQueryString($queryArray));
    }

    protected function getHistoricalTimeLineWeatherQueryString(WeatherQuery $query): string
    {
        return $this->getHistoricalWeatherQueryString($query);
    }

    protected function getHistoricalWeatherQueryString(WeatherQuery $query): string
    {
        $queryArray = [
            'location' => implode(',', [$query->getLatitude(), $query->getLongitude()]),
            'fields' => $this->getQueryFields(),
            'units' => 'metric',
            'timesteps' => ['current', '1h'],
            'apikey' => $this->key,
        ];

        if ($query->getDateTime() !== null) {
            $queryArray['startTime'] = $query->getDateTime()->format('Y-m-d\TH:i:s\Z');
        }

        return sprintf('https://api.tomorrow.io/v4/timelines?%s', $this->createQueryString($queryArray));
    }

    protected function mapRawData(float $latitude, float $longitude, array $rawData, ?string $type = null, ?string $units = null): Weather|WeatherCollection
    {
        $weatherCollection = new \PhpWeather\Common\WeatherCollection();

        if ($units === null) {
            $units = Unit::METRIC;
        }

        if (
            array_key_exists('data', $rawData) &&
            is_array($rawData['data']) &&
            array_key_exists('timelines', $rawData['data']) &&
            is_array($rawData['data']['timelines'])
        ) {
            foreach ($rawData['data']['timelines'] as $timelineRawData) {
                if (!is_array($timelineRawData)) {
                    continue;
                }
                $type = null;
                if (
                    array_key_exists('timestep', $timelineRawData) &&
                    $timelineRawData['timestep'] === 'current'
                ) {
                    $type = Type::CURRENT;
                }
                if (
                    array_key_exists('intervals', $timelineRawData) &&
                    is_array($timelineRawData['intervals'])
                ) {
                    foreach ($timelineRawData['intervals'] as $intervalRawData) {
                        $weather = $this->mapRawIntervalData($intervalRawData, $latitude, $longitude, $units, $type);
                        if ($weather !== null) {
                            $weatherCollection->add($weather);
                        }
                    }
                }
            }
        }

        return $weatherCollection;
    }

    /**
     * @param  array<string, mixed>  $intervalRawData
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  string  $units
     * @param  string|null  $type
     * @return Weather|null
     */
    private function mapRawIntervalData(array $intervalRawData, float $latitude, float $longitude, string $units, ?string $type): ?Weather
    {
        if (!array_key_exists('startTime', $intervalRawData)) {
            return null;
        }

        if (!array_key_exists('values', $intervalRawData)) {
            return null;
        }

        $dateTime = (new DateTime())->setTimezone(new DateTimeZone('UTC'));
        $dateTime->setTimestamp(strtotime($intervalRawData['startTime']));
        $now = (new DateTime())->setTimezone(new DateTimeZone('UTC'));

        $weather = new \PhpWeather\Common\Weather();
        $weather->setLatitude($latitude);
        $weather->setLongitude($longitude);
        $weather->setUtcDateTime($dateTime);
        foreach ($this->getSources() as $source) {
            $weather->addSource($source);
        }

        if ($type !== null) {
            $weather->setType($type);
        } elseif ($dateTime < $now) {
            $weather->setType(Type::HISTORICAL);
        } else {
            $weather->setType(Type::FORECAST);
        }


        if (array_key_exists('temperature', $intervalRawData['values'])) {
            $weather->setTemperature(UnitConverter::mapTemperature($intervalRawData['values']['temperature'], Unit::TEMPERATURE_CELSIUS, $units));
        }
        if (array_key_exists('temperatureApparent', $intervalRawData['values'])) {
            $weather->setFeelsLike(UnitConverter::mapTemperature($intervalRawData['values']['temperatureApparent'], Unit::TEMPERATURE_CELSIUS, $units));
        }
        if (array_key_exists('dewPoint', $intervalRawData['values'])) {
            $weather->setDewPoint(UnitConverter::mapTemperature($intervalRawData['values']['dewPoint'], Unit::TEMPERATURE_CELSIUS, $units));
        }
        if (array_key_exists('humidity', $intervalRawData['values'])) {
            $weather->setHumidity($intervalRawData['values']['humidity']);
        }
        if (array_key_exists('pressureSeaLevel', $intervalRawData['values'])) {
            $weather->setPressure(UnitConverter::mapPressure($intervalRawData['values']['pressureSeaLevel'], Unit::PRESSURE_HPA, $units));
        }
        if (array_key_exists('windSpeed', $intervalRawData['values'])) {
            $weather->setWindSpeed(UnitConverter::mapSpeed($intervalRawData['values']['windSpeed'], Unit::SPEED_MS, $units));
        }
        if (array_key_exists('windDirection', $intervalRawData['values'])) {
            $weather->setWindDirection($intervalRawData['values']['windDirection']);
        }
        if (array_key_exists('precipitationIntensity', $intervalRawData['values'])) {
            $weather->setPrecipitation(UnitConverter::mapPrecipitation($intervalRawData['values']['precipitationIntensity'], Unit::PRECIPITATION_MM, $units));
        }
        if (array_key_exists('precipitationProbability', $intervalRawData['values'])) {
            $weather->setPrecipitationProbability($intervalRawData['values']['precipitationProbability']);
        }
        if (array_key_exists('cloudCover', $intervalRawData['values'])) {
            $weather->setCloudCover($intervalRawData['values']['cloudCover']);
        }

        if (array_key_exists('weatherCode', $intervalRawData['values'])) {
            $weather->setWeathercode($this->mapWeatherCode($intervalRawData['values']['weatherCode']));
            $weather->setIcon($this->mapIcon($intervalRawData['values']['weatherCode'], $dateTime, $latitude, $longitude));
        }

        return $weather;
    }

    public function getSources(): array
    {
        return [
            new Source('tomorrow', 'tomorrow.io', 'https://www.tomorrow.io/'),
        ];
    }

    private function mapWeatherCode(int $weatherCode): ?int
    {
        $weatherCode = (int)substr((string)$weatherCode, 0, 4);

        return match ($weatherCode) {
            1000 => 0,
            1100, 1101, 1103 => 1,
            1102 => 2,
            1001 => 3,
            2100, 2000, 2102, 2103, 2106, 2107, 2108 => 45,
            4000, 4203, 4204, 4205 => 53,
            4200, 4213, 4214, 4215 => 61,
            4001, 4209, 4208, 4210 => 63,
            4201, 4211, 4202, 4212 => 65,
            5108, 5114, 5112, 6201, 6213, 6214, 6215, 6212, 6220, 6222, 6207, 6202, 6208, 7102, 7000, 7101, 7110, 7111, 7112, 7108, 7107, 7109, 7113, 7114, 7116, 7105, 7115, 7117, 7106, 7103 => 67,
            5001, 5100, 5115, 5116, 5117, 51021, 5103, 5104 => 71,
            5000, 5105, 5106, 5107, 5110 => 73,
            5101, 5119, 5120, 5121 => 75,
            5122, 6000, 6200, 6002, 6003, 6004, 6204, 6206 => 56,
            6001, 6205, 6203, 6209 => 66,
            8000, 8001, 8003, 8002 => 95,
            default => null,
        };
    }

    private function mapIcon(int $weatherCode, DateTime $weatherDateTime, float $latitude, float $longitude): ?string
    {
        $isNight = null;
        if (strlen((string)$weatherCode) > 4) {
            $isNight = (substr((string)$weatherCode, 4, 1) === '1');
        }
        if ($isNight === null) {
            $dateSunInfo = date_sun_info($weatherDateTime->getTimestamp(), $latitude, $longitude);
            $isNight = $weatherDateTime->getTimestamp() < $dateSunInfo['sunrise'] || $weatherDateTime->getTimestamp() > $dateSunInfo['sunset'];

        }
        $code = $this->mapWeatherCode($weatherCode);

        return match ($code) {
            0, 1 => $isNight ? 'night-clear' : 'day-sunny',
            2 => $isNight ? 'night-partly-cloudy' : 'day-sunny-overcast',
            3 => $isNight ? 'night-cloudy' : 'day-cloudy',
            45, 48 => $isNight ? 'night-fog' : 'day-fog',
            51, 53, 55, 56, 57 => $isNight ? 'night-sprinkle' : 'day-sprinkle',
            61, 63, 65, 66, 67 => $isNight ? 'night-rain' : 'day-rain',
            71, 73, 75, 77, 85, 86 => $isNight ? 'night-snow' : 'day-snow',
            80, 81, 82 => $isNight ? 'night-showers' : 'day-showers',
            95, 96, 99 => $isNight ? 'night-thunderstorm' : 'day-thunderstorm',
            default => null,
        };
    }
}