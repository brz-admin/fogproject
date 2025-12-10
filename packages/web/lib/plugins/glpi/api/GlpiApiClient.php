<?php
/**
 * GLPI API Client
 * 
 * Handles communication with GLPI REST API
 * 
 * @category API
 * @package  FOGProject
 * @author   Mistral Vibe <vibe@mistral.ai>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * GLPI API Client
 * 
 * Provides methods to interact with GLPI REST API
 */
class GlpiApiClient {
    
    /**
     * GLPI base URL
     * @var string
     */
    private $baseUrl;
    
    /**
     * API token
     * @var string
     */
    private $apiToken;
    
    /**
     * Session token
     * @var string|null
     */
    private $sessionToken = null;
    
    /**
     * Constructor
     * 
     * @param string $baseUrl
     * @param string $apiToken
     */
    public function __construct($baseUrl, $apiToken) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiToken = $apiToken;
    }
    
    /**
     * Test connection to GLPI
     * 
     * @return bool
     */
    public function testConnection() {
        try {
            $response = $this->request('GET', '/initSession', [], true);
            return isset($response['session_token']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Authenticate with GLPI API
     * 
     * @return bool
     * @throws Exception
     */
    public function authenticate() {
        $response = $this->request('GET', '/initSession', [], true);
        
        if (isset($response['session_token'])) {
            $this->sessionToken = $response['session_token'];
            return true;
        }
        
        throw new Exception(_('GLPI authentication failed: Invalid response'));
    }
    
    /**
     * Search computers by serial number
     * 
     * @param string $serialNumber
     * @return array
     * @throws Exception
     */
    public function searchComputersBySerial($serialNumber) {
        $this->ensureAuthenticated();
        
        $criteria = [
            [
                'field' => 5, // Serial number field ID in GLPI
                'searchtype' => 'contains',
                'value' => $serialNumber
            ]
        ];
        
        return $this->request('GET', '/Computer', [
            'criteria' => json_encode($criteria),
            'expand_dropdowns' => 'true'
        ]);
    }
    
    /**
     * Get computer by ID
     * 
     * @param int $computerID
     * @return array
     * @throws Exception
     */
    public function getComputer($computerID) {
        $this->ensureAuthenticated();
        
        return $this->request('GET', "/Computer/{$computerID}", [
            'expand_dropdowns' => 'true'
        ]);
    }
    
    /**
     * Create computer
     * 
     * @param array $computerData
     * @return array
     * @throws Exception
     */
    public function createComputer($computerData) {
        $this->ensureAuthenticated();
        
        return $this->request('POST', '/Computer', $computerData);
    }
    
    /**
     * Update computer
     * 
     * @param int $computerID
     * @param array $computerData
     * @return array
     * @throws Exception
     */
    public function updateComputer($computerID, $computerData) {
        $this->ensureAuthenticated();
        
        return $this->request('PUT', "/Computer/{$computerID}", $computerData);
    }
    
    /**
     * Search computers with advanced criteria
     * 
     * @param array $criteria
     * @param int $limit
     * @param int $offset
     * @return array
     * @throws Exception
     */
    public function searchComputers($criteria = [], $limit = 50, $offset = 0) {
        $this->ensureAuthenticated();
        
        $params = [
            'limit' => $limit,
            'offset' => $offset,
            'expand_dropdowns' => 'true'
        ];
        
        if (!empty($criteria)) {
            $params['criteria'] = json_encode($criteria);
        }
        
        return $this->request('GET', '/Computer', $params);
    }
    
    /**
     * Get all locations
     * 
     * @return array
     * @throws Exception
     */
    public function getLocations() {
        $this->ensureAuthenticated();
        
        return $this->request('GET', '/Location');
    }
    
    /**
     * Get all users
     * 
     * @return array
     * @throws Exception
     */
    public function getUsers() {
        $this->ensureAuthenticated();
        
        return $this->request('GET', '/User');
    }
    
    /**
     * Ensure we are authenticated
     * 
     * @throws Exception
     */
    protected function ensureAuthenticated() {
        if ($this->sessionToken === null) {
            $this->authenticate();
        }
    }
    
    /**
     * Make API request
     * 
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param bool $authRequest
     * @return array
     * @throws Exception
     */
    protected function request($method, $endpoint, $params = [], $authRequest = false) {
        $url = $this->baseUrl . '/apirest.php' . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($authRequest) {
            $headers[] = 'Authorization: user_token ' . $this->apiToken;
            $headers[] = 'App-Token: ' . $this->apiToken;
        } else {
            if ($this->sessionToken === null) {
                $this->authenticate();
            }
            $headers[] = 'Session-Token: ' . $this->sessionToken;
            $headers[] = 'App-Token: ' . $this->apiToken;
        }
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ];
        
        if (!empty($params)) {
            if ($method === 'GET') {
                $url .= '?' . http_build_query($params);
                $options[CURLOPT_URL] = $url;
            } else {
                $options[CURLOPT_POSTFIELDS] = json_encode($params);
            }
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }
        
        if ($httpCode >= 400) {
            $this->handleErrorResponse($httpCode, $response);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
    
    /**
     * Handle error responses
     * 
     * @param int $httpCode
     * @param string $response
     * @throws Exception
     */
    protected function handleErrorResponse($httpCode, $response) {
        $errorMessages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error'
        ];
        
        $message = isset($errorMessages[$httpCode]) ? $errorMessages[$httpCode] : 'HTTP Error ' . $httpCode;
        
        try {
            $errorData = json_decode($response, true);
            if (isset($errorData['message'])) {
                $message .= ': ' . $errorData['message'];
            }
        } catch (Exception $e) {
            // Ignore JSON decode errors
        }
        
        throw new Exception($message);
    }
    
    /**
     * Get session token
     * 
     * @return string|null
     */
    public function getSessionToken() {
        return $this->sessionToken;
    }
    
    /**
     * Invalidate session
     */
    public function invalidateSession() {
        $this->sessionToken = null;
    }
}