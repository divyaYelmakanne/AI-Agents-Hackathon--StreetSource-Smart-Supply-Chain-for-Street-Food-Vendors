del /Q add_sample_products.php 2>nul
del /Q add_sample_suppliers.php 2>nul
del /Q api_test_final.php 2>nul
del /Q auto_login_dashboard.php 2>nul
del /Q check_database.php 2>nul
del /Q check_logos.php 2>nul
del /Q check_orders_table.php 2>nul
del /Q check_products_structure.php 2>nul
del /Q check_products_table.php 2>nul
del /Q check_suppliers.php 2>nul
del /Q check_supplier_products.php 2>nul
del /Q check_table_structure.php 2>nul
del /Q complete_test.html 2>nul
del /Q dashboard_output.html 2>nul
del /Q debug_dashboard.php 2>nul
del /Q debug_products_fixed.php 2>nul
del /Q debug_raw_response.php 2>nul
del /Q debug_suppliers.php 2>nul
del /Q debug_test.html 2>nul
del /Q final_debug.html 2>nul
del /Q final_test.html 2>nul
del /Q final_test.php 2>nul
del /Q fix_database.php 2>nul
del /Q fix_orders_table.php 2>nul
del /Q insert_sample_data.sql 2>nul
del /Q location_test.html 2>nul
del /Q maps_debug.html 2>nul
del /Q migrate_database.php 2>nul
del /Q modern_order.php 2>nul
del /Q query('DESCRIBE 2>nul
del /Q quick_api_test.php 2>nul
del /Q quick_login.php 2>nul
del /Q quick_test_login.php 2>nul
del /Q session_check.php 2>nul
del /Q simple_db_test.php 2>nul
del /Q simple_test.html 2>nul
del /Q test_api_direct.php 2>nul
del /Q test_api_error.php 2>nul
del /Q test_correct_columns.php 2>nul
del /Q test_dashboard_simple.php 2>nul
del /Q test_debug.html 2>nul
del /Q test_direct_products.php 2>nul
del /Q test_fixed_api.html 2>nul
del /Q test_order_complete.php 2>nul
del /Q test_products_query.php 2>nul
del /Q test_suppliers_direct.php 2>nul
del /Q test_supplier_debug.html 2>nul
del /Q test_supplier_list.html 2>nul
del /Q truncate_database.php 2>nul
@echo off
echo Starting final project cleanup...
echo.

REM Remove all debug and test files
echo Removing debug and test files...
del /Q add_product_for_user.php 2>nul
del /Q add_product_images.php 2>nul
del /Q add_test_product.php 2>nul
del /Q api_debug.php 2>nul
del /Q api_test.html 2>nul
del /Q check_db.php 2>nul
del /Q check_location.php 2>nul
del /Q check_orders.php 2>nul
del /Q check_products.php 2>nul
del /Q check_users_table.php 2>nul
del /Q check_user_products.php 2>nul
del /Q complete_fix.php 2>nul
del /Q create_test_delivered_orders.php 2>nul
del /Q create_test_order.php 2>nul
del /Q debug_orders.php 2>nul
del /Q debug_order_email_data.php 2>nul
del /Q debug_otp.php 2>nul
del /Q debug_place_order.php 2>nul
del /Q debug_products.php 2>nul
del /Q debug_product_api.php 2>nul
del /Q debug_user_api.php 2>nul
del /Q debug_vendor_data.php 2>nul
del /Q debug_vendor_info.php 2>nul
del /Q fix_orders_visibility.php 2>nul
del /Q full_diagnostic.php 2>nul
del /Q geolocation_test.html 2>nul
del /Q google_maps_config.html 2>nul
del /Q google_maps_integration.html 2>nul
del /Q otp_debug_live.php 2>nul
del /Q otp_verification_test.php 2>nul
del /Q session_test.php 2>nul
del /Q simple_order_test.php 2>nul

del /Q test_api.php 2>nul
del /Q test_api_suppliers.html 2>nul
del /Q test_email_preview.php 2>nul
del /Q test_email_send.php 2>nul
del /Q test_login.php 2>nul
del /Q test_order_flow.php 2>nul
del /Q test_order_placement.php 2>nul
del /Q test_otp.php 2>nul
del /Q test_place_order.php 2>nul
del /Q test_redirects.php 2>nul
del /Q test_rejection_email.php 2>nul
del /Q test_simple_order.php 2>nul
del /Q test_success_flow.php 2>nul
del /Q test_supplier_visibility.php 2>nul
del /Q test_suppliers.php 2>nul
del /Q web_db_test.php 2>nul
del /Q verify_orders_table.php 2>nul

REM Remove setup and migration files (no longer needed)
echo Removing setup and migration files...
del /Q setup.php 2>nul
del /Q setup_database.php 2>nul
del /Q setup_delivery_fields.php 2>nul
del /Q setup_geolocation.php 2>nul
del /Q setup_test_data.php 2>nul
del /Q update_delivery_schema.php 2>nul
del /Q update_location_schema.php 2>nul
del /Q update_orders_schema.php 2>nul

REM Remove demo and guide files
echo Removing demo and documentation files...
del /Q enhanced_features_demo.html 2>nul
del /Q ENHANCED_FEATURES_GUIDE.md 2>nul
del /Q ORDER_FIX_SUMMARY.md 2>nul
del /Q OTP_FIX_SUMMARY.md 2>nul
del /Q EMAIL_VERIFICATION_README.md 2>nul
del /Q CLEANUP_GUIDE.md 2>nul

REM Remove temporary and log files
echo Removing temporary files...
del /Q temp_orders_end.txt 2>nul

REM Remove old cleanup files
echo Removing old cleanup scripts...
del /Q cleanup.bat 2>nul
del /Q cleanup_project.ps1 2>nul

REM Remove unnecessary PHP files in php directory
echo Cleaning up php directory...
del /Q "php\execute(['sanketmali0281@gmail.com'])" 2>nul
del /Q "php\simple_email_test.php" 2>nul
del /Q "php\test_db.php" 2>nul
del /Q "php\test_email.php" 2>nul

REM Remove legacy order file (we have proper order management now)
echo Removing legacy files...
del /Q order.php 2>nul

echo.
echo ===================================
echo Cleanup completed successfully!
echo.
echo Remaining core files:
echo - index.php (login page)
echo - register.php (registration)
echo - verify_email.php (email verification)
echo - README.md (project documentation)
echo - composer.json/lock (dependencies)
echo - sql/ (database schema)
echo - php/ (core PHP logic)
echo - assets/ (CSS/JS files)
echo - supplier/ (supplier dashboard)
echo - vendor/ (vendor dashboard)  
echo - uploads/ (file storage)
echo.
echo Project is now clean and production-ready!
echo ===================================
pause
