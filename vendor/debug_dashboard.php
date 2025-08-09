<?php
session_start();
include '../php/db.php';

echo "<h2>🔧 Vendor Dashboard Debug</h2>";

// Check session
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>📊 Session Information</h4>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'><strong>❌ PROBLEM: No user_id in session!</strong></p>";
    
    // Auto-fix by logging in
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $vendor_query = "SELECT id, name, email FROM users WHERE role = 'vendor' LIMIT 1";
        $vendor_stmt = $db->prepare($vendor_query);
        $vendor_stmt->execute();
        $vendor = $vendor_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vendor) {
            $_SESSION['user_id'] = $vendor['id'];
            $_SESSION['role'] = 'vendor';
            $_SESSION['name'] = $vendor['name'];
            $_SESSION['user_name'] = $vendor['name']; // Dashboard uses this
            $_SESSION['email'] = $vendor['email'];
            echo "<p style='color: green;'>✅ Fixed: Auto-logged in as " . $vendor['name'] . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}
echo "</div>";

// Test the exact dashboard query
if (isset($_SESSION['user_id'])) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>🔍 Dashboard Query Test</h4>";
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        $vendor_id = $_SESSION['user_id'];
        
        echo "<p><strong>Testing with vendor_id:</strong> $vendor_id</p>";
        
        // Exact query from dashboard
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                    SUM(CASE WHEN status = 'delivered' THEN total_price ELSE 0 END) as total_spent
                  FROM orders WHERE vendor_id = :vendor_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':vendor_id', $vendor_id);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Stats Query Result:</strong></p>";
        echo "<ul>";
        echo "<li>Total Orders: " . $stats['total_orders'] . "</li>";
        echo "<li>Pending Orders: " . $stats['pending_orders'] . "</li>";
        echo "<li>Delivered Orders: " . $stats['delivered_orders'] . "</li>";
        echo "<li>Total Spent: $" . $stats['total_spent'] . "</li>";
        echo "</ul>";
        
        // Recent orders query
        $query = "SELECT o.*, p.name as product_name, u.name as supplier_name, p.unit
                  FROM orders o
                  JOIN products p ON o.product_id = p.id
                  JOIN users u ON o.supplier_id = u.id
                  WHERE o.vendor_id = :vendor_id
                  ORDER BY o.order_date DESC
                  LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':vendor_id', $vendor_id);
        $stmt->execute();
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Recent Orders Query:</strong></p>";
        if (empty($recent_orders)) {
            echo "<p style='color: red;'>❌ No orders found for vendor_id: $vendor_id</p>";
            
            // Check what orders exist in the system
            echo "<p><strong>All orders in system:</strong></p>";
            $all_query = "SELECT id, vendor_id, supplier_id, product_id, status, order_date FROM orders ORDER BY order_date DESC LIMIT 10";
            $all_stmt = $db->query($all_query);
            $all_orders = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($all_orders)) {
                echo "<p>No orders exist in the entire system!</p>";
            } else {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Order ID</th><th>Vendor ID</th><th>Supplier ID</th><th>Product ID</th><th>Status</th><th>Date</th></tr>";
                foreach ($all_orders as $order) {
                    $highlight = ($order['vendor_id'] == $vendor_id) ? 'background: yellow;' : '';
                    echo "<tr style='$highlight'>";
                    echo "<td>" . $order['id'] . "</td>";
                    echo "<td>" . $order['vendor_id'] . "</td>";
                    echo "<td>" . $order['supplier_id'] . "</td>";
                    echo "<td>" . $order['product_id'] . "</td>";
                    echo "<td>" . $order['status'] . "</td>";
                    echo "<td>" . $order['order_date'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "<p><small>Yellow rows match current vendor_id</small></p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Found " . count($recent_orders) . " orders!</p>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Order ID</th><th>Product</th><th>Supplier</th><th>Quantity</th><th>Total</th><th>Status</th></tr>";
            foreach ($recent_orders as $order) {
                echo "<tr>";
                echo "<td>" . $order['id'] . "</td>";
                echo "<td>" . $order['product_name'] . "</td>";
                echo "<td>" . $order['supplier_name'] . "</td>";
                echo "<td>" . $order['quantity'] . " " . $order['unit'] . "</td>";
                echo "<td>$" . $order['total_price'] . "</td>";
                echo "<td>" . $order['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
}

// Create test order if none exist
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>🧪 Create Test Order</h4>";

if (isset($_POST['create_order'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $vendor_id = $_SESSION['user_id'];
        
        // Get or create supplier and product
        $supplier_query = "SELECT id FROM users WHERE role = 'supplier' LIMIT 1";
        $supplier_stmt = $db->query($supplier_query);
        $supplier = $supplier_stmt->fetch();
        
        if (!$supplier) {
            // Create supplier
            $create_supplier = "INSERT INTO users (name, email, password, role, phone, created_at) 
                               VALUES ('Test Supplier', 'supplier@test.com', 'password123', 'supplier', '1234567890', NOW())";
            $db->exec($create_supplier);
            $supplier_id = $db->lastInsertId();
        } else {
            $supplier_id = $supplier['id'];
        }
        
        $product_query = "SELECT id, price FROM products WHERE is_active = 1 LIMIT 1";
        $product_stmt = $db->query($product_query);
        $product = $product_stmt->fetch();
        
        if (!$product) {
            // Create product
            $create_product = "INSERT INTO products (name, description, price, unit, stock, supplier_id, category, is_active, created_at) 
                              VALUES ('Test Product', 'Test description', 25.00, 'kg', 100, $supplier_id, 'test', 1, NOW())";
            $db->exec($create_product);
            $product_id = $db->lastInsertId();
            $price = 25.00;
        } else {
            $product_id = $product['id'];
            $price = $product['price'];
        }
        
        // Create order
        $order_query = "INSERT INTO orders (vendor_id, supplier_id, product_id, quantity, total_price, delivery_address, status, order_date) 
                       VALUES (:vendor_id, :supplier_id, :product_id, 2, :total_price, 'Test Address', 'pending', NOW())";
        $stmt = $db->prepare($order_query);
        $total_price = ($price * 2) + 20; // 2 items + delivery fee
        
        $result = $stmt->execute([
            'vendor_id' => $vendor_id,
            'supplier_id' => $supplier_id,
            'product_id' => $product_id,
            'total_price' => $total_price
        ]);
        
        if ($result) {
            $order_id = $db->lastInsertId();
            echo "<p style='color: green;'>✅ Test order created successfully! Order ID: $order_id</p>";
            echo "<p>Vendor ID: $vendor_id | Supplier ID: $supplier_id | Product ID: $product_id</p>";
            echo "<p><strong>Now refresh the dashboard to see if it appears!</strong></p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create order</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating order: " . $e->getMessage() . "</p>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='create_order' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px;'>Create Test Order</button>";
echo "</form>";
echo "</div>";

// Action links
echo "<div style='margin: 20px 0;'>";
echo "<h4>🔗 Quick Links</h4>";
echo "<a href='dashboard.php' target='_blank' style='padding: 10px 15px; background: #28a745; color: white; text-decoration: none; margin: 5px; border-radius: 5px;'>📊 Open Dashboard</a>";
echo "<a href='myorders.php' target='_blank' style='padding: 10px 15px; background: #ffc107; color: black; text-decoration: none; margin: 5px; border-radius: 5px;'>📋 My Orders</a>";
echo "<a href='../order.php?product_id=1' target='_blank' style='padding: 10px 15px; background: #17a2b8; color: white; text-decoration: none; margin: 5px; border-radius: 5px;'>🛒 Place Order</a>";
echo "</div>";
?>
