# Inventra: AI-Powered Department Store Inventory

Inventra is a modern, AI-integrated inventory management system designed specifically for department stores. It combines robust stock tracking with predictive insights to optimize retail operations.

## 🚀 Key Features
- **AI-Driven Insights:** Automated monthly sales summaries and predictive restock recommendations powered by Gemini.
- **Role-Based Access:** Specialized dashboards for Managers, Supervisors, Salesmen, and Logistic Handlers.
- **Smart Stock Tracking:** Real-time monitoring of SKU levels, thresholds, and automated stock movement logs.
- **Supplier Management:** Integrated procurement workflow with Purchase Order (PO) tracking.
- **Access Workflow:** Formalized `access_request` system for secure inventory overrides.

## 🛠️ Technical Stack
- **Backend:** PHP 8.1+ (MVC Architecture)
- **Database:** MySQL/MariaDB
- **Frontend:** Vanilla JS & Custom CSS
- **AI:** Google Gemini API
- **Environment:** Optimized for XAMPP/Local development

## ⚙️ Setup & Installation

### Local Development (XAMPP/Docker)
1. **Clone the repository:**
   ```bash
   git clone https://github.com/NischhalNidhi/inventory-system.git
   ```
2. **Environment Setup:**
   - Rename `.env.example` to `.env`.
   - Configure your database credentials.
3. **Initialize Database:**
   ```bash
   php database/init.php
   php database/seed.php
   ```
4. **Run:** Access via `http://localhost/Inventra/public` (XAMPP) or use the included `Dockerfile`.

### ☁️ Railway Deployment
1. **Push to GitHub:** Commit all changes and push to your repository.
2. **Connect to Railway:** Create a new project on Railway and link your GitHub repo.
3. **Add MySQL:** Add a MySQL database service in your Railway project.
4. **Environment Variables:** Railway will automatically provide `MYSQLHOST`, `MYSQLUSER`, etc. The app is pre-configured to detect these.
5. **Finalize:**
   - Open the Railway terminal for your app service.
   - Run `php database/init.php` to set up the tables.
   - Run `php database/seed.php` for initial data.


## 📜 License
This project is licensed under the MIT License.
