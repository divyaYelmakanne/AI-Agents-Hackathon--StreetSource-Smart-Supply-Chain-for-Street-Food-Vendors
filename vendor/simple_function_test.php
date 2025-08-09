<!DOCTYPE html>
<html>
<head>
    <title>Simple Supplier Test</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>Simple Supplier Function Test</h2>
        
        <div class="alert alert-info">
            <h6>This will test if the supplier loading functions work at all</h6>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <button onclick="testBasicFunction()" class="btn btn-primary w-100">Test Basic Function</button>
            </div>
            <div class="col-md-4">
                <button onclick="testAPI()" class="btn btn-success w-100">Test API Only</button>
            </div>
            <div class="col-md-4">
                <button onclick="testDOMUpdate()" class="btn btn-warning w-100">Test DOM Update</button>
            </div>
        </div>
        
        <!-- Test Container -->
        <div class="card">
            <div class="card-header"><h6>🏪 Test Results</h6></div>
            <div class="card-body">
                <div id="suppliersContainer">
                    <div class="text-center text-muted py-4">
                        <p>🗺️ Click "Find Suppliers" to see all available suppliers</p>
                        <p><small>We'll show nearby suppliers first, then all others</small></p>
                        <button class="btn btn-primary" onclick="testBasicFunction()">🔍 Find All Suppliers</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Console Log -->
        <div class="card mt-3">
            <div class="card-header"><h6>📋 Console Output</h6></div>
            <div class="card-body">
                <div id="logOutput" style="font-family: monospace; background: #f8f9fa; padding: 10px; max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>

    <script>
        // Capture console logs
        const logDiv = document.getElementById('logOutput');
        
        function addLog(message) {
            const timestamp = new Date().toLocaleTimeString();
            logDiv.innerHTML += `[${timestamp}] ${message}<br>`;
            logDiv.scrollTop = logDiv.scrollHeight;
            console.log(message);
        }
        
        // Test 1: Basic function test
        function testBasicFunction() {
            addLog('🧪 testBasicFunction called');
            
            const container = document.getElementById('suppliersContainer');
            if (!container) {
                addLog('❌ ERROR: suppliersContainer not found!');
                return;
            }
            
            addLog('✅ Container found, updating...');
            container.innerHTML = '<div class="alert alert-success">✅ Basic function test successful! DOM update working.</div>';
            addLog('✅ Container updated successfully');
            
            // Test API after basic test
            setTimeout(() => {
                testAPI();
            }, 1000);
        }
        
        // Test 2: API test
        async function testAPI() {
            addLog('🧪 testAPI called');
            
            const container = document.getElementById('suppliersContainer');
            container.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Testing API...</p></div>';
            
            try {
                addLog('📡 Making API request...');
                const response = await fetch('../php/minimal_api.php');
                addLog(`📡 API Response: ${response.status} ${response.statusText}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const text = await response.text();
                addLog(`📡 Response length: ${text.length} characters`);
                addLog(`📡 Response starts with: ${text.substring(0, 50)}...`);
                
                if (text.startsWith('{')) {
                    const data = JSON.parse(text);
                    addLog('✅ JSON parsed successfully');
                    addLog(`📊 Success: ${data.success}`);
                    addLog(`📊 Suppliers: ${data.suppliers ? data.suppliers.length : 0}`);
                    
                    if (data.success && data.suppliers && data.suppliers.length > 0) {
                        displaySuppliers(data.suppliers);
                        addLog('✅ Suppliers displayed');
                    } else {
                        container.innerHTML = '<div class="alert alert-warning">⚠️ API returned no suppliers</div>';
                        addLog('⚠️ No suppliers in response');
                    }
                } else {
                    container.innerHTML = '<div class="alert alert-danger">❌ Non-JSON response</div>';
                    addLog('❌ Response is not JSON');
                }
                
            } catch (error) {
                addLog(`❌ API Error: ${error.message}`);
                container.innerHTML = `<div class="alert alert-danger">❌ API Error: ${error.message}</div>`;
            }
        }
        
        // Test 3: DOM update test
        function testDOMUpdate() {
            addLog('🧪 testDOMUpdate called');
            
            const container = document.getElementById('suppliersContainer');
            if (!container) {
                addLog('❌ Container not found');
                return;
            }
            
            let counter = 0;
            const interval = setInterval(() => {
                counter++;
                container.innerHTML = `<div class="alert alert-info">DOM Update Test ${counter}/5</div>`;
                addLog(`DOM updated ${counter}/5`);
                
                if (counter >= 5) {
                    clearInterval(interval);
                    container.innerHTML = '<div class="alert alert-success">✅ DOM update test completed</div>';
                    addLog('✅ DOM update test completed');
                }
            }, 500);
        }
        
        // Display suppliers function
        function displaySuppliers(suppliers) {
            addLog(`🏪 displaySuppliers called with ${suppliers.length} suppliers`);
            
            const container = document.getElementById('suppliersContainer');
            
            let html = `<div class="alert alert-success">✅ Found ${suppliers.length} suppliers!</div>`;
            
            suppliers.forEach((supplier, index) => {
                html += `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <h6>🏪 ${supplier.name}</h6>
                            <p class="text-muted small mb-1">📍 ${supplier.address || 'No address'}</p>
                            <p class="text-muted small mb-1">📞 ${supplier.phone || 'No phone'}</p>
                            <p class="text-muted small mb-0">
                                📏 Distance: ${supplier.distance}km | 
                                ⭐ Rating: ${supplier.avg_rating || 0}/5
                            </p>
                            <div class="mt-2">
                                ${supplier.is_nearby ? 
                                    '<span class="badge bg-success">📍 Nearby</span>' : 
                                    '<span class="badge bg-secondary">📍 Far</span>'
                                }
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="testAPI()">🔄 Reload Suppliers</button>
                    <button class="btn btn-success" onclick="window.open('../vendor/dashboard.php', '_blank')">📱 Open Dashboard</button>
                </div>
            `;
            
            container.innerHTML = html;
            addLog('✅ Suppliers HTML generated and inserted');
        }
        
        // Auto-start basic test
        window.addEventListener('load', () => {
            addLog('🚀 Page loaded, starting basic test...');
            setTimeout(() => {
                testBasicFunction();
            }, 1000);
        });
    </script>
</body>
</html>
