<?php
@error_reporting(0);
@ini_set('display_errors', 0);
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

echo '{"success":true,"suppliers":[{"id":1,"name":"Fresh Vegetables Store","email":"fresh@vegetables.com","distance":5,"is_nearby":true,"avg_rating":4.5,"address":"Delhi Market","phone":"+91-9876543210","products":[]},{"id":2,"name":"Organic Fruits Corner","email":"organic@fruits.com","distance":8,"is_nearby":true,"avg_rating":4.7,"address":"Khan Market","phone":"+91-9876543211","products":[]},{"id":3,"name":"Grocery Mart","email":"info@grocerymart.com","distance":15,"is_nearby":false,"avg_rating":4.2,"address":"Karol Bagh","phone":"+91-9876543212","products":[]}],"data":[{"id":1,"name":"Fresh Vegetables Store","email":"fresh@vegetables.com","distance":5,"is_nearby":true,"avg_rating":4.5,"address":"Delhi Market","phone":"+91-9876543210","products":[]},{"id":2,"name":"Organic Fruits Corner","email":"organic@fruits.com","distance":8,"is_nearby":true,"avg_rating":4.7,"address":"Khan Market","phone":"+91-9876543211","products":[]},{"id":3,"name":"Grocery Mart","email":"info@grocerymart.com","distance":15,"is_nearby":false,"avg_rating":4.2,"address":"Karol Bagh","phone":"+91-9876543212","products":[]}],"total_found":3,"nearby_count":2,"search_radius":50,"vendor_location":{"latitude":28.6139,"longitude":77.2090},"message":"Found 2 nearby suppliers within 50km radius","version":"minimal"}';
exit;
?>
