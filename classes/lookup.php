<?php
/**
 * Lookup class to get availability and whois data
 * Supports standard WHOIS sockets and modern RDAP protocols
 *
 * @author abaron <github.com/abaron>
 * @since Mar 30, 2019
 */

require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

class Lookup
{
    public $domain;
    public $servers;
    public $serverData = [];
    public $whoisServer;
    public $executeTime = 0;
    public $response = [
        "code"    => 200,
        "status"  => "OK",
        "message" => "",
        "data"    => []
    ];

    /**
     * Default constructor
     */
    public function __construct()
    {
    }

    /**
     * Set target domain name
     * @param string $domain target domain
     * @return void
     */
    public function setDomain($domain)
    {
        $this->domain = (string) $domain;
    }

    /**
     * Get target domain name
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set path to servers configuration file
     * @param string $servers configuration file path
     * @return void
     */
    public function setservers($servers)
    {
        $this->servers = (string) $servers;
        $this->serverData = [];
    }

    /**
     * Get path to servers configuration file
     * @return string
     */
    public function getservers()
    {
        return $this->servers;
    }

    /**
     * Set whois server address
     * @param string $whoisServer whois server host
     * @return void
     */
    public function setWhoisServer($whoisServer)
    {
        $this->whoisServer = (string) $whoisServer;
    }

    /**
     * Get whois server address
     * @return string
     */
    public function getWhoisServer()
    {
        return $this->whoisServer;
    }

    /**
     * Load server configuration data from file
     * @return array decoded configuration array
     */
    public function loadServers()
    {
        if (!empty($this->serverData)) {
            return $this->serverData;
        }

        $filePath = !empty($this->servers) && file_exists($this->servers)
            ? $this->servers
            : dirname(dirname(__FILE__)) . '/misc/servers/servers.json';

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if (self::isValidJson($content)) {
                $this->serverData = json_decode($content, true) ?: [];
                return $this->serverData;
            }
        }

        return [];
    }

    /**
     * Resolve server configuration for a given domain name
     * Checks exact multi-level suffix first, then parent TLD, falling back to RDAP
     * @param string $domain target domain name
     * @return array resolved server configuration
     */
    public function resolveConfig($domain)
    {
        $servers = $this->loadServers();
        $domain = strtolower(trim((string)$domain));
        $parts = explode('.', $domain);

        // Check suffix from longest match (e.g. ai.id) to shortest (e.g. id)
        for ($i = 1; $i < count($parts); $i++) {
            $candidate = implode('.', array_slice($parts, $i));
            if (isset($servers[$candidate])) {
                return $servers[$candidate];
            }
        }

        // Global fallback to RDAP redirector
        return [
            'protocol' => 'rdap',
            'server' => 'https://rdap.org/domain/',
            'not_found' => 'not found'
        ];
    }

    /**
     * Check domain availability using WHOIS or RDAP
     * @param string $domain domain name with extension
     * @return boolean true if domain is available for registration
     */
    public function isAvailable($domain)
    {
        $domain = strtolower(trim((string)$domain));
        $config = $this->resolveConfig($domain);
        $protocol = $config['protocol'] ?? 'whois';
        $start = microtime(true);
        $isAvailable = false;

        if ($protocol === 'rdap' || str_starts_with($config['server'] ?? '', 'http')) {
            $url = rtrim($config['server'], '/') . '/' . urlencode($domain);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BulkDomainAvailability/1.0');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 404) {
                $isAvailable = true;
            } else if ($httpCode === 200) {
                $isAvailable = false;
            } else {
                // If RDAP returns unexpected status, check if response payload indicates not found
                if ($response && stripos($response, 'not found') !== false) {
                    $isAvailable = true;
                }
            }
        } else {
            // Standard Port 43 WHOIS socket connection
            $serverHost = $config['server'] ?? '';
            $port = $config['port'] ?? 43;
            $fp = !empty($serverHost) ? @fsockopen($serverHost, $port, $errno, $errstr, 4) : false;

            if ($fp) {
                stream_set_timeout($fp, 4);
                fwrite($fp, $domain . "\r\n");
                $response = '';
                while (!feof($fp)) {
                    $response .= fgets($fp, 128);
                }
                fclose($fp);

                $notFoundPattern = $config['not_found'] ?? 'NOT FOUND';
                if (!empty($notFoundPattern) && stripos($response, $notFoundPattern) !== false) {
                    $isAvailable = true;
                } else {
                    $genericNotFound = [
                        'not found',
                        'no match for',
                        'domain not found',
                        'no object found',
                        'status: free',
                        'is available',
                        'nothing found',
                        'no entries found'
                    ];
                    foreach ($genericNotFound as $pattern) {
                        if (stripos($response, $pattern) !== false) {
                            $isAvailable = true;
                            break;
                        }
                    }
                }
            } else {
                // Socket connection failed; attempt RDAP fallback
                $url = 'https://rdap.org/domain/' . urlencode($domain);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_USERAGENT, 'BulkDomainAvailability/1.0');
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($httpCode === 404) {
                    $isAvailable = true;
                } else if ($httpCode === 200) {
                    $isAvailable = false;
                }
            }
        }

        $this->executeTime = number_format(microtime(true) - $start, 2);
        return $isAvailable;
    }

    /**
     * Get WHOIS data for a domain name
     * @param string $domain domain name with extension
     * @return string raw WHOIS text or formatted RDAP data
     */
    public function getWhois($domain)
    {
        $domain = strtolower(trim((string)$domain));
        $config = $this->resolveConfig($domain);
        $protocol = $config['protocol'] ?? 'whois';
        $start = microtime(true);

        if ($protocol === 'rdap' || str_starts_with($config['server'] ?? '', 'http')) {
            $url = rtrim($config['server'], '/') . '/' . urlencode($domain);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BulkDomainAvailability/1.0');
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 200 && $response) {
                $rdapJson = json_decode($response, true);
                if (is_array($rdapJson)) {
                    $this->executeTime = number_format(microtime(true) - $start, 2);
                    return self::formatRdapToWhois($rdapJson, $config['server']);
                }
            }

            $this->executeTime = number_format(microtime(true) - $start, 2);
            return 'No WHOIS data returned for this domain (HTTP ' . $httpCode . ').';
        }

        $serverHost = !empty($this->whoisServer) && $this->whoisServer !== '-'
            ? $this->whoisServer
            : ($config['server'] ?? '');
        $port = $config['port'] ?? 43;

        $fp = !empty($serverHost) ? @fsockopen($serverHost, $port, $errno, $errstr, 4) : false;
        if ($fp) {
            stream_set_timeout($fp, 4);
            fwrite($fp, $domain . "\r\n");
            $response = '';
            while (!feof($fp)) {
                $response .= fgets($fp, 128);
            }
            fclose($fp);
            $this->executeTime = number_format(microtime(true) - $start, 2);
            return !empty($response) ? $response : 'No WHOIS data returned for this domain.';
        }

        // Socket failed; attempt RDAP fallback for whois inspection
        $url = 'https://rdap.org/domain/' . urlencode($domain);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BulkDomainAvailability/1.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->executeTime = number_format(microtime(true) - $start, 2);
        if ($httpCode === 200 && $response) {
            $rdapJson = json_decode($response, true);
            if (is_array($rdapJson)) {
                return self::formatRdapToWhois($rdapJson, 'https://rdap.org/domain/');
            }
        }

        return 'Could not retrieve WHOIS data. Server connection timed out.';
    }

    /**
     * Format raw RDAP JSON payload into structured WHOIS-like text
     * @param array $data decoded RDAP JSON response
     * @param string $serverUrl source RDAP server URL
     * @return string human-readable WHOIS representation
     */
    public static function formatRdapToWhois($data, $serverUrl = '')
    {
        $lines = [];
        $domainName = !empty($data['ldhName']) ? $data['ldhName'] : (!empty($data['handle']) ? $data['handle'] : '');
        $lines[] = 'Domain Name: ' . strtoupper($domainName);
        if (!empty($data['handle'])) {
            $lines[] = 'Registry Domain ID: ' . $data['handle'];
        }
        if (!empty($data['status'])) {
            $lines[] = 'Domain Status: ' . implode(', ', (array)$data['status']);
        }
        if (!empty($data['events'])) {
            foreach ($data['events'] as $event) {
                $action = ucwords(str_replace('_', ' ', $event['eventAction'] ?? 'Event'));
                $date = $event['eventDate'] ?? '-';
                $lines[] = $action . ' Date: ' . $date;
            }
        }
        if (!empty($data['nameservers'])) {
            foreach ($data['nameservers'] as $ns) {
                if (!empty($ns['ldhName'])) {
                    $lines[] = 'Name Server: ' . strtoupper($ns['ldhName']);
                }
            }
        }
        if (!empty($data['entities'])) {
            foreach ($data['entities'] as $entity) {
                $roles = !empty($entity['roles']) ? implode(', ', $entity['roles']) : 'Entity';
                $name = '';
                if (!empty($entity['vcardArray'][1])) {
                    foreach ($entity['vcardArray'][1] as $vcardItem) {
                        if ($vcardItem[0] === 'fn' && !empty($vcardItem[3])) {
                            $name = $vcardItem[3];
                            break;
                        }
                    }
                }
                $lines[] = ucwords($roles) . ': ' . ($name ?: ($entity['handle'] ?? '-'));
            }
        }
        $lines[] = '';
        $lines[] = '>>> Registration protocol: RDAP (ICANN RFC 7483/RFC 9083) <<<';
        if (!empty($serverUrl)) {
            $lines[] = '>>> Source: ' . $serverUrl . ' <<<';
        }

        return implode("\n", $lines);
    }

    /**
     * Request handler for AJAX calls
     * @param array $post array of $_POST variables
     * @param string $servers path of servers.json configuration
     * @return void
     */
    public function run($post, $servers = null)
    {
        if (!$this->isAjax()) return false;

        header('Content-type: application/json');

        if (!empty($servers) && file_exists($servers)) {
            $this->servers = $servers;
        } else {
            $this->servers = dirname(dirname(__FILE__)) . '/misc/servers/servers.json';
        }

        $domain = isset($post['domain']) ? trim($post['domain']) : null;
        if (!empty($domain) && is_string($domain) && function_exists('idn_to_ascii')) {
            $asciiDomain = @idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($asciiDomain) {
                $domain = $asciiDomain;
            }
        }

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
                    $config = $this->resolveConfig($domain);
                    $serverDisplay = !empty($config['server']) ? $config['server'] : '-';
                    $this->whoisServer = $serverDisplay;
                    $this->response['data']['domain'] = strtolower($domain);
                    $this->response['data']['whois_data'] = $this->getWhois($domain);
                    $this->response['data']['execute_time'] = $this->executeTime;
                    $this->response['data']['execute_time_unit'] = 's';
                    $this->response['data']['whois_server'] = $serverDisplay;
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
                $config = $this->resolveConfig($domain);
                $serverDisplay = !empty($config['server']) ? $config['server'] : '-';
                $this->response['data']['domain'] = strtolower($domain);
                $this->response['data']['is_available'] = $this->isAvailable($domain);
                $this->response['data']['execute_time'] = $this->executeTime;
                $this->response['data']['execute_time_unit'] = 's';
                $this->response['data']['whois_server'] = $serverDisplay;
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
     * Generate HTML options of domain extensions
     * @param string $servers path of servers.json configuration
     * @return string options html
     */
    public static function htmlOptions($servers = null)
    {
        if (empty($servers) || !file_exists($servers)) {
            $servers = dirname(dirname(__FILE__)) . '/misc/servers/servers.json';
        }

        if (self::isValidJson($json = file_get_contents($servers))) {
            $html = '';
            $serverDecoded = json_decode($json, true) ?: [];
            ksort($serverDecoded);
            self::moveToTop($serverDecoded, 'org');
            self::moveToTop($serverDecoded, 'net');
            self::moveToTop($serverDecoded, 'com');

            foreach ($serverDecoded as $k => $v) {
                $unicode = $k;
                if (str_starts_with($k, 'xn--') && function_exists('idn_to_utf8')) {
                    $utf = @idn_to_utf8($k, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                    if ($utf && $utf !== $k) {
                        $unicode = $utf;
                    }
                }
                $label = ($unicode !== $k) ? ('.' . $unicode . ' (.' . $k . ')') : ('.' . $k);
                $selected = in_array($k, ['com', 'net', 'org']) ? ' selected="selected"' : '';
                $html .= sprintf(
                    '<option value="%s"%s data-unicode="%s" data-label="%s">%s</option>',
                    htmlspecialchars($k),
                    $selected,
                    htmlspecialchars($unicode),
                    htmlspecialchars($label),
                    htmlspecialchars($label)
                );
            }

            return sprintf('<option value="" disabled="disabled">%s</option>', '-- Choose Extensions --') . $html;
        }

        return '';
    }

    /**
     * Parse domain to get extension/suffix accurately
     * Uses Public Suffix List rules with fallback to server keys and dots
     * @param string $domain domain name with extension
     * @param array $servers optional map of available server extensions
     * @return string domain suffix starting with a dot
     */
    public static function getExtension($domain, $servers = null)
    {
        $domain = strtolower(trim((string)$domain));
        if (strpos($domain, '.') === false) return '';

        try {
            static $rules = null;
            if ($rules === null) {
                $manager = new \Pdp\Manager(new \Pdp\Cache(), new \Pdp\CurlHttpClient());
                $rules = $manager->getRules();
            }
            $resolved = $rules->resolve($domain);
            $suffix = $resolved->getPublicSuffix();
            if (!empty($suffix)) {
                return '.' . $suffix;
            }
        } catch (\Exception $e) {
        }

        if (is_array($servers)) {
            $parts = explode('.', $domain);
            for ($i = 1; $i < count($parts); $i++) {
                $candidate = implode('.', array_slice($parts, $i));
                if (isset($servers[$candidate])) {
                    return '.' . $candidate;
                }
            }
        }

        return substr($domain, strrpos($domain, '.'));
    }

    /**
     * Validate string JSON
     * @param string $data string json
     * @return boolean true if valid JSON string
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
     * @param string $domain domain name with extension
     * @return boolean true if valid domain syntax
     */
    public static function isValidDomain($domain)
    {
        $re = '/^(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{2,63}$/m';

        return (bool) preg_match($re, $domain);
    }

    /**
     * Move array element to top of array by key
     * @param array &$array array data
     * @param mixed $key string or integer key
     * @return void
     */
    public static function moveToTop(&$array, $key)
    {
        if (isset($array[$key])) {
            $temp = array($key => $array[$key]);
            unset($array[$key]);
            $array = $temp + $array;
        }
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
