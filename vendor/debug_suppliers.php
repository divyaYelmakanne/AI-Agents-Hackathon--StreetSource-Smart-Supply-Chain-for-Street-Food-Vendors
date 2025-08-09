<!DOCTYPE html>
<html>
<head>
    <title>Supplier Loading Debug</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>Supplier Loading Debug</h2>
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <button id="testAPIBtn" class="btn btn-primary w-100" onclick="testAPI()">1. Test API</button>
            </div>
            <div class="col-md-4">
                <button id="testDisplayBtn" class="btn btn-success w-100" onclick="testDisplay()">2. Test Display</button>
            </div>
            <div class="col-md-4">
                <button id="fullTestBtn" class="btn btn-warning w-100" onclick="fullTest()">3. Full Test</button>
            </div>
        </div>
        
        <!-- Results -->
        <div class="card mb-3">
            <div class="card-header"><h6>API Test Results</h6></div>
            <div class="card-body">
                <div id="apiResults">Click "Test API" to start</div>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-header"><h6>Suppliers Display</h6></div>
            <div class="card-body">
                <div id="suppliersContainer">Click "Test Display" to start</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h6>Console Log</h6></div>
            <div class="card-body">
                <div id="consoleLog" style="font-family: monospace; background: #f8f9fa; padding: 10px; max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>

    <script>
        // Capture console logs
        const originalLog = console.log;
        const originalError = console.error;
        const logDiv = document.getElementById('consoleLog');
        
        function addToLog(level, message) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.innerHTML = `[${timestamp}] ${level}: ${message}`;
            logEntry.className = level === 'ERROR' ? 'text-danger' : level === 'WARN' ? 'text-warning' : 'text-dark';
            logDiv.appendChild(logEntry);
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToLog('LOG', args.join(' '));
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addToLog('ERROR', args.join(' '));
        };
        
        // Test 1: API
        async function testAPI() {
            const btn = document.getElementById('testAPIBtn');
            const results = document.getElementById('apiResults');
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
            btn.disabled = true;
            
            try {
                console.log('Testing minimal API...');
                const response = await fetch('../php/minimal_api.php');
                console.log('Response status:', response.status);
                
                const text = await response.text();
                console.log('Response text length:', text.length);
                console.log('Response starts with:', text.substring(0, 50));
                
                if (text.startsWith('{')) {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON successfully');
                    console.log('Suppliers found:', data.suppliers?.length || 0);
                    
                    results.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ API Working</h6>
                            <p>Status: ${response.status}</p>
                            <p>Suppliers: ${data.suppliers?.length || 0}</p>
                            <p>Success: ${data.success}</p>
                            <details>
                                <summary>Raw JSON (first 500 chars)</summary>
                                <pre>${text.substring(0, 500)}...</pre>
                            </details>
                        </div>
                    `;
                } else {
                    results.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>❌ Non-JSON Response</h6>
                            <pre>${text.substring(0, 200)}</pre>
                        </div>
                    `;
                }
                
                btn.innerHTML = '✅ API Tested';
                btn.className = 'btn btn-success w-100';
                
            } catch (error) {
                console.error('API test error:', error);
                results.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ API Error</h6>
                        <p>${error.message}</p>
                    </div>
                `;
                btn.innerHTML = '❌ API Failed';
                btn.className = 'btn btn-danger w-100';
            }
            
            btn.disabled = false;
        }
        
        // Test 2: Display
        async function testDisplay() {
            const btn = document.getElementById('testDisplayBtn');
            const container = document.getElementById('suppliersContainer');
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
            btn.disabled = true;
            
            try {
                console.log('Testing supplier display...');
                
                // Get data from API
                const response = await fetch('../php/minimal_api.php');
                const data = await response.json();
                
                console.log('Display test - data received:', data);
                
                if (data.success && data.suppliers) {
                    const suppliers = data.suppliers;
                    console.log('Displaying', suppliers.length, 'suppliers');
                    
                    // Test the display function
                    displaySuppliers(suppliers);
                    
                    btn.innerHTML = `✅ Displayed ${suppliers.length}`;
                    btn.className = 'btn btn-success w-100';
                } else {
                    container.innerHTML = '<div class="alert alert-warning">No suppliers in API response</div>';
                    btn.innerHTML = '⚠️ No Data';
                    btn.className = 'btn btn-warning w-100';
                }
                
            } catch (error) {
                console.error('Display test error:', error);
                container.innerHTML = `<div class="alert alert-danger">Display error: ${error.message}</div>`;
                btn.innerHTML = '❌ Display Failed';
                btn.className = 'btn btn-danger w-100';
            }
            
            btn.disabled = false;
        }
        
        // Test 3: Full Test (same as dashboard)
        async function fullTest() {
            const btn = document.getElementById('fullTestBtn');
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Full Test...';
            btn.disabled = true;
            
            try {
                console.log('Starting full test (simulating dashboard)...');
                
                // Simulate the exact same function as dashboard
                await findSuppliersOnMap();
                
                btn.innerHTML = '✅ Full Test Done';
                btn.className = 'btn btn-success w-100';
                
            } catch (error) {
                console.error('Full test error:', error);
                btn.innerHTML = '❌ Full Test Failed';
                btn.className = 'btn btn-danger w-100';
            }
            
            btn.disabled = false;
        }
        
        // Copy of displaySuppliers function from dashboard
        function displaySuppliers(suppliers) {
            console.log('displaySuppliers called with:', suppliers);
            const container = document.getElementById('suppliersContainer');
            console.log('Container found:', container);
            
            if (!suppliers || suppliers.length === 0) {
                console.log('No suppliers to display');
                container.innerHTML = `
                    <div class="text-center text-muted">
                        <p>🔍 No suppliers found</p>
                    </div>
                `;
                return;
            }
            
            console.log('Processing', suppliers.length, 'suppliers');
            
            let html = `<div class="alert alert-info">Found ${suppliers.length} suppliers</div>`;
            
            suppliers.forEach((supplier, index) => {
                const rating = supplier.avg_rating || 0;
                const isNearby = supplier.is_nearby || false;
                const nearbyBadge = isNearby ? '<span class="badge bg-success me-2">📍 Nearby</span>' : '';
                
                html += `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <h6>${nearbyBadge}🏪 ${supplier.name}</h6>
                            <p class="text-muted small mb-1">📍 ${supplier.address || 'Address not provided'}</p>
                            <p class="text-muted small mb-1">📞 ${supplier.phone || 'Phone not provided'}</p>
                            <p class="text-muted small mb-0">📏 Distance: ${supplier.distance}km | ⭐ Rating: ${rating}/5</p>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            console.log('Suppliers displayed successfully');
        }
        
        // Copy of findSuppliersOnMap function from dashboard
        async function findSuppliersOnMap() {
            console.log('findSuppliersOnMap called (debug version)');
            
            const container = document.getElementById('suppliersContainer');
            container.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Searching...</p></div>';
            
            try {
                const url = `../php/minimal_api.php`;
                console.log('API URL:', url);
                
                const response = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const text = await response.text();
                console.log('Raw response length:', text.length);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response from server');
                }
                
                console.log('Parsed data:', data);
                
                if (data.success) {
                    const suppliers = data.suppliers || data.data || [];
                    console.log('Found suppliers:', suppliers.length);
                    
                    displaySuppliers(suppliers);
                } else {
                    console.error('API error:', data.message);
                    container.innerHTML = `<div class="alert alert-warning">API returned error: ${data.message || 'Unknown error'}</div>`;
                }
                
            } catch (error) {
                console.error('findSuppliersOnMap error:', error);
                container.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }
        
        // Auto-run API test
        window.addEventListener('load', () => {
            console.log('Debug page loaded');
            testAPI();
        });
    </script>
</body>
</html>
