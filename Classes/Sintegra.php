<?php

namespace Classes;

/**
 *
 */
class Sintegra
{
    /**
     * @var string
     */
    protected static $cookieFile = __DIR__."/../cookiejar";

    /**
     * @var string
     */
    protected static $url = "www.sintegra.fazenda.pr.gov.br";

    /**
     * @return void
     */
    public static function consultCNPJ()
    {
        self::clearCookies();
        self::generateCaptcha();

        $captcha = readline("Captcha: ");
        $cnpj = readline("CNPJ: ");

        $response = self::retrieveInfo(self::$url."/sintegra/", [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                '_method' => 'POST',
                'data[Sintegra1][CodImage]' => $captcha,
                'data[Sintegra1][Cnpj]' => $cnpj,
                'empresa' => 'Consultar Empresa'
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST
        ]);

        $data[] = self::prepareData($response);

        self::otherIE($response, $data);

        print_r($data);

        self::clearCookies();
        exit();
    }

    /**
     * @param $html
     * @param $data
     * @return array|mixed
     */
    private static function otherIE($html, &$data = [])
    {
        $dom = (@\DOMDocument::loadHTML($html));
        if(!($formInfo = $dom->getElementById("Sintegra1CampoAnterior")) || is_null($dom->getElementById("consultar"))) {
            return $data;
        }

        $response = self::retrieveInfo(self::$url."/sintegra/sintegra1/consultar", [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                '_method' => 'POST',
                'data[Sintegra1][campoAnterior]' => $formInfo->getAttribute('value'),
                'consultar' => ''
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST
        ]);

        $data[] = self::prepareData($response);

        return self::otherIE($response, $data);
    }

    /**
     * @return array
     */
    private static function generateCaptcha()
    {
        self::retrieveInfo(self::$url);
        $response = self::retrieveInfo(self::$url."/sintegra/captcha?".self::random(2));

        self::saveCaptcha($response);
    }

    /**
     * @param $img
     * @param $name
     * @return void
     */
    private static function saveCaptcha($img, $name = 'captcha.jpeg')
    {
        if(file_exists($name)) {
            unlink($name);
        }

        $fp = fopen($name,'x');
        fwrite($fp, $img);
        fclose($fp);
    }

    /**
     * @param $multiplier
     * @return float
     */
    private static function random($multiplier = 1)
    {
        return ((float)rand() / (float)getrandmax()) * $multiplier;
    }

    /**
     * @return void
     */
    private static function clearCookies()
    {
        if(file_exists(self::$cookieFile)) {
            unlink(self::$cookieFile);
        }
    }

    /**
     * @param $url
     * @param $options
     * @return bool|string
     */
    private static function retrieveInfo($url, $options = [])
    {
        $curl = curl_init($url);
        $defaultOptions = array(
            CURLOPT_HTTPHEADER => [
                "Pragma: no-cache",
                "Origin: ".self::$url,
                "Host: ".self::$url,
                "User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0",
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Accept-Language: pt-BR,pt;q=0.8,en-US;q=0.5,en;q=0.3",
                "Referer: ".self::$url."/sintegra/sintegra1",
                "Connection: keep-alive"
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_COOKIEFILE => self::$cookieFile,
            CURLOPT_COOKIEJAR => self::$cookieFile
        );

        $defaultOptions = array_replace($defaultOptions, $options);
        curl_setopt_array($curl, $defaultOptions);
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    /**
     * @param $html
     * @return array
     */
    private static function prepareData($html)
    {
        $data = [];
        $dom = (@\DOMDocument::loadHTML($html));
        $finder = new \DOMXPath($dom);
        $tds = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' form_label ')]");

        foreach ($tds as $td) {
            $name = trim(str_replace(":", "", $td->nodeValue));
            if($name != "SPED (EFD, NF-e, CT-e)")
            $data[$name] = trim($td->nextSibling->nextSibling->nodeValue);
        }

        return $data;
    }
}