# Garage Rental

## Requirements

- PHP 8.3+ with the `bcmath`, `mbstring`, `intl`, `fileinfo`, `gd` and `pdo_mysql` extensions
- Composer 2
- Node.js 20.19+ or 22.12+ (with npm)
- MySQL 8+ or MariaDB 10.6+
- Git

## Build

### 1. Clone the repository

```bash
git clone https://github.com/liwangivone/garage-rental.git
cd garage-rental
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Create the `.env` file

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Edit `.env`

Set the database to match your MySQL account, and change the admin credentials if needed:

```dotenv
APP_NAME="Garage Rental"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=garage_rental
DB_USERNAME=root
DB_PASSWORD=

ADMIN_NAME="Administrator"
ADMIN_EMAIL=admin@garagerental.test
ADMIN_PASSWORD=password
```

### 5. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE garage_rental;"
```

### 6. Set up the application

```bash
php artisan migrate --seed
php artisan storage:link
```

Optional, to add demo data:

```bash
php artisan db:seed --class=DemoSeeder
```

### 7. Build and run

```bash
npm run build
php artisan serve
```

Open <http://localhost:8000> and log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
