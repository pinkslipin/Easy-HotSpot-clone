<?php
/**
 * Mock Router for Development
 * 
 * This file simulates MikroTik router responses when you don't have
 * a physical router connected. Enable it by setting MOCK_MODE = true
 * in config.php
 * 
 * HOW IT WORKS:
 * - Instead of sending commands to a real router, the app stores
 *   "hotspot users" in a local JSON file
 * - You can test voucher creation, listing, deletion without hardware
 */

class MockRouterUtil implements \Countable {
    private $dataFile;
    private $menu = '/';
    private $data;
    private $lastCount; // Track count before add operations
    
    public function __construct() {
        $this->dataFile = __DIR__ . '/mock_data/hotspot_users.json';
        $this->loadData();
        $this->lastCount = count($this->data['users']);
    }
    
    private function loadData() {
        if (!file_exists(dirname($this->dataFile))) {
            mkdir(dirname($this->dataFile), 0777, true);
        }
        
        if (file_exists($this->dataFile)) {
            $this->data = json_decode(file_get_contents($this->dataFile), true);
        } else {
            $this->data = [
                'users' => [],
                'profiles' => [
                    ['name' => 'default', 'rate-limit' => '1M/1M', 'shared-users' => '1'],
                    ['name' => '2Mbps', 'rate-limit' => '2M/2M', 'shared-users' => '1'],
                    ['name' => '5Mbps', 'rate-limit' => '5M/5M', 'shared-users' => '1'],
                    ['name' => '10Mbps', 'rate-limit' => '10M/10M', 'shared-users' => '1'],
                ],
                'active' => [],
                'log' => []
            ];
            $this->saveData();
        }
    }
    
    private function saveData() {
        file_put_contents($this->dataFile, json_encode($this->data, JSON_PRETTY_PRINT));
    }
    
    public function setMenu($menu) {
        $this->menu = $menu;
        return $this;
    }
    
    public function getAll() {
        $items = [];
        
        if (strpos($this->menu, 'profile') !== false) {
            foreach ($this->data['profiles'] as $profile) {
                $items[] = new MockResponse($profile);
            }
        } elseif (strpos($this->menu, 'active') !== false) {
            foreach ($this->data['active'] as $active) {
                $items[] = new MockResponse($active);
            }
        } elseif (strpos($this->menu, 'user') !== false) {
            foreach ($this->data['users'] as $user) {
                $items[] = new MockResponse($user);
            }
        } elseif (strpos($this->menu, 'log') !== false) {
            foreach (array_slice($this->data['log'], -50) as $log) {
                $items[] = new MockResponse($log);
            }
        }
        
        return $items;
    }
    
    public function add(array $values) {
        $id = '*' . uniqid();
        $values['.id'] = $id;
        $values['uptime'] = '0s';
        $values['bytes-in'] = '0';
        $values['bytes-out'] = '0';
        
        if (strpos($this->menu, 'profile') !== false) {
            $this->data['profiles'][] = $values;
            $this->log("Profile added: " . $values['name']);
        } else {
            // Check for duplicate username
            foreach ($this->data['users'] as $user) {
                if ($user['name'] === $values['name']) {
                    return ''; // Duplicate, return empty
                }
            }
            $this->data['users'][] = $values;
            $this->log("User added: " . $values['name']);
        }
        
        $this->saveData();
        return $id;
    }
    
    public function remove() {
        // For simplicity, this mock doesn't implement full remove
        // In real usage, you'd pass criteria
        return $this;
    }
    
    /**
     * Remove a user by username
     * @param string $username The username to remove
     * @return bool True if user was removed, false if not found
     */
    public function removeUser($username) {
        $found = false;
        foreach ($this->data['users'] as $key => $user) {
            if ($user['name'] === $username) {
                unset($this->data['users'][$key]);
                $this->data['users'] = array_values($this->data['users']); // Re-index array
                $this->log("User removed: " . $username);
                $found = true;
                break;
            }
        }
        if ($found) {
            $this->saveData();
        }
        return $found;
    }
    
    private function log($message) {
        $this->data['log'][] = [
            'time' => date('M/d/Y H:i:s'),
            'topics' => 'hotspot,info',
            'message' => $message
        ];
    }
    
    // Countable interface - required for count($util) to work
    #[\ReturnTypeWillChange]
    public function count(): int {
        if (strpos($this->menu, 'profile') !== false) {
            return count($this->data['profiles']);
        }
        return count($this->data['users']);
    }
}

class MockResponse {
    private $data;
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public function getProperty($name) {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }
    
    // Make the object callable like $entry('time') - used by server log
    public function __invoke($name) {
        return $this->getProperty($name);
    }
}

class MockClient {
    public function sendSync($request) {
        return new MockResponseCollection();
    }
}

class MockResponseCollection {
    public function getProperty($name) {
        return null;
    }
    
    public function getAllOfType($type) {
        return [];
    }
}
