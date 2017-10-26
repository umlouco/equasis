<?php

namespace MarioFlores\Equasis;

use MarioFlores\Proxynpm\Proxynpm;
use MarioFlores\Equasis\Database;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class Equasis {

    public $response;
    private $client;
    public $equasis;

    public function scrape($mmsi = null, $imo = null) {
        $this->setGuzzle();
        $this->login();
        if (!is_null($imo)) {
            $this->getByImo($imo);
        }
    }

    public function getByMmsi($mmsi) {
        $response = $client->request('POST', 'http://www.equasis.org/EquasisWeb/restricted/ShipList?fs=ShipSearch', [
            'form_params' => [
                'P_PAGE' => '1',
                'P_IMO' => '',
                'P_CALLSIGN' => '',
                'P_NAME' => '',
                'P_MMSI' => $mmsi,
                'Submit' => 'SEARCH'
            ]
        ]);
    }

    public function getByImo($imo) {
        $this->response = $this->client->request('POST', 'http://www.equasis.org/EquasisWeb/restricted/Search?fs=Search', [
            'form_params' => [
                'P_PAGE' => '1',
                'P_PAGE_COMP' => '1',
                'P_PAGE_SHIP' => '1',
                'ongletActifSC' => 'ship',
                'P_ENTREE_HOME_HIDDEN' => $imo,
                'P_ENTREE' => $imo,
                'checkbox-shipSearch' => 'Ship',
                'Submit' => 'SEARCH'
            ]
        ]);
        if ($this->response->getStatusCode() != 200) {
            throw new RuntimeException($this->response->getStatusCode());
        }
    }

    public function setGuzzle() {
        $proxys = new Proxynpm;
        $proxy = $proxys->getProxy();
        $this->setHeaders();
        $this->client = new Client([
            'headers' => $this->setHeaders(),
            'timeout' => 60,
            'cookies' => new \GuzzleHttp\Cookie\CookieJar,
            'http_errors' => false,
            'allow_redirects' => true,
            'proxy' => 'tcp://' . $proxy['ip'] . ':' . $proxy['port']
        ]);
    }

    private function setHeaders() {
        return [
            'User-Agent' => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:47.0) Gecko/20100101 Firefox/47.0",
            'Accept-Language' => "en-US,en;q=0.5"
        ];
    }

    public function login() {
        $conta = Database::orderByRaw("RAND()")->first();

        $this->client->request('GET', 'http://www.equasis.org/EquasisWeb/public/HomePage?fs=HomePage&P_ACTION=NEW_CONNECTION');
        $response = $this->client->request('POST', 'http://www.equasis.org/EquasisWeb/authen/HomePage?fs=HomePage', [
            'form_params' => [
                'j_email' => $conta['email'],
                'j_password' => $conta['passe'],
                'submit' => 'Login',
            ]
        ]);
        if ($response->getStatusCode() != 200) {
            throw new RuntimeException($this->response->getStatusCode());
        }
        return $response->getBody()->getContents();
    }

}
