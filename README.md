🍜 StreetSource — Smart Supply Chain for Street Food Vendors

A modern, production-ready platform connecting street food vendors with raw material suppliers, built with PHP, MySQL, and Bootstrap.


🚀 Key Highlights
🔄 Real-time Order Management (Place ➡️ Accept ➡️ Deliver)

📍 Geolocation-based Supplier Discovery

📦 Product & Inventory Management

📊 Analytics for Revenue & Performance

🔒 Secure Login & Role-based Dashboards

📱 Mobile-First, Clean UI with Bootstrap 5


👤 User Roles
🛍️ Vendors (Buyers)
Find nearby suppliers (within 10km radius)

Browse available stock & place orders

Track orders & receive delivery updates

Rate & review suppliers post-delivery


🏭 Suppliers (Sellers)
Add/manage products with pricing & stock levels

Accept, process & update order statuses

View revenue, performance metrics & reviews

Coordinate directly with vendors via profile info


🛠️ Tech Stack
Layer	Technology
Frontend	HTML5, CSS3, JavaScript
UI Framework	Bootstrap 5.1.3
Backend	PHP 8+ (OOP)
Database	MySQL 8+
Auth	PHP Sessions + bcrypt hashing
Location	HTML5 Geolocation API
Server	Apache (XAMPP/WAMP recommended)


📂 Project Structure
bash
Copy
Edit
StreetSource/
├── index.php              # 🔐 Login
├── register.php           # 📝 Registration
├── verify_email.php       # ✅ Email verification
├── sql/                   # 🗃️ Database schema & sample data
├── php/                   # ⚙️ Core backend logic (APIs, DB, sessions)
├── assets/                # 🎨 CSS & JS files
├── uploads/               # 🖼️ Product images
├── vendor/                # 🛍️ Vendor dashboard & pages
├── supplier/              # 🏭 Supplier dashboard & pages
├── final_cleanup.bat      # 🧹 Cleanup utility
⚡ Quick Setup


1️⃣ Requirements
PHP 7.4 or higher

MySQL 5.7 or higher

Composer (dependency manager)

XAMPP / WAMP / LAMP stack


2️⃣ Installation Guide
bash
Copy
Edit
# 📁 Move project to your web root
cd C:/xampp/htdocs/
# ⬇️ Clone or copy StreetSource here
3️⃣ Database Setup
🔥 Start Apache & MySQL via XAMPP

🌐 Visit http://localhost/phpmyadmin

🗃️ Import: sql/schema.sql to create database and tables


4️⃣ Configuration
Edit DB credentials in: php/db.php

php
Copy
Edit
private $host = 'localhost';
private $db_name = 'streetsource';
private $username = 'root';
private $password = '';
5️⃣ Run the App
🌐 Go to: http://localhost/StreetSource/


✅ Use sample credentials:

Vendor: raju@chaat.com / password

Supplier: fresh@vegetables.com / password

📦 Core Features
📍 Geolocation Matching
HTML5 Geolocation API + Haversine Formula

Filter suppliers within 10km radius

Accurate & configurable distance logic

🛒 Order System
Place, Accept, Deliver flow

Stock deducted automatically per order

Status tracking: Pending → Accepted → Delivered


🌟 Ratings & Reviews
5-star system with optional text

Review linked to actual orders

Public supplier ratings for transparency


📱 Mobile UI
Bootstrap-powered responsive layout

Touch-friendly buttons and layouts

Ideal for on-the-go street vendors

🔐 Security Features
🧂 Password Hashing using bcrypt

🔐 Session-based Role Auth (Supplier / Vendor)

🚫 SQL Injection Protection via prepared statements

✔️ Input Validation & Sanitization


📊 Database Overview
users Table
Vendor/Supplier roles

Includes latitude & longitude for matching

Contact info for coordination

products Table
Linked to supplier

Contains name, description, price, stock

Availability status

orders Table
Vendor ↔ Supplier mapping

Product, quantity, price, status tracking

Order lifecycle: Pending → Accepted → Delivered

reviews Table
Star ratings & feedback

Linked to verified orders only

Averages displayed on supplier profiles

🌟 UI Components
🧾 Dashboard Cards – Quick stats & insights

📦 Product Cards – Price, stock, and action buttons

📍 Supplier Map/Listing – Nearby sellers in real-time

⭐ Review Interface – Leave & view feedback

🟢 Status Badges – Color-coded order statuses


🔧 Maintenance
Run final_cleanup.bat in the root directory 🧹
✅ Removes all dev/test/setup files
🟢 Leaves a clean, production-ready project


📈 Future Roadmap
Coming Soon 🚧
🔔 Push Notifications for new orders

💳 Payment Gateway Integration

📱 Native Mobile Apps (Android/iOS)

📢 Multi-language Support

🧭 GPS Navigation to supplier

🛒 Bulk Ordering & Cart System


Technical Enhancements
🔌 RESTful API support

💬 WebSocket-based real-time updates

🧱 Redis/Memcached for caching

🛡️ 2FA and advanced security protocols

🤝 Contributing
Contributions welcome! ✨


📌 Guidelines:

Follow PSR-4 PHP standards

Include docs with new features

Test on multiple devices

Use semantic versioning


🌐 Browser Compatibility
Browser	Supported ✅
Chrome 60+	✅
Firefox 55+	✅
Safari 11+	✅
Edge 79+	✅
Mobile Browsers	✅


💬 Support
Having issues?

🔍 Check the code & DB schema

🧪 Use provided sample data

🛠️ Run in local dev environment


📄 License
This project is open for learning, extension, and deployment.
Use it freely for commercial or educational purposes.

**Made with ❤️ for India's street food vendors**

