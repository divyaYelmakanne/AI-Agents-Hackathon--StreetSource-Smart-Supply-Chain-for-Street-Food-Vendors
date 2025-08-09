<!DOCTYPE html>
<html>
<head>
    <title>🎉 Enhanced Vendor Dashboard Demo</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .feature-highlight {
            border: 2px solid #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        }
        .before-after {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .before {
            background: #f8d7da;
            color: #721c24;
        }
        .after {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <h2>🎉 Enhanced Vendor Dashboard - No Map, Direct Suppliers!</h2>
        
        <div class="alert alert-success feature-highlight">
            <h4>✅ Dashboard Enhancement Complete!</h4>
            <p><strong>What's Changed:</strong></p>
            <ul class="mb-0">
                <li>🗺️ ❌ <strong>Removed Map</strong> - No more complex map interface</li>
                <li>🏪 ✅ <strong>Direct Supplier Display</strong> - Suppliers load automatically on page load</li>
                <li>📍 ✅ <strong>Smart Location</strong> - Shows nearby suppliers first, then all others</li>
                <li>⚡ ✅ <strong>Auto-Loading</strong> - No need to click "Find Suppliers" - it loads immediately</li>
                <li>📦 ✅ <strong>Full Product Integration</strong> - Complete supplier profiles and product catalogs</li>
            </ul>
        </div>
        
        <!-- Before/After Comparison -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="before-after">
                    <div class="before p-3 text-center">
                        <h6>❌ BEFORE</h6>
                        <p class="small mb-0">🗺️ Map interface + separate supplier list</p>
                        <p class="small mb-0">⏳ Manual "Find Suppliers" button required</p>
                        <p class="small mb-0">🔧 Complex map controls</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="before-after">
                    <div class="after p-3 text-center">
                        <h6>✅ AFTER</h6>
                        <p class="small mb-0">🏪 Direct supplier listing</p>
                        <p class="small mb-0">⚡ Auto-loads on page open</p>
                        <p class="small mb-0">🎯 Clean, focused interface</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Features Overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>⚡ Auto-Loading</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li>✅ Suppliers load automatically</li>
                            <li>✅ No button clicking required</li>
                            <li>✅ Shows loading spinner</li>
                            <li>✅ Backup loading system</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>🎯 Smart Prioritization</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li>✅ Nearby suppliers first</li>
                            <li>✅ Distance-based sorting</li>
                            <li>✅ Clear nearby badges</li>
                            <li>✅ Fallback to all suppliers</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6>🏪 Rich Supplier Profiles</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li>✅ Complete business info</li>
                            <li>✅ Product catalogs</li>
                            <li>✅ Interactive profiles</li>
                            <li>✅ Direct ordering</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Demo Actions -->
        <div class="row g-2 mb-4">
            <div class="col-md-6">
                <button onclick="openNewDashboard()" class="btn btn-success w-100 btn-lg">
                    🚀 Open Enhanced Dashboard
                </button>
                <small class="text-muted">See the new supplier-focused interface</small>
            </div>
            <div class="col-md-6">
                <button onclick="testAPI()" class="btn btn-info w-100 btn-lg">
                    🧪 Test API Response
                </button>
                <small class="text-muted">View raw supplier data with products</small>
            </div>
        </div>
        
        <!-- Live Preview -->
        <div class="card">
            <div class="card-header">
                <h6>👀 Live Preview - Your Supplier Data</h6>
            </div>
            <div class="card-body">
                <div id="preview" class="text-center text-muted">
                    <button onclick="loadPreview()" class="btn btn-primary">
                        🔍 Load Supplier Preview
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Benefits Summary -->
        <div class="alert alert-info mt-4">
            <h6>🎯 Key Benefits of the New Dashboard:</h6>
            <div class="row">
                <div class="col-md-6">
                    <ul class="mb-0">
                        <li><strong>Faster Loading:</strong> Suppliers appear immediately</li>
                        <li><strong>Better UX:</strong> No map complexity for customers</li>
                        <li><strong>Mobile Friendly:</strong> Works better on phones</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="mb-0">
                        <li><strong>Direct Focus:</strong> Customers see suppliers, not maps</li>
                        <li><strong>Complete Profiles:</strong> Full business info + products</li>
                        <li><strong>Easy Ordering:</strong> Direct product ordering buttons</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openNewDashboard() {
            window.open('dashboard.php', '_blank');
        }
        
        function testAPI() {
            window.open('../php/get_suppliers_with_products.php?lat=21.1320832&lng=79.0953984&radius=25', '_blank');
        }
        
        async function loadPreview() {
            const preview = document.getElementById('preview');
            preview.innerHTML = '<div class="spinner-border"></div><p>Loading preview...</p>';
            
            try {
                const response = await fetch('../php/get_suppliers_with_products.php?lat=21.1320832&lng=79.0953984&radius=25');
                const data = await response.json();
                
                if (data.success && data.suppliers.length > 0) {
                    const supplier = data.suppliers[0];
                    
                    let html = `
                        <div class="alert alert-success text-start">
                            <h6>✅ Found ${data.suppliers.length} supplier(s)</h6>
                            <p class="mb-0">${data.message}</p>
                        </div>
                        
                        <div class="card text-start">
                            <div class="card-body">
                                <h6>
                                    <span class="badge bg-success me-2">📍 Nearby</span>
                                    🏪 ${supplier.business_name}
                                </h6>
                                <p class="text-muted mb-2">👤 ${supplier.contact_name}</p>
                                <p class="text-muted mb-2">📞 ${supplier.phone}</p>
                                <p class="text-muted mb-2">📍 ${supplier.address}</p>
                                <p class="text-muted mb-2">📦 ${supplier.product_count} products available</p>
                                <p class="text-muted mb-0">⭐ ${supplier.avg_rating}/5 rating</p>
                                
                                <div class="mt-3">
                                    <h6>📦 Sample Products:</h6>
                                    <div class="row">
                    `;
                    
                    supplier.products.slice(0, 3).forEach(product => {
                        html += `
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body p-2 text-center">
                                        <h6 class="card-title">${product.name}</h6>
                                        <p class="text-success">₹${product.price}</p>
                                        <small class="text-muted">${product.description}</small>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    preview.innerHTML = html;
                } else {
                    preview.innerHTML = `
                        <div class="alert alert-warning">
                            <h6>⚠️ No suppliers found</h6>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                preview.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ Error loading preview</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        // Auto-load preview
        setTimeout(loadPreview, 1000);
    </script>
</body>
</html>
