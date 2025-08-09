<!DOCTYPE html>
<html>
<head>
    <title>Location & Supplier Test</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Location & Supplier Test</h2>
        
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <button id="locationBtn" class="btn btn-primary w-100" onclick="getLocation()">📍 Get Location</button>
            </div>
            <div class="col-md-3">
                <button id="suppliersBtn" class="btn btn-success w-100" onclick="loadSuppliers()">🏪 Load Suppliers</button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-warning w-100" onclick="testAll()">🧪 Test All</button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-info w-100" onclick="showMapTest()">🗺️ Test Map</button>
            </div>
        </div>
        
        <!-- Status Display -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h6>📍 Location Status</h6></div>
                    <div class="card-body">
                        <div id="locationStatus">Click "Get Location" to start</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h6>🏪 Suppliers Status</h6></div>
                    <div class="card-body">
                        <div id="suppliersStatus">Click "Load Suppliers" to start</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Test -->
        <div class="card mt-3" id="mapCard" style="display: none;">
            <div class="card-header"><h6>🗺️ Map Test</h6></div>
            <div class="card-body">
                <div id="mapContainer" style="height: 300px; background: #f0f0f0; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center;">
                    <span class="text-muted">Map will load here</span>
                </div>
            </div>
        </div>
        
        <!-- Suppliers List -->
        <div class="card mt-3">
            <div class="card-header"><h6>📋 Suppliers List</h6></div>
            <div class="card-body">
                <div id="suppliersList">No suppliers loaded yet</div>
            </div>
        </div>
    </div>

    <script>
        let userLocation = null;
        
        function getLocation() {
            const btn = document.getElementById('locationBtn');
            const status = document.getElementById('locationStatus');
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Getting...';
            btn.disabled = true;
            status.innerHTML = '🔄 Getting your location...';
            
            if (!navigator.geolocation) {
                status.innerHTML = '❌ Geolocation not supported';
                btn.innerHTML = '❌ Not Supported';
                btn.disabled = false;
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    
                    status.innerHTML = `
                        ✅ Location found!<br>
                        <small>Lat: ${userLocation.lat.toFixed(4)}</small><br>
                        <small>Lng: ${userLocation.lng.toFixed(4)}</small><br>
                        <small>Accuracy: ${position.coords.accuracy}m</small>
                    `;
                    
                    btn.innerHTML = '✅ Location Found';
                    btn.className = 'btn btn-success w-100';
                    btn.disabled = false;
                },
                (error) => {
                    let errorMsg = 'Unknown error';
                    let suggestion = '';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Location access denied by permissions policy or user';
                            suggestion = 'Try refreshing the page and allowing location access, or use default location';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Location information unavailable';
                            suggestion = 'Check your GPS/network connection';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Location request timed out';
                            suggestion = 'Try again or use default location';
                            break;
                    }
                    
                    status.innerHTML = `
                        ❌ Location error: ${errorMsg}<br>
                        <small>${suggestion}</small><br>
                        <button class="btn btn-secondary btn-sm mt-2" onclick="useDefaultTestLocation()">
                            🏙️ Use Delhi as Default
                        </button>
                    `;
                    btn.innerHTML = '❌ Failed';
                    btn.className = 'btn btn-danger w-100';
                    btn.disabled = false;
                }
            );
        }
        
        async function loadSuppliers() {
            const btn = document.getElementById('suppliersBtn');
            const status = document.getElementById('suppliersStatus');
            const list = document.getElementById('suppliersList');
            
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';
            btn.disabled = true;
            status.innerHTML = '🔄 Loading suppliers...';
            list.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
            
            try {
                const response = await fetch('../php/minimal_api.php');
                const text = await response.text();
                
                console.log('API Response:', text);
                
                if (!text.startsWith('{')) {
                    throw new Error('Non-JSON response: ' + text.substring(0, 100));
                }
                
                const data = JSON.parse(text);
                
                if (data.success && data.suppliers) {
                    status.innerHTML = `
                        ✅ Suppliers loaded!<br>
                        <small>Total: ${data.total_found}</small><br>
                        <small>Nearby: ${data.nearby_count}</small>
                    `;
                    
                    // Display suppliers
                    let html = '';
                    data.suppliers.forEach(supplier => {
                        html += `
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h6 class="mb-1">${supplier.name}</h6>
                                            <small class="text-muted">${supplier.address} • ${supplier.distance}km</small>
                                        </div>
                                        <div class="col-auto">
                                            ${supplier.is_nearby ? '<span class="badge bg-success">Nearby</span>' : '<span class="badge bg-secondary">Far</span>'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    list.innerHTML = html;
                    
                    btn.innerHTML = `✅ Loaded ${data.suppliers.length}`;
                    btn.className = 'btn btn-success w-100';
                    btn.disabled = false;
                    
                } else {
                    throw new Error('API returned no suppliers');
                }
                
            } catch (error) {
                status.innerHTML = `❌ Error: ${error.message}`;
                list.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
                
                btn.innerHTML = '❌ Failed';
                btn.className = 'btn btn-danger w-100';
                btn.disabled = false;
            }
        }
        
        function testAll() {
            getLocation();
            setTimeout(() => {
                loadSuppliers();
            }, 2000);
        }
        
        function showMapTest() {
            const card = document.getElementById('mapCard');
            const container = document.getElementById('mapContainer');
            
            card.style.display = 'block';
            
            if (typeof google !== 'undefined' && google.maps) {
                try {
                    const map = new google.maps.Map(container, {
                        center: userLocation || { lat: 28.6139, lng: 77.2090 },
                        zoom: 12
                    });
                    
                    if (userLocation) {
                        new google.maps.Marker({
                            position: userLocation,
                            map: map,
                            title: 'Your Location'
                        });
                    }
                    
                    container.innerHTML = ''; // Clear placeholder text
                } catch (error) {
                    container.innerHTML = `<span class="text-danger">Map error: ${error.message}</span>`;
                }
            } else {
                container.innerHTML = '<span class="text-warning">Google Maps not loaded. Loading...</span>';
                loadGoogleMaps();
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
        }
        
        function useDefaultTestLocation() {
            userLocation = { lat: 28.6139, lng: 77.2090 };
            
            const status = document.getElementById('locationStatus');
            const btn = document.getElementById('locationBtn');
            
            status.innerHTML = `
                ✅ Using default location (Delhi)!<br>
                <small>Lat: 28.6139</small><br>
                <small>Lng: 77.2090</small><br>
                <small>This is a fallback when GPS is not available</small>
            `;
            
            btn.innerHTML = '✅ Default Location';
            btn.className = 'btn btn-secondary w-100';
            btn.disabled = false;
        }
    </script>
</body>
</html>
