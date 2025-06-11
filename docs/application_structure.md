# Application Structure Documentation

## Directory Structure

```
web/htdocs/
├── admin/                    # Administrative interface
│   ├── pages/               # Admin page controllers
│   └── index.php           # Admin entry point
├── api/                     # REST API endpoints
│   ├── admin/              # Admin-specific APIs
│   └── shop/               # Shopping APIs
├── auth/                    # Authentication system
│   ├── pages/              # Login, registration, password reset
│   └── template-parts/     # Auth-specific templates
├── classes/                 # Core business logic classes
│   └── utilities/          # Utility classes
├── inc/                     # Core includes and configuration
├── jobs/                    # Background job scripts
├── public/                  # Public-facing pages
│   ├── pages/              # Public page controllers
│   └── template-parts/     # Shared template components
├── shop/                    # E-commerce functionality
│   ├── pages/              # Shop page controllers
│   ├── payments/           # Payment processing
│   └── invoices/           # PDF invoice generation
├── sql/                     # Database migration scripts
├── user/                    # User dashboard
│   └── pages/              # User-specific pages
├── uploads/                 # File upload storage
├── vendor/                  # Composer dependencies
├── assets/                  # Static assets (CSS, JS, images)
└── images/                  # Product images
```

## Core PHP Classes

### Database Layer
```php
// classes/DB.php - Database abstraction layer
class DB {
    public function query($sql)          // Execute SELECT queries
    public function exec($sql)           // Execute DDL/DML queries
    public function select_all($table)   // Get all records
    public function select_one($table, $id) // Get single record
    public function insert_one($table, $data) // Insert record
    public function update_one($table, $data, $id) // Update record
    public function delete_one($table, $id) // Delete record
}

// Base class for all model managers
class DBManager extends DB {
    protected $columns        // Table column definitions
    protected $tableName     // Target table name
    
    public function get($id)         // Get single entity
    public function getAll()         // Get all entities
    public function create($obj)     // Create new entity
    public function update($obj, $id) // Update entity
    public function delete($id)      // Delete entity
}
```

### Product Management
```php
// classes/Product.php
class Product {
    public $id, $name, $autori, $editore, $ISBN, $price, $category_id
    public $sconto, $data_inizio_sconto, $data_fine_sconto, $qta
}

class ProductManager extends DBManager {
    public function GetProducts($categoryId)           // Get products by category
    public function GetProductWithImages($productId)   // Get product with images
    public function SearchProducts($search)            // Search functionality
    public function GetProductsPaginated($categoryId, $offset, $limit) // Pagination
    public function decreaseQuantity($productId)       // Inventory management
    public function increaseQuantity($productId)       // Inventory management
    public function MoveTempImages($tmpDir, $productId) // Image management
}

class ProductImage {
    public $id, $product_id, $image_extension, $title, $alt, $order_number
}

class ProductImageManager extends DBManager {
    public function getImages($productId)  // Get product images
}
```

### User Management
```php
// classes/User.php
class User {
    public $id, $first_name, $last_name, $email, $user_type, $profile_id
}

class UserManager extends DBManager {
    public function register($first_name, $last_name, $email, $password, $profile_id)
    public function login($email, $password)           // Authentication
    public function isValidPassword($pwd)              // Password validation
    public function isValidEmail($email)               // Email validation
    public function userExists($email)                 // Check if user exists
    public function createAddress($userId, $street, $city, $cap) // Address management
    public function createResetLink($userId)           // Password reset
    public function updatePassword($userId, $password) // Password updates
}
```

### Shopping Cart System
```php
// classes/Cart.php
class Cart {
    public $id, $user_id, $client_id, $shipment_id
}

class CartItem {
    public $id, $cart_id, $product_id, $quantity
}

class CartManager extends DBManager {
    public function getCurrentCartId()                 // Get active cart
    public function addToCart($productId, $cartId)     // Add item to cart
    public function removeFromCart($productId, $cartId) // Remove item
    public function getCartItems($cartId)              // Get cart contents
    public function getCartTotal($cartId)              // Calculate totals
    public function mergeCarts()                       // Merge guest/user carts
    public function ResetExpiredCarts()                // Clean old carts
    public function isEmptyCart($cartId)               // Check if empty
}
```

### Order Management
```php
// classes/Cart.php (also contains order management)
class Order {
    public $id, $numPratica, $user_id, $status
    public $is_restored, $is_email_sent, $shipment_name, $shipment_price
}

class OrderManager extends DBManager {
    public function createOrderFromCart($cartId, $userId) // Convert cart to order
    public function getOrderItems($orderId)              // Get order details
    public function getOrderTotal($orderId)              // Calculate order total
    public function updateStatus($orderId, $status)      // Update order status
    public function updateStatusItem($id, $status)       // Update item status
    public function getAllOrders($status)                // Get orders by status
    public function sendAcceptanceEmail(...)             // Send notifications
    public function SavePaymentDetails(...)              // Store payment info
}

class PraticaManager extends DBManager {
    public function updatePratica()        // Increment practice number
    public function GetnumPratica()        // Get current practice number
}
```

### Category Management
```php
// classes/Category.php
class Category {
    public $id, $name, $description, $metadesc, $parent_id
}

class CategoryManager extends DBManager {
    public function GetCategoriesAndSubs($parentId, $productId) // Hierarchical categories
    public function SaveSubcategories($subcategoryIds, $productId) // Product categorization
}
```

### Profile and Special Treatments
```php
// classes/Profile.php
class Profile {
    public $id, $name
}

class ProfileManager extends DBManager {
    public function GetUserDiscount()              // Calculate user discount
    public function GetUserDelayedPayments()       // Get payment terms
    public function SaveProfileTreatments($profileId, $treatments)
    public function GetTreatmentsByType($profileId, $treatmentType)
}

// classes/SpecialTreatment.php
class SpecialTreatment {
    public $id, $name, $special_treatment_value, $type_code
}

class SpecialTreatmentManager extends DBManager {
    public function GetTypes()              // Get treatment types
    public function getAllTreatments()      // Get all treatments
}
```

### Content Management
```php
// classes/NewsManager.php
class News {
    public $id, $user_id, $title, $content, $created_at, $is_published
}

class NewsManager extends DBManager {
    public function getRecentNews($limit)   // Get latest news
}

// classes/DownloadManager.php
class Download {
    public $id, $user_id, $title, $description, $filename, $filepath
    public $filesize, $filetype, $created_at, $is_published
}

class DownloadManager extends DBManager {
    public function uploadFile($file, $userId, $title, $description) // File upload
    public function getAllDownloadsWithUserInfo()                   // Get downloads
}
```

### Utility Classes
```php
// classes/utilities/ImageUtilities.php
class ImageUtilities {
    public static function thumbnail($file)    // Generate thumbnails
    public static function wallpaper($file)    // Generate full-size images
}

// classes/utilities/PdfUtilities.php
class PdfUtilities extends Fpdf {
    public function printOrderInvoice($orderId, $orderItems, ...) // Generate PDFs
}

// classes/utilities/UrlUtilities.php
class UrlUtilities {
    public function category($id, $name)       // SEO-friendly category URLs
    public function product($id, $name)        // SEO-friendly product URLs
    public function static($page)              // Static page URLs
}

// classes/utilities/Utilities.php
class Utilities {
    public static function guidv4()            // Generate UUIDs
}
```

## Page Controllers

### Entry Points
- `index.php` - Main redirect to public area
- `public/index.php` - Public homepage and pages
- `shop/index.php` - Shopping area entry point
- `admin/index.php` - Administrative interface
- `auth/index.php` - Authentication pages
- `user/index.php` - User dashboard

### Routing System
The application uses a simple routing system based on `$_GET['page']` parameter:

```php
// Example routing in shop/index.php
$page = 'products-list';
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}
include "pages/$page.php";
```

### URL Rewriting
SEO-friendly URLs are handled via `.htaccess` files:
```apache
# Root .htaccess
RewriteRule ^category/([0-9]+)-([a-z0-9-]+)/?$ shop?page=products-list&id=$1&slug=$2
RewriteRule ^product/([0-9]+)-([a-z0-9-]+)/?$ shop?page=view-product&id=$1

# Shop .htaccess
RewriteRule ^category/([0-9]+)-([a-z0-9-]+)/?$ ?page=products-list&id=$1
RewriteRule ^product/([0-9]+)-([a-z0-9-]+)/?$ ?page=view-product&id=$1
```

## Security Features

1. **SQL Injection Prevention**: Parameterized queries and input escaping
2. **XSS Protection**: HTML entity encoding via `esc_html()` function
3. **Authentication**: Session-based login with password hashing
4. **Authorization**: Role-based access control
5. **CSRF Protection**: Form token validation (implemented but not shown)
6. **File Upload Security**: Type and size validation
7. **Direct Access Prevention**: `defined('ROOT_URL')` checks

## Configuration

### Database Configuration
```php
// inc/config.php (not shown in documents but referenced)
define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'database_name');
define('ROOT_URL', 'https://domain.com/');
define('ROOT_PATH', '/path/to/htdocs/');
define('SITE_NAME', 'Mercatino del Libro Usato');
```

### Payment Integration
- PayPal: Configured in `shop/payments/paypal/`
- Stripe: Configured in `shop/payments/stripe/`
- Support for delayed payments for special user profiles