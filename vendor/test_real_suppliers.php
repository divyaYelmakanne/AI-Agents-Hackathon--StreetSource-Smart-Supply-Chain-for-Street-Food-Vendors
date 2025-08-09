<!DOCTYPE html>
<html>
<head>
    <title>Real Suppliers API Test</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>🏪 Real Suppliers API Test</h2>
        
        <div class="alert alert-info">
            <strong>Testing real suppliers from your database!</strong>
            <br>This will show suppliers that actually exist in your StreetSource system.
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <button onclick="testNearSupplier()" class="btn btn-success w-100">Test Near Supplier</button>
                <small class="text-muted">Test with coordinates near your existing supplier</small>
            </div>
            <div class="col-md-4">
                <button onclick="testDefaultLocation()" class="btn btn-primary w-100">Test Default (Delhi)</button>
                <small class="text-muted">Test with default Delhi coordinates</small>
            </div>
            <div class="col-md-4">
                <button onclick="testCustomLocation()" class="btn btn-warning w-100">Test Custom Location</button>
                <small class="text-muted">Test with custom coordinates</small>
            </div>
        </div>
        
        <!-- Custom location input -->
        <div id="customLocationPanel" class="card mb-3" style="display: none;">
            <div class="card-body">
                <h6>Custom Location Test</h6>
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="number" id="customLat" class="form-control" placeholder="Latitude" value="21.13">
                    </div>
                    <div class="col-md-3">
                        <input type="number" id="customLng" class="form-control" placeholder="Longitude" value="79.09">
                    </div>
                    <div class="col-md-3">
                        <input type="number" id="customRadius" class="form-control" placeholder="Radius (km)" value="50">
                    </div>
                    <div class="col-md-3">
                        <button onclick="runCustomTest()" class="btn btn-success w-100">Run Test</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Results -->
        <div class="card">
            <div class="card-header">
                <h6>📋 Test Results</h6>
            </div>
            <div class="card-body">
                <div id="results">Click a test button to start</div>
            </div>
        </div>
        
        <!-- Log -->
        <div class="card mt-3">
            <div class="card-header">
                <h6>📝 API Log</h6>
            </div>
            <div class="card-body">
                <div id="logOutput" style="font-family: monospace; background: #f8f9fa; padding: 10px; max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>

    <script>
        let logDiv = document.getElementById('logOutput');
        
        function addLog(message) {
            const timestamp = new Date().toLocaleTimeString();
            logDiv.innerHTML += `[${timestamp}] ${message}<br>`;
            logDiv.scrollTop = logDiv.scrollHeight;
            console.log(message);
        }
        
        async function testAPI(lat, lng, radius, testName) {
            const results = document.getElementById('results');
            
            addLog(`🧪 Starting ${testName}`);
            addLog(`📍 Coordinates: ${lat}, ${lng}, Radius: ${radius}km`);
            
            results.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Testing API...</p></div>';
            
            try {
                const url = `../php/get_real_suppliers.php?lat=${lat}&lng=${lng}&radius=${radius}`;
                addLog(`📡 API URL: ${url}`);
                
                const response = await fetch(url);
                addLog(`📡 Response: ${response.status} ${response.statusText}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const text = await response.text();
                addLog(`📡 Response length: ${text.length} characters`);
                
                const data = JSON.parse(text);
                addLog(`✅ JSON parsed successfully`);
                addLog(`📊 Success: ${data.success}`);
                addLog(`📊 Total suppliers: ${data.total_found}`);
                addLog(`📊 Nearby suppliers: ${data.nearby_count}`);
                
                // Display results
                if (data.success && data.suppliers && data.suppliers.length > 0) {
                    let html = `
                        <div class="alert alert-success">
                            <h6>✅ ${testName} Successful!</h6>
                            <p>Found ${data.total_found} suppliers (${data.nearby_count} nearby)</p>
                        </div>
                    `;
                    
                    data.suppliers.forEach(supplier => {
                        html += `
                            <div class="card mb-2">
                                <div class="card-body">
                                    <h6>🏪 ${supplier.name}</h6>
                                    <p class="text-muted mb-1"><strong>Contact:</strong> ${supplier.contact_name}</p>
                                    <p class="text-muted mb-1"><strong>Phone:</strong> ${supplier.phone}</p>
                                    <p class="text-muted mb-1"><strong>Email:</strong> ${supplier.email}</p>
                                    <p class="text-muted mb-1"><strong>Address:</strong> ${supplier.address}</p>
                                    <p class="text-muted mb-1"><strong>Distance:</strong> ${supplier.distance}km</p>
                                    <p class="text-muted mb-1"><strong>Products:</strong> ${supplier.product_count} items</p>
                                    <p class="text-muted mb-0"><strong>Rating:</strong> ${supplier.avg_rating}/5 (${supplier.review_count} reviews)</p>
                                    <div class="mt-2">
                                        ${supplier.is_nearby ? 
                                            '<span class="badge bg-success">📍 Nearby</span>' : 
                                            '<span class="badge bg-secondary">📍 Far</span>'
                                        }
                                        <span class="badge bg-info">🏪 ID: ${supplier.id}</span>
                                        <span class="badge bg-warning">📦 ${supplier.product_count} products</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    results.innerHTML = html;
                    addLog(`✅ ${data.suppliers.length} suppliers displayed`);
                    
                } else if (data.success && data.suppliers && data.suppliers.length === 0) {
                    results.innerHTML = `
                        <div class="alert alert-warning">
                            <h6>⚠️ No Suppliers Found</h6>
                            <p>${data.message}</p>
                            <p>Try increasing the radius or testing near coordinates: 21.13, 79.09</p>
                        </div>
                    `;
                    addLog(`⚠️ No suppliers found in ${radius}km radius`);
                    
                } else {
                    results.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>❌ API Error</h6>
                            <p>${data.error || 'Unknown error'}</p>
                        </div>
                    `;
                    addLog(`❌ API returned error: ${data.error || 'Unknown error'}`);
                }
                
            } catch (error) {
                addLog(`❌ Test failed: ${error.message}`);
                results.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ ${testName} Failed</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        // Test functions
        function testNearSupplier() {
            testAPI(21.13, 79.09, 50, 'Near Supplier Test');
        }
        
        function testDefaultLocation() {
            testAPI(28.6139, 77.2090, 50, 'Default Location Test');
        }
        
        function testCustomLocation() {
            document.getElementById('customLocationPanel').style.display = 'block';
        }
        
        function runCustomTest() {
            const lat = parseFloat(document.getElementById('customLat').value);
            const lng = parseFloat(document.getElementById('customLng').value);
            const radius = parseInt(document.getElementById('customRadius').value);
            
            if (isNaN(lat) || isNaN(lng) || isNaN(radius)) {
                alert('Please enter valid numbers for coordinates and radius');
                return;
            }
            
            testAPI(lat, lng, radius, 'Custom Location Test');
        }
        
        // Auto-run test
        window.addEventListener('load', () => {
            addLog('🚀 Real Suppliers API Test loaded');
            setTimeout(() => {
                testNearSupplier();
            }, 1000);
        });
    </script>
</body>
</html>
