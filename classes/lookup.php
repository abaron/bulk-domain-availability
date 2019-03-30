<?php
/**
 * Lookup class to get availability and whois
 *
 * @author abaron <github.com/abaron>
 * @since  Mar 30, 2019
 */

require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

use Helge\Loader\JsonLoader;
use Helge\Client\SimpleWhoisClient;
use Helge\Service\DomainAvailability;

class Lookup
{
    public $domain;
    public $servers;
    public $whoisServer;
    public $executeTime = 0;
    public $response = [
        "code"    => 200,
        "status"  => "OK",
        "message" => "",
        "data"    => []
    ];

    public function __construct()
    {
    }

    public function setDomain($domain)
    {
        $this->domain = (string) $domain;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function setservers($servers)
    {
        $this->servers = (string) $servers;
    }

    public function getservers()
    {
        return $this->server;
    }

    public function setWhoisServer($whoisServer)
    {
        $this->whoisServer = (string) $whoisServer;
    }

    public function getWhoisServer()
    {
        return $this->whoisServer;
    }

    /**
     * Availability checker
     * @param  string  $domain domain name with extension
     * @return boolean         true if available
     */
    public function isAvailable($domain)
    {
        $whoisClient = new SimpleWhoisClient();
        $dataLoader  = new JsonLoader($this->servers);

        try {
            $service = new DomainAvailability($whoisClient, $dataLoader);
            $start   = microtime(true);
            $result  = $service->isAvailable($domain);
            $this->executeTime = number_format(microtime(true) - $start, 2);

            return $result;
        } catch (Exception $e) {
        }

        return false;
    }

    /**
     * Get whois data
     * @param  string $domain domain name with extension
     * @return string         whois data
     */
    public function getWhois($domain)
    {
        $whoisClient = new SimpleWhoisClient($this->whoisServer);
        $whoisClient->query($domain);

        return $whoisClient->getResponse();
    }

    /**
     * Request handler in one method
     * @param  array  $post    array of $_POST
     * @param  string $servers path of servers.json
     * @return json            json data result
     */
    public function run($post, $servers = null)
    {
        if (!$this->isAjax()) return false;

        header('Content-type: application/json');

        if (
            empty($servers) ||
            !file_exists($servers) ||
            !self::isValidJson(file_get_contents($servers))
        )
            $this->servers = dirname(dirname(__FILE__)) .
                "/vendor/helgesverre/domain-availability/src/data/servers.json";
        else
            $this->servers = $servers;

        $serverDecoded = json_decode(file_get_contents($this->servers), true);
        $domain = isset($post['domain']) ? trim($post['domain']) : null;

        if (!empty($post['whois'])) {
            if (!empty($domain) && is_string($domain)) {
                if (!self::isValidDomain(trim($domain))) {
                    $this->response = [
                        "code"    => 411,
                        "status"  => "Error",
                        "message" => "Invalid domain name",
                        "data"    => []
                    ];
                } else {
                    $this->whoisServer = empty(self::getExtension($domain)) ? '-' : (
                        isset($serverDecoded[substr(self::getExtension($domain), 1)]['server']) ? $serverDecoded[substr(self::getExtension($domain), 1)]['server'] : '-'
                    );
                    $this->response['data']['domain'] = strtolower($domain);
                    $this->response['data']['whois_data'] = $this->getWhois($domain);
                    $this->response['data']['execute_time'] = $this->executeTime;
                    $this->response['data']['execute_time_unit'] = 's';
                    $this->response['data']['whois_server'] = empty(self::getExtension($domain)) ? '-' : (
                        isset($serverDecoded[substr(self::getExtension($domain), 1)]['server']) ? $serverDecoded[substr(self::getExtension($domain), 1)]['server'] : '-'
                    );
                }
            }
        } else if (!empty($domain) && is_string($domain)) {
            if (!self::isValidDomain(trim($domain))) {
                $this->response = [
                    "code"    => 411,
                    "status"  => "Error",
                    "message" => "Invalid domain name",
                    "data"    => []
                ];
            } else {
                $this->response['data']['domain'] = strtolower($domain);

                if ($this->isAvailable($domain)) {
                    $this->response['data']['is_available'] = true;
                } else {
                    $this->response['data']['is_available'] = false;
                }

                $this->response['data']['execute_time'] = $this->executeTime;
                $this->response['data']['execute_time_unit'] = 's';
                $this->response['data']['whois_server'] = empty(self::getExtension($domain)) ? '-' : (
                    isset($serverDecoded[substr(self::getExtension($domain), 1)]['server']) ? $serverDecoded[substr(self::getExtension($domain), 1)]['server'] : '-'
                );
            }
        } else {
            $this->response = [
                "code"    => 499,
                "status"  => "Error",
                "message" => "Invalid param(s)",
                "data"    => []
            ];
        }

        die(json_encode($this->response));
    }

    /**
     * Generate html options of tld
     * @param  string $servers path of servers.json
     * @return string          options html
     */
    public static function htmlOptions($servers = null)
    {
        if (empty($servers))
            $servers = dirname(dirname(__FILE__)) .
                "/vendor/helgesverre/domain-availability/src/data/servers.json";

        if (self::isValidJson($json = file_get_contents($servers))) {
            $html = '';
            $option = '<option value="%1$s"%2$s>%1$s</option>';
            $serverDecoded = json_decode($json, true);
            ksort($serverDecoded);
            self::moveToTop($serverDecoded, 'com');
            self::moveToTop($serverDecoded, 'net');
            self::moveToTop($serverDecoded, 'org');

            foreach ($serverDecoded as $k => $v) {
                if (in_array($k, ['com', 'net', 'org']))
                    $html = sprintf($option, $k, ' selected="selected"') . $html;
                else
                    $html .= sprintf($option, $k, '');
            }

            return sprintf($option, '-- Choose Extensions --', 'disabled="disabled"') . $html;
        }

        return '';
    }

    /**
     * Parse domain to get extension
     * @param  string $domain domain name with extension
     * @return string         tld / extension of domain
     */
    public static function getExtension($domain)
    {
        $host = parse_url('http://' . $domain);
        preg_match('/(.*?)((\.co)?.[a-z]{2,4})$/i', $host['host'], $m);

        return isset($m[2]) ? $m[2] : '';
    }

    /**
     * Validate string json
     * @param  string  $data string json
     * @return boolean       true if valid
     */
    public static function isValidJson($data = null)
    {
        if (!empty($data)) {
                @json_decode($data);
                return (json_last_error() === JSON_ERROR_NONE);
        }

        return false;
    }

    /**
     * Domain name validator
     * @param  string  $domain domain name with extension
     * @return boolean         true if valid
     */
    public static function isValidDomain($domain)
    {
        $re = '/^(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{2,63}$/m';

        return preg_match($re, $domain);
    }

    /**
     * Move array element to top of array by key
     * @param  array &$array array data
     * @param  mixed $key    string or integer key
     * @return pointer       return $array
     */
    public static function moveToTop(&$array, $key) {
        $temp = array($key => $array[$key]);
        unset($array[$key]);
        $array = $temp + $array;
    }

    /**
     * Ajax request validator
     * @return boolean true if ajax request
     */
    public static function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}
