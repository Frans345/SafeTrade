# SafeTrade – C2C Marketplace

**Live app:** https://fpl-assistant-goat.streamlit.app
A consumer-to-consumer marketplace built with PHP, MySQL (PDO) and Bootstrap 5 for my Eduvos ITECA project.

## Features
- Register / login with sessions and a 30-minute inactivity timeout
- Create, edit and browse listings by category and province
- Bidding, comments, saved listings, reviews and seller ratings
- Private messaging between buyers and sellers
- Seller verification (ID document upload) and user reporting
- Admin dashboard and inspection tools (soft deletes for users and listings)

## Tech
PHP 8, MySQL/MariaDB, PDO, Bootstrap 5, Bootstrap Icons, SortableJS

## Running locally
1. Install XAMPP (or any PHP + MySQL stack) and put this folder in `htdocs/`.
2. Create a MySQL database called `safetrade`.
3. Set credentials via environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) or edit the defaults in `includes/db.php`.
4. Create the tables (`users`, `listings`, `bids`, `messages`, `reviews`, `comments`, `saved_listings`, `reports`), then open `setup_admin.php` once and delete it afterwards.
5. Visit `http://localhost/SafeTrade/index.php`.

## Note
GitHub Pages only hosts static files, so the PHP app needs a PHP host (e.g. InfinityFree) to run live.
