<!DOCTYPE html>
<html>
<head>
    <title>🏪 Supplier Search Debug</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>🏪 Supplier Search Debug</h2>
        
        <div class="alert alert-success">
            <h5>✅ Fixed Issue: Now Shows Real Suppliers!</h5>
            <p><strong>Solution:</strong> Enhanced API to show nearby suppliers first, then all suppliers if none nearby</p>
            <p><strong>Your Supplier:</strong> Sanket Mali ("New Supplier") with 6 products at coordinates 21.13, 79.09</p>
        </div>
        
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>🎯 Search Strategy</h6>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li><strong>First:</strong> Search within specified radius (10-50km)</li>
                            <li><strong>If no results:</strong> Show all suppliers from database</li>
                            <li><strong>Sort by:</strong> Distance (nearest first)</li>
                            <li><strong>Mark nearby:</strong> Suppliers within 10km as "nearby"</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>🔧 Fixes Applied</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li>✅ Created real database API</li>
                            <li>✅ Updated dashboard to use real API</li>
                            <li>✅ Enhanced search logic</li>
                            <li>✅ Fixed default coordinates</li>
                            <li>✅ Added fallback to show all suppliers</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6>🧪 Live API Test</h6>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <label>Search Coordinates:</label>
                        <input type="number" id="lat" class="form-control" value="21.1320832" step="any">
                        <small class="text-muted">Latitude</small>
                    </div>
                    <div class="col-md-3">
                        <input type="number" id="lng" class="form-control" value="79.0953984" step="any" style="margin-top: 24px;">
                        <small class="text-muted">Longitude</small>
                    </div>
                    <div class="col-md-3">
                        <label>Radius (km):</label>
                        <select id="radius" class="form-control">
                            <option value="5">5 km</option>
                            <option value="10" selected>10 km</option>
                            <option value="25">25 km</option>
                            <option value="50">50 km</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button onclick="testSearch()" class="btn btn-primary w-100" style="margin-top: 24px;">
                            🔍 Test Search
                        </button>
                    </div>
                </div>
                
                <div id="results">Click "Test Search" to see results</div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6>📋 Test Results</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <button onclick="testNearby()" class="btn btn-success w-100">Test: Exact Location</button>
                        <small class="text-muted">Should find Sanket Mali nearby</small>
                    </div>
                    <div class="col-md-4">
                        <button onclick="testFar()" class="btn btn-warning w-100">Test: Far Location</button>
                        <small class="text-muted">Should show all suppliers</small>
                    </div>
                    <div class="col-md-4">
                        <button onclick="openDashboard()" class="btn btn-info w-100">Open Dashboard</button>
                        <small class="text-muted">Test live dashboard</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function testSearch() {
            const lat = document.getElementById('lat').value;
            const lng = document.getElementById('lng').value;
            const radius = document.getElementById('radius').value;
            const results = document.getElementById('results');
            
            results.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
            
            try {
                const url = `../php/get_real_suppliers.php?lat=${lat}&lng=${lng}&radius=${radius}`;
                console.log('Testing URL:', url);
                
                const response = await fetch(url);
                const data = await response.json();
                
                console.log('API Response:', data);
                
                if (data.success && data.suppliers.length > 0) {
                    let html = `
                        <div class="alert alert-success">
                            <h6>✅ Success: ${data.message}</h6>
                            <p>Found ${data.total_found} suppliers (${data.nearby_count} nearby)</p>
                        </div>
                    `;
                    
                    data.suppliers.forEach(supplier => {
                        html += `
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">🏪 ${supplier.name}</h6>
                                            <p class="mb-1 text-muted">📞 ${supplier.phone}</p>
                                            <p class="mb-1 text-muted">📧 ${supplier.email}</p>
                                            <p class="mb-1 text-muted">📍 ${supplier.address}</p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge ${supplier.is_nearby ? 'bg-success' : 'bg-secondary'}">
                                                ${supplier.distance}km away
                                            </span>
                                            <br>
                                            <span class="badge bg-info mt-1">
                                                📦 ${supplier.product_count} products
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    results.innerHTML = html;
                } else {
                    results.innerHTML = `
                        <div class="alert alert-warning">
                            <h6>⚠️ ${data.message}</h6>
                            <p>Try increasing the radius or testing different coordinates</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                console.error('Error:', error);
                results.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ Error</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        function testNearby() {
            document.getElementById('lat').value = '21.1320832';
            document.getElementById('lng').value = '79.0953984';
            document.getElementById('radius').value = '10';
            testSearch();
        }
        
        function testFar() {
            document.getElementById('lat').value = '28.6139';
            document.getElementById('lng').value = '77.2090';
            document.getElementById('radius').value = '50';
            testSearch();
        }
        
        function openDashboard() {
            window.open('dashboard.php', '_blank');
        }
        
        // Auto-run nearby test
        window.addEventListener('load', () => {
            setTimeout(testNearby, 1000);
        });
    </script>
</body>
</html>
