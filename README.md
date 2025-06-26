# FoodiFusion - Smart Restaurant Aggregator Platform

## Overview
FoodiFusion is a Smart Restaurant Management System

Welcome to the Smart Restaurant Management System, a comprehensive web application designed to streamline restaurant operations and enhance the customer ordering experience. This platform features AI-powered payment verification, robust user management, and a clean, intuitive interface for both customers and restaurant owners.

---

## Table of Contents

- [Key Features](#key-features)
- [How It Works](#how-it-works)
- [Technical Stack](#technical-stack)
- [Project Documentation](#project-documentation)
- [Setup and Installation](#setup-and-installation)
  - [1. Prerequisites](#1-prerequisites)
  - [2. Clone the Repository](#2-clone-the-repository)
  - [3. Database Setup](#3-database-setup)
  - [4. Environment Configuration](#4-environment-configuration)
  - [5. Install Dependencies](#5-install-dependencies)
  - [6. Running the Application](#6-running-the-application)

---

## Key Features

- **AI-Powered Payment Verification**: Utilizes the OpenRouter API to automatically verify mobile money payment screenshots, reducing manual work and preventing fraud.
- **Dual User Dashboards**: Separate, feature-rich dashboards for both Customers and Restaurant Owners.
- **Complete Restaurant Management**: Allows restaurant owners to manage their profile, menu items (add/edit/delete), and view incoming orders.
- **Seamless Customer Experience**: Customers can browse restaurants, view menus, place orders, and track their order history.
- **Robust Input Validation**: Secure server-side and client-side validation on all forms (registration, login, profile updates) to ensure data integrity.
- **Secure Authentication**: A complete authentication system with session management and password hashing.

---

## How It Works

The application operates on a standard LAMP/XAMPP stack. The backend is built with PHP, handling all business logic, database interactions, and API communications. The frontend uses HTML, CSS, and vanilla JavaScript for user interface and interactivity.

- **Users** (Customers or Restaurant Owners) register and log in through the main page.
- **Customers** can browse restaurants, add items to their cart, and proceed to an order page.
- During checkout, the customer uploads a **payment screenshot**. This image is sent to a backend service (`verify-payment.php`) which calls the **OpenRouter AI API** to validate its authenticity.
- If the payment is verified, the order is confirmed and placed in the system. If not, the user is prompted with an error.
- **Restaurant Owners** can log in to their dashboard to manage their restaurant's information and menu, and to see new orders as they arrive.

---

## Technical Stack

- **Backend**: PHP 8.x
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **AI Service**: [OpenRouter API](https://openrouter.ai/) for image analysis
- **Web Server**: Apache (via XAMPP/WAMP/LAMP)
- **Dependency Management**: Composer

---

## Project Documentation

For a deeper understanding of the project's structure and architecture, please refer to the following documents:

- **[File & Directory Guide](./PROJECT_FILES.md)**: A detailed explanation of every file and folder in the project.
- **[UML Diagrams](./UML_DIAGRAMS.md)**: Visual diagrams (Use Case, Class, Sequence) illustrating the system's architecture and key workflows.

---

## Setup and Installation

Follow these steps to get the project running on your local machine.

### 1. Prerequisites

- A local server environment like [XAMPP](https://www.apachefriends.org/index.html) or WAMP (for Windows) or a LAMP stack (for Linux).
- [Composer](https://getcomposer.org/) installed globally.
- An API key from [OpenRouter.ai](https://openrouter.ai/).

### 2. Clone the Repository

Clone this project into your web server's root directory (e.g., `C:/xampp/htdocs/`).

```bash
# Example for XAMPP
cd C:/xampp/htdocs/
git clone <your-repository-url> smart-rest
```

### 3. Database Setup

1.  Start Apache and MySQL in your XAMPP control panel.
2.  Open a web browser and navigate to `http://localhost/phpmyadmin`.
3.  Create a new database. You can name it `smart_restaurant_db` or similar.
4.  Select the new database and go to the **Import** tab.
5.  Click **Choose File** and select the `database/schema.sql` file from this project.
6.  Click **Go** to execute the script and create all the necessary tables.

### 4. Environment Configuration

1.  In the project's root directory, find the `.env` file.
2.  Open it and fill in your database credentials and your OpenRouter API key:

    ```ini
    DB_HOST=localhost
    DB_NAME=smart_restaurant_db
    DB_USER=root
    DB_PASS=
    OPENROUTER_API_KEY="your-openrouter-api-key-here"
    ```

    *Note: The default XAMPP setup has a `root` user with no password.*

### 5. Install Dependencies

Open a terminal or command prompt in the project's root directory and run Composer to install the required PHP packages.

```bash
composer install
```

### 6. Running the Application

Open your web browser and navigate to `http://localhost/smart-rest/` (or the folder name you used). You should now see the application's main login and registration page.

## Features

### For Customers
- AI-powered restaurant recommendations based on preferences, allergies, and order history
- Restaurant discovery with filtering options
- Comprehensive profile management for dietary preferences and allergies
- Order placement with customization options
- Order tracking and history

### For Restaurants
- Dashboard with real-time statistics
- Order management system
- Menu management (CRUD operations)
- Analytics dashboard with business intelligence
- Customer reviews system
- Restaurant settings management

## Technology Stack
- PHP for backend logic
- MySQL for database
- HTML, CSS for frontend structure and styling
- JavaScript for interactive elements
- XAMPP for local development environment

## Project Structure
```
foodifusion/
├── assets/
│   ├── css/
│   │   ├── style.css (Main styling)
│   │   └── dashboard.css (Dashboard styling)
│   ├── js/
│   │   └── main.js (Frontend functionality)
│   └── images/
│       ├── breakfast.svg
│       ├── dessert.svg
│       ├── drink.svg
│       └── food.svg
├── config/
│   └── database.php (Database connection and setup)
├── includes/
│   └── auth.php (Authentication functions)
├── index.php (Landing page)
├── customer-dashboard.php (Customer main interface)
├── customer-profile.php (Customer profile management)
├── restaurant-dashboard.php (Restaurant main interface)
├── order.php (Order placement page)
├── order-confirmation.php (Order confirmation page)
├── logout.php (Logout functionality)
└── README.md (Documentation)
```

## Setup Instructions

### Prerequisites
- XAMPP (or similar local server environment with PHP and MySQL)
- Web browser

### Installation
1. Clone or download this repository to your XAMPP htdocs folder
2. Start Apache and MySQL services in XAMPP
3. Open your browser and navigate to `http://localhost/smart%20rest/index.php`
4. The database will be automatically created on first access

### Database Setup
The application will automatically create the necessary database and tables when first accessed. The database structure includes:

- `customers` - Customer information and authentication
- `restaurants` - Restaurant information and authentication
- `menu_categories` - Restaurant menu categories
- `menu_items` - Restaurant menu items
- `orders` - Customer orders
- `order_items` - Items within orders
- `reviews` - Customer reviews for restaurants
- `customer_preferences` - Customer dietary preferences
- `customer_allergies` - Customer allergy information

## Usage

### Customer Flow
1. Register as a customer from the homepage
2. Log in to access the customer dashboard
3. View AI-powered restaurant recommendations
4. Browse restaurants and place orders
5. Manage profile, preferences, and allergies

### Restaurant Flow
1. Register as a restaurant from the homepage
2. Log in to access the restaurant dashboard
3. Manage orders and menu items
4. View analytics and customer reviews
5. Update restaurant settings

## AI Recommendation System
The platform features a smart recommendation system that analyzes:
- Customer dietary preferences
- Allergy information
- Previous order patterns
- Restaurant ratings and cuisine types
- Location proximity

Based on these factors, it provides personalized restaurant recommendations with confidence scores and reasoning.

## Currency
All prices and financial information are displayed in FCFA (Central African Franc).

## License
This project is for educational purposes only.

## Author
FoodiFusion Team