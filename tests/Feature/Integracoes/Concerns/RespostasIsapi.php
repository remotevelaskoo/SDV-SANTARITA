<?php

namespace Tests\Feature\Integracoes\Concerns;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Respostas no formato observado no DS-K1T673DX-BR em bancada (docs/016),
 * com valores sintéticos: nenhum IP, série ou certificado real.
 */
trait RespostasIsapi
{
    /** @param  array<string, mixed>  $sobrescrever */
    protected function fingirTerminal(string $firmware, array $sobrescrever = []): void
    {
        // Fábrica nova a cada cenário: no Http::fake o primeiro stub registrado vence.
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(array_merge([
            '*/ISAPI/System/deviceInfo' => Http::response($this->xmlDeviceInfo($firmware), 200, ['Content-Type' => 'application/xml']),
            '*/ISAPI/AccessControl/capabilities' => Http::response($this->xmlCapacidadesAcesso(), 200, ['Content-Type' => 'application/xml']),
            '*/ISAPI/Streaming/channels' => Http::response($this->xmlCanais(), 200, ['Content-Type' => 'application/xml']),
            '*/ISAPI/Streaming/channels/101/picture' => Http::response("\xFF\xD8\xFF\xE0".str_repeat('x', 64), 200, ['Content-Type' => 'image/jpeg']),
            '*/ISAPI/AccessControl/RemoteControl/door/1' => Http::response($this->xmlStatus(1, 'ok'), 200, ['Content-Type' => 'application/xml']),
            '*/ISAPI/System/time' => fn () => Http::response($this->xmlHora(), 200, ['Content-Type' => 'application/xml']),
            '*/ISAPI/AccessControl/UserInfo/SetUp*' => Http::response($this->jsonStatus(1), 200),
            '*/ISAPI/AccessControl/UserInfo/Delete*' => Http::response($this->jsonStatus(1), 200),
            '*/ISAPI/AccessControl/UserInfo/Search*' => Http::response(['UserInfoSearch' => ['responseStatusStrg' => 'NO MATCH', 'numOfMatches' => 0, 'totalMatches' => 0]], 200),
        ], $sobrescrever));
    }

    protected function xmlDeviceInfo(string $firmware, ?string $build = null): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><DeviceInfo version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<deviceName>subdoorOne</deviceName><model>DS-K1T673DX-BR</model><serialNumber>SERIE-SINTETICA-01</serialNumber>'
            ."<firmwareVersion>{$firmware}</firmwareVersion>"
            .($build === null ? '' : "<firmwareReleasedDate>{$build}</firmwareReleasedDate>")
            .'<deviceType>ACS</deviceType></DeviceInfo>';
    }

    protected function xmlCapacidadesAcesso(bool $porta = true): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><AccessControl version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<isSupportRemoteControlDoor>'.($porta ? 'true' : 'false').'</isSupportRemoteControlDoor>'
            .'<isSupportUserInfo>true</isSupportUserInfo><isSupportUserInfoDetailDelete>true</isSupportUserInfoDetailDelete>'
            .'<isSupportFDLib>true</isSupportFDLib><isSupportAcsEvent>true</isSupportAcsEvent></AccessControl>';
    }

    protected function xmlCanais(): string
    {
        $canal = fn (int $id, string $habilitado) => "<StreamingChannel version=\"2.0\"><id>{$id}</id><channelName>CANAL</channelName>"
            ."<enabled>{$habilitado}</enabled><Video><enabled>true</enabled><videoInputChannelID>1</videoInputChannelID></Video></StreamingChannel>";

        return '<?xml version="1.0" encoding="UTF-8"?><StreamingChannelList version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .$canal(100, 'false').$canal(101, 'true').$canal(102, 'true').'</StreamingChannelList>';
    }

    /** Hora do terminal no formato observado em bancada; padrão: agora, no fuso da aplicação. */
    protected function xmlHora(?\DateTimeImmutable $hora = null): string
    {
        $hora ??= new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone')));

        return '<?xml version="1.0" encoding="UTF-8"?><Time version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<timeMode>manual</timeMode><localTime>'.$hora->format(DATE_ATOM).'</localTime><timeZone>CST+3:00:00</timeZone></Time>';
    }

    /** @return array<string, mixed> */
    protected function jsonStatus(int $codigo, string $sub = 'ok'): array
    {
        return ['statusCode' => $codigo, 'statusString' => $codigo === 1 ? 'OK' : 'Invalid Content', 'subStatusCode' => $sub];
    }

    protected function xmlStatus(int $codigo, string $sub): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><ResponseStatus version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            ."<requestURL>/ISAPI/AccessControl/RemoteControl/door/1</requestURL><statusCode>{$codigo}</statusCode>"
            ."<statusString>OK</statusString><subStatusCode>{$sub}</subStatusCode></ResponseStatus>";
    }
}
