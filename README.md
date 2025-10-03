# Laravel API Base Project

A professional Laravel API base project following industry standards for easy integration into existing systems.

## 🏗️ Architecture

This project follows **clean architecture principles** with **separation of concerns**:

- **Controllers**: Handle HTTP requests and responses
- **Form Requests**: Validate incoming data
- **Services**: Business logic layer
- **Repositories**: Data access layer
- **Models**: Eloquent models with relationships

## 🔧 Features

- ✅ **Laravel 12** (Latest version)
- ✅ **API-only structure** (no Blade views)
- ✅ **Laravel Sanctum** authentication
- ✅ **API versioning** (`/api/v1/...`)
- ✅ **PSR-12 coding standards**
- ✅ **Repository pattern** with interfaces
- ✅ **Service layer** for business logic
- ✅ **Form request validation**
- ✅ **Standardized JSON responses**
- ✅ **Global exception handling**
- ✅ **CORS configuration**

## 📁 Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/V1/     # API Controllers
│   └── Requests/Api/V1/        # Form Request Validators
├── Models/                     # Eloquent Models
├── Repositories/              # Repository Layer
├── Services/                  # Business Logic Layer
└── Traits/                   # Reusable Traits
```

## 🚀 Getting Started

### 1. Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Database Setup

Configure your database in `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations:

```bash
php artisan migrate
```

### 3. Sanctum Configuration

Sanctum is already installed and configured. No additional setup required.

## 📚 API Documentation

### Base URL
```
http://your-domain.com/api/v1
```

### Authentication Endpoints

#### Register
```http
POST /api/v1/auth/register

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

#### Login
```http
POST /api/v1/auth/login

{
    "email": "john@example.com",
    "password": "password123"
}
```

#### Logout (Protected)
```http
POST /api/v1/user/logout
Authorization: Bearer {token}
```

### User Profile Endpoints

#### Get Profile (Protected)
```http
GET /api/v1/user/profile
Authorization: Bearer {token}
```

#### Update Profile (Protected)
```http
PUT /api/v1/user/profile
Authorization: Bearer {token}

{
    "name": "John Doe Updated",
    "email": "john.updated@example.com"
}
```

### Posts CRUD Example (Protected)

#### Get All Posts
```http
GET /api/v1/posts?per_page=15
Authorization: Bearer {token}
```

#### Create Post
```http
POST /api/v1/posts
Authorization: Bearer {token}

{
    "title": "Post Title",
    "content": "Post content here...",
    "status": "draft" // or "published"
}
```

#### Get Single Post
```http
GET /api/v1/posts/{id}
Authorization: Bearer {token}
```

#### Update Post
```http
PUT /api/v1/posts/{id}
Authorization: Bearer {token}

{
    "title": "Updated Title",
    "content": "Updated content...",
    "status": "published"
}
```

#### Delete Post
```http
DELETE /api/v1/posts/{id}
Authorization: Bearer {token}
```

## 📋 Standard Response Format

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "data": null
}
```

### Validation Error Response
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "field_name": ["Error message"]
    }
}
```

## 🔒 Authentication

This API uses **Laravel Sanctum** for authentication:

1. Register or login to receive an API token
2. Include the token in the `Authorization` header: `Bearer {token}`
3. Protected routes require authentication

## ⚙️ Configuration

### CORS
CORS is configured in `bootstrap/app.php` and handles cross-origin requests.

### Rate Limiting
API routes use the default `throttle:api` middleware (60 requests per minute).

### Exception Handling
Global exception handling is configured in `bootstrap/app.php` with standardized JSON responses.

## 🧹 Code Standards

This project follows **PSR-12** coding standards. To format code:

```bash
php vendor/bin/pint
```

## 🏗️ Extending the API

### Adding New Endpoints

1. **Create Model & Migration**:
   ```bash
   php artisan make:model YourModel --migration
   ```

2. **Create Repository Interface & Implementation**:
   ```php
   // app/Repositories/YourModelRepositoryInterface.php
   // app/Repositories/YourModelRepository.php
   ```

3. **Create Service**:
   ```php
   // app/Services/YourModelService.php
   ```

4. **Create Form Requests**:
   ```php
   // app/Http/Requests/Api/V1/StoreYourModelRequest.php
   // app/Http/Requests/Api/V1/UpdateYourModelRequest.php
   ```

5. **Create Controller**:
   ```php
   // app/Http/Controllers/Api/V1/YourModelController.php
   ```

6. **Register Repository Binding**:
   ```php
   // In app/Providers/AppServiceProvider.php
   $this->app->bind(YourModelRepositoryInterface::class, YourModelRepository::class);
   ```

7. **Add Routes**:
   ```php
   // In routes/api.php
   Route::apiResource('your-models', YourModelController::class);
   ```

## 📝 Notes

- No dummy data, factories, or seeders included (as requested)
- API-only structure (no web interface)
- Ready for integration into existing systems
- Follows Laravel best practices and industry standards
- Minimal, clean, and production-ready

## 🤝 Contributing

This is a base project template. Follow the established patterns when extending functionality.
