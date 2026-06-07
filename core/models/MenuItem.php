<?php
/**
 * MenuItem Model
 */

require_once __DIR__ . '/Model.php';

class MenuItem extends Model {
    protected $table = 'menu_items';

    private function hasColumn(string $column): bool {
        try {
            $stmt = $this->db->prepare('SHOW COLUMNS FROM menu_items LIKE :column_name');
            $stmt->execute(['column_name' => $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Menu item column lookup failed: ' . $e->getMessage());
            return false;
        }
    }

    private function appendPublicVisibilityFilters(string $sql, string $alias = 'mi'): string {
        if ($this->hasColumn('visible_website')) {
            $sql .= " AND {$alias}.visible_website = 1";
        }
        if ($this->hasColumn('availability_status')) {
            $sql .= " AND {$alias}.availability_status <> 'unavailable'";
        }
        return $sql;
    }
    
    /**
     * Get menu items with category info
     */
    public function getWithCategory($id) {
        $sql = "SELECT mi.*, mc.name_fa as category_name_fa, mc.name_en as category_name_en
                FROM menu_items mi
                LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                WHERE mi.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all menu items with categories
     */
    public function getAllWithCategories($filters = []) {
        $sql = "SELECT mi.*, mc.name_fa as category_name_fa, mc.name_en as category_name_en
                FROM menu_items mi
                LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND mi.category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (isset($filters['is_available'])) {
            $sql .= " AND mi.is_available = :is_available";
            $params['is_available'] = $filters['is_available'];
            if ((int)$filters['is_available'] === 1) {
                $sql = $this->appendPublicVisibilityFilters($sql, 'mi');
                $sql .= " AND COALESCE(mc.visible_website, 1) = 1 AND COALESCE(mc.is_active, 1) = 1";
            }
        }
        
        if (isset($filters['is_featured'])) {
            $sql .= " AND mi.is_featured = :is_featured";
            $params['is_featured'] = $filters['is_featured'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (mi.name_fa LIKE :search OR mi.name_en LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY mi.sort_order ASC, mi.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get featured items
     */
    public function getFeatured($limit = 6) {
        $sql = "SELECT mi.*, mc.name_fa as category_name_fa
                FROM menu_items mi
                LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                WHERE mi.is_featured = 1 AND mi.is_available = 1";
        
        $sql = $this->appendPublicVisibilityFilters($sql, 'mi') . " ORDER BY mi.sort_order ASC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get items by category
     */
    public function getByCategory($categoryId, $availableOnly = true) {
        $sql = "SELECT * FROM menu_items WHERE category_id = :category_id";
        
        if ($availableOnly) {
            $sql .= " AND is_available = 1";
            $sql = $this->appendPublicVisibilityFilters($sql, 'menu_items');
        }

        $sql .= " ORDER BY sort_order ASC, name_fa ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['category_id' => $categoryId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Increment view count
     */
    public function incrementViews($id) {
        $stmt = $this->db->prepare("UPDATE menu_items SET views = views + 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Increment order count
     */
    public function incrementOrders($id, $quantity = 1) {
        $stmt = $this->db->prepare("UPDATE menu_items SET orders_count = orders_count + :quantity WHERE id = :id");
        return $stmt->execute(['id' => $id, 'quantity' => $quantity]);
    }
    
    /**
     * Search menu items
     */
    public function search($query) {
        $sql = "SELECT mi.*, mc.name_fa as category_name_fa
                FROM menu_items mi
                LEFT JOIN menu_categories mc ON mi.category_id = mc.id
                WHERE (mi.name_fa LIKE :query OR mi.name_en LIKE :query OR mi.description_fa LIKE :query)
                AND mi.is_available = 1";
        
        $sql = $this->appendPublicVisibilityFilters($sql, 'mi') . " ORDER BY mi.is_featured DESC, mi.sort_order ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['query' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }
}
