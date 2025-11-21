<?php
require_once 'bd.php';

/**
 * BaseModel: helper pequeño para usar mysqli con sentencias preparadas
 * Provee run($sql, $params, $fetch) que devuelve filas asociativas o affected rows.
 */
class BaseModel {
    protected $db;

    public function __construct() {
        $this->db = getConnection();
    }

    /**
     * Ejecuta una consulta. Si $params está vacío se usa query directa.
     * $fetch: 'one' | 'all' | 'none' (none devuelve affected rows)
     */
    protected function run($sql, $params = [], $fetch = 'all') {
        // sin parámetros: ejecución directa
        if (empty($params)) {
            $res = $this->db->query($sql);
            if ($res === false) return false;
            if ($res === true) return $this->db->affected_rows;
            if ($fetch === 'one') return $res->fetch_assoc() ?: null;
            $rows = [];
            while ($row = $res->fetch_assoc()) $rows[] = $row;
            return $rows;
        }

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) return false;

        if (!empty($params)) {
            // construir string de tipos
            $types = '';
            foreach ($params as $p) {
                if (is_int($p)) $types .= 'i';
                elseif (is_float($p)) $types .= 'd';
                else $types .= 's';
            }

            // bind_param requiere referencias
            $refs = [];
            foreach ($params as $k => $v) $refs[$k] = &$params[$k];
            array_unshift($refs, $types);
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }

        $ok = $stmt->execute();
        if ($ok === false) {
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        if ($result === false) {
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }

        if ($fetch === 'one') {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    protected function lastId() {
        return $this->db->insert_id;
    }
}

class User extends BaseModel {
    public function __construct() {
        parent::__construct();
    }

    // Obtener usuario por ID
    public function getById($id) {
        return $this->run("SELECT * FROM `users` WHERE `id` = ?", [$id], 'one');
    }

    // Obtener usuario por username
    public function getByUsername($username) {
        return $this->run("SELECT * FROM `users` WHERE `username` = ?", [$username], 'one');
    }

    // Obtener usuario por email
    public function getByEmail($email) {
        return $this->run("SELECT * FROM `users` WHERE `email` = ?", [$email], 'one');
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
    $sql = "INSERT INTO `users` (`username`, `email`, `password_hash`, `role`) VALUES (?, ?, ?, ?)";
        $this->run($sql, [$username, $email, $passwordHash, $role], 'none');
        return $this->lastId();
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
        $res = $this->run($sql, $values, 'none');
        return $res !== false;
    }

    // Eliminar usuario
    public function delete($id) {
        $res = $this->run("DELETE FROM `users` WHERE `id` = ?", [$id], 'none');
        return $res !== false;
    }

    // Listar todos los usuarios
    public function getAll($activeOnly = false) {
        $sql = "SELECT * FROM `users`";
        if ($activeOnly) {
            $sql .= " WHERE `active` = 1";
        }
        $sql .= " ORDER BY `created_at` DESC";
        return $this->run($sql, [], 'all');
    }

    // Verificar si es superadmin
    public function isSuperadmin($userId) {
        $user = $this->getById($userId);
        return $user && $user['role'] === 'superadmin';
    }
}

class Item extends BaseModel {
    public function __construct() {
        parent::__construct();
    }

    // Obtener item por ID
    public function getById($id) {
        $item = $this->run("SELECT * FROM `items` WHERE `id` = ?", [$id], 'one');
        if ($item && !empty($item['meta_data'])) {
            $item['meta_data'] = json_decode($item['meta_data'], true);
        }
        return $item;
    }

    // Obtener item por shortname
    public function getByShortname($shortname) {
        $item = $this->run("SELECT * FROM `items` WHERE `shortname` = ?", [$shortname], 'one');
        if ($item && !empty($item['meta_data'])) {
            $item['meta_data'] = json_decode($item['meta_data'], true);
        }
        return $item;
    }

    // Obtener item por slug
    public function getBySlug($slug) {
        $item = $this->run("SELECT * FROM `items` WHERE `slug` = ?", [$slug], 'one');
        if ($item && !empty($item['meta_data'])) {
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

        $params = [
            $data['parent_id'] ?? null,
            $data['type'] ?? null,
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
        ];

        $this->run($sql, $params, 'none');
        return $this->lastId();
    }

    // Actualizar item
    public function update($id, $data, $userRole = 'superadmin') {
        // Si es editor, verificar permisos
        if ($userRole === 'editor') {
            $item = $this->getById($id);
            $data = $this->filterEditableFields($data, $item['editable_fields'] ?? null);
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
                $values[] = ($field === 'meta_data' && is_array($data[$field]))
                    ? json_encode($data[$field])
                    : $data[$field];
            }
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $sql = "UPDATE `items` SET " . implode(', ', $fields) . " WHERE `id` = ?";
        $res = $this->run($sql, $values, 'none');
        return $res !== false;
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
        $res = $this->run("DELETE FROM `items` WHERE `id` = ?", [$id], 'none');
        return $res !== false;
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

        $items = $this->run($sql, $params, 'all');

        foreach ($items as &$item) {
            if (!empty($item['meta_data'])) {
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

        $items = $this->run($sql, $params, 'all');

        foreach ($items as &$item) {
            if (!empty($item['meta_data'])) {
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

        $items = $this->run($sql, $params, 'all');

        foreach ($items as &$item) {
            if (!empty($item['meta_data'])) {
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

        $items = $this->run($sql, $params, 'all');

        foreach ($items as &$item) {
            if (!empty($item['meta_data'])) {
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

        $editableFields = $item['editable_fields'] ?? null;

        if (empty($editableFields)) {
            $defaultEditable = ['title', 'subtitle', 'content', 'excerpt', 'status'];
            return in_array($fieldName, $defaultEditable);
        }

        $allowed = array_map('trim', explode(',', $editableFields));
        return in_array($fieldName, $allowed);
    }
}