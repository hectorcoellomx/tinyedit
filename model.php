<?php
require_once 'bd.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    // Obtener usuario por ID
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM `users` WHERE `id` = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener usuario por username
    public function getByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM `users` WHERE `username` = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Obtener usuario por email
    public function getByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Autenticar usuario
    public function authenticate($username, $password) {
        $user = $this->getByUsername($username);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    }
    
    // Crear usuario
    public function create($username, $email, $password, $role = 'editor') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
            INSERT INTO `users` (`username`, `email`, `password_hash`, `role`) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $passwordHash, $role]);
        return $this->db->lastInsertId();
    }
    
    // Actualizar usuario
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        if (isset($data['username'])) {
            $fields[] = "`username` = ?";
            $values[] = $data['username'];
        }
        if (isset($data['email'])) {
            $fields[] = "`email` = ?";
            $values[] = $data['email'];
        }
        if (isset($data['password'])) {
            $fields[] = "`password_hash` = ?";
            $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['role'])) {
            $fields[] = "`role` = ?";
            $values[] = $data['role'];
        }
        if (isset($data['active'])) {
            $fields[] = "`active` = ?";
            $values[] = $data['active'];
        }
        
        if (empty($fields)) return false;
        
        $values[] = $id;
        $sql = "UPDATE `users` SET " . implode(', ', $fields) . " WHERE `id` = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    // Eliminar usuario
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM `users` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }
    
    // Listar todos los usuarios
    public function getAll($activeOnly = false) {
        $sql = "SELECT * FROM `users`";
        if ($activeOnly) {
            $sql .= " WHERE `active` = 1";
        }
        $sql .= " ORDER BY `created_at` DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verificar si es superadmin
    public function isSuperadmin($userId) {
        $user = $this->getById($userId);
        return $user && $user['role'] === 'superadmin';
    }
}

class Item {
    private $db;
    
    public function __construct() {
        $this->db = getConnection();
    }
    
    // Obtener item por ID
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM `items` WHERE `id` = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item && $item['meta_data']) {
            $item['meta_data'] = json_decode($item['meta_data'], true);
        }
        return $item;
    }
    
    // Obtener item por shortname
    public function getByShortname($shortname) {
        $stmt = $this->db->prepare("SELECT * FROM `items` WHERE `shortname` = ?");
        $stmt->execute([$shortname]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item && $item['meta_data']) {
            $item['meta_data'] = json_decode($item['meta_data'], true);
        }
        return $item;
    }
    
    // Obtener item por slug
    public function getBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM `items` WHERE `slug` = ?");
        $stmt->execute([$slug]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item && $item['meta_data']) {
            $item['meta_data'] = json_decode($item['meta_data'], true);
        }
        return $item;
    }
    
    // Crear item
    public function create($data) {
        $sql = "INSERT INTO `items` (
            `parent_id`, `type`, `shortname`, `slug`, `title`, `subtitle`, 
            `content`, `excerpt`, `meta_data`, `order`, `status`, 
            `editable_fields`, `published_at`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $metaData = isset($data['meta_data']) ? json_encode($data['meta_data']) : null;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['parent_id'] ?? null,
            $data['type'],
            $data['shortname'] ?? null,
            $data['slug'] ?? null,
            $data['title'] ?? null,
            $data['subtitle'] ?? null,
            $data['content'] ?? null,
            $data['excerpt'] ?? null,
            $metaData,
            $data['order'] ?? 0,
            $data['status'] ?? 'draft',
            $data['editable_fields'] ?? null,
            $data['published_at'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Actualizar item
    public function update($id, $data, $userRole = 'superadmin') {
        // Si es editor, verificar permisos
        if ($userRole === 'editor') {
            $item = $this->getById($id);
            $data = $this->filterEditableFields($data, $item['editable_fields']);
        }
        
        $fields = [];
        $values = [];
        
        $allowedFields = [
            'parent_id', 'type', 'shortname', 'slug', 'title', 'subtitle',
            'content', 'excerpt', 'meta_data', 'order', 'status',
            'editable_fields', 'published_at'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $field === 'meta_data' && is_array($data[$field]) 
                    ? json_encode($data[$field]) 
                    : $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $values[] = $id;
        $sql = "UPDATE `items` SET " . implode(', ', $fields) . " WHERE `id` = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    // Filtrar campos editables (para editores)
    private function filterEditableFields($data, $editableFields) {
        if (empty($editableFields)) {
            // Campos editables por defecto
            $allowed = ['title', 'subtitle', 'content', 'excerpt', 'status'];
        } else {
            $allowed = array_map('trim', explode(',', $editableFields));
        }
        
        return array_intersect_key($data, array_flip($allowed));
    }
    
    // Eliminar item
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM `items` WHERE `id` = ?");
        return $stmt->execute([$id]);
    }
    
    // Listar items por tipo
    public function getByType($type, $status = null, $parentId = null) {
        $sql = "SELECT * FROM `items` WHERE `type` = ?";
        $params = [$type];
        
        if ($status !== null) {
            $sql .= " AND `status` = ?";
            $params[] = $status;
        }
        
        if ($parentId !== null) {
            $sql .= " AND `parent_id` = ?";
            $params[] = $parentId;
        }
        
        $sql .= " ORDER BY `order` ASC, `created_at` DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as &$item) {
            if ($item['meta_data']) {
                $item['meta_data'] = json_decode($item['meta_data'], true);
            }
        }
        
        return $items;
    }
    
    // Obtener hijos de un item
    public function getChildren($parentId, $status = null) {
        $sql = "SELECT * FROM `items` WHERE `parent_id` = ?";
        $params = [$parentId];
        
        if ($status !== null) {
            $sql .= " AND `status` = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY `order` ASC, `created_at` DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as &$item) {
            if ($item['meta_data']) {
                $item['meta_data'] = json_decode($item['meta_data'], true);
            }
        }
        
        return $items;
    }
    
    // Obtener árbol completo (recursivo)
    public function getTree($parentId = null, $status = null) {
        $items = $this->getChildren($parentId, $status);
        
        foreach ($items as &$item) {
            $item['children'] = $this->getTree($item['id'], $status);
        }
        
        return $items;
    }
    
    // Listar todos los items
    public function getAll($filters = []) {
        $sql = "SELECT * FROM `items` WHERE 1=1";
        $params = [];
        
        if (isset($filters['type'])) {
            $sql .= " AND `type` = ?";
            $params[] = $filters['type'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND `status` = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['parent_id'])) {
            $sql .= " AND `parent_id` = ?";
            $params[] = $filters['parent_id'];
        }
        
        $sql .= " ORDER BY `order` ASC, `created_at` DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as &$item) {
            if ($item['meta_data']) {
                $item['meta_data'] = json_decode($item['meta_data'], true);
            }
        }
        
        return $items;
    }
    
    // Buscar items
    public function search($query, $fields = ['title', 'content']) {
        $conditions = [];
        $params = [];
        
        foreach ($fields as $field) {
            $conditions[] = "`$field` LIKE ?";
            $params[] = "%$query%";
        }
        
        $sql = "SELECT * FROM `items` WHERE " . implode(' OR ', $conditions);
        $sql .= " ORDER BY `created_at` DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as &$item) {
            if ($item['meta_data']) {
                $item['meta_data'] = json_decode($item['meta_data'], true);
            }
        }
        
        return $items;
    }
    
    // Verificar si un usuario puede editar un campo
    public function canEditField($userRole, $itemId, $fieldName) {
        if ($userRole === 'superadmin') {
            return true;
        }
        
        $item = $this->getById($itemId);
        if (!$item) return false;
        
        $editableFields = $item['editable_fields'];
        
        if (empty($editableFields)) {
            $defaultEditable = ['title', 'subtitle', 'content', 'excerpt', 'status'];
            return in_array($fieldName, $defaultEditable);
        }
        
        $allowed = array_map('trim', explode(',', $editableFields));
        return in_array($fieldName, $allowed);
    }
}