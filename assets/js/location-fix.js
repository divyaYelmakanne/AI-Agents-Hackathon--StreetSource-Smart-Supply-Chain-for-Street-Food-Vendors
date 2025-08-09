/* Location Fix Script for Dashboard */

// Override the location tracking function with better error handling
function trackCurrentLocation() {
    console.log('✅ Fixed trackCurrentLocation called');
    
    const btn = document.getElementById('trackLocationBtn');
    const statusDiv = document.getElementById('locationStatus');
    const statusText = document.getElementById('locationText');
    
    if (!btn) {
        console.error('❌ trackLocationBtn button not found');
        alert('Error: Location button not found in page');
        return;
    }
    
    console.log('📍 Starting location tracking...');
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Getting location...';
    btn.disabled = true;
    
    // Show status if elements exist
    if (statusDiv && statusText) {
        statusDiv.style.display = 'block';
        statusText.textContent = '🔄 Getting your location...';
    }
    
    if (!navigator.geolocation) {
        console.error('❌ Geolocation not supported');
        btn.innerHTML = '❌ Not Supported';
        btn.className = 'btn btn-danger btn-sm w-100';
        btn.disabled = false;
        
        if (statusText) statusText.textContent = '❌ Geolocation not supported by this browser';
        alert('❌ Geolocation is not supported by this browser.');
        return;
    }
    
    console.log('🔍 Requesting geolocation permission...');
    
    const locationOptions = {
        enableHighAccuracy: true,
        timeout: 15000, // Increased timeout
        maximumAge: 0
    };
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            console.log('✅ Location success:', position.coords);
            
            // Store global position
            window.userPosition = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            
            console.log('📍 User position set:', window.userPosition);
            
            // Update button
            btn.innerHTML = '✅ Location Tracked';
            btn.className = 'btn btn-success btn-sm w-100';
            btn.disabled = false;
            
            // Update status
            if (statusText) {
                statusText.textContent = `📍 Location: ${window.userPosition.lat.toFixed(4)}, ${window.userPosition.lng.toFixed(4)}`;
            }
            
            // Update map container if no map available
            const mapContainer = document.getElementById('suppliersMap');
            if (mapContainer && !window.map) {
                console.log('🗺️ Showing location fallback (no map)');
                mapContainer.innerHTML = `
                    <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                        <div class="text-center p-4">
                            <h5>📍 Your Location Tracked</h5>
                            <p class="mb-2">Latitude: <code>${window.userPosition.lat.toFixed(6)}</code></p>
                            <p class="mb-3">Longitude: <code>${window.userPosition.lng.toFixed(6)}</code></p>
                            <p class="mb-3">Accuracy: <span class="badge bg-info">${position.coords.accuracy.toFixed(0)} meters</span></p>
                            <a href="https://www.google.com/maps?q=${window.userPosition.lat},${window.userPosition.lng}" 
                               target="_blank" class="btn btn-primary btn-sm">
                                🗺️ View on Google Maps
                            </a>
                            <button class="btn btn-success btn-sm ms-2" onclick="findSuppliersOnMapEnhanced()">
                                🔍 Find Suppliers
                            </button>
                        </div>
                    </div>
                `;
            }
            
            // Enable find suppliers button
            const findBtn = document.getElementById('findSuppliersBtn');
            if (findBtn) {
                findBtn.disabled = false;
                findBtn.innerHTML = '🔍 Find Suppliers';
                findBtn.className = 'btn btn-success btn-sm w-100';
                console.log('✅ Find suppliers button enabled');
            }
            
            // Show success message
            showAlert('success', '✅ Location tracked successfully! Now you can find suppliers.');
            console.log('✅ Location tracking completed successfully');
        },
        function(error) {
            console.error('❌ Location error:', error);
            
            btn.innerHTML = '❌ Location Error';
            btn.className = 'btn btn-danger btn-sm w-100';
            btn.disabled = false;
            
            let errorMsg = 'Unknown error occurred';
            let solution = '';
            
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg = 'Location access denied by user';
                    solution = 'Please allow location access and try again.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg = 'Location information unavailable';
                    solution = 'Please check your GPS/location settings.';
                    break;
                case error.TIMEOUT:
                    errorMsg = 'Location request timed out';
                    solution = 'Please try again or check your internet connection.';
                    break;
            }
            
            if (statusText) {
                statusText.textContent = `❌ Error: ${errorMsg}`;
            }
            
            const fullError = `${errorMsg}. ${solution}`;
            showAlert('danger', `❌ Location Error: ${fullError}`);
            
            // Offer to use default location
            if (confirm(`❌ Location Error: ${fullError}\n\nWould you like to use default location (Delhi) instead?`)) {
                console.log('🌍 Using default location (Delhi)');
                window.userPosition = { lat: 28.6139, lng: 77.2090 };
                
                btn.innerHTML = '🌍 Using Delhi';
                btn.className = 'btn btn-warning btn-sm w-100';
                
                if (statusText) {
                    statusText.textContent = '🌍 Using default location: Delhi';
                }
                
                // Enable find suppliers with default location
                const findBtn = document.getElementById('findSuppliersBtn');
                if (findBtn) {
                    findBtn.disabled = false;
                    findBtn.innerHTML = '🔍 Find Suppliers (Delhi)';
                    findBtn.className = 'btn btn-warning btn-sm w-100';
                }
                
                showAlert('warning', '🌍 Using default location (Delhi). You can still find suppliers!');
            }
        },
        locationOptions
    );
}

// Enhanced find suppliers function
function findSuppliersOnMapEnhanced() {
    console.log('🔍 Enhanced findSuppliersOnMapEnhanced called');
    
    const btn = document.getElementById('findSuppliersBtn');
    const container = document.getElementById('suppliersContainer');
    
    if (!btn || !container) {
        console.error('❌ Required elements not found');
        console.log('Available elements:', {
            btn: !!btn,
            container: !!container
        });
        alert('Error: Page elements not found');
        return;
    }
    
    // Use tracked location or default
    let searchLat, searchLng;
    if (window.userPosition) {
        searchLat = window.userPosition.lat;
        searchLng = window.userPosition.lng;
        console.log('📍 Using tracked location:', searchLat, searchLng);
    } else {
        searchLat = 28.6139; // Delhi
        searchLng = 77.2090;
        console.log('🌍 Using default location (Delhi):', searchLat, searchLng);
        showAlert('info', '🌍 Using default location (Delhi). Click "Track My Location" for personalized results.');
    }
    
    const radius = document.getElementById('radiusSelect') ? document.getElementById('radiusSelect').value : 10;
    console.log(`🔍 Searching within ${radius}km radius`);
    
    // Update UI
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Searching...';
    btn.disabled = true;
    
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">🔍 Searching for suppliers near you...</p>
        </div>
    `;
    
    // Make API call
    const url = `../php/get_suppliers_bulletproof.php?lat=${searchLat}&lng=${searchLng}&radius=${radius}`;
    console.log('🌐 API URL:', url);
    
    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        console.log('📡 Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('📄 Raw response length:', text.length);
        console.log('📄 Raw response preview:', text.substring(0, 200));
        
        let data;
        try {
            data = JSON.parse(text);
            console.log('✅ JSON parsed successfully:', data);
        } catch (e) {
            console.error('❌ JSON parse error:', e);
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.success) {
            const suppliers = data.suppliers || data.data || []; // Support both response formats
            console.log(`✅ Found ${suppliers.length} suppliers`);
            
            // Store for other functions
            window.currentSuppliersData = suppliers;
            
            // Display suppliers
            if (typeof displaySuppliers === 'function') {
                displaySuppliers(suppliers);
                console.log('✅ Suppliers displayed using displaySuppliers()');
            } else {
                // Simple fallback display
                if (suppliers.length > 0) {
                    container.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ Found ${suppliers.length} suppliers!</h6>
                            <p>Suppliers are loading... Please check the dashboard.</p>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <h6>⚠️ No suppliers found</h6>
                            <p>No suppliers available in your area (${radius}km radius).</p>
                            <button class="btn btn-primary btn-sm" onclick="findSuppliersOnMapEnhanced()">🔄 Try Again</button>
                        </div>
                    `;
                }
            }
            
            // Update button
            btn.innerHTML = `✅ Found ${suppliers.length} Suppliers`;
            btn.className = 'btn btn-success btn-sm w-100';
            btn.disabled = false;
            
            showAlert('success', `✅ Found ${suppliers.length} suppliers within ${radius}km!`);
            
        } else {
            console.error('❌ API error:', data.message);
            container.innerHTML = `
                <div class="alert alert-warning">
                    <h6>⚠️ No suppliers found</h6>
                    <p>${data.message || 'No suppliers available in your area.'}</p>
                    <button class="btn btn-primary btn-sm" onclick="findSuppliersOnMapEnhanced()">🔄 Try Again</button>
                </div>
            `;
            
            btn.innerHTML = '🔄 Try Again';
            btn.className = 'btn btn-warning btn-sm w-100';
            btn.disabled = false;
            
            showAlert('warning', '⚠️ No suppliers found in your area. Try expanding the search radius.');
        }
        
    })
    .catch(error => {
        console.error('❌ Error finding suppliers:', error);
        
        container.innerHTML = `
            <div class="alert alert-danger">
                <h6>❌ Error Loading Suppliers</h6>
                <p><strong>Error:</strong> ${error.message}</p>
                <div class="mt-2">
                    <button class="btn btn-primary btn-sm" onclick="findSuppliersOnMapEnhanced()">🔄 Try Again</button>
                    <a href="../debug_dashboard.php" target="_blank" class="btn btn-info btn-sm ms-2">🔧 Debug</a>
                </div>
            </div>
        `;
        
        btn.innerHTML = '❌ Error - Retry';
        btn.className = 'btn btn-danger btn-sm w-100';
        btn.disabled = false;
        
        showAlert('danger', `❌ Error loading suppliers: ${error.message}`);
    });
}

// Helper function for alerts (fallback if not defined)
function showAlert(type, message) {
    if (window.showAlert && typeof window.showAlert === 'function') {
        window.showAlert(type, message);
    } else {
        // Simple fallback
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'warning' ? 'alert-warning' : 
                          type === 'danger' ? 'alert-danger' : 'alert-info';
        
        const alertContainer = document.getElementById('alertContainer') || document.body;
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        if (alertContainer === document.body) {
            alertContainer.insertBefore(alertDiv, alertContainer.firstChild);
        } else {
            alertContainer.appendChild(alertDiv);
        }
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Location fix script loaded');
    
    // Test if required elements exist
    const requiredElements = ['trackLocationBtn', 'findSuppliersBtn', 'suppliersContainer'];
    let missingElements = [];
    
    requiredElements.forEach(id => {
        if (!document.getElementById(id)) {
            missingElements.push(id);
        }
    });
    
    if (missingElements.length > 0) {
        console.warn('⚠️ Missing elements:', missingElements);
    } else {
        console.log('✅ All required elements found');
    }
});

console.log('📍 Location fix script initialized');
