# Database Schema Documentation

## Core Tables

### User Management
```sql
-- User accounts with role-based permissions
user (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(255),
    last_name VARCHAR(255), 
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    user_type ENUM('admin', 'pwuser', 'regular'),
    profile_id INT,
    reset_link VARCHAR(255),
    created_at TIMESTAMP
)

-- User addresses for shipping
address (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT FOREIGN KEY REFERENCES user(id),
    street VARCHAR(255),
    city VARCHAR(255),
    cap VARCHAR(10)
)
```

### Product Catalog
```sql
-- Main product catalog (school textbooks)
product (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),           -- Book title
    autori VARCHAR(255),         -- Authors
    editore VARCHAR(255),        -- Publisher
    ISBN VARCHAR(255),           -- ISBN code
    price DECIMAL(10,2),         -- Base price
    category_id INT FOREIGN KEY, -- Subject/category
    qta INT,                     -- Available quantity
    sconto INT,                  -- Discount percentage
    data_inizio_sconto DATE,     -- Discount start date
    data_fine_sconto DATE,       -- Discount end date
    mtitle TEXT,                 -- Meta title for SEO
    metadescription TEXT         -- Meta description for SEO
)

-- Product images
product_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT FOREIGN KEY REFERENCES product(id),
    image_extension VARCHAR(10),
    title VARCHAR(255),
    alt VARCHAR(255),
    order_number INT
)

-- Categories and subcategories (subjects)
category (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    description LONGTEXT,
    metadesc TEXT,
    parent_id INT FOREIGN KEY REFERENCES category(id)
)

-- Product-category relationships
product_categories (
    product_id INT FOREIGN KEY REFERENCES product(id),
    subcategory_id INT FOREIGN KEY REFERENCES category(id)
)
```

### Shopping Cart System
```sql
-- Shopping carts (session-based)
cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT FOREIGN KEY REFERENCES user(id),
    client_id VARCHAR(255),      -- Session identifier for guests
    shipment_id INT,
    last_interaction DATETIME DEFAULT CURRENT_TIMESTAMP
)

-- Items in shopping carts
cart_item (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cart_id INT FOREIGN KEY REFERENCES cart(id),
    product_id INT FOREIGN KEY REFERENCES product(id),
    quantity INT
)
```

### Order Management
```sql
-- Main orders table (practices)
orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numPratica VARCHAR(50),      -- Practice number for tracking
    user_id INT FOREIGN KEY REFERENCES user(id),
    status ENUM('inviata', 'accettata', 'chiusa', 'annullata'),
    is_restored INT,             -- Whether inventory was restored if cancelled
    is_email_sent INT,           -- Email notification status
    shipment_name VARCHAR(255),
    shipment_price DECIMAL(10,2),
    payment_code VARCHAR(255),
    payment_status VARCHAR(255),
    payment_method VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)

-- Individual items in orders
order_item (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT FOREIGN KEY REFERENCES orders(id),
    product_id INT FOREIGN KEY REFERENCES product(id),
    quantity INT,
    single_price DECIMAL(10,2),  -- Price at time of order
    status ENUM('accettare', 'vendere', 'venduto', 'eliminato'),
    updated_at DATE
)

-- Temporary calculation table for sales
order_item1 (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT,
    quantity INT,
    single_price DECIMAL(10,2),
    status VARCHAR(50)
)
```

### Practice Number Management
```sql
-- Sequential practice number generation
pratica (
    numPratica INT
)
```

### User Profiles and Special Treatments
```sql
-- User profile templates
profile (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255)
)

-- Special treatment types (discounts, payment terms)
special_treatment_type (
    code VARCHAR(50) PRIMARY KEY,
    description VARCHAR(255),
    special_treatment_name VARCHAR(255)
)

-- Special treatments
special_treatment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_code VARCHAR(50) FOREIGN KEY,
    name VARCHAR(255),
    special_treatment_value VARCHAR(255)
)

-- Profile-treatment relationships
profile_treatments (
    profile_id INT FOREIGN KEY REFERENCES profile(id),
    special_treatment_id INT FOREIGN KEY REFERENCES special_treatment(id)
)
```

### Shipping and Logistics
```sql
-- Shipping methods
shipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(2000),
    price DECIMAL(10,2)
)
```

### Content Management
```sql
-- News articles
news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT FOREIGN KEY REFERENCES user(id),
    title VARCHAR(255),
    content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_published TINYINT(1) DEFAULT 1
)

-- Downloadable files
downloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT FOREIGN KEY REFERENCES user(id),
    title VARCHAR(255),
    description TEXT,
    filename VARCHAR(255),
    filepath VARCHAR(255),
    filesize INT,
    filetype VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_published TINYINT(1) DEFAULT 1
)

-- News-download relationships
news_downloads (
    news_id INT FOREIGN KEY REFERENCES news(id) ON DELETE CASCADE,
    download_id INT FOREIGN KEY REFERENCES downloads(id) ON DELETE CASCADE,
    PRIMARY KEY (news_id, download_id)
)
```

### Email Management
```sql
-- Email templates/campaigns
email (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(255),
    message TEXT
)

-- Email recipients
email_recipients (
    email_id INT FOREIGN KEY REFERENCES email(id),
    recipient_id INT FOREIGN KEY REFERENCES user(id)
)
```

### System Management
```sql
-- Database version tracking
version (
    version VARCHAR(14) PRIMARY KEY
)
```

## Key Relationships

1. **Users → Orders**: One-to-many relationship for tracking selling/buying activities
2. **Orders → Order Items**: One-to-many for individual books in each practice
3. **Products → Categories**: Many-to-many through product_categories table
4. **Users → Profiles**: Many-to-one for special treatment assignment
5. **Products → Images**: One-to-many for multiple product photos
6. **Cart → Cart Items**: One-to-many for shopping cart functionality

## Business Logic Constraints

- **Practice Numbers**: Sequential, auto-generated for order tracking
- **Inventory Management**: Automatic quantity updates on cart actions
- **Status Workflow**: Strict progression through order statuses
- **Pricing**: Base price + €2 commission for committee
- **User Roles**: Hierarchical permissions (admin > pwuser > regular)

## Indexes and Performance

The schema includes appropriate indexes on:
- Foreign key relationships
- User email (unique constraint)
- Product ISBN and category lookups
- Order status and date fields
- Practice number for quick lookup