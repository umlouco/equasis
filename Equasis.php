<?php

namespace MarioFlores\Equasis;

use MarioFlores\Proxynpm\Proxynpm;
use MarioFlores\Equasis\Database;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use RuntimeException;

class Equasis {

    public $response;
    private $client;
    public $equasis;
    public $path_proxy; 
    public $vessle; 

    public function scrape($mmsi = null, $imo = null) {
        try {
            $this->setGuzzle();
            $this->login();
            if (!is_null($imo)) {
                $this->getByImo($imo);
            } else {
                $this->getByMmsi($mmsi);
                $html = $this->response->getBody()->getContents();
                $imos = $this->getImoFromSearch($html);
                $imo = trim($imos[0]);
            }

            $this->getVessle($imo);
            $this->vessleData($this->response->getBody()->getContents()); 
            
        } catch (Exception $ex) {
            
        }
    }

    public function getVessle($imo) {
        try {
            $this->response = $this->client->request('POST', 'http://www.equasis.org/EquasisWeb/restricted/ShipInfo?fs=Search', [
                'form_params' => [
                    'P_IMO' => $imo
                ]
            ]);
        } catch (RequestException $e) {
            $this->errors[] = Psr7\str($e->getRequest());
        }
        if ($this->response->getStatusCode() != 200) {
            throw new RuntimeException($response->getStatusCode());
        }
    }

    public function getByMmsi($mmsi) {
        try {
            $this->response = $this->client->request('POST', 'http://www.equasis.org/EquasisWeb/restricted/Search?fs=Search', [
                'form_params' => [
                    'P_PAGE' => 1,
                    'P_PAGE_COMP' => 1,
                    'P_PAGE_SHIP' => 1,
                    'ongletActifSC' => 'ship',
                    'P_ENTREE_HOME_HIDDEN' => '',
                    'P_IMO' => '',
                    'P_CALLSIGN' => '',
                    'P_NAME' => '',
                    'P_NAME_cu' => 'on',
                    'P_MMSI' => $mmsi,
                    'P_GT_GT' => '',
                    'P_GT_LT' => '',
                    'P_DW_GT' => '',
                    'P_DW_LT' => '',
                    'P_YB_GT' => '',
                    'P_YB_LT' => '',
                    'P_CLASS_rb' => 'HC',
                    'P_CLASS_ST_rb' => 'HC',
                    'P_FLAG_rb' => 'HC',
                    'P_CatTypeShip_rb' => 'HC',
                    'buttonAdvancedSearch' => 'advancedOk',
                ]
            ]);
        } catch (RequestException $e) {
            $this->errors[] = Psr7\str($e->getRequest());
        }
        if ($this->response->getStatusCode() != 200) {
            throw new RuntimeException($this->response->getStatusCode());
        }
    }

    public function getByImo($imo) {
        try {
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
        } catch (RequestException $e) {
            $this->errors[] = Psr7\str($e->getRequest());
        }
        if ($this->response->getStatusCode() != 200) {
            throw new RuntimeException($this->response->getStatusCode());
        }
    }

    public function setGuzzle() {
        $proxys = new Proxynpm($this->path_proxy);
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
        try {
            $this->client->request('GET', 'http://www.equasis.org/EquasisWeb/public/HomePage?fs=HomePage&P_ACTION=NEW_CONNECTION');
            $response = $this->client->request('POST', 'http://www.equasis.org/EquasisWeb/authen/HomePage?fs=HomePage', [
                'form_params' => [
                    'j_email' => $conta['email'],
                    'j_password' => $conta['passe'],
                    'submit' => 'Login',
                ]
            ]);
        } catch (RequestException $e) {
            $this->errors[] = Psr7\str($e->getRequest());
        }
        if ($response->getStatusCode() != 200) {
            throw new RuntimeException($this->response->getStatusCode());
        }
        return $response->getBody()->getContents();
    }

    function getImoFromSearch($html) {
        $crawler = new Crawler($html);
        $imo = $crawler->filter('#ShipResultId')->each(function($node, $key) {
            return $node->filter('a')->eq(0)->text();
        });
        return $imo; 
    }
    
        function vessleData($html) {
        $this->vessle = array();

        $html = new \Symfony\Component\DomCrawler\Crawler($html);

        $this->vessle['nome'] = trim($html->filter('.color-gris-bleu-copyright > b:nth-child(1)')->text());
        $this->vessle['imo'] = trim($html->filter('.color-gris-bleu-copyright > b:nth-child(2)')->text());
        $this->vessle['bandeira'] = str_replace('(', '', trim($html->filter('.access-body > div:nth-child(1) > div:nth-child(1) > div:nth-child(2) > div:nth-child(4) ')->text()));
        $this->vessle['bandeira'] = str_replace(')', '', $this->vessle['bandeira']);
        $this->vessle['callsign'] = str_replace('(', '', trim($html->filter('.access-body > div:nth-child(1) > div:nth-child(1) > div:nth-child(3) > div:nth-child(2) ')->text()));
        $this->vessle['mmsi'] = str_replace('(', '', trim($html->filter('.access-body > div:nth-child(1) > div:nth-child(1) > div:nth-child(4) > div:nth-child(2) ')->text()));
        $this->vessle['tonnage'] = str_replace('(', '', trim($html->filter('.access-body > div:nth-child(1) > div:nth-child(1) > div:nth-child(5) > div:nth-child(2) ')->text()));
        $this->vessle['dwt'] = str_replace('(', '', trim($html->filter('.access-body > div:nth-child(1) > div:nth-child(1) > div:nth-child(6) > div:nth-child(2) ')->text()));
        $this->vessle['tipo'] = str_replace('(', '', trim($html->filter('.access-body > div:nth-child(1) > div:nth-child(1) > div:nth-child(7) > div:nth-child(2) ')->text()));
        $this->vessle['year'] = str_replace('(', '', trim($html->filter('.access-body > div:nth-child(1) > div:nth-child(1) > div:nth-child(8) > div:nth-child(2) ')->text()));
        $armadores = $html->filter("[name='formShipToComp'] > table tr ");
        if (sizeof($armadores) > 1) {
            $first = true;
            foreach ($armadores as $armador) {
                try {
                    if ($first == false) {
                        $linha = new \Symfony\Component\DomCrawler\Crawler($armador);
                        $this->vessle['armadores'][] = array(
                            'imo' => trim($linha->filter("a")->text()),
                            'tipo' => trim($linha->filter('td:nth-child(2)')->text()),
                            'nome' => trim($linha->filter('td:nth-child(3)')->text()),
                            'morada' => trim($linha->filter('td:nth-child(4)')->text())
                        );
                    }
                } catch (Exception $ex) {
                    echo $ex->getMessage() . "\n";
                }
                $first = false;
            }
        }
        
    }

}
