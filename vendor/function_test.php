<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Function Test</title>
    <meta http-equiv="Permissions-Policy" content="geolocation=*">
</head>
<body>
    <h1>Dashboard Function Test</h1>
    
    <div id="test-results"></div>
    
    <script>
        // Test if we can access the dashboard functions
        function testDashboardFunctions() {
            const results = document.getElementById('test-results');
            let output = '<h3>Function Test Results:</h3>';
            
            // Test if findSuppliersOnMap exists
            if (typeof findSuppliersOnMap === 'function') {
                output += '<p>✅ findSuppliersOnMap function exists</p>';
            } else {
                output += '<p>❌ findSuppliersOnMap function NOT found</p>';
                output += '<p>Type: ' + typeof findSuppliersOnMap + '</p>';
            }
            
            // Test if we can define it
            try {
                window.findSuppliersOnMap = function() {
                    console.log('Test function called');
                    alert('Test function works!');
                };
                output += '<p>✅ Can define findSuppliersOnMap function</p>';
            } catch (e) {
                output += '<p>❌ Cannot define function: ' + e.message + '</p>';
            }
            
            // Test basic JavaScript
            try {
                const testVar = 'test';
                output += '<p>✅ Basic JavaScript works</p>';
            } catch (e) {
                output += '<p>❌ Basic JavaScript error: ' + e.message + '</p>';
            }
            
            results.innerHTML = output;
        }
        
        // Run test
        window.addEventListener('load', testDashboardFunctions);
    </script>
    
    <button onclick="findSuppliersOnMap()">Test Function Call</button>
    <br><br>
    
    <!-- Now load the actual dashboard JavaScript -->
    <script>
        // Copy a simplified version of the dashboard function
        async function findSuppliersOnMap() {
            console.log('🔍 TEST: findSuppliersOnMap called');
            alert('✅ Function called successfully!');
            
            try {
                const response = await fetch('../php/minimal_api.php');
                const data = await response.json();
                console.log('API response:', data);
                alert(`API works! Found ${data.suppliers ? data.suppliers.length : 0} suppliers`);
            } catch (error) {
                console.error('API error:', error);
                alert('❌ API error: ' + error.message);
            }
        }
    </script>
</body>
</html>
