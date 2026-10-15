# Quiz Platform

> **Live community quiz platform with one-account-per-device lock, per-user question shuffling, multi-layer anti-cheat, and a speed-weighted leaderboard. PHP/MySQL + Flask.**

A high-concurrency, security-first competitive quiz engine built for community events and timed contests. Designed from the ground up to prevent multi-accounting, unauthorized assistance, and coordinated cheating while maintaining high responsiveness under heavy traffic spikes.

---

## 🛡️ Core Highlights & Anti-Cheat Architecture

### 1. One-Account-Per-Device Lock
- **Canvas & Browser Fingerprinting**: Captures client hardware, screen, and rendering characteristics to identify devices uniquely.
- **Incognito & Multi-Account Deterrence**: Combines persistent `device_lock` identifiers and browser signatures to enforce strict single-competitor rules even if private browsing or clearing cookies is attempted.

### 2. Multi-Layer Anti-Cheat Safeguards
- **Tab & Window Blur Tracking**: Monitors window focus and visibility state changes. Switching tabs, opening secondary apps, or minimizing the quiz triggers instant warnings and automated disqualification.
- **DevTools & Inspection Deterrence**: Disables context menus (right-click), text selection, drag-and-drop, and standard shortcut combinations (`Ctrl+Shift+I`, `F12`, `Ctrl+U`, `Ctrl+C`).
- **Dynamic Session Watermarking**: Injects subtle, semi-transparent user metadata (username, timestamp, session ID) across quiz question cards to trace and deter phone camera leaks.

### 3. Per-User Question & Option Shuffling
- **Isolated Question Order**: Every candidate receives a randomized permutation of questions.
- **Option Permutation**: Choices ($A, B, C, D$) are dynamically shuffled per user session, making shared answer keys ("A-C-B-D") useless.

### 4. Speed-Weighted Real-Time Leaderboard
- **Dynamic Scoring Engine**: Awards base points for correct answers alongside high-resolution reaction time bonuses.
- **Tie-Breaker Metrics**: Ranks contestants accurately based on precision time-to-answer calculations.

---

## 🏗️ Architecture & Dual-Stack Support

The platform is designed with a production-ready **PHP / MySQL** stack alongside a complete **Python / Flask** implementation, sharing equivalent data models and anti-cheat behaviors.

- **Production / Host Stack**: PHP 8.x, Apache (`.htaccess`), MySQL / MariaDB (PDO)
- **Alternative / WSGI Stack**: Python 3.10+, Flask, Flask-SQLAlchemy, Flask-Login, Gunicorn
- **Client**: Vanilla JavaScript (no heavy frontend dependencies), Bootstrap 5, Custom CSS3 animations
- **Database**: MySQL / PostgreSQL / SQLite

---

## 🚀 Getting Started

### Option A: PHP / MySQL Setup (LAMP / WAMP / Shared Hosting)

1. **Clone the repository**:
   ```bash
   git clone https://github.com/alanjoyt/quiz-platform.git
   cd quiz-platform
   ```

2. **Database Initialization**:
   - Create a new MySQL database (e.g., `cheranalloor_faces`).
   - Import the database structure from [`schema.sql`](schema.sql):
     ```bash
     mysql -u username -p cheranalloor_faces < schema.sql
     ```

3. **Configure Credentials**:
   - Copy the configuration template:
     ```bash
     cp config.sample.php config.php
     ```
   - Edit `config.php` with your database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
   - *Note: `config.php` is strictly ignored by Git to keep credentials secure.*

4. **Serve with Apache / WAMP**:
   - Place the project inside your web server's document root (e.g., `C:/wamp64/www/quiz-platform` or `/var/www/html/quiz-platform`).
   - Access the platform via `http://localhost/quiz-platform`.

---

### Option B: Python / Flask Setup (Linux / PaaS / Local Dev)

1. **Create and activate a virtual environment**:
   ```bash
   python -m venv venv
   # On Windows:
   .\venv\Scripts\activate
   # On Linux/macOS:
   source venv/bin/activate
   ```

2. **Install dependencies**:
   ```bash
   pip install -r requirements.txt
   ```

3. **Configure Environment Variables**:
   - Copy the environment template:
     ```bash
     cp .env.example .env
     ```
   - Update `SECRET_KEY` and optional `DATABASE_URL` in `.env`.

4. **Initialize Database**:
   ```bash
   python db_init.py
   ```

5. **Run the Application**:
   - **Local Development**:
     ```bash
     python app.py
     ```
   - **Production (WSGI / Gunicorn)**:
     ```bash
     gunicorn -w 4 -b 0.0.0.0:5000 app:app
     ```

---

## ⚙️ Administration & Configuration

- Access the Admin Dashboard at `/admin` to:
  - Toggle quiz state (**Draft**, **Live**, **Closed**).
  - Add, edit, and bulk-manage question banks.
  - Review live cheat detection flags, disqualification logs, and competitor timestamps.
  - Export leaderboard results to CSV.

---

## 🔒 Security & Secret Management

- Production secrets and database credentials must never be committed to source control.
- Configuration templates ([`config.sample.php`](config.sample.php) and [`.env.example`](.env.example)) are provided with placeholders for clean team onboarding and automated CI/CD pipelines.
- Session cookies are protected with `HttpOnly`, `SameSite=Lax`, and SSL/TLS flags.

---

## 📄 License

This project is licensed under the MIT License.
