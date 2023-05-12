<?php
namespace chenjia404\PhpIpfsApi;

use Exception;
/**
 * Class IPFS
 *
 * @package rannmann\PhpIpfsApi
 */
class IPFS
{
    /**
     * @var string
     */
    private $gatewayHost;
    /**
     * @var string
     */
    private $gatewayApiHost;
    /**
     * @var \CurlHandle
     */
    private $curl;

    protected $curlTimeout = 5;

    const ERROR_BAD_PROGRAMMER = 1;
    const ERROR_EMPTY_RESPONSE = 2;

    /**
     * IPFS constructor.
     *
     * @param string $host
     * @param int    $port
     * @param int    $apiPort
     */
    function __construct($gatewayHost = "http://localhost:8080", $gatewayApiHost = "http://localhost:5001")
    {
        $this->gatewayHost = $gatewayHost;
        $this->gatewayApiHost = $gatewayApiHost;
    }

    /**
     * Gets the base url for all API calls, no trailing slash.
     *
     * @return string
     */
    private function getApiUrl(): string
    {
        return "{$this->gatewayApiHost}/api/v0";
    }

    /**
     * Retrieves the contents of a single hash
     *
     * @param  string $hash
     * @return string
     * @throws Exception
     */
    public function cat($hash,array $params = [])
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/cat",'',$params)
        );

        return $response;
    }

    /**
     * Retrieves the contents of a single hash
     *
     * @param  string $hash
     * @return string
     * @throws Exception
     */
    public function get($hash)
    {
        return $this->curl($this->gatewayHost . "/ipfs/$hash");
    }

    /**
     * Adds content to IPFS.
     *
     * @param string $content
     * @param array $params
     * @link https://docs.ipfs.tech/reference/kubo/rpc/#api-v0-add Available Parameters
     * @return string|null
     * @throws Exception
     */
    public function add(string $content, array $params = [])
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/add", $content, $params)
        );

        return $response;
    }

    /**
     * @param  string $filePath
     * @param  array  $params
     * @link https://docs.ipfs.tech/reference/kubo/rpc/#api-v0-add Available Parameters
     * @return mixed|null
     * @throws Exception
     */
    public function addFromPath(string $filePath, array $params = [])
    {
        $response = $this->safeDecode(
            $this->curlFile($this->gatewayApiHost . "/add", $filePath, $params)
        );

        return $response;
    }

    /**
     * @param string $fileUrl
     * @param array $params
     * @return mixed|null
     * @throws Exception
     */
    public function addFromUrl(string $fileUrl, array $params = [])
    {
        $fileContents = file_get_contents($fileUrl);
        if ($fileContents === false) {
            throw new Exception("File content unable to be retrieved");
        }
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/add", $fileContents, $params)
        );

        return $response;
    }

    /**
     * Returns the node structure of a hash
     *
     * @param  string $hash
     * @return array|null
     * @throws Exception
     */
    public function ls($hash)
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/ls/$hash")
        );

        return $response;
    }

    /**
     * @param  string $hash
     * @return int|null
     * @throws Exception
     */
    public function size($hash): ?int
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/object/stat/$hash")
        );

        return $response;
    }

    /**
     * Pin a hash
     *
     * @param  string $hash
     * @return array|null   Array with a list of all pinned items
     * @throws Exception
     */
    public function pinAdd($hash): ?array
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/pin/add/$hash")
        );

        return $response;
    }

    /**
     * Unpin a hash
     *
     * @param  string $hash
     * @return array|null
     * @throws Exception
     */
    public function pinRm($hash): ?array
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/pin/rm/$hash")
        );


        return $response;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function version()
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/version")
        );

        return $response;
    }

    /**
     * Show IPFS node id info
     *
     * @link https://docs.ipfs.tech/reference/kubo/rpc/#api-v0-id
     * @return array|null
     * @throws Exception
     */
    public function id(): ?array
    {
        $response = $this->safeDecode(
            $this->curl($this->getApiUrl() . "/id")
        );
        return $response;
    }


    /**
     * @param  $input
     * @return array|null
     */
    private function safeDecode($input): ?array
    {
        if ($input === null || $input === false) {
            return null;
        }
        return json_decode($input, true);
    }

    private function resetCurl()
    {
        if (empty($this->curl)) {
            $this->curl = curl_init();
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->getCurlTimeout());
            curl_setopt($this->curl, CURLOPT_HEADER, 0);
            curl_setopt($this->curl, CURLOPT_BINARYTRANSFER, 1);
        }
        // Shared resets
        curl_setopt($this->curl, CURLOPT_POST, 0); // We'll set this to 1 if we actually post data.
    }

    /**
     * Sets up CURL to send raw data as the IPFS file
     *
     * @param string $data
     */
    private function setCurlData(string $data): void
    {
        $boundary = "a831rwxi1a3gzaorw1w2z49dlsor";
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array("Content-Type: multipart/form-data; boundary=$boundary"));
        curl_setopt($this->curl, CURLOPT_POST, 1);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, "--$boundary\r\nContent-Type: application/octet-stream\r\nContent-Disposition: file; \r\n\r\n" . $data . "\r\n--$boundary\r\n");
    }

    /**
     * Sets up CURL to send the file data from source as IPFS file
     *
     * @param string $filePath
     */
    private function setCurlFile(string $filePath): void
    {
        curl_setopt($this->curl, CURLOPT_POST, 1);
        $cfile = curl_file_create(realpath($filePath), 'application/octet-stream', basename($filePath));
        $postFields = ['file' => $cfile];
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postFields);
    }

    /**
     * @param  string      $url
     * @param  string|null $data
     * @param  string|null $filePath
     * @param  array       $params   GET parameters
     * @return string
     * @throws Exception
     */
    private function executeCurl(string $url, ?string $data = null, ?string $filePath = null, $params = []): string
    {
        if ($data && $filePath) {
            throw new Exception(
                "Cannot send both POST data and a file at the same time",
                self::ERROR_BAD_PROGRAMMER
            );
        }

        $queryString = $params ? '?' . http_build_query($params) : '';
        curl_setopt($this->curl, CURLOPT_URL, $url . $queryString);

        if ($data) {
            $this->setCurlData($data);
        } elseif ($filePath) {
            $this->setCurlFile($filePath);
        }

        // See what IPFS says
        $output = curl_exec($this->curl);

        // Store this for later
        $responseCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

        // Free up resources
        curl_close($this->curl);
        $this->curl = null;

        $this->handleCurlResponse($output, $responseCode);

        return $output;
    }

    /**
     * @param $output
     * @param $responseCode
     * @throws Exception
     */
    private function handleCurlResponse($output, $responseCode): void
    {
        // Handle any 400s or 500s
        if ($responseCode >= 400 && $responseCode < 600) {
            $data = @json_decode($output, true);
            if (!$data AND json_last_error() != JSON_ERROR_NONE) {
                throw new Exception(
                    "IPFS returned response code $responseCode: " . substr($output, 0, 200),
                    $responseCode
                );
            }
            if (is_array($data)) {
                if (isset($data['Code']) && isset($data['Message'])) {
                    throw new Exception("IPFS Error {$data['Code']}: {$data['Message']}", $responseCode);
                }
            }
        }

        if ($output === false) {
            // If we get no response and no 400-500 error, something really weird happened.
            throw new Exception("IPFS Error: No Response", self::ERROR_EMPTY_RESPONSE);
        }
    }

    /**
     * @param  string $url
     * @param  string $data
     * @param  array  $params GET parameters
     * @return string
     * @throws Exception
     */
    private function curl(string $url, string $data = "", array $params = []): string
    {
        $this->resetCurl();
        $output = $this->executeCurl($url, $data, null, $params);

        return $output;
    }

    /**
     * @param  string $url
     * @param  string $filePath or Directory path
     * @param  array  $params   GET parameters
     * @return string
     * @throws Exception
     */
    private function curlFile(string $url, string $filePath, array $params = []): string
    {
        if (!file_exists($filePath)) {
            throw new Exception(
                "Upload file not found"
            );
        }
        $this->resetCurl();
        $output = $this->executeCurl($url, null, $filePath, $params);

        return $output;
    }

    /**
     * @return int
     */
    public function getCurlTimeout(): int
    {
        return $this->curlTimeout;
    }

    /**
     * @param int $curlTimeout
     */
    public function setCurlTimeout(int $curlTimeout): void
    {
        $this->curlTimeout = $curlTimeout;
    }
}

