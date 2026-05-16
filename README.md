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
1. **Clone the repository:**
   ```bash
   git clone https://github.com/NischhalNidhi/Inventra.git
   ```
2. **Database Configuration:**
   - Import `database/schema.sql` into your MySQL server.
   - (Optional) Run `database/seed_department_store.php` for demo data.
3. **Environment Setup:**
   - Rename `.env.example` to `.env`.
   - Configure `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.
   - Add your `AI_INSIGHTS_API_KEY` for Gemini integration.
4. **Run:** Access the project via `http://localhost/Inventra/public`.

## 📜 License
This project is licensed under the MIT License.
