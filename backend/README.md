# Internship Management Platform - Backend

## Day 1: Environment Setup

### Prerequisites
- XAMPP installed (Apache + MySQL + PHP)
- PHP 7.4 or higher

### Setup Instructions

1. **Install XAMPP**
   - Download from https://www.apachefriends.org/
   - Install and start Apache + MySQL services

2. **Database Setup**
   ```bash
   # Open phpMyAdmin (http://localhost/phpmyadmin)
   # Or use MySQL command line
   mysql -u root -p < database/schema.sql
   ```

3. **Project Setup**
   - Copy this `backend` folder to `C:/xampp/htdocs/internship-backend/`
   - Access API at: http://localhost/internship-backend/api/

4. **Test Connection**
   - Visit: http://localhost/internship-backend/api/index.php
   - Should return JSON with API info

### Project Structure
```
backend/
├── api/              # API endpoints
├── config/           # Configuration files
├── database/         # SQL schemas
└── README.md
```

### Next Steps (Day 2)
- Create database tables
- Set up user registration endpoint
