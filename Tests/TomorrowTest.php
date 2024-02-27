<?php
declare(strict_types=1);

namespace PhpWeather\Provider\Tomorrow;

use Http\Client\HttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpWeather\Common\WeatherQuery;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class TomorrowTest extends TestCase
{
    private MockObject|HttpClient $client;
    private MockObject|RequestFactoryInterface $requestFactory;
    private Tomorrow $provider;

    public function setUp(): void
    {
        $this->client = $this->createMock(HttpClient::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);

        $this->provider = new Tomorrow($this->client, 'key', $this->requestFactory);
    }

    public function testCurrentWeather(): void
    {
        $latitude = 47.8739259;
        $longitude = 8.0043961;
        $datetime = (new \DateTime())->setTimezone(new \DateTimeZone('UTC'))->setDate(2022, 07, 31)->setTime(16, 00);
        $testQuery = WeatherQuery::create($latitude, $longitude, $datetime);
        $testString = 'https://api.tomorrow.io/v4/timelines?location=47.8739259,8.0043961&fields=cloudCover&fields=dewPoint&fields=temperatureApparent&fields=humidity&fields=precipitationIntensity&fields=precipitationProbability&fields=pressureSeaLevel&fields=temperature&fields=weatherCode&fields=windSpeed&fields=windDirection&units=metric&timesteps=current&apikey=key';

        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->expects(self::once())->method('createRequest')->with('GET', $testString)->willReturn($request);

        $responseBodyString = file_get_contents(__DIR__.'/resources/currentWeather.json');
        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn($responseBodyString);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($body);
        $this->client->expects(self::once())->method('sendRequest')->with($request)->willReturn($response);

        $currentWeather = $this->provider->getCurrentWeather($testQuery);
        self::assertSame($latitude, $currentWeather->getLatitude());
        self::assertSame(20.5, $currentWeather->getTemperature());
        self::assertSame('day-cloudy', $currentWeather->getIcon());
        self::assertCount(1, $currentWeather->getSources());

    }
}