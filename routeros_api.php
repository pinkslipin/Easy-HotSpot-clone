<?php
/**
 * Modern RouterOS API Wrapper
 * 
 * Uses evilfreelancer/routeros-api-php library
 * Compatible with RouterOS 6.43+ and 7.x
 * 
 * This wrapper provides the same interface as the old PEAR2 library
 * so existing code doesn't need major changes.
 */

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;
use RouterOS\Config;

/**
 * Modern RouterOS Util class - compatible with old PEAR2 interface
 */
class ModernRouterUtil {
    private $client;
    private $menu = '/ip/hotspot/user';
    
    public function __construct($client) {
        $this->client = $client;
    }
    
    /**
     * Set the current menu path (e.g., '/ip hotspot user')
     * Converts old style paths to new style (spaces to slashes)
     */
    public function setMenu($menu) {
        // Convert old format "/ip hotspot user" to new format "/ip/hotspot/user"
        $this->menu = str_replace(' ', '/', $menu);
        return $this;
    }
    
    /**
     * Get current menu
     */
    public function getMenu() {
        return $this->menu;
    }
    
    /**
     * Add an entry to the current menu
     */
    public function add(array $params) {
        $query = new Query($this->menu . '/add');
        
        foreach ($params as $key => $value) {
            $query->equal($key, $value);
        }
        
        try {
            $response = $this->client->query($query)->read();
            return isset($response[0]['ret']) ? $response[0]['ret'] : true;
        } catch (Exception $e) {
            error_log("RouterOS add error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all entries from current menu
     */
    public function getAll($properties = null) {
        $query = new Query($this->menu . '/print');
        
        if ($properties) {
            $query->equal('.proplist', $properties);
        }
        
        try {
            $response = $this->client->query($query)->read();
            // Convert to RouterItem objects for compatibility
            $items = [];
            foreach ($response as $row) {
                $items[] = new RouterItem($row);
            }
            return $items;
        } catch (Exception $e) {
            error_log("RouterOS getAll error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Find entries matching criteria
     */
    public function find($property, $value) {
        $query = (new Query($this->menu . '/print'))
            ->where($property, $value);
        
        try {
            $response = $this->client->query($query)->read();
            $items = [];
            foreach ($response as $row) {
                $items[] = new RouterItem($row);
            }
            return $items;
        } catch (Exception $e) {
            error_log("RouterOS find error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Remove entry by ID or name
     */
    public function remove($id) {
        $query = (new Query($this->menu . '/remove'))
            ->equal('.id', $id);
        
        try {
            $this->client->query($query)->read();
            return true;
        } catch (Exception $e) {
            error_log("RouterOS remove error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove user by name
     */
    public function removeUser($username) {
        // First find the user ID
        $query = (new Query($this->menu . '/print'))
            ->where('name', $username);
        
        try {
            $response = $this->client->query($query)->read();
            
            if (!empty($response) && isset($response[0]['.id'])) {
                $id = $response[0]['.id'];
                return $this->remove($id);
            }
            return false;
        } catch (Exception $e) {
            error_log("RouterOS removeUser error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Count entries in current menu
     */
    public function count() {
        $items = $this->getAll('.id');
        return count($items);
    }
    
    /**
     * Enable countable interface
     */
    public function __invoke() {
        return $this->count();
    }
}

/**
 * Router Item wrapper - provides getProperty() method for compatibility
 */
class RouterItem {
    private $data;
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    public function getProperty($name) {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }
    
    /**
     * Magic getter for property access like $item->name
     */
    public function __get($name) {
        // Try exact match first
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
        // Try with dot prefix (RouterOS style like .id)
        if (isset($this->data['.' . $name])) {
            return $this->data['.' . $name];
        }
        return null;
    }
    
    /**
     * Magic isset for property checking
     */
    public function __isset($name) {
        return isset($this->data[$name]) || isset($this->data['.' . $name]);
    }
    
    /**
     * Make object callable for legacy code: $entry('time')
     */
    public function __invoke($name) {
        return $this->__get($name);
    }
    
    public function toArray() {
        return $this->data;
    }
}

/**
 * Modern Router Client wrapper
 */
class ModernRouterClient {
    private $client;
    
    public function __construct($host, $user, $pass, $port = 8728) {
        $config = new Config([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port,
            'timeout' => 10,
        ]);
        
        $this->client = new Client($config);
    }
    
    /**
     * Get the underlying client
     */
    public function getClient() {
        return $this->client;
    }
    
    /**
     * Execute a query and return response
     */
    public function query($query) {
        if ($query instanceof Query) {
            return $this->client->query($query)->read();
        }
        
        // If it's a string, create a query
        return $this->client->query(new Query($query))->read();
    }
    
    /**
     * Send sync request (compatibility with old PEAR2 interface)
     */
    public function sendSync($request) {
        if ($request instanceof ModernRequest) {
            $query = $request->toQuery();
            $response = $this->client->query($query)->read();
            return new ModernResponse($response);
        }
        return null;
    }
}

/**
 * Modern Request wrapper (compatibility with old RouterOS\Request)
 */
class ModernRequest {
    private $command;
    private $arguments = [];
    private $whereConditions = [];
    
    public function __construct($command) {
        // Convert old format "/ip/hotspot/user/print" or "/ip hotspot user print"
        $this->command = str_replace(' ', '/', $command);
    }
    
    public function setArgument($key, $value) {
        $this->arguments[$key] = $value;
        return $this;
    }
    
    public function setQuery($query) {
        // Handle ModernQuery objects
        if ($query instanceof ModernQuery) {
            $this->whereConditions = $query->getConditions();
        }
        return $this;
    }
    
    public function toQuery() {
        $query = new Query($this->command);
        
        foreach ($this->arguments as $key => $value) {
            $query->equal($key, $value);
        }
        
        foreach ($this->whereConditions as $condition) {
            $query->where($condition['property'], $condition['value']);
        }
        
        return $query;
    }
}

/**
 * Modern Query builder (compatibility with old RouterOS\Query)
 */
class ModernQuery {
    private $conditions = [];
    private $negated = false;
    
    const OP_EQ = '=';
    
    public static function where($property, $value, $op = '=') {
        $query = new self();
        $query->conditions[] = [
            'property' => $property,
            'value' => $value,
            'op' => $op
        ];
        return $query;
    }
    
    public function not() {
        $this->negated = true;
        return $this;
    }
    
    public function getConditions() {
        return $this->conditions;
    }
    
    public function isNegated() {
        return $this->negated;
    }
}

/**
 * Modern Response wrapper
 */
class ModernResponse {
    private $data;
    
    public function __construct($data) {
        $this->data = is_array($data) ? $data : [];
    }
    
    public function getProperty($name) {
        if (!empty($this->data) && isset($this->data[0][$name])) {
            return $this->data[0][$name];
        }
        return null;
    }
    
    public function getAllOfType($type) {
        // Return all data items (in new library, data is already filtered)
        $items = [];
        foreach ($this->data as $row) {
            $items[] = new RouterItem($row);
        }
        return $items;
    }
    
    public function toArray() {
        return $this->data;
    }
}

/**
 * Factory function to create a router connection
 */
function createRouterConnection($host, $user, $pass, $port = 8728) {
    try {
        $client = new ModernRouterClient($host, $user, $pass, $port);
        $util = new ModernRouterUtil($client->getClient());
        return [
            'client' => $client,
            'util' => $util,
            'success' => true,
            'error' => null
        ];
    } catch (Exception $e) {
        return [
            'client' => null,
            'util' => null,
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Test router connection
 */
function testRouterConnection($host, $user, $pass, $port = 8728) {
    try {
        $config = new Config([
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'port' => $port,
            'timeout' => 5,
        ]);
        
        $client = new Client($config);
        
        // Try to get system identity
        $response = $client->query(new Query('/system/identity/print'))->read();
        
        return [
            'success' => true,
            'identity' => isset($response[0]['name']) ? $response[0]['name'] : 'Unknown',
            'message' => 'Connected successfully!'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'identity' => null,
            'message' => $e->getMessage()
        ];
    }
}
