<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>Dashboard Debug Tests</h2>
        
        <div class="row g-4">
            <!-- API Test -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>1. API Test</h5>
                    </div>
                    <div class="card-body">
                        <button onclick="testAPI()" class="btn btn-primary">Test Minimal API</button>
                        <div id="apiResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <!-- Location Test -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>2. Location Test</h5>
                    </div>
                    <div class="card-body">
                        <button onclick="testLocation()" class="btn btn-success">Test Location</button>
                        <div id="locationResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <!-- Map Test -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>3. Map Test</h5>
                    </div>
                    <div class="card-body">
                        <button onclick="testMap()" class="btn btn-warning">Test Google Maps</button>
                        <div id="mapResult" class="mt-3"></div>
                        <div id="mapContainer" style="height: 300px; width: 100%; margin-top: 10px; display: none;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Supplier Display Test -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>4. Supplier Display Test</h5>
                    </div>
                    <div class="card-body">
                        <button onclick="testSupplierDisplay()" class="btn btn-info">Test Supplier Display</button>
                        <div id="supplierDisplayResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Test 1: API
        async function testAPI() {
            const result = document.getElementById('apiResult');
            result.innerHTML = '<div class="spinner-border"></div> Testing...';
            
            try {
                const response = await fetch('../php/minimal_api.php');
                const text = await response.text();
                
                console.log('API Raw Response:', text);
                
                if (text.startsWith('{')) {
                    const data = JSON.parse(text);
                    result.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ API Success</h6>
                            <p>Total: ${data.total_found} suppliers</p>
                            <p>Nearby: ${data.nearby_count} suppliers</p>
                            <p>Data length: ${data.suppliers.length}</p>
                            <details>
                                <summary>Raw JSON</summary>
                                <pre>${JSON.stringify(data, null, 2)}</pre>
                            </details>
                        </div>
                    `;
                } else {
                    result.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>❌ API Failed</h6>
                            <p>Non-JSON response received</p>
                            <pre>${text}</pre>
                        </div>
                    `;
                }
            } catch (error) {
                result.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ API Error</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        // Test 2: Location
        function testLocation() {
            const result = document.getElementById('locationResult');
            result.innerHTML = '<div class="spinner-border"></div> Getting location...';
            
            if (!navigator.geolocation) {
                result.innerHTML = `
                    <div class="alert alert-warning">
                        <h6>⚠️ Geolocation Not Supported</h6>
                        <p>Your browser doesn't support geolocation</p>
                    </div>
                `;
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    result.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ Location Success</h6>
                            <p>Lat: ${position.coords.latitude}</p>
                            <p>Lng: ${position.coords.longitude}</p>
                            <p>Accuracy: ${position.coords.accuracy}m</p>
                        </div>
                    `;
                },
                (error) => {
                    result.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>❌ Location Error</h6>
                            <p>Error: ${error.message}</p>
                            <p>Code: ${error.code}</p>
                        </div>
                    `;
                }
            );
        }
        
        // Test 3: Map
        function testMap() {
            const result = document.getElementById('mapResult');
            const container = document.getElementById('mapContainer');
            
            result.innerHTML = '<div class="spinner-border"></div> Testing Google Maps...';
            
            if (typeof google !== 'undefined' && google.maps) {
                // Google Maps is loaded
                try {
                    container.style.display = 'block';
                    const map = new google.maps.Map(container, {
                        center: { lat: 28.6139, lng: 77.2090 },
                        zoom: 12
                    });
                    
                    result.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ Google Maps Success</h6>
                            <p>Map initialized successfully</p>
                        </div>
                    `;
                } catch (error) {
                    result.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>❌ Map Creation Failed</h6>
                            <p>${error.message}</p>
                        </div>
                    `;
                }
            } else {
                result.innerHTML = `
                    <div class="alert alert-warning">
                        <h6>⚠️ Google Maps Not Loaded</h6>
                        <p>Google Maps API is not available</p>
                        <p>Check your API key and internet connection</p>
                    </div>
                `;
                
                // Try to load Google Maps
                loadGoogleMaps();
            }
        }
        
        // Test 4: Supplier Display
        async function testSupplierDisplay() {
            const result = document.getElementById('supplierDisplayResult');
            result.innerHTML = '<div class="spinner-border"></div> Testing supplier display...';
            
            try {
                // Get sample data from API
                const response = await fetch('../php/minimal_api.php');
                const data = await response.json();
                
                if (data.success && data.suppliers) {
                    // Test displaying suppliers
                    let html = '<div class="alert alert-success"><h6>✅ Supplier Display Test</h6></div>';
                    html += '<div class="row">';
                    
                    data.suppliers.forEach(supplier => {
                        html += `
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>${supplier.name}</h6>
                                        <p class="text-muted small">Distance: ${supplier.distance}km</p>
                                        <p class="text-muted small">Rating: ${supplier.avg_rating}/5</p>
                                        <p class="text-muted small">Address: ${supplier.address}</p>
                                        ${supplier.is_nearby ? '<span class="badge bg-success">Nearby</span>' : '<span class="badge bg-secondary">Not Nearby</span>'}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    result.innerHTML = html;
                } else {
                    result.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>❌ No Supplier Data</h6>
                            <p>API returned no suppliers</p>
                        </div>
                    `;
                }
            } catch (error) {
                result.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>❌ Supplier Display Error</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        function loadGoogleMaps() {
            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyCz07V966JMJPy1We6CyOH1ycDgmCDpsGI&callback=initMap';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }
        
        function initMap() {
            console.log('Google Maps loaded successfully');
            const result = document.getElementById('mapResult');
            if (result.innerHTML.includes('Testing Google Maps')) {
                testMap();
            }
        }
        
        // Auto-run tests on load
        window.addEventListener('load', () => {
            console.log('Debug page loaded');
            testAPI();
        });
    </script>
</body>
</html>
