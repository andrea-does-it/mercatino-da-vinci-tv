# Mercatino del Libro Usato - School Bookshop Website Documentation

## Overview

This is a comprehensive web application for managing a second-hand school bookshop ("Mercatino del Libro Usato") designed specifically for the Leonardo Da Vinci High School in Treviso, Italy. The system is managed by the parent committee (Comitato Genitori) and allows parents and students to sell and buy used textbooks.

## Key Features

### For Sellers (Parents/Students)
- **Book Registration**: Add textbooks to sell by selecting from officially adopted school books
- **Practice Management**: Track selling requests through a numbered practice system
- **Status Tracking**: Monitor book status (submitted → accepted → for sale → sold)
- **Revenue Tracking**: View earnings from sold books (seller gets original price, committee gets €2 commission)

### For Buyers
- **Book Browsing**: Search and filter through available used books by subject/category
- **Shopping Cart**: Add books to cart for purchase
- **Order Management**: Track purchase history and status

### For Administrators
- **Practice Management**: Accept/reject book selling requests
- **Inventory Control**: Manage book catalog, categories, and pricing
- **Sales Tracking**: Monitor daily and total sales revenue
- **User Management**: Handle user accounts and permissions
- **Content Management**: Manage news, downloads, and site content

## Technical Stack

- **Backend**: PHP 7+ with custom MVC-like architecture
- **Database**: MySQL with comprehensive relational design
- **Frontend**: Bootstrap 4, jQuery, HTML5/CSS3
- **Payment Processing**: PayPal and Stripe integration
- **PDF Generation**: FPDF library for invoices and reports
- **File Uploads**: Custom image management system
- **Email System**: PHP mail() function for notifications

## System Architecture

The application follows a modular, object-oriented design with:
- **Clean separation** of concerns between presentation, business logic, and data layers
- **Role-based access control** (admin, power user, regular user)
- **Comprehensive audit trail** for all transactions
- **Automatic inventory management** with quantity tracking
- **Multi-language support** (Italian interface)

## User Roles

1. **Regular Users** (parents/students): Can sell and buy books
2. **Power Users** (pwuser): Committee members with practice management access
3. **Administrators** (admin): Full system access including user and content management

## Core Workflow

1. **Book Submission**: Users select from official school textbooks and submit selling requests
2. **Practice Creation**: System assigns practice numbers for tracking
3. **Review Process**: Administrators review and accept/reject submissions
4. **Sales Management**: Accepted books are marked as available for purchase
5. **Transaction Completion**: Sales are recorded with automatic commission calculation
6. **Settlement**: Sellers collect revenue minus committee commission

This documentation provides a comprehensive guide for developers, system administrators, and stakeholders involved in maintaining and extending the platform.