<?php
/**
 * Order Model
 */

require_once __DIR__ . '/Model.php';

class Order extends Model {
    protected $table = 'orders';
    
    /**
     * Create new order with items
     */
    public function createOrder($orderData, $items) {
        try {
            $this->beginTransaction();
            
            // Generate order number
            $orderData['order_number'] = $this->generateOrderNumber();
            
            // Create order
            $orderId = $this->create($orderData);
            
            // Create order items
            foreach ($items as $item) {
                $this->createOrderItem($orderId, $item);
            }
            
            $this->commit();
            return $orderId;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Generate unique order number
     */
    private function generateOrderNumber() {
        $prefix = 'KEY';
        $date = date('ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $date . $random;
    }
    
    /**
     * Create order item
     */
    private function createOrderItem($orderId, $itemData) {
        $sql = "INSERT INTO order_items (order_id, menu_item_id, item_name_fa, item_name_en, quantity, unit_price, subtotal, notes)
                VALUES (:order_id, :menu_item_id, :item_name_fa, :item_name_en, :quantity, :unit_price, :subtotal, :notes)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'order_id' => $orderId,
            'menu_item_id' => $itemData['menu_item_id'],
            'item_name_fa' => $itemData['item_name_fa'],
            'item_name_en' => $itemData['item_name_en'],
            'quantity' => $itemData['quantity'],
            'unit_price' => $itemData['unit_price'],
            'subtotal' => $itemData['subtotal'],
            'notes' => $itemData['notes'] ?? null
        ]);
    }
    
    /**
     * Get order with items
     */
    public function getOrderWithItems($id) {
        // Get order
        $order = $this->find($id);
        
        if (!$order) {
            return null;
        }
        
        // Get order items
        $stmt = $this->db->prepare("
            SELECT oi.*, mi.image 
            FROM order_items oi
            LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE oi.order_id = :order_id
        ");
        $stmt->execute(['order_id' => $id]);
        $order['items'] = $stmt->fetchAll();
        
        return $order;
    }
    
    /**
     * Get orders by user
     */
    public function getByUser($userId, $limit = null) {
        $sql = "SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Update order status
     */
    public function updateStatus($id, $status, $adminNotes = null) {
        $data = ['order_status' => $status];
        
        if ($status === 'delivered') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'cancelled') {
            $data['cancelled_at'] = date('Y-m-d H:i:s');
        }
        
        if ($adminNotes) {
            $data['admin_notes'] = $adminNotes;
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Get orders by status
     */
    public function getByStatus($status) {
        return $this->where(['order_status' => $status], 'created_at DESC');
    }
    
    /**
     * Get today's orders
     */
    public function getTodayOrders() {
        $sql = "SELECT * FROM orders WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC";
        return $this->query($sql);
    }
    
    /**
     * Get orders statistics
     */
    public function getStatistics($startDate = null, $endDate = null) {
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(total) as total_revenue,
                    AVG(total) as average_order_value,
                    COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders
                FROM orders
                WHERE 1=1";
        
        $params = [];
        
        if ($startDate) {
            $sql .= " AND created_at >= :start_date";
            $params['start_date'] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND created_at <= :end_date";
            $params['end_date'] = $endDate;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Get recent orders
     */
    public function getRecent($limit = 10) {
        $sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
