# Inventra - Modern Inventory Management System

Inventra is a streamlined, web-based inventory management system designed for department stores. It features real-time stock tracking, purchase order management, and AI-driven sales insights.

## Features
- **Dashboard**: High-level overview of stock health and sales trends.
- **Inventory**: Complete product management with category and supplier associations.
- **Logistics**: Manage purchase orders and track shipments.
- **Reports**: Detailed sales and inventory reports with interactive charts.
- **AI Insights**: Automated business analysis based on historical data.

## Quick Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/your-username/Inventra.git
   cd Inventra
   ```

2. **Run the setup script**:
   Open your terminal/command prompt and run:
   ```bash
   git clone https://github.com/NischhalNidhi/Inventra.git
   ```
   *This will create your `.env` file, initialize the database, and seed demo data.*

3. **Configure your Web Server**:
   Point your web server (Apache/Nginx) document root to the `public/` folder.
   
   Example (Apache vhost):
   ```apache
   DocumentRoot "C:/xampp/htdocs/Inventra/public"
   ```

4. **Access the application**:
   Open `http://localhost/` in your browser.

### Default Login
- **Username**: `manager`
- **Password**: `password`

## Database Management
- **Manual Import**: Import `database/schema.sql` into your MySQL database.
- **Auto Setup**: The application automatically initializes the database on first run.

## Requirements
- PHP 8.1+
- MySQL/MariaDB
- Extensions: `pdo_mysql`, `curl`
