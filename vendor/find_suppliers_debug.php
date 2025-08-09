<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Find Suppliers Debug</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>Dashboard Find Suppliers Debug</h2>
        
        <div class="alert alert-info">
            <h6>Debug Steps:</h6>
            <ol>
                <li>Test API directly</li>
                <li>Test DOM elements</li>
                <li>Test exact dashboard function</li>
                <li>Check for JavaScript errors</li>
            </ol>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <button onclick="step1_testAPI()" class="btn btn-primary w-100">1. Test API</button>
            </div>
            <div class="col-md-3">
                <button onclick="step2_testDOM()" class="btn btn-success w-100">2. Test DOM</button>
            </div>
            <div class="col-md-3">
                <button onclick="step3_testFunction()" class="btn btn-warning w-100">3. Test Function</button>
            </div>
            <div class="col-md-3">
                <button onclick="step4_fullDebug()" class="btn btn-danger w-100">4. Full Debug</button>
            </div>
        </div>
        
        <!-- Console Output -->
        <div class="card mb-3">
            <div class="card-header"><h6>📋 Debug Results</h6></div>
            <div class="card-body">
                <div id="debugOutput" style="font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>
            </div>
        </div>
        
        <!-- Supplier Container Test -->
        <div class="card">
            <div class="card-header"><h6>🏪 Suppliers Container Test</h6></div>
            <div class="card-body">
                <div id="suppliersContainer">This is the test suppliers container</div>
            </div>
        </div>
    </div>

    <script>
        let debugLog = '';
        
        function log(message) {
            const timestamp = new Date().toLocaleTimeString();
            debugLog += `[${timestamp}] ${message}\n`;
            document.getElementById('debugOutput').textContent = debugLog;
            console.log(message);
        }
        
        // Step 1: Test API
        async function step1_testAPI() {
            log('=== STEP 1: Testing API ===');
            
            try {
                const response = await fetch('../php/minimal_api.php');
                log(`API Response Status: ${response.status}`);
                log(`API Response OK: ${response.ok}`);
                
                const text = await response.text();
                log(`API Response Length: ${text.length} characters`);
                log(`API Response Start: ${text.substring(0, 100)}...`);
                
                if (text.startsWith('{')) {
                    const data = JSON.parse(text);
                    log(`✅ JSON Parse SUCCESS`);
                    log(`API Success: ${data.success}`);
                    log(`Suppliers Count: ${data.suppliers ? data.suppliers.length : 'undefined'}`);
                    log(`Data structure: ${JSON.stringify(Object.keys(data))}`);
                } else {
                    log(`❌ NON-JSON Response: ${text}`);
                }
            } catch (error) {
                log(`❌ API ERROR: ${error.message}`);
            }
        }
        
        // Step 2: Test DOM elements
        function step2_testDOM() {
            log('=== STEP 2: Testing DOM Elements ===');
            
            // Check if elements exist
            const findBtn = document.getElementById('findSuppliersBtn');
            const container = document.getElementById('suppliersContainer');
            const radiusSelect = document.getElementById('radiusSelect');
            
            log(`findSuppliersBtn exists: ${!!findBtn}`);
            log(`suppliersContainer exists: ${!!container}`);
            log(`radiusSelect exists: ${!!radiusSelect}`);
            
            if (findBtn) {
                log(`findSuppliersBtn innerHTML: "${findBtn.innerHTML}"`);
                log(`findSuppliersBtn disabled: ${findBtn.disabled}`);
                log(`findSuppliersBtn onclick: ${findBtn.onclick}`);
                log(`findSuppliersBtn className: "${findBtn.className}"`);
            }
            
            if (container) {
                log(`suppliersContainer innerHTML length: ${container.innerHTML.length}`);
                log(`suppliersContainer innerHTML start: "${container.innerHTML.substring(0, 100)}"`);
            }
            
            // Test if we can update the container
            try {
                container.innerHTML = '<div class="alert alert-success">✅ DOM Test - Container Updated Successfully!</div>';
                log(`✅ DOM UPDATE SUCCESS`);
            } catch (error) {
                log(`❌ DOM UPDATE ERROR: ${error.message}`);
            }
        }
        
        // Step 3: Test the exact function from dashboard
        async function step3_testFunction() {
            log('=== STEP 3: Testing Dashboard Function ===');
            
            // Mock the required variables and functions
            window.userPosition = { lat: 28.6139, lng: 77.2090 };
            window.findSuppliersInProgress = false;
            
            // Mock showAlert function if it doesn't exist
            if (typeof showAlert === 'undefined') {
                window.showAlert = function(type, message) {
                    log(`ALERT [${type}]: ${message}`);
                };
            }
            
            // Copy the exact function from dashboard
            async function findSuppliersOnMap() {
                log('findSuppliersOnMap function called');
                
                // Prevent recursive calls
                if (window.findSuppliersInProgress) {
                    log('⚠️ findSuppliersOnMap already in progress, skipping...');
                    return;
                }
                
                window.findSuppliersInProgress = true;
                log('Set findSuppliersInProgress = true');
                
                try {
                    let searchLat, searchLng;
                    
                    // Use tracked location or default to Delhi
                    if (!userPosition) {
                        searchLat = 28.6139;
                        searchLng = 77.2090;
                        log('Using default coordinates (Delhi): ' + searchLat + ', ' + searchLng);
                    } else {
                        searchLat = userPosition.lat;
                        searchLng = userPosition.lng;
                        log('Using tracked coordinates: ' + searchLat + ', ' + searchLng);
                    }
                
                    const radius = 50; // Default radius since we might not have the select
                    const container = document.getElementById('suppliersContainer');
                    
                    log('Setting loading state...');
                    container.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">🔍 Searching for suppliers...</p>
                        </div>
                    `;
                    
                    try {
                        // Make API call
                        const url = `../php/minimal_api.php`;
                        log('Making API call to: ' + url);
                        
                        const response = await fetch(url, {
                            method: 'GET',
                            credentials: 'same-origin'
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const text = await response.text();
                        log('API response received, length: ' + text.length);
                        
                        let data;
                        try {
                            data = JSON.parse(text);
                            log('JSON parsed successfully');
                        } catch (e) {
                            log('JSON parse error: ' + e.message);
                            throw new Error('Invalid JSON response from server');
                        }
                        
                        log('Parsed data: ' + JSON.stringify(data, null, 2));
                        
                        if (data.success) {
                            const suppliers = data.suppliers || data.data || [];
                            log('Found suppliers: ' + suppliers.length);
                            
                            // Simple display instead of complex function
                            if (suppliers.length > 0) {
                                let html = '<div class="alert alert-success">✅ Found ' + suppliers.length + ' suppliers!</div>';
                                suppliers.forEach((supplier, index) => {
                                    html += `
                                        <div class="card mb-2">
                                            <div class="card-body">
                                                <h6>🏪 ${supplier.name}</h6>
                                                <p class="text-muted">📍 ${supplier.address || 'No address'}</p>
                                                <p class="text-muted">📞 ${supplier.phone || 'No phone'}</p>
                                                <p class="text-muted">Distance: ${supplier.distance}km</p>
                                                ${supplier.is_nearby ? '<span class="badge bg-success">Nearby</span>' : '<span class="badge bg-secondary">Far</span>'}
                                            </div>
                                        </div>
                                    `;
                                });
                                container.innerHTML = html;
                                log('✅ Suppliers displayed successfully');
                            } else {
                                container.innerHTML = '<div class="alert alert-warning">No suppliers found</div>';
                                log('No suppliers to display');
                            }
                        } else {
                            log('API returned success=false: ' + (data.message || 'Unknown error'));
                            container.innerHTML = '<div class="alert alert-danger">API Error: ' + (data.message || 'Unknown error') + '</div>';
                        }
                        
                    } catch (error) {
                        log('Function error: ' + error.message);
                        container.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
                    } finally {
                        window.findSuppliersInProgress = false;
                        log('Set findSuppliersInProgress = false');
                    }
                    
                } catch (outerError) {
                    log('Outer function error: ' + outerError.message);
                    window.findSuppliersInProgress = false;
                }
            }
            
            // Call the function
            try {
                await findSuppliersOnMap();
                log('✅ Function completed');
            } catch (error) {
                log('❌ Function call error: ' + error.message);
            }
        }
        
        // Step 4: Full debug with all checks
        async function step4_fullDebug() {
            log('=== STEP 4: Full Debug ===');
            await step1_testAPI();
            step2_testDOM();
            await step3_testFunction();
            log('=== DEBUG COMPLETE ===');
        }
        
        // Auto-run first test
        window.addEventListener('load', () => {
            log('Debug page loaded, starting API test...');
            step1_testAPI();
        });
    </script>
</body>
</html>
