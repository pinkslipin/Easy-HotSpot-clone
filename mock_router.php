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
            // In mock mode, show vouchers with status='Active' that have been used (uptime > 0)
            $items = $this->getActiveUsersFromDB();
        } elseif (strpos($this->menu, 'user') !== false) {
            // In mock mode, pull users from database so they stay in sync with voucher creation
            $items = $this->getUsersFromDB();
        } elseif (strpos($this->menu, 'log') !== false) {
            foreach (array_slice($this->data['log'], -50) as $log) {
                $items[] = new MockResponse($log);
            }
        }
        
        return $items;
    }
    
    /**
     * Pull voucher users from the database (syncs mock user list with DB)
     */
    private function getUsersFromDB() {
        $items = [];
        try {
            require_once __DIR__ . '/dbconfig.php';
            global $DB_con;
            $stmt = $DB_con->prepare("SELECT user_name, package_name, limit_uptime, price, status, 
                                             created_on, batch_id, limit_bytes, profile
                                      FROM hotspot_vouchers ORDER BY created_on DESC");
            $stmt->execute();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $limitUptime = $row['limit_uptime'] ?: 'Not Limited';
                $profileName = $row['profile'] ?: 'default';
                $items[] = new MockResponse([
                    '.id'               => '*' . crc32($row['user_name']),
                    'name'              => $row['user_name'],
                    'profile'           => $profileName,
                    'limit-uptime'      => $limitUptime,
                    'limit-bytes-total' => $row['limit_bytes'] ? $row['limit_bytes'] : null,
                    'uptime'            => ($row['status'] === 'Active') ? '0s' : '1h',
                    'bytes-in'          => '0',
                    'bytes-out'         => '0',
                    'comment'           => 'PKG:' . ($row['package_name'] ?? ''),
                    'server'            => 'hotspot1',
                ]);
            }
        } catch (\Exception $e) {
            // Fall back to JSON data if DB fails
            foreach ($this->data['users'] as $user) {
                $items[] = new MockResponse($user);
            }
        }
        return $items;
    }
    
    /**
     * Pull active sessions from the database (vouchers with status 'Active')
     */
    private function getActiveUsersFromDB() {
        $items = [];
        try {
            require_once __DIR__ . '/dbconfig.php';
            global $DB_con;
            $stmt = $DB_con->prepare("SELECT user_name, package_name, limit_uptime, created_on
                                      FROM hotspot_vouchers 
                                      WHERE status = 'Active' 
                                      ORDER BY created_on DESC");
            $stmt->execute();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $items[] = new MockResponse([
                    '.id'                => '*' . crc32($row['user_name']),
                    'server'             => 'hotspot1',
                    'domain'             => 'mindspace',
                    'user'               => $row['user_name'],
                    'address'            => '192.168.88.' . rand(10, 250),
                    'uptime'             => '0s',
                    'session-time-left'  => $row['limit_uptime'] ?: 'unlimited',
                ]);
            }
        } catch (\Exception $e) {
            // Fall back to JSON data if DB fails
            foreach ($this->data['active'] as $active) {
                $items[] = new MockResponse($active);
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
        // Use DB count for users in mock mode
        try {
            require_once __DIR__ . '/dbconfig.php';
            global $DB_con;
            $stmt = $DB_con->prepare("SELECT COUNT(*) as cnt FROM hotspot_vouchers");
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)$row['cnt'];
        } catch (\Exception $e) {
            return count($this->data['users']);
        }
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
