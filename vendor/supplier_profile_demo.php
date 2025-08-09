<!DOCTYPE html>
<html>
<head>
    <title>🏪 Enhanced Supplier Display Demo</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .supplier-card {
            transition: all 0.3s ease;
        }
        .supplier-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }
        .nearby-supplier {
            border-left: 4px solid #28a745 !important;
            background-color: #f8fff8;
        }
        .products-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        .product-card {
            transition: all 0.2s ease;
            border: 1px solid #e9ecef;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        .demo-card {
            border: 2px solid #007bff;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <h2>🏪 Enhanced Supplier Display - Feature Demo</h2>
        
        <div class="alert alert-success">
            <h5>✅ Enhancement Complete!</h5>
            <p><strong>New Features Added:</strong></p>
            <ul class="mb-0">
                <li>📋 <strong>Supplier Profile View</strong> - Complete business information and stats</li>
                <li>📦 <strong>Product Showcase</strong> - Interactive product cards with ordering</li>
                <li>🎨 <strong>Enhanced UI</strong> - Beautiful cards with hover effects and badges</li>
                <li>📞 <strong>Quick Actions</strong> - Contact supplier and order products directly</li>
            </ul>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <button onclick="loadSupplierDemo()" class="btn btn-primary w-100">
                    🏪 Load Live Supplier Data
                </button>
            </div>
            <div class="col-md-4">
                <button onclick="openDashboard()" class="btn btn-success w-100">
                    🚀 Open Enhanced Dashboard
                </button>
            </div>
            <div class="col-md-4">
                <button onclick="testAPI()" class="btn btn-info w-100">
                    🧪 Test API Response
                </button>
            </div>
        </div>
        
        <!-- Demo Results -->
        <div id="demo-results">
            <div class="demo-card card p-4 text-center">
                <h5>👆 Click "Load Live Supplier Data" to see the enhanced display</h5>
                <p class="text-muted">This will show real supplier data with profile and products</p>
            </div>
        </div>
        
        <!-- Feature Showcase -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>📋 Supplier Profile Features</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li>✅ Business information display</li>
                            <li>✅ Contact details (phone, email, address)</li>
                            <li>✅ Business statistics (products, ratings, reviews)</li>
                            <li>✅ Member since information</li>
                            <li>✅ Distance from your location</li>
                            <li>✅ Interactive modal with full details</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>📦 Product Showcase Features</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li>✅ Expandable product grid</li>
                            <li>✅ Product cards with hover effects</li>
                            <li>✅ Price display with currency</li>
                            <li>✅ Product descriptions</li>
                            <li>✅ Direct "Order Now" buttons</li>
                            <li>✅ Responsive design for all devices</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function loadSupplierDemo() {
            const results = document.getElementById('demo-results');
            results.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Loading supplier data...</p></div>';
            
            try {
                const response = await fetch('../php/get_suppliers_with_products.php?lat=21.1320832&lng=79.0953984&radius=10');
                const data = await response.json();
                
                if (data.success && data.suppliers.length > 0) {
                    const supplier = data.suppliers[0];
                    
                    let html = `
                        <div class="alert alert-success">
                            <h6>✅ Live Data Loaded Successfully!</h6>
                            <p>Found ${data.suppliers.length} supplier(s) with ${supplier.product_count} products</p>
                        </div>
                        
                        <div class="supplier-card nearby-supplier card mb-3 border rounded p-3">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <span class="badge bg-success me-2">📍 Nearby</span>
                                        🏪 ${supplier.business_name || supplier.name}
                                    </h6>
                                    <div class="mb-2">
                                        ⭐⭐⭐⭐☆ (${supplier.avg_rating}/5) - ${supplier.review_count} reviews
                                    </div>
                                    <p class="text-muted mb-2"><small>📦 ${supplier.product_count} products available</small></p>
                                    <p class="text-muted mb-2"><small>📍 ${supplier.address}</small></p>
                                    <p class="text-muted mb-2"><small>📞 ${supplier.phone}</small></p>
                                    <p class="text-muted mb-2"><small>📧 ${supplier.email}</small></p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-success btn-sm mb-2" onclick="toggleProductsDemo()">
                                        📦 View Products (${supplier.product_count})
                                    </button><br>
                                    <button class="btn btn-info btn-sm" onclick="viewProfileDemo()">
                                        👤 Profile
                                    </button>
                                </div>
                            </div>
                            
                            <div id="products-demo" class="products-section mt-3" style="display: none;">
                                <hr>
                                <h6>📦 Available Products:</h6>
                                <div class="row">
                    `;
                    
                    supplier.products.forEach(product => {
                        html += `
                            <div class="col-md-6 col-lg-4 mb-2">
                                <div class="card product-card h-100">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1">${product.name}</h6>
                                        <p class="card-text text-success mb-1"><strong>₹${product.price}</strong></p>
                                        <p class="card-text text-muted small mb-2">${product.description}</p>
                                        <button class="btn btn-primary btn-sm" onclick="demoOrder('${product.name}', ${product.price})">
                                            🛒 Order Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                    
                    results.innerHTML = html;
                    
                    // Store supplier data for profile demo
                    window.demoSupplier = supplier;
                    
                } else {
                    results.innerHTML = `
                        <div class="alert alert-warning">
                            <h6>⚠️ No suppliers found</h6>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                results.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ Error loading data</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        function toggleProductsDemo() {
            const productsDiv = document.getElementById('products-demo');
            if (productsDiv.style.display === 'none') {
                productsDiv.style.display = 'block';
            } else {
                productsDiv.style.display = 'none';
            }
        }
        
        function viewProfileDemo() {
            if (!window.demoSupplier) return;
            
            const supplier = window.demoSupplier;
            const profile = supplier.profile;
            
            const modalHtml = `
                <div class="modal fade" id="demoProfileModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">🏪 ${profile.business_name} - Supplier Profile</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>📋 Business Information</h6>
                                        <p><strong>Business Name:</strong> ${profile.business_name}</p>
                                        <p><strong>Contact Person:</strong> ${profile.contact_person}</p>
                                        <p><strong>Email:</strong> ${profile.email}</p>
                                        <p><strong>Phone:</strong> ${profile.phone}</p>
                                        <p><strong>Address:</strong> ${profile.address}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>📊 Business Stats</h6>
                                        <p><strong>Member Since:</strong> ${profile.member_since}</p>
                                        <p><strong>Total Products:</strong> ${profile.total_products}</p>
                                        <p><strong>Average Rating:</strong> ${profile.average_rating}/5 ⭐</p>
                                        <p><strong>Total Reviews:</strong> ${profile.total_reviews}</p>
                                        <p><strong>Distance:</strong> ${supplier.distance}km away</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" onclick="alert('📞 Calling ${profile.phone}...')">
                                    📞 Contact Supplier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal
            const existing = document.getElementById('demoProfileModal');
            if (existing) existing.remove();
            
            // Add and show modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('demoProfileModal'));
            modal.show();
        }
        
        function demoOrder(productName, price) {
            alert(`🛒 Demo Order: ${productName} for ₹${price}\n\nIn the real dashboard, this would redirect to the order page!`);
        }
        
        function openDashboard() {
            window.open('dashboard.php', '_blank');
        }
        
        function testAPI() {
            window.open('../php/get_suppliers_with_products.php?lat=21.1320832&lng=79.0953984&radius=10', '_blank');
        }
        
        // Auto-load demo
        window.addEventListener('load', () => {
            setTimeout(loadSupplierDemo, 1000);
        });
    </script>
</body>
</html>
