// StreetSource JavaScript Functions

// Geolocation functionality
function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation is not supported by this browser.'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                });
            },
            (error) => {
                let errorMessage;
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = "User denied the request for Geolocation.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = "Location information is unavailable.";
                        break;
                    case error.TIMEOUT:
                        errorMessage = "The request to get user location timed out.";
                        break;
                    default:
                        errorMessage = "An unknown error occurred.";
                        break;
                }
                reject(new Error(errorMessage));
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 300000 // 5 minutes
            }
        );
    });
}

// Update location for vendors
async function updateVendorLocation() {
    const btn = document.getElementById('locationBtn');
    if (!btn) return;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Getting location...';
    
    try {
        const location = await getCurrentLocation();
        
        // Send location to server
        const response = await fetch('php/update_location.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(location)
        });
        
        const result = await response.json();
        
        if (result.success) {
            btn.innerHTML = '📍 Location Updated';
            btn.className = 'btn btn-success btn-sm';
            
            // Refresh suppliers
            if (typeof loadNearbySuppliers === 'function') {
                loadNearbySuppliers();
            }
        } else {
            throw new Error(result.error || 'Failed to update location');
        }
    } catch (error) {
        btn.innerHTML = '📍 Get Location';
        btn.className = 'btn btn-primary btn-sm';
        showAlert('Error: ' + error.message, 'danger');
    } finally {
        btn.disabled = false;
    }
}

// Load nearby suppliers for vendors
async function loadNearbySuppliers() {
    const container = document.getElementById('suppliersContainer');
    if (!container) return;
    
    container.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div><p>Finding nearby suppliers...</p></div>';
    
    try {
        const response = await fetch('php/get_nearby_suppliers.php');
        const result = await response.json();
        
        if (result.success) {
            displaySuppliers(result.suppliers);
        } else {
            throw new Error(result.error || 'Failed to load suppliers');
        }
    } catch (error) {
        container.innerHTML = '<div class="alert alert-danger">Error loading suppliers: ' + error.message + '</div>';
    }
}

// Display suppliers
function displaySuppliers(suppliers) {
    const container = document.getElementById('suppliersContainer');
    
    if (suppliers.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No suppliers found within 10km radius. Try updating your location or expanding the search area.</div>';
        return;
    }
    
    let html = '';
    
    suppliers.forEach(supplier => {
        html += `
            <div class="card supplier-card">
                <div class="supplier-header">
                    <div class="supplier-info">
                        <div>
                            <h5 class="mb-1">${supplier.name}</h5>
                            <small>${supplier.city} • ${supplier.distance} km away</small>
                        </div>
                        <div class="text-right">
                            <div class="rating">
                                ${generateStars(supplier.avg_rating)}
                                <small>(${supplier.review_count} reviews)</small>
                            </div>
                            <span class="distance-badge">${supplier.distance} km</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text"><small class="text-muted">📍 ${supplier.address}</small></p>
                    <p class="card-text"><small class="text-muted">📞 ${supplier.phone}</small></p>
                    
                    <h6 class="mt-3 mb-2">Available Products:</h6>
                    ${generateProductsList(supplier.products, supplier.id)}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Generate star rating
function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= Math.floor(rating)) {
            stars += '⭐';
        } else if (i - 0.5 <= rating) {
            stars += '⭐';
        } else {
            stars += '☆';
        }
    }
    return stars + ` (${rating})`;
}

// Generate products list
function generateProductsList(products, supplierId) {
    if (products.length === 0) {
        return '<p class="text-muted">No products available</p>';
    }
    
    let html = '';
    products.forEach(product => {
        html += `
            <div class="product-item">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-1">${product.name}</h6>
                        <p class="mb-0 text-muted small">${product.description || ''}</p>
                    </div>
                    <div class="col-md-3">
                        <div class="price-tag">${formatCurrency(product.price)}/${product.unit}</div>
                        <div class="stock-info">Stock: ${product.stock} ${product.unit}</div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary btn-sm" onclick="showOrderModal(${supplierId}, ${product.id}, '${product.name}', ${product.price}, '${product.unit}', ${product.stock})">
                            Order Now
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    return html;
}

// Format currency
function formatCurrency(amount) {
    return '₹' + parseFloat(amount).toFixed(2);
}

// Show order modal
function showOrderModal(supplierId, productId, productName, price, unit, stock) {
    const modal = document.getElementById('orderModal');
    if (!modal) return;
    
    document.getElementById('modalProductName').textContent = productName;
    document.getElementById('modalPrice').textContent = formatCurrency(price) + '/' + unit;
    document.getElementById('modalStock').textContent = 'Available: ' + stock + ' ' + unit;
    
    document.getElementById('orderSupplierId').value = supplierId;
    document.getElementById('orderProductId').value = productId;
    document.getElementById('orderQuantity').max = stock;
    document.getElementById('orderQuantity').value = 1;
    
    updateOrderTotal();
    
    $('#orderModal').modal('show');
}

// Update order total
function updateOrderTotal() {
    const quantity = parseInt(document.getElementById('orderQuantity').value) || 0;
    const priceText = document.getElementById('modalPrice').textContent;
    const price = parseFloat(priceText.replace('₹', '').split('/')[0]);
    const total = quantity * price;
    
    document.getElementById('orderTotal').textContent = formatCurrency(total);
}

// Place order
async function placeOrder() {
    const form = document.getElementById('orderForm');
    const submitBtn = document.getElementById('orderSubmitBtn');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Placing order...';
    
    try {
        const formData = new FormData(form);
        const response = await fetch('php/place_order.php', {
            method: 'POST',
            body: formData
        });
        
        // Since this redirects, we don't need to handle the response
        window.location.reload();
    } catch (error) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Place Order';
        showAlert('Error placing order: ' + error.message, 'danger');
    }
}

// Show alert
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 5000);
}

// Star rating functionality
function setRating(rating) {
    document.getElementById('reviewRating').value = rating;
    
    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById('star' + i);
        if (i <= rating) {
            star.className = 'star active';
            star.textContent = '⭐';
        } else {
            star.className = 'star inactive';
            star.textContent = '☆';
        }
    }
}

// Update order status (for suppliers)
async function updateOrderStatus(orderId, status) {
    try {
        const response = await fetch('php/update_order_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            throw new Error(result.error || 'Failed to update order status');
        }
    } catch (error) {
        showAlert('Error updating order: ' + error.message, 'danger');
    }
}

// Initialize page based on current page
document.addEventListener('DOMContentLoaded', function() {
    // Auto-load suppliers on vendor dashboard
    if (document.getElementById('suppliersContainer')) {
        loadNearbySuppliers();
    }
    
    // Initialize tooltips if Bootstrap is loaded
    if (typeof $!== 'undefined' && $.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
});
